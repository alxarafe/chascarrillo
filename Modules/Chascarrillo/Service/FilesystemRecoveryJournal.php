<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use JsonException;
use RuntimeException;
use Throwable;

/** Durable, attempt-scoped recovery evidence for managed filesystem mutations. */
final class FilesystemRecoveryJournal
{
    public const FORMAT_VERSION = 1;
    public const ROOT = 'var/update/recovery';
    public const JOURNAL = 'journal.json';

    private const PENDING = 'pending';
    private const PRECONDITION_CHECKED = 'precondition_checked';
    private const APPLYING = 'applying';
    private const APPLIED = 'applied';
    private const RESTORING = 'restoring';
    private const RESTORED = 'restored';

    private SafePath $paths;
    private string $attemptId;
    private string $base;

    /** @var array<string,mixed> */
    private array $document;

    private function __construct(SafePath $paths, string $attemptId, array $document)
    {
        $this->paths = $paths;
        $this->attemptId = $attemptId;
        $this->base = self::ROOT . '/' . $attemptId;
        $this->document = $document;
    }

    /**
     * @param list<array<string,mixed>> $requested
     */
    public static function prepare(SafePath $paths, string $attemptId, array $requested): self
    {
        self::assertAttemptId($attemptId);
        foreach ([self::ROOT, self::ROOT . '/' . $attemptId] as $index => $relative) {
            $type = $paths->nodeType($relative);
            if ($index === 1 && $type !== SafePath::NODE_MISSING) {
                throw new RuntimeException('Ya existe el área de recuperación del intento');
            }
            if (!in_array($type, [SafePath::NODE_MISSING, SafePath::NODE_DIRECTORY], true)) {
                throw new RuntimeException("Ruta de recovery insegura: {$relative}");
            }
        }

        $virtual = [];
        $originals = [];
        $createdDirectories = [];
        $operations = [];
        $backupBytes = 0;
        $largestTemporary = 0;
        foreach ($requested as $index => $request) {
            if (
                !is_string($request['type'] ?? null)
                || !in_array($request['type'], ['copy', 'remove', 'asset_copy', 'asset_remove', 'asset_manifest'], true)
                || !is_string($request['path'] ?? null)
                || !array_key_exists('new_hash', $request)
                || ($request['new_hash'] !== null && !self::isHash($request['new_hash']))
                || !is_int($request['new_size'] ?? null)
                || $request['new_size'] < 0
            ) {
                throw new RuntimeException('Operación de recuperación no válida');
            }
            $path = RelativePath::canonical($request['path']);
            if (str_starts_with($path . '/', 'var/update/')) {
                throw new RuntimeException("Recovery no puede administrar su propia ruta: {$path}");
            }
            if (!isset($originals[$path])) {
                $nodeType = $paths->nodeType($path);
                if (!in_array($nodeType, [SafePath::NODE_MISSING, SafePath::NODE_FILE], true)) {
                    throw new RuntimeException("Tipo no recuperable en {$path}: {$nodeType}");
                }
                $oldHash = $nodeType === SafePath::NODE_FILE ? $paths->hash($path) : null;
                $mode = $nodeType === SafePath::NODE_FILE
                    ? (fileperms($paths->requireFile($path)) & 0777)
                    : null;
                if ($nodeType === SafePath::NODE_FILE) {
                    $size = filesize($paths->requireFile($path));
                    if ($size === false) {
                        throw new RuntimeException("No se pudo medir {$path}");
                    }
                    $backupBytes += $size;
                    $largestTemporary = max($largestTemporary, $size);
                }
                $originals[$path] = [
                    'type' => $nodeType,
                    'hash' => $oldHash,
                    'mode' => $mode,
                    'backup' => $nodeType === SafePath::NODE_FILE
                        ? sprintf('backups/%06d.bin', count($originals))
                        : null,
                ];
                self::collectMissingParents($paths, $path, $createdDirectories);
            }
            $before = $virtual[$path] ?? [
                'type' => $originals[$path]['type'],
                'hash' => $originals[$path]['hash'],
            ];
            $after = $request['new_hash'] === null
                ? ['type' => SafePath::NODE_MISSING, 'hash' => null]
                : ['type' => SafePath::NODE_FILE, 'hash' => $request['new_hash']];
            $operations[] = [
                'index' => $index,
                'type' => $request['type'],
                'path' => $path,
                'old_type' => $before['type'],
                'old_hash' => $before['hash'],
                'new_type' => $after['type'],
                'new_hash' => $after['hash'],
                'original_type' => $originals[$path]['type'],
                'original_hash' => $originals[$path]['hash'],
                'backup' => $originals[$path]['backup'],
                'mode' => $originals[$path]['mode'],
                'state' => self::PENDING,
            ];
            $virtual[$path] = $after;
            $largestTemporary = max($largestTemporary, $request['new_size']);
        }

        $document = [
            'format_version' => self::FORMAT_VERSION,
            'attempt_id' => $attemptId,
            'state' => 'preparing',
            'operations' => $operations,
            'created_directories' => array_keys($createdDirectories),
            'space' => [
                'backup_bytes' => $backupBytes,
                'largest_temporary_bytes' => $largestTemporary,
                'margin_bytes' => max(1048576, (int) ceil($backupBytes * 0.10)),
            ],
        ];
        $encoded = self::encode($document);
        $required = $backupBytes + (2 * $largestTemporary) + (2 * strlen($encoded))
            + $document['space']['margin_bytes'];
        $available = disk_free_space($paths->root());
        if ($available === false || $available < $required) {
            throw new RuntimeException("Espacio insuficiente para recovery: requiere {$required} bytes");
        }

        $base = self::ROOT . '/' . $attemptId;
        $paths->ensureDirectory($base . '/backups');
        foreach ($originals as $path => $original) {
            if ($original['type'] !== SafePath::NODE_FILE) {
                continue;
            }
            $backup = $base . '/' . $original['backup'];
            $paths->atomicCopyFrom($paths, $path, $backup);
            if ($paths->hash($backup) !== $original['hash']) {
                throw new RuntimeException("La copia de recovery no coincide con {$path}");
            }
            if (!chmod($paths->requireFile($backup), $original['mode'])) {
                throw new RuntimeException("No se pudieron conservar permisos de {$path}");
            }
        }
        $document['state'] = 'prepared';
        $journal = new self($paths, $attemptId, $document);
        $journal->persist();
        $journal->reload();
        return $journal;
    }

    public static function open(SafePath $paths, string $attemptId): self
    {
        self::assertAttemptId($attemptId);
        $base = self::ROOT . '/' . $attemptId;
        foreach ([self::ROOT, $base, $base . '/backups'] as $relative) {
            if ($paths->nodeType($relative) !== SafePath::NODE_DIRECTORY) {
                throw new RuntimeException("Área de recovery ausente o insegura: {$relative}");
            }
        }
        $journal = new self($paths, $attemptId, []);
        $journal->reload();
        return $journal;
    }

    public function apply(int $index, callable $mutation): void
    {
        $this->reload();
        if (($this->document['state'] ?? null) !== 'prepared') {
            throw new RuntimeException('El journal no está preparado para aplicar mutaciones');
        }
        $operation = $this->operation($index);
        if ($operation['state'] !== self::PENDING) {
            throw new RuntimeException("Transición de operación no válida: {$operation['state']}");
        }
        $this->assertCurrent($operation, 'old_type', 'old_hash');
        $this->setOperationState($index, self::PRECONDITION_CHECKED);
        $this->setOperationState($index, self::APPLYING);
        $mutation();
        $operation = $this->operation($index);
        $this->assertCurrent($operation, 'new_type', 'new_hash');
        $this->setOperationState($index, self::APPLIED);
    }

    public function rollback(): void
    {
        $this->reload();
        if ($this->document['state'] === 'rolled_back') {
            return;
        }
        if ($this->document['state'] === 'completed') {
            throw new RuntimeException('Un journal completado no puede usarse para rollback');
        }
        $this->verifyEveryBackup();
        $this->document['state'] = 'rolling_back';
        $this->persist();
        try {
            $restored = [];
            for ($index = count($this->document['operations']) - 1; $index >= 0; $index--) {
                $operation = $this->operation($index);
                $path = $operation['path'];
                $this->setOperationState($index, self::RESTORING);
                if (!isset($restored[$path])) {
                    $this->assertAttemptOwnedOrOriginal($path);
                    $this->restoreOriginal($operation);
                    $restored[$path] = true;
                }
                $this->setOperationState($index, self::RESTORED);
            }
            $directories = $this->document['created_directories'];
            usort($directories, static fn (string $a, string $b): int => substr_count($b, '/') <=> substr_count($a, '/'));
            foreach ($directories as $directory) {
                if ($this->paths->nodeType($directory) === SafePath::NODE_MISSING) {
                    continue;
                }
                if ($this->paths->nodeType($directory) !== SafePath::NODE_DIRECTORY) {
                    throw new RuntimeException("Conflicto al retirar directorio creado: {$directory}");
                }
                if ($this->paths->entries($directory) === []) {
                    $parent = dirname($directory);
                    $this->paths->removeEmptyParents($directory, $parent === '.' ? '' : $parent);
                }
            }
            $this->verifyOriginals();
            $this->document['state'] = 'rolled_back';
            $this->persist();
        } catch (Throwable $exception) {
            $this->document['state'] = 'rollback_failed';
            try {
                $this->persist();
            } catch (Throwable) {
                // Preserve the original recovery failure; state storage will also block retry.
            }
            throw $exception;
        }
    }

    public function markCompleted(): void
    {
        $this->reload();
        $this->document['state'] = 'completed';
        $this->persist();
    }

    public function isRolledBack(): bool
    {
        $this->reload();
        return ($this->document['state'] ?? null) === 'rolled_back';
    }

    /** @return array<string,mixed> */
    private function operation(int $index): array
    {
        $operation = $this->document['operations'][$index] ?? null;
        if (!is_array($operation) || ($operation['index'] ?? null) !== $index) {
            throw new RuntimeException('Índice de operación no válido en el journal');
        }
        return $operation;
    }

    private function setOperationState(int $index, string $state): void
    {
        $this->document['operations'][$index]['state'] = $state;
        $this->persist();
    }

    /** @param array<string,mixed> $operation */
    private function assertCurrent(array $operation, string $typeKey, string $hashKey): void
    {
        $type = $this->paths->nodeType($operation['path']);
        if ($type !== $operation[$typeKey]) {
            throw new RuntimeException("El destino no coincide con el journal: {$operation['path']}");
        }
        if ($type === SafePath::NODE_FILE && $this->paths->hash($operation['path']) !== $operation[$hashKey]) {
            throw new RuntimeException("El hash no coincide con el journal: {$operation['path']}");
        }
    }

    private function assertAttemptOwnedOrOriginal(string $path): void
    {
        $type = $this->paths->nodeType($path);
        $hashes = [];
        foreach ($this->document['operations'] as $operation) {
            if ($operation['path'] !== $path) {
                continue;
            }
            foreach (['old_hash', 'new_hash', 'original_hash'] as $field) {
                if (is_string($operation[$field])) {
                    $hashes[$operation[$field]] = true;
                }
            }
        }
        if ($type === SafePath::NODE_MISSING) {
            $missingAllowed = false;
            foreach ($this->document['operations'] as $operation) {
                if (
                    $operation['path'] === $path
                    && ($operation['new_type'] === SafePath::NODE_MISSING
                        || $operation['original_type'] === SafePath::NODE_MISSING)
                ) {
                    $missingAllowed = true;
                }
            }
            if (!$missingAllowed) {
                throw new RuntimeException("Conflicto de recuperación por archivo ausente: {$path}");
            }
            return;
        }
        if ($type !== SafePath::NODE_FILE || !isset($hashes[$this->paths->hash($path)])) {
            throw new RuntimeException("Conflicto de recuperación por modificación concurrente: {$path}");
        }
    }

    /** @param array<string,mixed> $operation */
    private function restoreOriginal(array $operation): void
    {
        $path = $operation['path'];
        if ($operation['original_type'] === SafePath::NODE_MISSING) {
            if ($this->paths->nodeType($path) === SafePath::NODE_FILE) {
                $current = $this->paths->hash($path);
                $newHashes = [];
                foreach ($this->document['operations'] as $candidate) {
                    if ($candidate['path'] === $path && is_string($candidate['new_hash'])) {
                        $newHashes[$candidate['new_hash']] = true;
                    }
                }
                if (!isset($newHashes[$current])) {
                    throw new RuntimeException("No se retirará un archivo nuevo modificado: {$path}");
                }
                $this->paths->unlinkFile($path);
            }
            return;
        }
        $backup = $this->base . '/' . $operation['backup'];
        if ($this->paths->hash($backup) !== $operation['original_hash']) {
            throw new RuntimeException("Snapshot corrupto para {$path}");
        }
        $this->paths->atomicCopyFrom($this->paths, $backup, $path);
        if (!chmod($this->paths->requireFile($path), $operation['mode'])) {
            throw new RuntimeException("No se pudieron restaurar permisos de {$path}");
        }
    }

    private function verifyEveryBackup(): void
    {
        $seen = [];
        foreach ($this->document['operations'] as $operation) {
            if ($operation['original_type'] !== SafePath::NODE_FILE || isset($seen[$operation['path']])) {
                continue;
            }
            $seen[$operation['path']] = true;
            $backup = $this->base . '/' . $operation['backup'];
            if (
                $this->paths->nodeType($backup) !== SafePath::NODE_FILE
                || $this->paths->hash($backup) !== $operation['original_hash']
            ) {
                throw new RuntimeException("Snapshot ausente o corrupto para {$operation['path']}");
            }
        }
    }

    private function verifyOriginals(): void
    {
        $seen = [];
        foreach ($this->document['operations'] as $operation) {
            if (isset($seen[$operation['path']])) {
                continue;
            }
            $seen[$operation['path']] = true;
            $this->assertCurrent($operation, 'original_type', 'original_hash');
        }
    }

    private function reload(): void
    {
        $relative = $this->base . '/' . self::JOURNAL;
        if ($this->paths->nodeType($relative) !== SafePath::NODE_FILE) {
            throw new RuntimeException('Journal de recuperación ausente o inseguro');
        }
        try {
            $document = json_decode($this->paths->read($relative), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Journal de recuperación corrupto', 0, $exception);
        }
        if (!is_array($document)) {
            throw new RuntimeException('El journal de recuperación no es un objeto JSON');
        }
        $this->validate($document);
        $this->document = $document;
    }

    private function persist(): void
    {
        $this->validate($this->document);
        $relative = $this->base . '/' . self::JOURNAL;
        $this->paths->atomicWrite($relative, self::encode($this->document));
    }

    /** @param array<string,mixed> $document */
    private function validate(array $document): void
    {
        $fields = array_keys($document);
        sort($fields, SORT_STRING);
        if (
            $fields !== ['attempt_id', 'created_directories', 'format_version', 'operations', 'space', 'state']
            || ($document['format_version'] ?? null) !== self::FORMAT_VERSION
            || ($document['attempt_id'] ?? null) !== $this->attemptId
            || !in_array($document['state'] ?? null, [
                'preparing', 'prepared', 'rolling_back', 'rolled_back', 'rollback_failed', 'completed',
            ], true)
            || !is_array($document['operations'] ?? null)
            || !array_is_list($document['operations'])
            || !is_array($document['created_directories'] ?? null)
            || !array_is_list($document['created_directories'])
            || !is_array($document['space'] ?? null)
        ) {
            throw new RuntimeException('Esquema de journal desconocido o incompleto');
        }
        $spaceFields = array_keys($document['space']);
        sort($spaceFields, SORT_STRING);
        if ($spaceFields !== ['backup_bytes', 'largest_temporary_bytes', 'margin_bytes']) {
            throw new RuntimeException('Metadatos de espacio corruptos en el journal');
        }
        foreach ($document['space'] as $bytes) {
            if (!is_int($bytes) || $bytes < 0) {
                throw new RuntimeException('Tamaño no válido en el journal');
            }
        }
        $seenDirectories = [];
        foreach ($document['created_directories'] as $directory) {
            if (
                !is_string($directory) || RelativePath::canonical($directory) !== $directory
                || str_starts_with($directory . '/', 'var/update/') || isset($seenDirectories[$directory])
            ) {
                throw new RuntimeException('Directorio no canónico o duplicado en el journal');
            }
            $seenDirectories[$directory] = true;
        }
        foreach ($document['operations'] as $index => $operation) {
            if (!is_array($operation)) {
                throw new RuntimeException('Operación corrupta en el journal');
            }
            $operationFields = array_keys($operation);
            sort($operationFields, SORT_STRING);
            if (
                $operationFields !== [
                'backup', 'index', 'mode', 'new_hash', 'new_type', 'old_hash', 'old_type',
                'original_hash', 'original_type', 'path', 'state', 'type',
                ]
                || ($operation['index'] ?? null) !== $index
                || !is_string($operation['path'] ?? null)
                || RelativePath::canonical($operation['path']) !== $operation['path']
                || str_starts_with($operation['path'] . '/', 'var/update/')
                || !in_array($operation['type'] ?? null, [
                    'copy', 'remove', 'asset_copy', 'asset_remove', 'asset_manifest',
                ], true)
                || !in_array($operation['state'] ?? null, [
                    self::PENDING, self::PRECONDITION_CHECKED, self::APPLYING,
                    self::APPLIED, self::RESTORING, self::RESTORED,
                ], true)
                || !in_array($operation['old_type'] ?? null, [SafePath::NODE_MISSING, SafePath::NODE_FILE], true)
                || !in_array($operation['new_type'] ?? null, [SafePath::NODE_MISSING, SafePath::NODE_FILE], true)
                || !in_array($operation['original_type'] ?? null, [SafePath::NODE_MISSING, SafePath::NODE_FILE], true)
            ) {
                throw new RuntimeException('Operación corrupta en el journal');
            }
            foreach (['old_hash', 'new_hash', 'original_hash'] as $field) {
                if ($operation[$field] !== null && !self::isHash($operation[$field])) {
                    throw new RuntimeException("Hash no válido en {$field}");
                }
            }
            foreach ([['old_type', 'old_hash'], ['new_type', 'new_hash'], ['original_type', 'original_hash']] as $pair) {
                if (($operation[$pair[0]] === SafePath::NODE_FILE) !== is_string($operation[$pair[1]])) {
                    throw new RuntimeException('Tipo y hash incoherentes en el journal');
                }
            }
            if ($operation['original_type'] === SafePath::NODE_FILE) {
                if (
                    !is_string($operation['backup'])
                    || preg_match('/^backups\/[0-9]{6}\.bin$/', $operation['backup']) !== 1
                    || !is_int($operation['mode']) || $operation['mode'] < 0 || $operation['mode'] > 0777
                ) {
                    throw new RuntimeException('Metadatos de snapshot incoherentes');
                }
            } elseif ($operation['backup'] !== null || $operation['mode'] !== null) {
                throw new RuntimeException('Una ausencia previa no puede contener snapshot');
            }
        }
    }

    /** @param array<string,mixed> $document */
    private static function encode(array $document): string
    {
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar el journal de recuperación');
        }
        return $json . "\n";
    }

    /** @param array<string,true> $directories */
    private static function collectMissingParents(SafePath $paths, string $path, array &$directories): void
    {
        $parent = dirname($path);
        $parts = $parent === '.' ? [] : explode('/', $parent);
        $current = '';
        foreach ($parts as $part) {
            $current = $current === '' ? $part : $current . '/' . $part;
            $type = $paths->nodeType($current);
            if ($type === SafePath::NODE_MISSING) {
                $directories[$current] = true;
            } elseif ($type !== SafePath::NODE_DIRECTORY) {
                throw new RuntimeException("Padre no seguro para recovery: {$current}");
            }
        }
    }

    private static function assertAttemptId(string $attemptId): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $attemptId) !== 1) {
            throw new RuntimeException('Identificador de intento no válido para recovery');
        }
    }

    private static function isHash(mixed $hash): bool
    {
        return is_string($hash) && preg_match('/^[a-f0-9]{64}$/', $hash) === 1;
    }
}

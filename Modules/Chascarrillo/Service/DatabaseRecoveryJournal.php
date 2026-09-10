<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use JsonException;
use RuntimeException;

/** Durable per-migration evidence; it never contains SQL or connection data. */
final class DatabaseRecoveryJournal
{
    public const FORMAT_VERSION = 1;
    public const JOURNAL = 'database.json';

    private const PENDING = 'pending';
    private const STARTED = 'started';
    private const APPLIED = 'applied';
    private const FAILED = 'failed';
    private const AMBIGUOUS = 'ambiguous';

    private string $base;

    /** @param array<string,mixed> $document */
    private function __construct(
        private readonly SafePath $paths,
        private readonly string $attemptId,
        private array $document
    ) {
        $this->base = FilesystemRecoveryJournal::ROOT . '/' . $attemptId;
    }

    /** @param list<string> $pending */
    public static function prepare(
        SafePath $paths,
        string $attemptId,
        DatabaseRecoveryCapability $capability,
        array $pending
    ): self {
        self::assertAttemptId($attemptId);
        $base = FilesystemRecoveryJournal::ROOT . '/' . $attemptId;
        $paths->requireDirectory($base);
        if ($paths->nodeType($base . '/' . self::JOURNAL) !== SafePath::NODE_MISSING) {
            throw new RuntimeException('Ya existe el journal de recuperación de base de datos');
        }
        self::assertMigrations($pending);
        if ($pending !== [] && !$capability->allowsMigrations()) {
            throw new RuntimeException('La capacidad de recovery no autoriza migraciones');
        }
        $migrations = [];
        foreach ($pending as $migration) {
            $migrations[] = ['id' => $migration, 'status' => self::PENDING];
        }
        $journal = new self($paths, $attemptId, [
            'format_version' => self::FORMAT_VERSION,
            'attempt_id' => $attemptId,
            'status' => $pending === [] ? 'not_required' : 'pending',
            'recovery' => $pending === [] ? null : $capability->toArray(),
            'migrations' => $migrations,
        ]);
        $journal->persist();
        $journal->reload();
        return $journal;
    }

    public static function open(SafePath $paths, string $attemptId): self
    {
        self::assertAttemptId($attemptId);
        $journal = new self($paths, $attemptId, []);
        $journal->reload();
        return $journal;
    }

    public function start(string $migration): void
    {
        $this->reload();
        $index = $this->index($migration);
        if ($this->document['migrations'][$index]['status'] !== self::PENDING) {
            throw new RuntimeException('La migración no está pendiente');
        }
        for ($position = 0; $position < $index; $position++) {
            if ($this->document['migrations'][$position]['status'] !== self::APPLIED) {
                throw new RuntimeException('La migración anterior no está confirmada');
            }
        }
        $this->document['migrations'][$index]['status'] = self::STARTED;
        $this->document['status'] = 'in_progress';
        $this->persist();
    }

    public function applied(string $migration): void
    {
        $this->transition($migration, self::STARTED, self::APPLIED);
    }

    public function failed(string $migration): void
    {
        $this->reload();
        $index = $this->index($migration);
        if ($this->document['migrations'][$index]['status'] !== self::STARTED) {
            throw new RuntimeException('Solo una migración iniciada puede marcarse fallida');
        }
        $this->document['migrations'][$index]['status'] = self::FAILED;
        $this->document['status'] = 'failed';
        $this->persist();
    }

    public function markAmbiguous(): void
    {
        $this->reload();
        foreach ($this->document['migrations'] as $index => $migration) {
            if ($migration['status'] === self::STARTED) {
                $this->document['migrations'][$index]['status'] = self::AMBIGUOUS;
                $this->document['status'] = 'ambiguous';
                $this->persist();
                return;
            }
        }
    }

    public function complete(): void
    {
        $this->reload();
        foreach ($this->document['migrations'] as $migration) {
            if ($migration['status'] !== self::APPLIED) {
                throw new RuntimeException('No todas las migraciones están confirmadas');
            }
        }
        $this->document['status'] = $this->document['migrations'] === [] ? 'not_required' : 'applied';
        $this->persist();
    }

    public function isKnownUnchanged(): bool
    {
        $this->reload();
        foreach ($this->document['migrations'] as $migration) {
            if ($migration['status'] !== self::PENDING) {
                return false;
            }
        }
        return true;
    }

    public function mayHaveChanged(): bool
    {
        return !$this->isKnownUnchanged();
    }

    public function currentMigration(): ?string
    {
        $this->reload();
        foreach ($this->document['migrations'] as $migration) {
            if (in_array($migration['status'], [self::STARTED, self::FAILED, self::AMBIGUOUS], true)) {
                return $migration['id'];
            }
        }
        return null;
    }

    public function lastAppliedMigration(): ?string
    {
        $this->reload();
        $last = null;
        foreach ($this->document['migrations'] as $migration) {
            if ($migration['status'] === self::APPLIED) {
                $last = $migration['id'];
            }
        }
        return $last;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $this->reload();
        return $this->document;
    }

    private function transition(string $migration, string $from, string $to): void
    {
        $this->reload();
        $index = $this->index($migration);
        if ($this->document['migrations'][$index]['status'] !== $from) {
            throw new RuntimeException("Transición de migración no válida: {$from} -> {$to}");
        }
        $this->document['migrations'][$index]['status'] = $to;
        $this->persist();
    }

    private function index(string $migration): int
    {
        foreach ($this->document['migrations'] as $index => $entry) {
            if ($entry['id'] === $migration) {
                return $index;
            }
        }
        throw new RuntimeException('Migración ajena al journal');
    }

    private function persist(): void
    {
        $json = json_encode($this->document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar el journal de base de datos');
        }
        $this->paths->atomicWrite($this->base . '/' . self::JOURNAL, $json . "\n");
    }

    private function reload(): void
    {
        try {
            $data = json_decode(
                $this->paths->read($this->base . '/' . self::JOURNAL),
                true,
                32,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('El journal de base de datos está corrupto', 0, $exception);
        }
        if (!is_array($data)) {
            throw new RuntimeException('El journal de base de datos no contiene un objeto');
        }
        self::assertDocument($data, $this->attemptId);
        $this->document = $data;
    }

    /** @param array<string,mixed> $data */
    private static function assertDocument(array $data, string $attemptId): void
    {
        $fields = array_keys($data);
        sort($fields, SORT_STRING);
        if (
            $fields !== ['attempt_id', 'format_version', 'migrations', 'recovery', 'status']
            || $data['format_version'] !== self::FORMAT_VERSION
            || $data['attempt_id'] !== $attemptId
            || !is_array($data['migrations'])
        ) {
            throw new RuntimeException('Esquema de journal de base de datos no válido');
        }
        $ids = [];
        foreach ($data['migrations'] as $migration) {
            if (
                !is_array($migration)
                || array_keys($migration) !== ['id', 'status']
                || !is_string($migration['id'])
                || !in_array(
                    $migration['status'],
                    [self::PENDING, self::STARTED, self::APPLIED, self::FAILED, self::AMBIGUOUS],
                    true
                )
                || isset($ids[$migration['id']])
            ) {
                throw new RuntimeException('Entrada de migración no válida');
            }
            self::assertMigration($migration['id']);
            $ids[$migration['id']] = true;
        }
        if (!in_array($data['status'], ['not_required', 'pending', 'in_progress', 'applied', 'failed', 'ambiguous'], true)) {
            throw new RuntimeException('Estado de journal de base de datos no válido');
        }
        if ($data['migrations'] === [] && ($data['status'] !== 'not_required' || $data['recovery'] !== null)) {
            throw new RuntimeException('Journal sin migraciones incoherente');
        }
        if ($data['migrations'] !== []) {
            if (!is_array($data['recovery'])) {
                throw new RuntimeException('Journal con migraciones sin recovery');
            }
            try {
                $recovery = DatabaseRecoveryCapability::fromArray($data['recovery']);
            } catch (\InvalidArgumentException $exception) {
                throw new RuntimeException('Recovery persistido no válido', 0, $exception);
            }
            if (!$recovery->allowsMigrations()) {
                throw new RuntimeException('Recovery persistido insuficiente');
            }
        }
    }

    /** @param list<string> $migrations */
    private static function assertMigrations(array $migrations): void
    {
        if (array_values(array_unique($migrations)) !== $migrations) {
            throw new RuntimeException('El plan contiene migraciones duplicadas');
        }
        foreach ($migrations as $migration) {
            self::assertMigration($migration);
        }
    }

    private static function assertMigration(string $migration): void
    {
        if (preg_match('/^[A-Za-z0-9_]+@[A-Za-z][A-Za-z0-9_]*$/', $migration) !== 1) {
            throw new RuntimeException('Identificador de migración no válido');
        }
    }

    private static function assertAttemptId(string $attemptId): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $attemptId) !== 1) {
            throw new RuntimeException('Identificador de intento no válido');
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use DateTimeImmutable;
use RuntimeException;

/** Validated, immutable snapshot of one release-update attempt. */
final class ReleaseUpdateState
{
    public const FORMAT_VERSION = 2;
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_INTERRUPTED = 'interrupted';
    public const STATUS_FILESYSTEM_ROLLED_BACK = 'filesystem_rolled_back';
    public const STATUS_ROLLBACK_FAILED = 'rollback_failed';
    public const PHASE_PREPARING = 'preparing';
    public const PHASE_INSTALLING_FILES = 'installing_files';
    public const PHASE_PUBLISHING_CLEANUP = 'publishing_cleanup';
    public const PHASE_PROMOTING_MANIFEST = 'promoting_manifest';
    public const PHASE_MIGRATIONS = 'migrations';
    public const PHASE_COMPLETED = 'completed';

    /** @var list<string> */
    private const PHASES = [
        self::PHASE_PREPARING,
        self::PHASE_INSTALLING_FILES,
        self::PHASE_PUBLISHING_CLEANUP,
        self::PHASE_MIGRATIONS,
        self::PHASE_PROMOTING_MANIFEST,
        self::PHASE_COMPLETED,
    ];

    /** @var list<string> */
    private const LEGACY_FIELDS = [
        'format_version', 'attempt_id', 'from_version', 'target_version', 'status', 'phase',
        'started_at', 'updated_at', 'mutations_started', 'error', 'recovered_from',
        'recovered_status',
    ];

    /** @var list<string> */
    private const FIELDS = [
        'format_version', 'attempt_id', 'from_version', 'target_version', 'status', 'phase',
        'started_at', 'updated_at', 'mutations_started', 'error', 'recovered_from',
        'recovered_status', 'reconciliation',
    ];

    /** @param array{class:string,message:string}|null $error */
    private function __construct(
        private readonly string $attemptId,
        private readonly string $fromVersion,
        private readonly string $targetVersion,
        private readonly string $status,
        private readonly string $phase,
        private readonly string $startedAt,
        private readonly string $updatedAt,
        private readonly bool $mutationsStarted,
        private readonly ?array $error,
        private readonly ?string $recoveredFrom,
        private readonly ?string $recoveredStatus,
        /** @var array<string,string>|null */
        private readonly ?array $reconciliation
    ) {
    }

    public static function start(
        string $fromVersion,
        string $targetVersion,
        ?string $recoveredFrom = null,
        ?string $recoveredStatus = null
    ): self {
        ApplicationVersion::assertValid($fromVersion, 'La versión anterior del estado');
        ApplicationVersion::assertValid($targetVersion, 'La versión objetivo del estado');
        self::assertRecovery($recoveredFrom, $recoveredStatus);
        $now = self::now();
        return new self(
            bin2hex(random_bytes(16)),
            $fromVersion,
            $targetVersion,
            self::STATUS_IN_PROGRESS,
            self::PHASE_PREPARING,
            $now,
            $now,
            false,
            null,
            $recoveredFrom,
            $recoveredStatus,
            null
        );
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $fields = array_keys($data);
        sort($fields, SORT_STRING);
        $format = $data['format_version'] ?? null;
        $expected = $format === 1 ? self::LEGACY_FIELDS : self::FIELDS;
        sort($expected, SORT_STRING);
        if ($fields !== $expected || !in_array($format, [1, self::FORMAT_VERSION], true)) {
            throw new RuntimeException('Esquema de estado de actualización desconocido o incompleto');
        }
        if ($format === 1) {
            $data['reconciliation'] = null;
        }
        foreach (['attempt_id', 'from_version', 'target_version', 'status', 'phase', 'started_at', 'updated_at'] as $key) {
            if (!is_string($data[$key])) {
                throw new RuntimeException("Campo de estado no válido: {$key}");
            }
        }
        if (!is_bool($data['mutations_started'])) {
            throw new RuntimeException('Campo de estado no válido: mutations_started');
        }
        if ($data['recovered_from'] !== null && !is_string($data['recovered_from'])) {
            throw new RuntimeException('Campo de estado no válido: recovered_from');
        }
        if ($data['recovered_status'] !== null && !is_string($data['recovered_status'])) {
            throw new RuntimeException('Campo de estado no válido: recovered_status');
        }
        if ($data['error'] !== null && !is_array($data['error'])) {
            throw new RuntimeException('Campo de estado no válido: error');
        }
        self::assertAttemptId($data['attempt_id'], 'attempt_id');
        ApplicationVersion::assertValid($data['from_version'], 'La versión anterior del estado');
        ApplicationVersion::assertValid($data['target_version'], 'La versión objetivo del estado');
        self::assertTimestamp($data['started_at'], 'started_at');
        self::assertTimestamp($data['updated_at'], 'updated_at');
        if ($data['updated_at'] < $data['started_at']) {
            throw new RuntimeException('updated_at no puede preceder a started_at');
        }
        self::assertRecovery($data['recovered_from'], $data['recovered_status']);
        self::assertReconciliation($data['reconciliation']);
        self::assertCombination(
            $data['status'],
            $data['phase'],
            $data['mutations_started'],
            $data['error'],
            $data['reconciliation']
        );
        /** @var array{class:string,message:string}|null $error */
        $error = $data['error'];
        return new self(
            $data['attempt_id'],
            $data['from_version'],
            $data['target_version'],
            $data['status'],
            $data['phase'],
            $data['started_at'],
            $data['updated_at'],
            $data['mutations_started'],
            $error,
            $data['recovered_from'],
            $data['recovered_status'],
            $data['reconciliation']
        );
    }

    public function advance(string $phase, bool $mutationsStarted): self
    {
        $this->assertInProgress();
        $current = array_search($this->phase, self::PHASES, true);
        $next = array_search($phase, self::PHASES, true);
        if (!is_int($current) || !is_int($next) || $next !== $current + 1 || $phase === self::PHASE_COMPLETED) {
            throw new RuntimeException("Transición de actualización no válida: {$this->phase} -> {$phase}");
        }
        if ($this->mutationsStarted && !$mutationsStarted) {
            throw new RuntimeException('mutations_started no puede volver a false');
        }
        if (!$mutationsStarted) {
            throw new RuntimeException("La fase {$phase} requiere mutations_started");
        }
        return $this->copy(self::STATUS_IN_PROGRESS, $phase, true, null);
    }

    public function promoteWithoutMigrations(): self
    {
        $this->assertInProgress();
        if ($this->phase !== self::PHASE_PUBLISHING_CLEANUP || !$this->mutationsStarted) {
            throw new RuntimeException('Solo puede omitirse migrations tras preparar el filesystem');
        }
        return $this->copy(self::STATUS_IN_PROGRESS, self::PHASE_PROMOTING_MANIFEST, true, null);
    }

    public function complete(): self
    {
        $this->assertInProgress();
        if ($this->phase !== self::PHASE_PROMOTING_MANIFEST || !$this->mutationsStarted) {
            throw new RuntimeException('La actualización solo puede completarse después de promover el manifiesto');
        }
        return $this->copy(self::STATUS_COMPLETED, self::PHASE_COMPLETED, true, null);
    }

    public function fail(string $class, string $message): self
    {
        $this->assertInProgress();
        return $this->copy(
            self::STATUS_FAILED,
            $this->phase,
            $this->mutationsStarted,
            self::validatedError($class, $message)
        );
    }

    public function interrupt(): self
    {
        $this->assertInProgress();
        return $this->copy(self::STATUS_INTERRUPTED, $this->phase, $this->mutationsStarted, null);
    }

    public function filesystemRolledBack(?string $class = null, ?string $message = null): self
    {
        $this->assertInProgress();
        if (!in_array($this->phase, [self::PHASE_INSTALLING_FILES, self::PHASE_PUBLISHING_CLEANUP], true)) {
            throw new RuntimeException('Rollback automático prohibido desde migraciones');
        }
        $error = $class === null || $message === null ? null : self::validatedError($class, $message);
        return $this->copy(self::STATUS_FILESYSTEM_ROLLED_BACK, $this->phase, true, $error);
    }

    public function rollbackFailed(string $class, string $message): self
    {
        $this->assertInProgress();
        if (!in_array($this->phase, [self::PHASE_INSTALLING_FILES, self::PHASE_PUBLISHING_CLEANUP], true)) {
            throw new RuntimeException('Rollback automático prohibido desde migraciones');
        }
        return $this->copy(
            self::STATUS_ROLLBACK_FAILED,
            $this->phase,
            true,
            self::validatedError($class, $message)
        );
    }

    public function attemptId(): string
    {
        return $this->attemptId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function phase(): string
    {
        return $this->phase;
    }

    public function mutationsStarted(): bool
    {
        return $this->mutationsStarted;
    }

    public function fromVersion(): string
    {
        return $this->fromVersion;
    }

    public function targetVersion(): string
    {
        return $this->targetVersion;
    }

    /** @return array<string,string>|null */
    public function reconciliation(): ?array
    {
        return $this->reconciliation;
    }

    /** @param array<string,string> $reconciliation */
    public function reconcile(array $reconciliation): self
    {
        if ($this->reconciliation !== null) {
            if ($this->reconciliation !== $reconciliation) {
                throw new RuntimeException('El intento ya tiene otra reconciliación');
            }
            return $this;
        }
        self::assertReconciliation($reconciliation);
        $completion = $reconciliation['resolution'] === 'completion';
        return new self(
            $this->attemptId,
            $this->fromVersion,
            $this->targetVersion,
            $completion ? self::STATUS_COMPLETED : ($this->isInProgress() ? self::STATUS_INTERRUPTED : $this->status),
            $completion ? self::PHASE_COMPLETED : $this->phase,
            $this->startedAt,
            self::now(),
            $this->mutationsStarted,
            $completion ? null : $this->error,
            $this->recoveredFrom,
            $this->recoveredStatus,
            $reconciliation
        );
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'format_version' => self::FORMAT_VERSION,
            'attempt_id' => $this->attemptId,
            'from_version' => $this->fromVersion,
            'target_version' => $this->targetVersion,
            'status' => $this->status,
            'phase' => $this->phase,
            'started_at' => $this->startedAt,
            'updated_at' => $this->updatedAt,
            'mutations_started' => $this->mutationsStarted,
            'error' => $this->error,
            'recovered_from' => $this->recoveredFrom,
            'recovered_status' => $this->recoveredStatus,
            'reconciliation' => $this->reconciliation,
        ];
    }

    /** @param array{class:string,message:string}|null $error */
    private function copy(string $status, string $phase, bool $mutationsStarted, ?array $error): self
    {
        return new self(
            $this->attemptId,
            $this->fromVersion,
            $this->targetVersion,
            $status,
            $phase,
            $this->startedAt,
            self::now(),
            $mutationsStarted,
            $error,
            $this->recoveredFrom,
            $this->recoveredStatus,
            $this->reconciliation
        );
    }

    private function assertInProgress(): void
    {
        if (!$this->isInProgress()) {
            throw new RuntimeException("El estado terminal {$this->status} no admite transiciones");
        }
    }

    /** @param mixed $error */
    private static function assertCombination(
        string $status,
        string $phase,
        bool $mutations,
        mixed $error,
        mixed $reconciliation
    ): void {
        if (!in_array($phase, self::PHASES, true)) {
            throw new RuntimeException("Fase de actualización desconocida: {$phase}");
        }
        if ($phase !== self::PHASE_PREPARING && $phase !== self::PHASE_COMPLETED && !$mutations) {
            throw new RuntimeException("La fase {$phase} requiere mutations_started");
        }
        if ($status === self::STATUS_COMPLETED) {
            if ($phase !== self::PHASE_COMPLETED || !$mutations || $error !== null) {
                throw new RuntimeException('Estado completed incoherente');
            }
            if ($reconciliation !== null && $reconciliation['resolution'] !== 'completion') {
                throw new RuntimeException('Estado completed con reconciliación incoherente');
            }
            return;
        }
        if ($phase === self::PHASE_COMPLETED) {
            throw new RuntimeException('La fase completed requiere estado completed');
        }
        if ($status === self::STATUS_FILESYSTEM_ROLLED_BACK) {
            if (!in_array($phase, [self::PHASE_INSTALLING_FILES, self::PHASE_PUBLISHING_CLEANUP], true) || !$mutations) {
                throw new RuntimeException('Estado filesystem_rolled_back incoherente');
            }
            if ($error !== null) {
                if (!is_array($error)) {
                    throw new RuntimeException('Error de rollback no válido');
                }
                self::validatedError($error['class'] ?? null, $error['message'] ?? null);
            }
            return;
        }
        if ($status === self::STATUS_ROLLBACK_FAILED) {
            if (!in_array($phase, [self::PHASE_INSTALLING_FILES, self::PHASE_PUBLISHING_CLEANUP], true) || !$mutations || !is_array($error)) {
                throw new RuntimeException('Estado rollback_failed incoherente');
            }
            self::validatedError($error['class'] ?? null, $error['message'] ?? null);
            return;
        }
        if ($status === self::STATUS_FAILED) {
            if (!is_array($error)) {
                throw new RuntimeException('El estado failed requiere error');
            }
            $errorFields = array_keys($error);
            sort($errorFields, SORT_STRING);
            if ($errorFields !== ['class', 'message']) {
                throw new RuntimeException('El esquema de error persistido no es válido');
            }
            self::validatedError($error['class'] ?? null, $error['message'] ?? null);
            return;
        }
        if (!in_array($status, [self::STATUS_IN_PROGRESS, self::STATUS_INTERRUPTED], true) || $error !== null) {
            throw new RuntimeException("Estado de actualización no válido: {$status}");
        }
    }

    private static function assertReconciliation(mixed $reconciliation): void
    {
        if ($reconciliation === null) {
            return;
        }
        if (!is_array($reconciliation)) {
            throw new RuntimeException('Reconciliación de estado no válida');
        }
        $fields = array_keys($reconciliation);
        sort($fields, SORT_STRING);
        if ($fields !== ['classification', 'fingerprint', 'record_sha256', 'resolution', 'resolved_at']) {
            throw new RuntimeException('Esquema de reconciliación de estado no válido');
        }
        if (
            !is_string($reconciliation['classification'])
            || !in_array($reconciliation['classification'], [
                'SAFE_RETRY', 'CONFIRMED_ROLLBACK', 'CONFIRMED_COMPLETION',
            ], true)
            || !is_string($reconciliation['resolution'])
            || !in_array($reconciliation['resolution'], ['retry', 'rollback', 'completion'], true)
            || !is_string($reconciliation['fingerprint'])
            || preg_match('/^[a-f0-9]{64}$/', $reconciliation['fingerprint']) !== 1
            || !is_string($reconciliation['record_sha256'])
            || preg_match('/^[a-f0-9]{64}$/', $reconciliation['record_sha256']) !== 1
            || !is_string($reconciliation['resolved_at'])
        ) {
            throw new RuntimeException('Reconciliación de estado no válida');
        }
        self::assertTimestamp($reconciliation['resolved_at'], 'resolved_at');
        $expected = [
            'SAFE_RETRY' => 'retry',
            'CONFIRMED_ROLLBACK' => 'rollback',
            'CONFIRMED_COMPLETION' => 'completion',
        ];
        if ($expected[$reconciliation['classification']] !== $reconciliation['resolution']) {
            throw new RuntimeException('Clasificación y resolución reconciliada no coinciden');
        }
    }

    /** @return array{class:string,message:string} */
    private static function validatedError(mixed $class, mixed $message): array
    {
        if (
            !is_string($class)
            || preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]{0,199}$/', $class) !== 1
            || !is_string($message)
            || $message === ''
            || strlen($message) > 500
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $message) === 1
        ) {
            throw new RuntimeException('Error persistido no válido');
        }
        return ['class' => $class, 'message' => $message];
    }

    private static function assertAttemptId(string $attemptId, string $field): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $attemptId) !== 1) {
            throw new RuntimeException("Identificador no válido en {$field}");
        }
    }

    private static function assertRecovery(?string $attemptId, ?string $status): void
    {
        if (($attemptId === null) !== ($status === null)) {
            throw new RuntimeException('Los campos de recuperación deben aparecer juntos');
        }
        if ($attemptId === null) {
            return;
        }
        self::assertAttemptId($attemptId, 'recovered_from');
        if (!in_array($status, [self::STATUS_FAILED, self::STATUS_INTERRUPTED, self::STATUS_FILESYSTEM_ROLLED_BACK], true)) {
            throw new RuntimeException('Estado de recuperación no válido');
        }
    }

    private static function assertTimestamp(string $timestamp, string $field): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $timestamp);
        if ($parsed === false || $parsed->format('Y-m-d\\TH:i:s\\Z') !== $timestamp) {
            throw new RuntimeException("Instante UTC no válido en {$field}");
        }
    }

    private static function now(): string
    {
        return gmdate('Y-m-d\\TH:i:s\\Z');
    }
}

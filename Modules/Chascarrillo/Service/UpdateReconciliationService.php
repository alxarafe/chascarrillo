<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use Closure;
use DateTimeImmutable;
use JsonException;
use RuntimeException;

final class UpdateReconciliationService
{
    public const RECORD = 'reconciliation.json';

    /** @var Closure():array{table_exists:bool,migrations:list<string>}|null */
    private readonly ?Closure $migrationSnapshot;

    /** @var Closure(string):bool|null */
    private readonly ?Closure $releaseValidation;

    /** @var Closure(ReleaseUpdateStorage,ReleaseUpdateState):void */
    private readonly Closure $stateWriter;

    /**
     * @param (callable():array{table_exists:bool,migrations:list<string>})|null $migrationSnapshot
     * @param (callable(string):bool)|null $releaseValidation
     * @param (callable(ReleaseUpdateStorage,ReleaseUpdateState):void)|null $stateWriter
     */
    public function __construct(
        private readonly string $installRoot,
        ?callable $migrationSnapshot = null,
        ?callable $releaseValidation = null,
        ?callable $stateWriter = null
    ) {
        $this->migrationSnapshot = $migrationSnapshot === null
            ? null
            : Closure::fromCallable($migrationSnapshot);
        $this->releaseValidation = $releaseValidation === null
            ? null
            : Closure::fromCallable($releaseValidation);
        $this->stateWriter = $stateWriter === null
            ? static function (ReleaseUpdateStorage $storage, ReleaseUpdateState $state): void {
                $storage->write($state);
            }
            : Closure::fromCallable($stateWriter);
    }

    /** @return array<string,mixed> */
    public function apply(string $attemptId, ?string $resolution, string $expectedFingerprint): array
    {
        UpdateReconciliationPlan::assertAttemptId($attemptId);
        if (
            !in_array($resolution, [
                UpdateReconciliationPlan::RESOLUTION_RETRY,
                UpdateReconciliationPlan::RESOLUTION_ROLLBACK,
                UpdateReconciliationPlan::RESOLUTION_COMPLETION,
            ], true)
        ) {
            throw new RuntimeException('Resolución administrativa no válida');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $expectedFingerprint) !== 1) {
            throw new RuntimeException('Huella esperada no válida');
        }

        $storage = new ReleaseUpdateStorage($this->installRoot);
        $storage->acquire();
        try {
            $state = $storage->load();
            if ($state === null || $state->attemptId() !== $attemptId) {
                throw new RuntimeException('El intento solicitado no es el intento activo');
            }
            if ($state->reconciliation() !== null) {
                return $this->confirmIdempotent($state, $resolution, $expectedFingerprint);
            }

            $plan = (new UpdateReconciliationInspector(
                $this->installRoot,
                $this->migrationSnapshot,
                $this->releaseValidation
            ))->inspect($attemptId);
            if (!hash_equals($plan->fingerprint(), $expectedFingerprint)) {
                throw new RuntimeException('La huella de evidencia cambió desde inspect; vuelva a inspeccionar');
            }
            if ($plan->resolution() === null || $plan->resolution() !== $resolution) {
                throw new RuntimeException('La resolución solicitada no coincide con la única resolución autorizada');
            }

            $record = $this->persistEvidence($plan);
            $paths = new SafePath($this->installRoot);
            $recordPath = $this->recordPath($attemptId);
            $recordHash = $paths->hash($recordPath);
            $reconciliation = [
                'classification' => $plan->classification(),
                'resolution' => $resolution,
                'fingerprint' => $plan->fingerprint(),
                'record_sha256' => $recordHash,
                'resolved_at' => $record['resolved_at'],
            ];
            $resolved = $state->reconcile($reconciliation);
            ($this->stateWriter)($storage, $resolved);
            return $record;
        } finally {
            $storage->release();
        }
    }

    /** @return array<string,mixed> */
    private function persistEvidence(UpdateReconciliationPlan $plan): array
    {
        $paths = new SafePath($this->installRoot);
        $base = FilesystemRecoveryJournal::ROOT . '/' . $plan->attemptId();
        $type = $paths->nodeType($base);
        if ($type === SafePath::NODE_MISSING) {
            $paths->ensureDirectory($base);
        } elseif ($type !== SafePath::NODE_DIRECTORY) {
            throw new RuntimeException('El área de recovery del intento no es segura');
        }

        $recordPath = $this->recordPath($plan->attemptId());
        $record = [
            'format_version' => 1,
            'attempt_id' => $plan->attemptId(),
            'classification' => $plan->classification(),
            'resolution' => $plan->resolution(),
            'fingerprint' => $plan->fingerprint(),
            'resolved_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'evidence' => $plan->evidence(),
        ];
        $nodeType = $paths->nodeType($recordPath);
        if ($nodeType === SafePath::NODE_FILE) {
            $existing = $this->loadRecord($paths, $recordPath);
            foreach (['attempt_id', 'classification', 'resolution', 'fingerprint', 'evidence'] as $field) {
                if ($existing[$field] !== $record[$field]) {
                    throw new RuntimeException('Ya existe una evidencia de reconciliación diferente');
                }
            }
            return $existing;
        }
        if ($nodeType !== SafePath::NODE_MISSING) {
            throw new RuntimeException('El registro de reconciliación no es un archivo regular seguro');
        }
        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar la reconciliación');
        }
        $paths->atomicWrite($recordPath, $json . "\n");
        return $this->loadRecord($paths, $recordPath);
    }

    /** @return array<string,mixed> */
    private function confirmIdempotent(
        ReleaseUpdateState $state,
        string $resolution,
        string $expectedFingerprint
    ): array {
        $reconciliation = $state->reconciliation();
        if (
            $reconciliation === null
            || $reconciliation['resolution'] !== $resolution
            || !hash_equals($reconciliation['fingerprint'], $expectedFingerprint)
        ) {
            throw new RuntimeException('El intento ya fue reconciliado con otra evidencia o resolución');
        }
        $paths = new SafePath($this->installRoot);
        $recordPath = $this->recordPath($state->attemptId());
        if (
            $paths->nodeType($recordPath) !== SafePath::NODE_FILE
            || !hash_equals($reconciliation['record_sha256'], $paths->hash($recordPath))
        ) {
            throw new RuntimeException('El registro durable de reconciliación no coincide con el estado');
        }
        $record = $this->loadRecord($paths, $recordPath);
        if (
            $record['attempt_id'] !== $state->attemptId()
            || $record['classification'] !== $reconciliation['classification']
            || $record['resolution'] !== $resolution
            || $record['fingerprint'] !== $expectedFingerprint
            || $record['resolved_at'] !== $reconciliation['resolved_at']
        ) {
            throw new RuntimeException('La evidencia durable de reconciliación es incoherente');
        }
        return $record;
    }

    /** @return array<string,mixed> */
    private function loadRecord(SafePath $paths, string $recordPath): array
    {
        try {
            $record = json_decode($paths->read($recordPath), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('El registro de reconciliación está corrupto', 0, $exception);
        }
        if (!is_array($record)) {
            throw new RuntimeException('El registro de reconciliación no contiene un objeto');
        }
        $fields = array_keys($record);
        sort($fields, SORT_STRING);
        if (
            $fields !== [
                'attempt_id', 'classification', 'evidence', 'fingerprint',
                'format_version', 'resolution', 'resolved_at',
            ]
            || $record['format_version'] !== 1
            || !is_string($record['attempt_id'])
            || !is_string($record['classification'])
            || !is_string($record['resolution'])
            || !is_string($record['fingerprint'])
            || !is_string($record['resolved_at'])
            || !is_array($record['evidence'])
        ) {
            throw new RuntimeException('Esquema de registro de reconciliación no válido');
        }
        UpdateReconciliationPlan::assertAttemptId($record['attempt_id']);
        if (preg_match('/^[a-f0-9]{64}$/', $record['fingerprint']) !== 1) {
            throw new RuntimeException('Huella de registro de reconciliación no válida');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $record['resolved_at']);
        if ($date === false || $date->format('Y-m-d\TH:i:s\Z') !== $record['resolved_at']) {
            throw new RuntimeException('Instante de reconciliación no válido');
        }
        return $record;
    }

    private function recordPath(string $attemptId): string
    {
        UpdateReconciliationPlan::assertAttemptId($attemptId);
        return FilesystemRecoveryJournal::ROOT . '/' . $attemptId . '/' . self::RECORD;
    }
}

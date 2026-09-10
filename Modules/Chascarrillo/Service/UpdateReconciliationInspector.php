<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use Closure;
use RuntimeException;
use Throwable;

final class UpdateReconciliationInspector
{
    /** @var Closure():array{table_exists:bool,migrations:list<string>} */
    private readonly Closure $migrationSnapshot;

    /** @var Closure(string):bool */
    private readonly Closure $releaseValidation;

    private readonly SafePath $paths;

    /**
     * @param (callable():array{table_exists:bool,migrations:list<string>})|null $migrationSnapshot
     * @param (callable(string):bool)|null $releaseValidation
     */
    public function __construct(
        private readonly string $installRoot,
        ?callable $migrationSnapshot = null,
        ?callable $releaseValidation = null
    ) {
        $this->paths = new SafePath($installRoot);
        $this->migrationSnapshot = $migrationSnapshot === null
            ? static fn (): array => (new AlxarafeMigrationTableInspector())->snapshot()
            : Closure::fromCallable($migrationSnapshot);
        $this->releaseValidation = $releaseValidation === null
            ? static function (string $root): bool {
                (new ReleaseValidator())->validate($root, true);
                return true;
            }
            : Closure::fromCallable($releaseValidation);
    }

    public function inspect(?string $attemptId = null): UpdateReconciliationPlan
    {
        if ($attemptId !== null) {
            UpdateReconciliationPlan::assertAttemptId($attemptId);
        }
        $state = (new ReleaseUpdateStorage($this->installRoot))->load();
        if ($state === null) {
            throw new RuntimeException('No existe un intento de actualización para inspeccionar');
        }
        $attemptId ??= $state->attemptId();
        if ($attemptId !== $state->attemptId()) {
            throw new RuntimeException('El intento solicitado no es el intento activo');
        }

        $evidence = [
            'state' => [
                'sha256' => $this->paths->hash(ReleaseUpdateStorage::STATE_PATH),
                'status' => $state->status(),
                'phase' => $state->phase(),
                'mutations_started' => $state->mutationsStarted(),
                'from_version' => $state->fromVersion(),
                'target_version' => $state->targetVersion(),
                'already_reconciled' => $state->reconciliation() !== null,
            ],
        ];

        if ($state->reconciliation() !== null) {
            $reconciliation = $state->reconciliation();
            $evidence['existing_resolution'] = [
                'classification' => $reconciliation['classification'],
                'resolution' => $reconciliation['resolution'],
                'fingerprint' => $reconciliation['fingerprint'],
                'record_sha256' => $reconciliation['record_sha256'],
            ];
            return new UpdateReconciliationPlan(
                $attemptId,
                $reconciliation['classification'],
                $reconciliation['resolution'],
                $evidence
            );
        }

        if (!$state->mutationsStarted()) {
            $evidence['filesystem'] = ['status' => 'not_started'];
            $evidence['database'] = ['status' => 'not_started'];
            if (
                $state->phase() === ReleaseUpdateState::PHASE_PREPARING
                && $state->status() !== ReleaseUpdateState::STATUS_COMPLETED
            ) {
                return new UpdateReconciliationPlan(
                    $attemptId,
                    UpdateReconciliationPlan::SAFE_RETRY,
                    UpdateReconciliationPlan::RESOLUTION_RETRY,
                    $evidence
                );
            }
            return new UpdateReconciliationPlan(
                $attemptId,
                UpdateReconciliationPlan::CONFLICT,
                null,
                $evidence
            );
        }

        [$filesystem, $filesystemDocument, $filesystemConflict] = $this->filesystemEvidence($attemptId);
        [$manifest, $manifestDocument, $manifestConflict] = $this->manifestEvidence($state);
        [$database, $databaseFlags] = $this->databaseEvidence($state, $attemptId);

        $evidence['filesystem'] = $filesystem;
        $evidence['manifest'] = $manifest;
        $evidence['database'] = $database;

        if ($filesystemConflict || $manifestConflict || $databaseFlags['conflict']) {
            return new UpdateReconciliationPlan($attemptId, UpdateReconciliationPlan::CONFLICT, null, $evidence);
        }
        if ($filesystemDocument === null) {
            return new UpdateReconciliationPlan($attemptId, UpdateReconciliationPlan::AMBIGUOUS, null, $evidence);
        }

        $oldCrosscheck = $manifestDocument !== null
            && $this->manifestMatchesOperations($manifestDocument['files'], $filesystemDocument, true);
        $newCrosscheck = $manifestDocument !== null
            && $this->manifestMatchesOperations($manifestDocument['files'], $filesystemDocument, false);
        $manifestOld = $manifest['kind'] === 'previous' && $manifest['tree_matches'] && $oldCrosscheck;
        $manifestNew = $manifest['kind'] === 'target' && $manifest['tree_matches'] && $newCrosscheck;
        $filesystemOld = in_array($filesystem['tree'], ['previous', 'both'], true);
        $filesystemNew = in_array($filesystem['tree'], ['target', 'both'], true);

        $evidence['coherence'] = [
            'filesystem_previous' => $filesystemOld,
            'filesystem_target' => $filesystemNew,
            'manifest_previous' => $manifestOld,
            'manifest_target' => $manifestNew,
            'database_previous' => $databaseFlags['previous'],
            'database_target' => $databaseFlags['target'],
        ];

        if ($databaseFlags['ambiguous']) {
            return new UpdateReconciliationPlan($attemptId, UpdateReconciliationPlan::AMBIGUOUS, null, $evidence);
        }

        if (
            $state->status() === ReleaseUpdateState::STATUS_FILESYSTEM_ROLLED_BACK
            && $filesystem['journal_state'] === 'rolled_back'
            && $this->operationsHaveState($filesystemDocument, 'restored')
            && $filesystemOld
            && $manifestOld
            && $databaseFlags['previous']
        ) {
            return new UpdateReconciliationPlan(
                $attemptId,
                UpdateReconciliationPlan::CONFIRMED_ROLLBACK,
                UpdateReconciliationPlan::RESOLUTION_ROLLBACK,
                $evidence
            );
        }

        $operationsApplied = $this->operationsHaveState($filesystemDocument, 'applied');
        if (
            $filesystemNew
            && $manifestNew
            && $databaseFlags['target']
            && in_array($filesystem['journal_state'], ['prepared', 'completed'], true)
            && $operationsApplied
            && in_array(
                $state->phase(),
                [ReleaseUpdateState::PHASE_MIGRATIONS, ReleaseUpdateState::PHASE_PROMOTING_MANIFEST],
                true
            )
        ) {
            try {
                $this->assertReleaseNodesSafe();
                $valid = ($this->releaseValidation)($this->installRoot);
            } catch (Throwable) {
                $valid = false;
            }
            $evidence['release_validation'] = $valid ? 'passed' : 'failed';
            if ($valid) {
                return new UpdateReconciliationPlan(
                    $attemptId,
                    UpdateReconciliationPlan::CONFIRMED_COMPLETION,
                    UpdateReconciliationPlan::RESOLUTION_COMPLETION,
                    $evidence
                );
            }
            return new UpdateReconciliationPlan($attemptId, UpdateReconciliationPlan::CONFLICT, null, $evidence);
        }

        if (
            ($manifest['kind'] === 'previous' && ($filesystemNew || $databaseFlags['target']))
            || ($manifest['kind'] === 'target' && ($filesystemOld || $databaseFlags['previous']))
            || ($manifestOld && $databaseFlags['target'])
            || ($manifestNew && $databaseFlags['previous'])
            || ($filesystemOld && ($manifestNew || $databaseFlags['target']))
            || ($filesystemNew && ($manifestOld || $databaseFlags['previous']))
        ) {
            return new UpdateReconciliationPlan($attemptId, UpdateReconciliationPlan::CONFLICT, null, $evidence);
        }
        if ($databaseFlags['recovery_required'] || $filesystem['tree'] === 'partial') {
            return new UpdateReconciliationPlan(
                $attemptId,
                UpdateReconciliationPlan::RECOVERY_REQUIRED,
                null,
                $evidence
            );
        }
        return new UpdateReconciliationPlan($attemptId, UpdateReconciliationPlan::AMBIGUOUS, null, $evidence);
    }

    /**
     * @return array{
     *   0:array<string,mixed>,
     *   1:array<string,mixed>|null,
     *   2:bool
     * }
     */
    private function filesystemEvidence(string $attemptId): array
    {
        $base = FilesystemRecoveryJournal::ROOT . '/' . $attemptId;
        $journalPath = $base . '/' . FilesystemRecoveryJournal::JOURNAL;
        $type = $this->paths->nodeType($journalPath);
        if ($type === SafePath::NODE_MISSING) {
            return [['status' => 'missing', 'tree' => 'unknown'], null, false];
        }
        if ($type !== SafePath::NODE_FILE) {
            return [['status' => 'unsafe_node', 'tree' => 'conflict'], null, true];
        }
        try {
            $journal = FilesystemRecoveryJournal::open($this->paths, $attemptId);
            $document = $journal->toArray();
        } catch (Throwable) {
            return [['status' => 'invalid', 'tree' => 'conflict'], null, true];
        }

        $backupConflict = false;
        $expected = [];
        foreach ($document['operations'] as $operation) {
            $path = $operation['path'];
            if (!isset($expected[$path])) {
                $expected[$path] = [
                    'old_type' => $operation['original_type'],
                    'old_hash' => $operation['original_hash'],
                    'new_type' => $operation['new_type'],
                    'new_hash' => $operation['new_hash'],
                ];
            } else {
                $expected[$path]['new_type'] = $operation['new_type'];
                $expected[$path]['new_hash'] = $operation['new_hash'];
            }
            if ($operation['original_type'] === SafePath::NODE_FILE) {
                $backup = $base . '/' . $operation['backup'];
                if (
                    $this->paths->nodeType($backup) !== SafePath::NODE_FILE
                    || $this->paths->hash($backup) !== $operation['original_hash']
                ) {
                    $backupConflict = true;
                }
            }
        }
        ksort($expected, SORT_STRING);

        $oldAll = true;
        $newAll = true;
        $external = false;
        $pathStates = [];
        foreach ($expected as $path => $states) {
            $type = $this->paths->nodeType($path);
            $hash = $type === SafePath::NODE_FILE ? $this->paths->hash($path) : null;
            $old = $type === $states['old_type'] && $hash === $states['old_hash'];
            $new = $type === $states['new_type'] && $hash === $states['new_hash'];
            $oldAll = $oldAll && $old;
            $newAll = $newAll && $new;
            if (!$old && !$new) {
                $external = true;
            }
            $pathStates[$path] = $old && $new ? 'both' : ($old ? 'previous' : ($new ? 'target' : 'conflict'));
        }

        if ($oldAll && $newAll) {
            $tree = 'both';
        } elseif ($oldAll) {
            $tree = 'previous';
        } elseif ($newAll) {
            $tree = 'target';
        } else {
            $tree = 'partial';
        }
        $mixed = !$oldAll && !$newAll;
        return [[
            'status' => 'valid',
            'sha256' => $this->paths->hash($journalPath),
            'journal_state' => $document['state'],
            'operation_count' => count($document['operations']),
            'tree' => $tree,
            'paths' => $pathStates,
            'backups_valid' => !$backupConflict,
        ], $document, $backupConflict || $external || $mixed];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>|null,2:bool} */
    private function manifestEvidence(ReleaseUpdateState $state): array
    {
        $path = ManagedFileManifest::FILENAME;
        $type = $this->paths->nodeType($path);
        if ($type === SafePath::NODE_MISSING) {
            return [['status' => 'missing', 'kind' => 'absent', 'tree_matches' => false], null, false];
        }
        if ($type !== SafePath::NODE_FILE) {
            return [['status' => 'unsafe_node', 'kind' => 'invalid', 'tree_matches' => false], null, true];
        }
        try {
            $manifest = ManagedFileManifest::load($this->installRoot, true, true);
        } catch (Throwable) {
            return [['status' => 'invalid', 'kind' => 'invalid', 'tree_matches' => false], null, true];
        }
        $treeMatches = true;
        $mismatches = [];
        foreach ($manifest['files'] as $relative => $hash) {
            $nodeType = $this->paths->nodeType($relative);
            if ($nodeType !== SafePath::NODE_FILE || $this->paths->hash($relative) !== $hash) {
                $treeMatches = false;
                $mismatches[] = $relative;
            }
        }
        $kind = $manifest['application_version'] === $state->fromVersion()
            ? 'previous'
            : ($manifest['application_version'] === $state->targetVersion() ? 'target' : 'other');
        return [[
            'status' => 'valid',
            'sha256' => $this->paths->hash($path),
            'kind' => $kind,
            'application_version' => $manifest['application_version'],
            'inventory_sha256' => hash(
                'sha256',
                UpdateReconciliationPlan::canonicalJson($manifest['files'])
            ),
            'file_count' => count($manifest['files']),
            'tree_matches' => $treeMatches,
            'mismatches' => $mismatches,
        ], $manifest, false];
    }

    /**
     * @return array{
     *   0:array<string,mixed>,
     *   1:array{previous:bool,target:bool,ambiguous:bool,recovery_required:bool,conflict:bool}
     * }
     */
    private function databaseEvidence(ReleaseUpdateState $state, string $attemptId): array
    {
        $journalPath = FilesystemRecoveryJournal::ROOT . '/' . $attemptId
            . '/' . DatabaseRecoveryJournal::JOURNAL;
        $type = $this->paths->nodeType($journalPath);
        if ($type === SafePath::NODE_MISSING) {
            $knownPrevious = $state->status() === ReleaseUpdateState::STATUS_FILESYSTEM_ROLLED_BACK
                && in_array(
                    $state->phase(),
                    [ReleaseUpdateState::PHASE_INSTALLING_FILES, ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP],
                    true
                );
            if ($knownPrevious) {
                try {
                    $snapshot = ($this->migrationSnapshot)();
                    $this->assertMigrationSnapshot($snapshot);
                    $actual = $snapshot['migrations'];
                    sort($actual, SORT_STRING);
                    return [
                        [
                            'status' => 'not_started',
                            'table_exists' => $snapshot['table_exists'],
                            'table_snapshot_sha256' => hash(
                                'sha256',
                                UpdateReconciliationPlan::canonicalJson($actual)
                            ),
                        ],
                        [
                            'previous' => true,
                            'target' => false,
                            'ambiguous' => false,
                            'recovery_required' => false,
                            'conflict' => false,
                        ],
                    ];
                } catch (Throwable) {
                    // Without the real table snapshot, rollback is not confirmed.
                }
            }
            return [
                ['status' => 'missing_or_unavailable'],
                [
                    'previous' => false,
                    'target' => false,
                    'ambiguous' => true,
                    'recovery_required' => false,
                    'conflict' => false,
                ],
            ];
        }
        if ($type !== SafePath::NODE_FILE) {
            return [
                ['status' => 'unsafe_node'],
                [
                    'previous' => false, 'target' => false, 'ambiguous' => false,
                    'recovery_required' => false, 'conflict' => true,
                ],
            ];
        }
        try {
            $document = DatabaseRecoveryJournal::open($this->paths, $attemptId)->toArray();
            $snapshot = ($this->migrationSnapshot)();
            $this->assertMigrationSnapshot($snapshot);
        } catch (Throwable) {
            return [
                ['status' => 'invalid_or_unavailable'],
                [
                    'previous' => false, 'target' => false, 'ambiguous' => true,
                    'recovery_required' => false, 'conflict' => false,
                ],
            ];
        }

        $actual = $snapshot['migrations'];
        sort($actual, SORT_STRING);
        $actualSet = array_fill_keys($actual, true);
        $statuses = [];
        $allAbsent = true;
        $allPresent = true;
        $hasUncertain = false;
        $hasFailed = false;
        foreach ($document['migrations'] as $migration) {
            $statuses[$migration['id']] = $migration['status'];
            $present = isset($actualSet[$migration['id']]);
            $allAbsent = $allAbsent && !$present;
            $allPresent = $allPresent && $present;
            $hasUncertain = $hasUncertain
                || in_array($migration['status'], ['started', 'ambiguous'], true);
            $hasFailed = $hasFailed || $migration['status'] === 'failed';
        }
        ksort($statuses, SORT_STRING);

        $previous = false;
        $target = false;
        $conflict = false;
        $recoveryRequired = false;
        if ($document['status'] === 'not_required' && $statuses === []) {
            $previous = true;
            $target = true;
        } elseif ($hasUncertain || $document['status'] === 'ambiguous') {
            // A migration row cannot prove a started DDL completed.
        } elseif ($hasFailed || $document['status'] === 'failed') {
            $recoveryRequired = true;
        } elseif (
            $document['status'] === 'pending'
            && count(array_unique($statuses)) <= 1
            && ($statuses === [] || reset($statuses) === 'pending')
            && $allAbsent
        ) {
            $previous = true;
        } elseif (
            $document['status'] === 'applied'
            && count(array_unique($statuses)) <= 1
            && ($statuses === [] || reset($statuses) === 'applied')
            && $allPresent
            && ($snapshot['table_exists'] || $statuses === [])
        ) {
            $target = true;
        } elseif ($document['status'] === 'in_progress') {
            $recoveryRequired = true;
        } else {
            $conflict = true;
        }

        return [[
            'status' => 'valid',
            'sha256' => $this->paths->hash($journalPath),
            'journal_status' => $document['status'],
            'migration_statuses' => $statuses,
            'table_exists' => $snapshot['table_exists'],
            'table_snapshot_sha256' => hash(
                'sha256',
                UpdateReconciliationPlan::canonicalJson($actual)
            ),
            'recovery' => $this->recoveryEvidence($document['recovery']),
        ], [
            'previous' => $previous,
            'target' => $target,
            'ambiguous' => $hasUncertain || $document['status'] === 'ambiguous',
            'recovery_required' => $recoveryRequired,
            'conflict' => $conflict,
        ]];
    }

    /** @param array<string,mixed> $filesystem */
    private function operationsHaveState(array $filesystem, string $state): bool
    {
        foreach ($filesystem['operations'] as $operation) {
            if ($operation['state'] !== $state) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,string> $manifestFiles @param array<string,mixed> $filesystem */
    private function manifestMatchesOperations(array $manifestFiles, array $filesystem, bool $original): bool
    {
        foreach ($filesystem['operations'] as $operation) {
            if (!in_array($operation['type'], ['copy', 'remove'], true)) {
                continue;
            }
            $type = $original ? $operation['original_type'] : $operation['new_type'];
            $hash = $original ? $operation['original_hash'] : $operation['new_hash'];
            if ($type === SafePath::NODE_FILE && ($manifestFiles[$operation['path']] ?? null) !== $hash) {
                return false;
            }
            if ($type === SafePath::NODE_MISSING && isset($manifestFiles[$operation['path']])) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed>|null $recovery @return array<string,mixed>|null */
    private function recoveryEvidence(?array $recovery): ?array
    {
        if ($recovery === null) {
            return null;
        }
        return [
            'strategy' => $recovery['strategy'],
            'level' => $recovery['level'],
            'provider' => $recovery['provider'],
            'reference_sha256' => is_string($recovery['reference'])
                ? hash('sha256', $recovery['reference'])
                : null,
            'backup_created_at' => $recovery['backup_created_at'],
            'verified_at' => $recovery['verified_at'],
            'restore_tested' => $recovery['restore_tested'],
        ];
    }

    private function assertReleaseNodesSafe(): void
    {
        foreach (
            [
                'composer.lock',
                'vendor/composer/installed.json',
                'Modules/Chascarrillo/Service/UpdateService.php',
                ...ReleaseValidator::ESSENTIAL_TEMPLATES,
            ] as $relative
        ) {
            $this->paths->requireFile($relative);
        }
        $this->paths->requireDirectory('vendor');
        $this->paths->requireDirectory('public_html');
    }

    /** @param array<string,mixed> $snapshot */
    private function assertMigrationSnapshot(array $snapshot): void
    {
        $fields = array_keys($snapshot);
        sort($fields, SORT_STRING);
        if (
            $fields !== ['migrations', 'table_exists']
            || !is_bool($snapshot['table_exists'])
            || !is_array($snapshot['migrations'])
            || !array_is_list($snapshot['migrations'])
        ) {
            throw new RuntimeException('Snapshot de migraciones no válido');
        }
        $seen = [];
        foreach ($snapshot['migrations'] as $migration) {
            if (!is_string($migration) || isset($seen[$migration])) {
                throw new RuntimeException('Snapshot de migraciones no válido');
            }
            $seen[$migration] = true;
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modules\Chascarrillo\Service\DatabaseRecoveryCapability;
use Modules\Chascarrillo\Service\DatabaseRecoveryJournal;
use Modules\Chascarrillo\Service\FilesystemRecoveryJournal;
use Modules\Chascarrillo\Service\ManagedFileManifest;
use Modules\Chascarrillo\Service\ReleaseUpdateState;
use Modules\Chascarrillo\Service\ReleaseUpdateStorage;
use Modules\Chascarrillo\Service\SafePath;
use Modules\Chascarrillo\Service\UpdateReconciliationInspector;
use Modules\Chascarrillo\Service\UpdateReconciliationPlan;
use Modules\Chascarrillo\Service\UpdateReconciliationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UpdateReconciliationTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = sys_get_temp_dir() . '/chascarrillo-reconciliation-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace, 0755, true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->workspace);
        parent::tearDown();
    }

    public function testInspectIsByteForByteReadOnlyAndNoMutationIsSafeRetry(): void
    {
        [$install, $state] = $this->stateFixture('safe-retry', false);
        $before = $this->snapshot($install);

        $plan = $this->inspector($install)->inspect($state->attemptId());

        self::assertSame(UpdateReconciliationPlan::SAFE_RETRY, $plan->classification());
        self::assertSame(UpdateReconciliationPlan::RESOLUTION_RETRY, $plan->resolution());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $plan->fingerprint());
        self::assertSame($before, $this->snapshot($install));
    }

    public function testCompletedB52RollbackAndPreviousDatabaseAreConfirmed(): void
    {
        [$install, $state] = $this->filesystemFixture('rolled-back', true, false);
        $migration = '2026_09_10_000001_pending@Test';
        DatabaseRecoveryJournal::prepare(
            new SafePath($install),
            $state->attemptId(),
            DatabaseRecoveryCapability::externalVerified(
                'test-provider',
                'rollback-backup',
                '2026-09-10T08:00:00Z',
                '2026-09-10T08:01:00Z',
                true
            ),
            [$migration]
        );

        $plan = $this->inspector($install)->inspect($state->attemptId());

        self::assertSame(UpdateReconciliationPlan::CONFIRMED_ROLLBACK, $plan->classification());
        self::assertSame(UpdateReconciliationPlan::RESOLUTION_ROLLBACK, $plan->resolution());
    }

    public function testNewTreeManifestAndAppliedMigrationsAreConfirmedCompletion(): void
    {
        [$install, $state, $migration] = $this->completionFixture('completed-materially');

        $plan = $this->inspector($install, [$migration])->inspect($state->attemptId());

        self::assertSame(UpdateReconciliationPlan::CONFIRMED_COMPLETION, $plan->classification());
        self::assertSame(UpdateReconciliationPlan::RESOLUTION_COMPLETION, $plan->resolution());
    }

    public function testMixedTreeAndExternalModificationAreConflicts(): void
    {
        foreach (['mixed', 'external'] as $case) {
            [$install, $state] = $this->filesystemFixture($case, false, false);
            $path = $install . '/managed.txt';
            file_put_contents($path, $case === 'mixed' ? 'old' : 'third-party');

            $plan = $this->inspector($install)->inspect($state->attemptId());

            self::assertSame(UpdateReconciliationPlan::CONFLICT, $plan->classification(), $case);
            self::assertNull($plan->resolution());
        }
    }

    public function testManifestAndDatabaseCrossedStatesRemainBlocked(): void
    {
        [$newTreeOldManifest, $first, $migration] = $this->completionFixture('old-manifest-new-db');
        $this->writeManifest($newTreeOldManifest, '0.8.17', ['managed.txt' => 'old']);
        self::assertSame(
            UpdateReconciliationPlan::CONFLICT,
            $this->inspector($newTreeOldManifest, [$migration])->inspect($first->attemptId())->classification()
        );

        [$newManifestOldDb, $second, $migration] = $this->completionFixture('new-manifest-old-db');
        self::assertSame(
            UpdateReconciliationPlan::CONFLICT,
            $this->inspector($newManifestOldDb)->inspect($second->attemptId())->classification()
        );
    }

    public function testStartedMigrationIsAmbiguousEvenWhenItsRowExists(): void
    {
        [$install, $state, $migration] = $this->completionFixture('started', false);

        $plan = $this->inspector($install, [$migration])->inspect($state->attemptId());

        self::assertSame(UpdateReconciliationPlan::AMBIGUOUS, $plan->classification());
        self::assertNull($plan->resolution());
    }

    public function testMissingCorruptAndUnknownJournalsRemainBlocked(): void
    {
        foreach (['missing', 'corrupt', 'unknown'] as $case) {
            [$install, $state] = $this->filesystemFixture('journal-' . $case, false, false);
            $journal = $install . '/var/update/recovery/' . $state->attemptId() . '/journal.json';
            if ($case === 'missing') {
                self::assertTrue(unlink($journal));
            } elseif ($case === 'corrupt') {
                file_put_contents($journal, '{broken');
            } else {
                $data = json_decode((string) file_get_contents($journal), true, 64, JSON_THROW_ON_ERROR);
                $data['format_version'] = 99;
                file_put_contents($journal, json_encode($data, JSON_THROW_ON_ERROR));
            }

            $plan = $this->inspector($install)->inspect($state->attemptId());

            self::assertContains(
                $plan->classification(),
                [UpdateReconciliationPlan::CONFLICT, UpdateReconciliationPlan::AMBIGUOUS],
                $case
            );
            self::assertNull($plan->resolution());
        }
    }

    public function testSymlinksAndInvalidAttemptIdentifiersAreRejectedSafely(): void
    {
        [$install, $state] = $this->filesystemFixture('symlink', false, false);
        self::assertTrue(unlink($install . '/managed.txt'));
        self::assertTrue(symlink($this->workspace . '/outside', $install . '/managed.txt'));
        self::assertSame(
            UpdateReconciliationPlan::CONFLICT,
            $this->inspector($install)->inspect($state->attemptId())->classification()
        );

        foreach (['../state', 'ABC', str_repeat('a', 31), str_repeat('a', 33)] as $attempt) {
            try {
                $this->inspector($install)->inspect($attempt);
                self::fail('El intento inválido debía rechazarse');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('intento', strtolower($exception->getMessage()));
            }
        }
    }

    public function testApplyRejectsOccupiedLockChangedFingerprintAndWrongResolution(): void
    {
        [$install, $state] = $this->stateFixture('apply-guards', false);
        $plan = $this->inspector($install)->inspect($state->attemptId());
        $service = $this->service($install);

        $holder = new ReleaseUpdateStorage($install);
        $holder->acquire();
        try {
            $service->apply($state->attemptId(), UpdateReconciliationPlan::RESOLUTION_RETRY, $plan->fingerprint());
            self::fail('El lock ocupado debía impedir apply');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('curso', $exception->getMessage());
        } finally {
            $holder->release();
        }

        $changed = $state->fail(RuntimeException::class, 'state changed after inspect');
        $storage = new ReleaseUpdateStorage($install);
        $storage->write($changed);
        try {
            $service->apply($state->attemptId(), UpdateReconciliationPlan::RESOLUTION_RETRY, $plan->fingerprint());
            self::fail('La huella obsoleta debía rechazarse');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('huella', strtolower($exception->getMessage()));
        }

        $fresh = $this->inspector($install)->inspect($state->attemptId());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('resolución');
        $service->apply($state->attemptId(), UpdateReconciliationPlan::RESOLUTION_COMPLETION, $fresh->fingerprint());
    }

    public function testConfirmedRollbackApplicationIsDurableAndIdempotent(): void
    {
        [$install, $state] = $this->filesystemFixture('apply-rollback', true, false);
        $plan = $this->inspector($install)->inspect($state->attemptId());
        $service = $this->service($install);

        $first = $service->apply($state->attemptId(), $plan->resolution(), $plan->fingerprint());
        $afterFirst = $this->snapshot($install);
        $second = $service->apply($state->attemptId(), $plan->resolution(), $plan->fingerprint());

        self::assertSame($first, $second);
        self::assertSame($afterFirst, $this->snapshot($install));
        $stored = (new ReleaseUpdateStorage($install))->load();
        self::assertSame(UpdateReconciliationPlan::RESOLUTION_ROLLBACK, $stored?->reconciliation()['resolution'] ?? null);
    }

    public function testConfirmedCompletionApplicationBecomesCompletedWithoutRepeatingWork(): void
    {
        [$install, $state, $migration] = $this->completionFixture('apply-completion');
        $plan = $this->inspector($install, [$migration])->inspect($state->attemptId());
        $service = $this->service($install, [$migration]);

        $service->apply($state->attemptId(), $plan->resolution(), $plan->fingerprint());
        $afterFirst = $this->snapshot($install);
        $service->apply($state->attemptId(), $plan->resolution(), $plan->fingerprint());

        self::assertSame($afterFirst, $this->snapshot($install));
        $stored = (new ReleaseUpdateStorage($install))->load();
        self::assertNotNull($stored);
        self::assertSame(ReleaseUpdateState::STATUS_COMPLETED, $stored->status());
        $reconciliation = $stored->reconciliation();
        self::assertNotNull($reconciliation);
        self::assertSame(UpdateReconciliationPlan::RESOLUTION_COMPLETION, $reconciliation['resolution']);
    }

    public function testPersistenceFailureLeavesPreviousStateBlockedAndKeepsImmutableEvidence(): void
    {
        [$install, $state] = $this->filesystemFixture('write-failure', true, false);
        $plan = $this->inspector($install)->inspect($state->attemptId());
        $service = $this->service(
            $install,
            [],
            static function (): void {
                throw new RuntimeException('simulated durable state failure');
            }
        );

        try {
            $service->apply($state->attemptId(), $plan->resolution(), $plan->fingerprint());
            self::fail('El fallo de persistencia debía propagarse');
        } catch (RuntimeException $exception) {
            self::assertSame('simulated durable state failure', $exception->getMessage());
        }

        self::assertNull((new ReleaseUpdateStorage($install))->load()?->reconciliation());
        self::assertFileExists(
            $install . '/var/update/recovery/' . $state->attemptId() . '/reconciliation.json'
        );
        $this->service($install)->apply($state->attemptId(), $plan->resolution(), $plan->fingerprint());
        $stored = (new ReleaseUpdateStorage($install))->load();
        self::assertNotNull($stored);
        $reconciliation = $stored->reconciliation();
        self::assertNotNull($reconciliation);
        self::assertSame(UpdateReconciliationPlan::RESOLUTION_ROLLBACK, $reconciliation['resolution']);
    }

    public function testFailedMigrationRequiresRecoveryAndInvalidDatabaseJournalBlocks(): void
    {
        [$install, $state, $migration] = $this->completionFixture('failed-migration', false);
        DatabaseRecoveryJournal::open(new SafePath($install), $state->attemptId())->failed($migration);
        self::assertSame(
            UpdateReconciliationPlan::RECOVERY_REQUIRED,
            $this->inspector($install)->inspect($state->attemptId())->classification()
        );

        foreach (['corrupt', 'unknown'] as $case) {
            [$badInstall, $badState] = $this->completionFixture('database-' . $case);
            $path = $badInstall . '/var/update/recovery/' . $badState->attemptId() . '/database.json';
            if ($case === 'corrupt') {
                file_put_contents($path, '{broken');
            } else {
                $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
                $data['format_version'] = 99;
                file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
            }
            $plan = $this->inspector($badInstall)->inspect($badState->attemptId());
            self::assertSame(UpdateReconciliationPlan::AMBIGUOUS, $plan->classification(), $case);
            self::assertNull($plan->resolution());
        }
    }

    public function testStateRecoveryAndManifestSymlinksAreRejectedWithoutFollowingThem(): void
    {
        [$stateInstall] = $this->stateFixture('state-link', false);
        $outsideState = $this->workspace . '/outside-state';
        file_put_contents($outsideState, 'outside');
        self::assertTrue(unlink($stateInstall . '/' . ReleaseUpdateStorage::STATE_PATH));
        self::assertTrue(symlink($outsideState, $stateInstall . '/' . ReleaseUpdateStorage::STATE_PATH));
        try {
            $this->inspector($stateInstall)->inspect();
            self::fail('El symlink de estado debía rechazarse');
        } catch (RuntimeException) {
            self::assertSame('outside', file_get_contents($outsideState));
        }

        [$recoveryInstall, $recoveryState] = $this->filesystemFixture('recovery-link', false, false);
        $recovery = $recoveryInstall . '/var/update/recovery/' . $recoveryState->attemptId();
        $this->removeTree($recovery);
        $outsideRecovery = $this->workspace . '/outside-recovery';
        self::assertTrue(mkdir($outsideRecovery));
        file_put_contents($outsideRecovery . '/journal.json', 'outside');
        self::assertTrue(symlink($outsideRecovery, $recovery));
        self::assertSame(
            UpdateReconciliationPlan::CONFLICT,
            $this->inspector($recoveryInstall)->inspect($recoveryState->attemptId())->classification()
        );
        self::assertSame('outside', file_get_contents($outsideRecovery . '/journal.json'));

        [$manifestInstall, $manifestState] = $this->filesystemFixture('manifest-link', false, false);
        $outsideManifest = $this->workspace . '/outside-manifest';
        file_put_contents($outsideManifest, 'outside');
        self::assertTrue(unlink($manifestInstall . '/' . ManagedFileManifest::FILENAME));
        self::assertTrue(symlink($outsideManifest, $manifestInstall . '/' . ManagedFileManifest::FILENAME));
        self::assertSame(
            UpdateReconciliationPlan::CONFLICT,
            $this->inspector($manifestInstall)->inspect($manifestState->attemptId())->classification()
        );
        self::assertSame('outside', file_get_contents($outsideManifest));
    }

    public function testSafeRetryResolutionAllowsExactlyOneNewCoordinatedAttempt(): void
    {
        [$install, $state] = $this->stateFixture('retry-coordinator', false);
        $plan = $this->inspector($install)->inspect($state->attemptId());
        $this->service($install)->apply($state->attemptId(), $plan->resolution(), $plan->fingerprint());

        $prepared = 0;
        try {
            (new \Modules\Chascarrillo\Service\ReleaseUpdateCoordinator())->prepareAndApply(
                $install,
                'v0.8.18',
                static function () use (&$prepared): string {
                    $prepared++;
                    throw new RuntimeException('stop after accepted retry');
                }
            );
            self::fail('El doble debía detener el nuevo intento');
        } catch (RuntimeException $exception) {
            self::assertSame('stop after accepted retry', $exception->getMessage());
        }

        self::assertSame(1, $prepared);
        $current = (new ReleaseUpdateStorage($install))->load()?->toArray();
        self::assertSame($state->attemptId(), $current['recovered_from'] ?? null);
    }

    public function testReconciledCompletionDoesNotRepeatTheSameTarget(): void
    {
        [$install, $state, $migration] = $this->completionFixture('completed-coordinator');
        $plan = $this->inspector($install, [$migration])->inspect($state->attemptId());
        $this->service($install, [$migration])
            ->apply($state->attemptId(), $plan->resolution(), $plan->fingerprint());

        $prepared = 0;
        try {
            (new \Modules\Chascarrillo\Service\ReleaseUpdateCoordinator())->prepareAndApply(
                $install,
                'v0.8.18',
                static function () use (&$prepared): string {
                    $prepared++;
                    return 'must-not-run';
                }
            );
            self::fail('El release reconciliado no debía repetirse');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('ya consta completada', $exception->getMessage());
        }
        self::assertSame(0, $prepared);
    }

    public function testPersistedAndRenderedEvidenceContainsNoAbsolutePathsSecretsSqlOrTrace(): void
    {
        [$install, $state] = $this->filesystemFixture('sanitized', true, false);
        $plan = $this->inspector($install)->inspect($state->attemptId());
        $this->service($install)->apply($state->attemptId(), $plan->resolution(), $plan->fingerprint());
        $record = (string) file_get_contents(
            $install . '/var/update/recovery/' . $state->attemptId() . '/reconciliation.json'
        );
        $rendered = json_encode($plan->toArray(), JSON_THROW_ON_ERROR);

        foreach ([$record, $rendered] as $output) {
            self::assertStringNotContainsString($install, $output);
            self::assertStringNotContainsString('password', strtolower($output));
            self::assertStringNotContainsString('select ', strtolower($output));
            self::assertStringNotContainsString('trace', strtolower($output));
        }
    }

    /** @return array{string,ReleaseUpdateState} */
    private function stateFixture(string $name, bool $mutations): array
    {
        $install = $this->workspace . '/' . $name;
        self::assertTrue(mkdir($install, 0755, true));
        $state = ReleaseUpdateState::start('0.8.17', '0.8.18');
        if ($mutations) {
            $state = $state->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true);
        }
        (new ReleaseUpdateStorage($install))->write($state);
        return [$install, $state];
    }

    /** @return array{string,ReleaseUpdateState} */
    private function filesystemFixture(string $name, bool $rollback, bool $newManifest): array
    {
        [$install, $state] = $this->stateFixture($name, false);
        file_put_contents($install . '/managed.txt', 'old');
        file_put_contents($install . '/managed-two.txt', 'old-two');
        $this->writeManifest($install, '0.8.17', [
            'managed.txt' => 'old',
            'managed-two.txt' => 'old-two',
        ]);
        $journal = FilesystemRecoveryJournal::prepare(new SafePath($install), $state->attemptId(), [[
            'type' => 'copy',
            'path' => 'managed.txt',
            'new_hash' => hash('sha256', 'new'),
            'new_size' => 3,
        ], [
            'type' => 'copy',
            'path' => 'managed-two.txt',
            'new_hash' => hash('sha256', 'new-two'),
            'new_size' => 7,
        ]]);
        $state = $state
            ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true)
            ->advance(ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP, true);
        $journal->apply(0, static function () use ($install): void {
            file_put_contents($install . '/managed.txt', 'new');
        });
        $journal->apply(1, static function () use ($install): void {
            file_put_contents($install . '/managed-two.txt', 'new-two');
        });
        if ($rollback) {
            $journal->rollback();
            $state = $state->filesystemRolledBack();
        } else {
            $state = $state->fail(RuntimeException::class, 'simulated interruption');
        }
        if ($newManifest) {
            $this->writeManifest($install, '0.8.18', [
                'managed.txt' => 'new',
                'managed-two.txt' => 'new-two',
            ]);
        }
        (new ReleaseUpdateStorage($install))->write($state);
        return [$install, $state];
    }

    /** @return array{string,ReleaseUpdateState,string} */
    private function completionFixture(string $name, bool $migrationApplied = true): array
    {
        [$install, $state] = $this->filesystemFixture($name, false, true);
        foreach (
            [
                'composer.lock',
                'vendor/composer/installed.json',
                'Modules/Chascarrillo/Service/UpdateService.php',
                ...\Modules\Chascarrillo\Service\ReleaseValidator::ESSENTIAL_TEMPLATES,
            ] as $relative
        ) {
            $directory = dirname($install . '/' . $relative);
            if (!is_dir($directory)) {
                self::assertTrue(mkdir($directory, 0755, true));
            }
            file_put_contents($install . '/' . $relative, 'safe fixture');
        }
        self::assertTrue(is_dir($install . '/public_html') || mkdir($install . '/public_html', 0755, true));
        $migration = '2026_09_10_000000_reconciliation@Test';
        $db = DatabaseRecoveryJournal::prepare(
            new SafePath($install),
            $state->attemptId(),
            DatabaseRecoveryCapability::externalVerified(
                'test-provider',
                'backup-reference',
                '2026-09-10T08:00:00Z',
                '2026-09-10T08:01:00Z',
                true
            ),
            [$migration]
        );
        $db->start($migration);
        if ($migrationApplied) {
            $db->applied($migration);
            $db->complete();
        }
        $state = ReleaseUpdateState::start('0.8.17', '0.8.18')
            ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true)
            ->advance(ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP, true)
            ->advance(ReleaseUpdateState::PHASE_MIGRATIONS, true)
            ->advance(ReleaseUpdateState::PHASE_PROMOTING_MANIFEST, true)
            ->fail(RuntimeException::class, 'simulated lost completion checkpoint');
        $data = $state->toArray();
        $data['attempt_id'] = json_decode(
            (string) file_get_contents($install . '/' . ReleaseUpdateStorage::STATE_PATH),
            true,
            32,
            JSON_THROW_ON_ERROR
        )['attempt_id'];
        $state = ReleaseUpdateState::fromArray($data);
        (new ReleaseUpdateStorage($install))->write($state);
        return [$install, $state, $migration];
    }

    /** @param list<string> $applied */
    private function inspector(string $install, array $applied = []): UpdateReconciliationInspector
    {
        return new UpdateReconciliationInspector(
            $install,
            static fn (): array => ['table_exists' => true, 'migrations' => $applied],
            static fn (): bool => true
        );
    }

    /**
     * @param list<string> $applied
     * @param (callable(ReleaseUpdateStorage,ReleaseUpdateState):void)|null $stateWriter
     */
    private function service(string $install, array $applied = [], ?callable $stateWriter = null): UpdateReconciliationService
    {
        return new UpdateReconciliationService(
            $install,
            static fn (): array => ['table_exists' => true, 'migrations' => $applied],
            static fn (): bool => true,
            $stateWriter
        );
    }

    /** @param array<string,string> $files path => contents */
    private function writeManifest(string $install, string $version, array $files): void
    {
        $hashes = [];
        foreach ($files as $path => $contents) {
            $hashes[$path] = hash('sha256', $contents);
        }
        ManagedFileManifest::write($install, [
            'format' => 2,
            'application_version' => $version,
            'files' => $hashes,
        ]);
    }

    /** @return array<string,string> */
    private function snapshot(string $root): array
    {
        $snapshot = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $node) {
            $relative = substr($node->getPathname(), strlen($root) + 1);
            if ($node->isLink()) {
                $snapshot[$relative] = 'link:' . (string) readlink($node->getPathname());
            } elseif ($node->isDir()) {
                $snapshot[$relative] = 'dir';
            } else {
                $snapshot[$relative] = 'file:' . hash_file('sha256', $node->getPathname());
            }
        }
        ksort($snapshot, SORT_STRING);
        return $snapshot;
    }

    private function removeTree(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeTree($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}

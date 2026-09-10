<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modules\Chascarrillo\Service\ManagedFileManifest;
use Modules\Chascarrillo\Service\ReleaseInstallationConflictException;
use Modules\Chascarrillo\Service\ReleaseInstaller;
use Modules\Chascarrillo\Service\ReleaseUpdateCoordinator;
use Modules\Chascarrillo\Service\ReleaseUpdateState;
use Modules\Chascarrillo\Service\ReleaseUpdateStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReleaseUpdateStateTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = sys_get_temp_dir() . '/chascarrillo-update-state-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace, 0755, true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->workspace);
        parent::tearDown();
    }

    public function testPreparationRunsAfterStateCreationWhileLockIsHeldAndFailureIsRecorded(): void
    {
        $install = $this->workspace . '/preparation-install';
        self::assertTrue(mkdir($install, 0755, true));
        $coordinator = new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true);

        try {
            $coordinator->prepareAndApply(
                $install,
                'v0.8.17',
                static function () use ($install): string {
                    self::assertFileExists($install . '/' . ReleaseUpdateStorage::STATE_PATH);
                    $contender = new ReleaseUpdateStorage($install);
                    try {
                        $contender->acquire();
                        self::fail('La preparación debía ejecutarse bajo el lock exclusivo');
                    } catch (RuntimeException $exception) {
                        self::assertStringContainsString('en curso', $exception->getMessage());
                    }
                    throw new RuntimeException('fallo de descarga simulado');
                }
            );
            self::fail('El fallo de preparación debía propagarse');
        } catch (RuntimeException $exception) {
            self::assertSame('fallo de descarga simulado', $exception->getMessage());
        }

        $state = $this->readState($install);
        self::assertSame(ReleaseUpdateState::STATUS_FAILED, $state['status']);
        self::assertSame(ReleaseUpdateState::PHASE_PREPARING, $state['phase']);
        self::assertFalse($state['mutations_started']);
        $released = new ReleaseUpdateStorage($install);
        $released->acquire();
        $released->release();
    }

    public function testStateExistsBeforeFirstMutationAndSuccessfulPhasesFollowRealOrder(): void
    {
        [$release, $install] = $this->releaseFixture('successful');
        $observed = [];
        (new ReleaseInstaller())->install(
            $release,
            $install,
            null,
            static function (string $phase, bool $mutationsStarted) use (&$observed): void {
                $observed[] = [$phase, $mutationsStarted];
            }
        );
        self::assertSame([
            [ReleaseUpdateState::PHASE_INSTALLING_FILES, true],
            [ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP, true],
            [ReleaseUpdateState::PHASE_PROMOTING_MANIFEST, true],
        ], $observed);

        [$release, $install] = $this->releaseFixture('coordinated');
        $expectedManifest = (string) file_get_contents($release . '/' . ManagedFileManifest::FILENAME);
        $migrationPhases = [];
        (new ReleaseUpdateCoordinator(
            new ReleaseInstaller(),
            function () use ($install, &$migrationPhases): bool {
                $migrationPhases[] = $this->readState($install)['phase'];
                return true;
            }
        ))->apply($release, $install);
        $state = $this->readState($install);
        self::assertSame([ReleaseUpdateState::PHASE_MIGRATIONS], $migrationPhases);
        self::assertSame(ReleaseUpdateState::STATUS_COMPLETED, $state['status']);
        self::assertSame(ReleaseUpdateState::PHASE_COMPLETED, $state['phase']);
        self::assertTrue($state['mutations_started']);
        self::assertFileExists($install . '/' . ReleaseUpdateStorage::STATE_PATH);
        self::assertSame(
            $expectedManifest,
            file_get_contents($install . '/' . ManagedFileManifest::FILENAME)
        );
    }

    public function testFailureBeforeMutationIsRecordedAndCanBeRetried(): void
    {
        [$release, $install] = $this->releaseFixture('preflight-failure');
        $this->writeFile($install . '/templates/partial/project_menu.blade.php', 'local change');
        ManagedFileManifest::write($install, [
            'format' => 2,
            'application_version' => '0.8.17',
            'files' => [
                'templates/partial/project_menu.blade.php' => hash('sha256', 'previous'),
            ],
        ]);
        $previousManifest = (string) file_get_contents($install . '/' . ManagedFileManifest::FILENAME);
        $migrations = 0;
        $coordinator = new ReleaseUpdateCoordinator(
            new ReleaseInstaller(),
            static function () use (&$migrations): bool {
                $migrations++;
                return true;
            }
        );

        try {
            $coordinator->apply($release, $install);
            self::fail('El conflicto debía cancelar la actualización');
        } catch (ReleaseInstallationConflictException) {
            $state = $this->readState($install);
            self::assertSame(ReleaseUpdateState::STATUS_FAILED, $state['status']);
            self::assertSame(ReleaseUpdateState::PHASE_PREPARING, $state['phase']);
            self::assertFalse($state['mutations_started']);
            self::assertSame(0, $migrations);
            self::assertSame('local change', file_get_contents($install . '/templates/partial/project_menu.blade.php'));
            self::assertSame(
                $previousManifest,
                file_get_contents($install . '/' . ManagedFileManifest::FILENAME)
            );
        }

        self::assertTrue(unlink($install . '/templates/partial/project_menu.blade.php'));
        self::assertTrue(unlink($install . '/' . ManagedFileManifest::FILENAME));
        $coordinator->apply($release, $install);
        self::assertSame(ReleaseUpdateState::STATUS_COMPLETED, $this->readState($install)['status']);
    }

    public function testMigrationFailureIsRecordedAfterMutationAndBlocksAnotherAttempt(): void
    {
        [$release, $install] = $this->releaseFixture('migration-failure');
        $coordinator = new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => false);

        try {
            $coordinator->apply($release, $install);
            self::fail('La migración fallida debía propagarse');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('migraciones', $exception->getMessage());
        }
        $failed = $this->readState($install);
        self::assertSame(ReleaseUpdateState::STATUS_FAILED, $failed['status']);
        self::assertSame(ReleaseUpdateState::PHASE_MIGRATIONS, $failed['phase']);
        self::assertTrue($failed['mutations_started']);
        self::assertFileDoesNotExist($install . '/' . ManagedFileManifest::FILENAME);

        $impossible = ReleaseUpdateState::start('0.8.17', '0.8.17')->toArray();
        $impossible['status'] = ReleaseUpdateState::STATUS_FAILED;
        $impossible['phase'] = ReleaseUpdateState::PHASE_MIGRATIONS;
        $impossible['error'] = ['class' => RuntimeException::class, 'message' => 'fallo'];
        try {
            ReleaseUpdateState::fromArray($impossible);
            self::fail('Una fase posterior sin mutations_started debía rechazarse');
        } catch (RuntimeException) {
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('intervención administrativa');
        (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
            ->apply($release, $install);
    }

    public function testMigrationFailurePreservesPreviousManifestByteForByte(): void
    {
        [$release, $install] = $this->releaseFixture('migration-manifest');
        $relative = 'templates/partial/project_menu.blade.php';
        $this->writeFile($install . '/' . $relative, 'previous release');
        ManagedFileManifest::write($install, [
            'format' => 2,
            'application_version' => '0.8.17',
            'files' => [$relative => hash('sha256', 'previous release')],
        ]);
        $manifestPath = $install . '/' . ManagedFileManifest::FILENAME;
        $previousManifest = (string) file_get_contents($manifestPath);

        try {
            (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => false))
                ->apply($release, $install);
            self::fail('La migración fallida debía propagarse');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('migraciones', $exception->getMessage());
        }

        self::assertFileExists($install . '/composer.lock');
        self::assertSame('new release', file_get_contents($install . '/' . $relative));
        self::assertSame($previousManifest, file_get_contents($manifestPath));
        $state = $this->readState($install);
        self::assertSame(ReleaseUpdateState::STATUS_FAILED, $state['status']);
        self::assertSame(ReleaseUpdateState::PHASE_MIGRATIONS, $state['phase']);
        self::assertTrue($state['mutations_started']);
    }

    public function testMigrationFailureWithoutPreviousManifestLeavesItAbsentAndSkipsPromotion(): void
    {
        [$release, $install] = $this->releaseFixture('migration-no-manifest');

        try {
            (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => false))
                ->apply($release, $install);
            self::fail('La migración fallida debía propagarse');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('migraciones', $exception->getMessage());
        }

        self::assertFileDoesNotExist($install . '/' . ManagedFileManifest::FILENAME);
        $state = $this->readState($install);
        self::assertSame(ReleaseUpdateState::STATUS_FAILED, $state['status']);
        self::assertSame(ReleaseUpdateState::PHASE_MIGRATIONS, $state['phase']);
        self::assertTrue($state['mutations_started']);
    }

    public function testPromotionFailureAfterSuccessfulMigrationPreservesManifestAndRecordsExactPhase(): void
    {
        [$release, $install] = $this->releaseFixture('promotion-failure');
        $relative = 'templates/partial/project_menu.blade.php';
        $this->writeFile($install . '/' . $relative, 'previous release');
        ManagedFileManifest::write($install, [
            'format' => 2,
            'application_version' => '0.8.17',
            'files' => [$relative => hash('sha256', 'previous release')],
        ]);
        $manifestPath = $install . '/' . ManagedFileManifest::FILENAME;
        $previousManifest = (string) file_get_contents($manifestPath);
        $migrations = 0;
        $coordinator = new ReleaseUpdateCoordinator(
            new ReleaseInstaller(),
            static function () use ($install, &$migrations): bool {
                $migrations++;
                self::assertTrue(chmod($install, 0555));
                return true;
            }
        );

        set_error_handler(static fn (int $severity): bool => $severity === E_NOTICE);
        try {
            $coordinator->apply($release, $install);
            self::fail('El fallo de promoción debía propagarse sin falso éxito');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('temporal', $exception->getMessage());
        } finally {
            restore_error_handler();
            self::assertTrue(chmod($install, 0755));
        }

        self::assertSame(1, $migrations);
        self::assertSame($previousManifest, file_get_contents($manifestPath));
        $state = $this->readState($install);
        self::assertSame(ReleaseUpdateState::STATUS_FAILED, $state['status']);
        self::assertSame(ReleaseUpdateState::PHASE_PROMOTING_MANIFEST, $state['phase']);
        self::assertTrue($state['mutations_started']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('intervención administrativa');
        (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
            ->apply($release, $install);
    }

    public function testFinalValidationAfterMigrationPreventsPromotionOfAMismatchedTree(): void
    {
        [$release, $install] = $this->releaseFixture('post-migration-validation');
        $relative = 'templates/partial/project_menu.blade.php';
        $this->writeFile($install . '/' . $relative, 'previous release');
        ManagedFileManifest::write($install, [
            'format' => 2,
            'application_version' => '0.8.17',
            'files' => [$relative => hash('sha256', 'previous release')],
        ]);
        $manifestPath = $install . '/' . ManagedFileManifest::FILENAME;
        $previousManifest = (string) file_get_contents($manifestPath);

        try {
            (new ReleaseUpdateCoordinator(
                new ReleaseInstaller(),
                function () use ($install, $relative): bool {
                    $this->writeFile($install . '/' . $relative, 'changed during migrations');
                    return true;
                }
            ))->apply($release, $install);
            self::fail('La validación final debía impedir la promoción');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('verificación final', $exception->getMessage());
        }

        self::assertSame($previousManifest, file_get_contents($manifestPath));
        $state = $this->readState($install);
        self::assertSame(ReleaseUpdateState::STATUS_FAILED, $state['status']);
        self::assertSame(ReleaseUpdateState::PHASE_MIGRATIONS, $state['phase']);
        self::assertTrue($state['mutations_started']);
    }

    public function testReleasedNonTerminalStateIsClassifiedAsInterruptedAndSafePreflightCanRestart(): void
    {
        [$release, $install] = $this->releaseFixture('safe-interruption');
        $storage = new ReleaseUpdateStorage($install);
        $previous = ReleaseUpdateState::start('0.8.17', '0.8.17');
        $storage->acquire();
        $storage->write($previous);
        $storage->release();

        (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
            ->apply($release, $install);
        $current = $this->readState($install);
        self::assertSame(ReleaseUpdateState::STATUS_COMPLETED, $current['status']);
        self::assertSame($previous->attemptId(), $current['recovered_from']);
        self::assertSame(ReleaseUpdateState::STATUS_INTERRUPTED, $current['recovered_status']);
    }

    public function testReleasedNonTerminalStateAfterMutationIsInterruptedAndBlocks(): void
    {
        [$release, $install] = $this->releaseFixture('unsafe-interruption');
        $storage = new ReleaseUpdateStorage($install);
        $previous = ReleaseUpdateState::start('0.8.17', '0.8.17')
            ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true);
        $storage->acquire();
        $storage->write($previous);
        $storage->release();

        try {
            (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                ->apply($release, $install);
            self::fail('La interrupción con posibles mutaciones debía bloquear');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('administrativa', $exception->getMessage());
        }
        $interrupted = $this->readState($install);
        self::assertSame(ReleaseUpdateState::STATUS_ROLLBACK_FAILED, $interrupted['status']);
        self::assertSame(ReleaseUpdateState::PHASE_INSTALLING_FILES, $interrupted['phase']);
        self::assertTrue($interrupted['mutations_started']);
        self::assertFileDoesNotExist($install . '/composer.lock');
    }

    public function testInterruptionsDuringMigrationsAndPromotionPreserveManifestAndBlock(): void
    {
        foreach ([ReleaseUpdateState::PHASE_MIGRATIONS, ReleaseUpdateState::PHASE_PROMOTING_MANIFEST] as $phase) {
            [$release, $install] = $this->releaseFixture('interrupted-' . $phase);
            ManagedFileManifest::write($install, [
                'format' => 2,
                'application_version' => '0.8.17',
                'files' => [],
            ]);
            $manifestPath = $install . '/' . ManagedFileManifest::FILENAME;
            $previousManifest = (string) file_get_contents($manifestPath);
            $previous = ReleaseUpdateState::start('0.8.17', '0.8.17')
                ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true)
                ->advance(ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP, true)
                ->advance(ReleaseUpdateState::PHASE_MIGRATIONS, true);
            if ($phase === ReleaseUpdateState::PHASE_PROMOTING_MANIFEST) {
                $previous = $previous->advance(ReleaseUpdateState::PHASE_PROMOTING_MANIFEST, true);
            }
            $storage = new ReleaseUpdateStorage($install);
            $storage->acquire();
            $storage->write($previous);
            $storage->release();

            try {
                (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                    ->apply($release, $install);
                self::fail("La interrupción en {$phase} debía bloquear");
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('intervención administrativa', $exception->getMessage());
            }

            $interrupted = $this->readState($install);
            self::assertSame(ReleaseUpdateState::STATUS_INTERRUPTED, $interrupted['status']);
            self::assertSame($phase, $interrupted['phase']);
            self::assertTrue($interrupted['mutations_started']);
            self::assertSame($previousManifest, file_get_contents($manifestPath));
        }
    }

    public function testConcurrentProcessIsRejectedAndLocksReleaseAfterSuccessAndError(): void
    {
        [$release, $install] = $this->releaseFixture('concurrency');
        $holder = new ReleaseUpdateStorage($install);
        $holder->acquire();
        try {
            (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                ->apply($release, $install);
            self::fail('El segundo proceso debía ser rechazado');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('en curso', $exception->getMessage());
            self::assertFileDoesNotExist($install . '/composer.lock');
        } finally {
            $holder->release();
        }

        (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
            ->apply($release, $install);
        $afterSuccess = new ReleaseUpdateStorage($install);
        $afterSuccess->acquire();
        $afterSuccess->release();

        [$badRelease, $badInstall] = $this->releaseFixture('error-unlock');
        self::assertTrue(unlink($badRelease . '/composer.lock'));
        try {
            (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                ->apply($badRelease, $badInstall);
        } catch (RuntimeException) {
        }
        $afterError = new ReleaseUpdateStorage($badInstall);
        $afterError->acquire();
        $afterError->release();
    }

    public function testCorruptUnknownOrInvalidStateAndImpossibleTransitionFailClosed(): void
    {
        [$release, $install] = $this->releaseFixture('invalid-state');
        $invalidDocuments = [
            '{broken',
            json_encode(['format_version' => 99], JSON_THROW_ON_ERROR),
            json_encode(['format_version' => 1, 'attempt_id' => 'missing-fields'], JSON_THROW_ON_ERROR),
        ];
        foreach ($invalidDocuments as $document) {
            $this->writeFile($install . '/' . ReleaseUpdateStorage::STATE_PATH, $document);
            try {
                (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                    ->apply($release, $install);
                self::fail('El estado inválido debía impedir la actualización');
            } catch (RuntimeException) {
                self::assertFileDoesNotExist($install . '/composer.lock');
            }
        }

        $this->expectException(RuntimeException::class);
        ReleaseUpdateState::start('0.8.17', '0.8.17')
            ->advance(ReleaseUpdateState::PHASE_PROMOTING_MANIFEST, true);
    }

    public function testStateDirectoryStateFileAndLockSymlinksAreRejectedWithoutExternalWrites(): void
    {
        foreach (['directory', 'state', 'lock'] as $case) {
            $install = $this->workspace . '/symlink-' . $case;
            $outside = $this->workspace . '/outside-' . $case;
            self::assertTrue(mkdir($install, 0755, true));
            self::assertTrue(mkdir($outside, 0755, true));
            $witness = $outside . '/witness.txt';
            $this->writeFile($witness, 'untouched');
            if ($case === 'directory') {
                self::assertTrue(mkdir($install . '/var', 0755, true));
                self::assertTrue(symlink($outside, $install . '/var/update'));
            } else {
                self::assertTrue(mkdir($install . '/var/update', 0755, true));
                $target = $case === 'state'
                    ? ReleaseUpdateStorage::STATE_PATH
                    : ReleaseUpdateStorage::LOCK_PATH;
                self::assertTrue(symlink($witness, $install . '/' . $target));
            }
            try {
                (new ReleaseUpdateStorage($install))->acquire();
                if ($case === 'state') {
                    (new ReleaseUpdateStorage($install))->load();
                }
                self::fail("El symlink de {$case} debía rechazarse");
            } catch (RuntimeException) {
                self::assertSame('untouched', file_get_contents($witness));
            }
        }
    }

    public function testManifestPromotionRequiresPreparationAndUnsafeManifestNodesAreRejected(): void
    {
        $installer = new ReleaseInstaller();
        try {
            $installer->promotePreparedManifest();
            self::fail('No debía poder promoverse un manifiesto sin una preparación válida');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('instalación validada', $exception->getMessage());
        }

        foreach (['symlink', 'directory'] as $case) {
            [$release, $install] = $this->releaseFixture('unsafe-manifest-' . $case);
            $outside = $this->workspace . '/outside-manifest-' . $case;
            $this->writeFile($outside, 'outside witness');
            $manifestPath = $install . '/' . ManagedFileManifest::FILENAME;
            if ($case === 'symlink') {
                self::assertTrue(symlink($outside, $manifestPath));
            } else {
                self::assertTrue(mkdir($manifestPath, 0755, true));
            }
            $migrations = 0;

            try {
                (new ReleaseUpdateCoordinator(
                    new ReleaseInstaller(),
                    static function () use (&$migrations): bool {
                        $migrations++;
                        return true;
                    }
                ))->apply($release, $install);
                self::fail("El nodo {$case} del manifiesto debía rechazarse");
            } catch (ReleaseInstallationConflictException) {
            }

            self::assertSame(0, $migrations);
            self::assertSame('outside witness', file_get_contents($outside));
            self::assertFileDoesNotExist($install . '/composer.lock');
            $state = $this->readState($install);
            self::assertSame(ReleaseUpdateState::STATUS_FAILED, $state['status']);
            self::assertSame(ReleaseUpdateState::PHASE_PREPARING, $state['phase']);
            self::assertFalse($state['mutations_started']);
        }
    }

    public function testAssetFailureAfterCopiesIsRecordedAndDoesNotRunMigrations(): void
    {
        [$release, $install] = $this->releaseFixture('asset-failure');
        $this->writeFile(
            $install . '/public_html/themes/.chascarrillo-theme-assets.json',
            '{invalid'
        );
        $migrations = 0;
        $coordinator = new ReleaseUpdateCoordinator(
            new ReleaseInstaller(),
            static function () use (&$migrations): bool {
                $migrations++;
                return true;
            }
        );

        try {
            $coordinator->apply($release, $install);
            self::fail('El fallo de assets debía propagarse sin éxito');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Manifiesto', $exception->getMessage());
        }
        $state = $this->readState($install);
        self::assertSame(ReleaseUpdateState::STATUS_FAILED, $state['status']);
        self::assertSame(ReleaseUpdateState::PHASE_PREPARING, $state['phase']);
        self::assertFalse($state['mutations_started']);
        self::assertSame(0, $migrations);
        self::assertFileDoesNotExist($install . '/composer.lock');
        self::assertStringNotContainsString(
            $this->workspace,
            (string) ($state["error"]["message"] ?? "")
        );
    }

    public function testTerminalStatesAndUnexpectedStateOrLockNodesAreRejected(): void
    {
        $completed = ReleaseUpdateState::start('0.8.17', '0.8.17')
            ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true)
            ->advance(ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP, true)
            ->advance(ReleaseUpdateState::PHASE_MIGRATIONS, true)
            ->advance(ReleaseUpdateState::PHASE_PROMOTING_MANIFEST, true)
            ->complete();
        try {
            $completed->interrupt();
            self::fail('completed debía ser terminal');
        } catch (RuntimeException) {
        }

        $promoting = ReleaseUpdateState::start('0.8.17', '0.8.17')
            ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true)
            ->advance(ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP, true)
            ->advance(ReleaseUpdateState::PHASE_MIGRATIONS, true)
            ->advance(ReleaseUpdateState::PHASE_PROMOTING_MANIFEST, true);
        try {
            $promoting->advance(ReleaseUpdateState::PHASE_MIGRATIONS, true);
            self::fail('La transición antigua promoting_manifest -> migrations debía rechazarse');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString(
                'promoting_manifest -> migrations',
                $exception->getMessage()
            );
        }
        $failed = ReleaseUpdateState::start('0.8.17', '0.8.17')->fail(RuntimeException::class, 'fallo');
        try {
            $failed->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true);
            self::fail('failed debía ser terminal');
        } catch (RuntimeException) {
        }

        foreach (['state', 'lock'] as $node) {
            $install = $this->workspace . '/unexpected-' . $node;
            self::assertTrue(mkdir($install . '/var/update', 0755, true));
            $relative = $node === 'state' ? ReleaseUpdateStorage::STATE_PATH : ReleaseUpdateStorage::LOCK_PATH;
            self::assertTrue(mkdir($install . '/' . $relative, 0755, true));
            $storage = new ReleaseUpdateStorage($install);
            try {
                $storage->acquire();
                if ($node === 'state') {
                    $storage->load();
                }
                self::fail("El directorio en {$node} debía rechazarse");
            } catch (RuntimeException) {
            } finally {
                $storage->release();
            }
        }
    }

    public function testSecondUpdateAfterCompletedCanStartAndRuntimeFilesAreExcludedFromArtifacts(): void
    {
        [$release, $install] = $this->releaseFixture('repeat');
        $migrations = 0;
        $coordinator = new ReleaseUpdateCoordinator(
            new ReleaseInstaller(),
            static function () use (&$migrations): bool {
                $migrations++;
                return true;
            }
        );
        $coordinator->apply($release, $install);
        $firstAttempt = $this->readState($install)['attempt_id'];
        $coordinator->apply($release, $install);
        $second = $this->readState($install);

        self::assertSame(2, $migrations);
        self::assertNotSame($firstAttempt, $second['attempt_id']);
        self::assertSame(ReleaseUpdateState::STATUS_COMPLETED, $second['status']);
        self::assertArrayNotHasKey(ReleaseUpdateStorage::STATE_PATH, ManagedFileManifest::generate($install)['files']);
        self::assertArrayNotHasKey(ReleaseUpdateStorage::LOCK_PATH, ManagedFileManifest::generate($install)['files']);
        $buildScript = (string) file_get_contents(dirname(__DIR__, 2) . '/bin/build_release.sh');
        self::assertStringContainsString('"var"', $buildScript);
    }

    /** @return array{string,string} */
    private function releaseFixture(string $name): array
    {
        $project = dirname(__DIR__, 2);
        $release = $this->workspace . '/' . $name . '-release';
        $install = $this->workspace . '/' . $name . '-install';
        self::assertTrue(mkdir($install, 0755, true));
        $this->writeFile($release . '/composer.lock', (string) file_get_contents($project . '/composer.lock'));
        $this->writeFile(
            $release . '/Modules/Chascarrillo/Service/UpdateService.php',
            (string) file_get_contents($project . '/Modules/Chascarrillo/Service/UpdateService.php')
        );
        $this->writeFile($release . '/Modules/Chascarrillo/Application/AppContainer.php', 'new file');
        $this->writeFile(
            $release . '/vendor/composer/installed.json',
            json_encode([
                'packages' => [[
                    'name' => 'alxarafe/alxarafe',
                    'version' => 'v0.6.11',
                    'source' => ['reference' => '4b5a6252750537280c04aa4378b3fcf2570f8efb'],
                ]],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );
        foreach ($this->essentialTemplates() as $relative => $contents) {
            $this->writeFile($release . '/' . $relative, $contents);
        }
        $this->writeFile($release . '/templates/partial/project_menu.blade.php', 'new release');
        $this->writeFile($release . '/public_html/index.php', '<?php // fixture');
        $this->writeFile(
            $release . '/vendor/alxarafe/alxarafe/templates/themes/default/css/default.css',
            'framework asset'
        );
        ManagedFileManifest::write($release, ManagedFileManifest::generate($release));
        return [$release, $install];
    }

    /** @return array<string,string> */
    private function essentialTemplates(): array
    {
        return [
            'vendor/alxarafe/alxarafe/templates/partial/layout/main.blade.php' => 'layout',
            'vendor/alxarafe/alxarafe/templates/partial/body_standard.blade.php' => 'standard',
            'vendor/alxarafe/alxarafe/templates/partial/body_empty.blade.php' => 'empty',
            'vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php'
                => 'clock-display controller=Auth partial.lang_switcher partial.theme_switcher Auth::$user',
            'vendor/alxarafe/alxarafe/templates/partial/lang_switcher.blade.php' => 'lang',
            'vendor/alxarafe/alxarafe/templates/partial/theme_switcher.blade.php' => 'theme',
            'vendor/alxarafe/alxarafe/templates/partial/project_menu.blade.php' => 'project',
        ];
    }

    /** @return array<string,mixed> */
    private function readState(string $install): array
    {
        $document = json_decode(
            (string) file_get_contents($install . '/' . ReleaseUpdateStorage::STATE_PATH),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($document);
        return $document;
    }

    private function writeFile(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) {
            self::assertTrue(mkdir(dirname($path), 0755, true));
        }
        self::assertNotFalse(file_put_contents($path, $contents));
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->removeTree($path . '/' . $entry);
        }
        rmdir($path);
    }
}

<?php

declare(strict_types=1);

// Test-only namespace shims require global functions and a probe beside the test class.
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses,Squiz.Functions.GlobalFunction.Found

namespace Modules\Chascarrillo\Service;

use Closure;

/** Test-local interception of native effects; disabled unless a matrix scenario arms it. */
final class ReleaseFailureProbe
{
    /** @var Closure(string,array<mixed>,Closure):mixed|null */
    private static ?Closure $interceptor = null;

    /** @var list<array{operation:string,arguments:array<mixed>}> */
    private static array $events = [];

    /** @param (callable(string,array<mixed>,Closure):mixed)|null $interceptor */
    public static function arm(?callable $interceptor): void
    {
        self::$interceptor = $interceptor === null ? null : Closure::fromCallable($interceptor);
        if ($interceptor !== null) {
            self::$events = [];
        }
    }

    /** @param array<mixed> $arguments */
    public static function invoke(string $operation, array $arguments, Closure $native): mixed
    {
        self::$events[] = ['operation' => $operation, 'arguments' => $arguments];
        if (self::$interceptor !== null) {
            return (self::$interceptor)($operation, $arguments, $native);
        }
        return $native();
    }

    /** @return list<array{operation:string,arguments:array<mixed>}> */
    public static function events(): array
    {
        return self::$events;
    }
}

function copy(string $from, string $to): bool
{
    return ReleaseFailureProbe::invoke('copy', [$from, $to], static fn (): bool => \copy($from, $to));
}

function rename(string $from, string $to): bool
{
    return ReleaseFailureProbe::invoke('rename', [$from, $to], static fn (): bool => \rename($from, $to));
}

function unlink(string $filename): bool
{
    return ReleaseFailureProbe::invoke('unlink', [$filename], static fn (): bool => \unlink($filename));
}

function opcache_invalidate(string $filename, bool $force = false): bool
{
    return ReleaseFailureProbe::invoke(
        'opcache_invalidate',
        [$filename, $force],
        static fn (): bool => \opcache_invalidate($filename, $force)
    );
}

function opcache_reset(): bool
{
    return ReleaseFailureProbe::invoke('opcache_reset', [], static fn (): bool => \opcache_reset());
}

namespace Tests\Feature;

use Modules\Chascarrillo\Service\ManagedFileManifest;
use Modules\Chascarrillo\Service\FilesystemRecoveryJournal;
use Modules\Chascarrillo\Service\SafePath;
use Modules\Chascarrillo\Service\ReleaseFailureProbe;
use Modules\Chascarrillo\Service\ReleaseInstallationConflictException;
use Modules\Chascarrillo\Service\ReleaseInstaller;
use Modules\Chascarrillo\Service\ReleaseUpdateCoordinator;
use Modules\Chascarrillo\Service\ReleaseUpdateState;
use Modules\Chascarrillo\Service\ReleaseUpdateStorage;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use ZipArchive;

final class ReleaseFailureRecoveryMatrixTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        ReleaseFailureProbe::arm(null);
        $this->workspace = sys_get_temp_dir() . '/chascarrillo-failure-matrix-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace, 0755, true));
    }

    protected function tearDown(): void
    {
        ReleaseFailureProbe::arm(null);
        $this->removeTree($this->workspace);
        parent::tearDown();
    }

    public function testPreparationFailuresAreSafeToRetryWithoutPersistentApplicationEffects(): void
    {
        foreach (['download', 'zip', 'release', 'plan'] as $scenario) {
            [$release, $install, $outside] = $this->releaseFixture('preparing-' . $scenario);
            $manifestBefore = $this->contents($install . '/' . ManagedFileManifest::FILENAME);
            $migrations = 0;
            $coordinator = new ReleaseUpdateCoordinator(
                new ReleaseInstaller(),
                static function () use (&$migrations): bool {
                    $migrations++;
                    return true;
                }
            );

            if ($scenario === 'release') {
                $this->writeFile($release . '/z-second.txt', 'tampered release');
            } elseif ($scenario === 'plan') {
                $this->writeFile($install . '/a-first.txt', 'local modification');
            }

            try {
                if ($scenario === 'download') {
                    $coordinator->prepareAndApply(
                        $install,
                        'v0.8.17',
                        static fn (): string => throw new RuntimeException('download failed')
                    );
                } elseif ($scenario === 'zip') {
                    $invalidZip = $this->workspace . '/invalid.zip';
                    $this->writeFile($invalidZip, 'not a zip');
                    $coordinator->prepareAndApply(
                        $install,
                        'v0.8.17',
                        static function () use ($invalidZip): string {
                            $zip = new ZipArchive();
                            if ($zip->open($invalidZip) !== true) {
                                throw new RuntimeException('zip validation failed');
                            }
                            throw new RuntimeException('invalid test setup');
                        }
                    );
                } else {
                    $coordinator->apply($release, $install, 'v0.8.17');
                }
                self::fail("{$scenario} debía fallar");
            } catch (Throwable $exception) {
                if ($scenario === 'plan') {
                    self::assertInstanceOf(ReleaseInstallationConflictException::class, $exception);
                }
            }

            $state = $this->readState($install);
            self::assertSame(ReleaseUpdateState::STATUS_FAILED, $state['status'], $scenario);
            self::assertSame(ReleaseUpdateState::PHASE_PREPARING, $state['phase'], $scenario);
            self::assertFalse($state['mutations_started'], $scenario);
            self::assertSame(0, $migrations, $scenario);
            self::assertSame($manifestBefore, $this->contents($install . '/' . ManagedFileManifest::FILENAME));
            self::assertSame($scenario === 'plan' ? 'local modification' : 'old a', $this->contents($install . '/a-first.txt'));
            self::assertSame('old z', $this->contents($install . '/z-second.txt'));
            self::assertFileDoesNotExist($install . '/composer.lock');
            self::assertSame('old obsolete a', $this->contents($install . '/obsolete/a.txt'));
            $this->assertSafetyWitnesses($install, $outside);
            $this->assertLockReleased($install);

            if ($scenario === 'release') {
                $this->writeFile($release . '/z-second.txt', 'new z');
                ManagedFileManifest::write($release, ManagedFileManifest::generate($release));
            } elseif ($scenario === 'plan') {
                $this->writeFile($install . '/a-first.txt', 'old a');
            }
            $coordinator->apply($release, $install, 'v0.8.17');
            self::assertSame(ReleaseUpdateState::STATUS_COMPLETED, $this->readState($install)['status']);
        }
    }

    public function testFailureBeforeFirstCopyRollsBackAndAllowsRetry(): void
    {
        [$release, $install, $outside] = $this->releaseFixture('before-first-copy');
        $this->failNativeWhen(
            'copy',
            static fn (array $arguments): bool => str_starts_with((string) $arguments[0], $release . '/'),
            false,
            'before first copy'
        );

        $this->expectCoordinatorFailure($release, $install, 'before first copy');

        $journalPersisted = null;
        $firstReleaseCopy = null;
        foreach (ReleaseFailureProbe::events() as $index => $event) {
            if (
                $event['operation'] === 'rename'
                && str_ends_with((string) $event['arguments'][1], '/journal.json')
            ) {
                $journalPersisted = $index;
            }
            if (
                $event['operation'] === 'copy'
                && str_starts_with((string) $event['arguments'][0], $release . '/')
            ) {
                $firstReleaseCopy = $index;
                break;
            }
        }
        self::assertIsInt($journalPersisted);
        self::assertIsInt($firstReleaseCopy);
        self::assertLessThan($firstReleaseCopy, $journalPersisted);

        $this->assertFailedMutationState($install, ReleaseUpdateState::PHASE_INSTALLING_FILES);
        self::assertSame('old a', $this->contents($install . '/a-first.txt'));
        self::assertSame('old z', $this->contents($install . '/z-second.txt'));
        self::assertSame('old obsolete a', $this->contents($install . '/obsolete/a.txt'));
        self::assertFileDoesNotExist($install . '/composer.lock');
        self::assertDirectoryDoesNotExist($install . '/vendor');
        $this->assertSafetyWitnesses($install, $outside);
        $this->assertLockReleased($install);
        $this->assertRetryBlocked($release, $install);
    }

    public function testFailureDuringLaterCopyRestoresThePreviousTreeAndAllowsRetry(): void
    {
        [$release, $install, $outside] = $this->releaseFixture('later-copy');
        $this->failNativeWhen(
            'copy',
            static fn (array $arguments): bool => $arguments[0] === $release . '/z-second.txt',
            false,
            'later copy'
        );

        $this->expectCoordinatorFailure($release, $install, 'later copy');

        $this->assertFailedMutationState($install, ReleaseUpdateState::PHASE_INSTALLING_FILES);
        self::assertSame('old a', $this->contents($install . '/a-first.txt'));
        self::assertSame('old z', $this->contents($install . '/z-second.txt'));
        self::assertSame('old obsolete a', $this->contents($install . '/obsolete/a.txt'));
        self::assertFileDoesNotExist($install . '/composer.lock');
        self::assertDirectoryDoesNotExist($install . '/vendor');
        $this->assertSafetyWitnesses($install, $outside);
        $this->assertLockReleased($install);
        $this->assertRetryBlocked($release, $install);
    }

    public function testFailureDuringLaterObsoleteRemovalRestoresThePreviousTree(): void
    {
        [$release, $install, $outside] = $this->releaseFixture('obsolete-removal');
        $this->failNativeWhen(
            'unlink',
            static fn (array $arguments): bool => $arguments[0] === $install . '/obsolete/z.txt',
            false,
            'obsolete removal'
        );

        $this->expectCoordinatorFailure($release, $install, 'obsolete removal');

        $this->assertFailedMutationState($install, ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP);
        self::assertSame('old a', $this->contents($install . '/a-first.txt'));
        self::assertSame('old z', $this->contents($install . '/z-second.txt'));
        self::assertSame('old obsolete a', $this->contents($install . '/obsolete/a.txt'));
        self::assertSame('old obsolete z', $this->contents($install . '/obsolete/z.txt'));
        $this->assertSafetyWitnesses($install, $outside);
        $this->assertLockReleased($install);
        $this->assertRetryBlocked($release, $install);
    }

    public function testAssetAndBladeFailuresRestoreManagedFilesAndClearDerivedCache(): void
    {
        foreach (['assets', 'blade'] as $scenario) {
            [$release, $install, $outside] = $this->releaseFixture($scenario);
            if ($scenario === 'assets') {
                $this->failNativeWhen(
                    'copy',
                    static fn (array $arguments): bool => $arguments[0]
                        === $release . '/vendor/alxarafe/alxarafe/templates/themes/default/css/z.css'
                        && str_contains($arguments[1], '/public_html/themes/default/css/'),
                    false,
                    'asset publication'
                );
            } else {
                $this->failNativeWhen(
                    'unlink',
                    static fn (array $arguments): bool => $arguments[0] === $install . '/var/cache/blade/z.php',
                    false,
                    'blade cleanup'
                );
            }

            $this->expectCoordinatorFailure($release, $install, $scenario === 'assets' ? 'asset publication' : 'blade cleanup');

            $this->assertFailedMutationState($install, ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP);
            self::assertSame('old asset a', $this->contents($install . '/public_html/themes/default/css/a.css'));
            if ($scenario === 'assets') {
                self::assertSame('old asset z', $this->contents($install . '/public_html/themes/default/css/z.css'));
            } else {
                self::assertSame('old asset z', $this->contents($install . '/public_html/themes/default/css/z.css'));
            }
            self::assertDirectoryDoesNotExist($install . '/var/cache/blade');
            $this->assertSafetyWitnesses($install, $outside);
            $this->assertLockReleased($install);
            $this->assertRetryBlocked($release, $install);
        }
    }

    public function testInstalledTreeVerificationDetectsAssetRemovalOfAManagedFile(): void
    {
        [$release, $install, $outside] = $this->releaseFixture('tree-verification');
        $relative = 'public_html/themes/legacy/css/obsolete.css';
        $this->writeFile($release . '/' . $relative, 'managed but obsolete asset');
        ManagedFileManifest::write($release, ManagedFileManifest::generate($release));
        $this->writeFile($install . '/' . $relative, 'managed but obsolete asset');
        $this->writeAssetManifest($install, [$relative => hash('sha256', 'managed but obsolete asset')]);

        $this->expectCoordinatorFailure($release, $install, 'verificación final');

        $this->assertFailedMutationState($install, ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP);
        self::assertSame('managed but obsolete asset', $this->contents($install . '/' . $relative));
        $this->assertSafetyWitnesses($install, $outside);
        $this->assertLockReleased($install);
    }

    public function testOpcacheResetFailureIsObservableAfterInvalidationsAndBeforeMigrations(): void
    {
        [$release, $install, $outside] = $this->releaseFixture('opcache');
        $this->failNativeWhen('opcache_reset', static fn (): bool => true, false, 'opcache reset');

        $this->expectCoordinatorFailure($release, $install, 'opcache reset');

        $operations = array_column(ReleaseFailureProbe::events(), 'operation');
        self::assertContains('opcache_invalidate', $operations);
        self::assertContains('opcache_reset', $operations);
        $this->assertFailedMutationState($install, ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP);
        $this->assertSafetyWitnesses($install, $outside);
        $this->assertLockReleased($install);
        $this->assertRetryBlocked($release, $install);
    }

    public function testFirstAndLaterMigrationFailuresKeepCommittedWitnessesAndRequireDatabaseRecovery(): void
    {
        foreach (['first', 'later'] as $scenario) {
            [$release, $install, $outside] = $this->releaseFixture('migration-' . $scenario);
            $database = new PDO('sqlite:' . $this->workspace . '/migration-' . $scenario . '.sqlite');
            $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $coordinator = new ReleaseUpdateCoordinator(
                new ReleaseInstaller(),
                static function () use ($database, $scenario): bool {
                    $database->exec('CREATE TABLE witnesses (name TEXT PRIMARY KEY)');
                    $database->exec("INSERT INTO witnesses VALUES ('migration-1')");
                    if ($scenario === 'first') {
                        throw new RuntimeException('first migration failed after mutation');
                    }
                    $database->exec("INSERT INTO witnesses VALUES ('migration-2')");
                    throw new RuntimeException('later migration failed after previous commit');
                }
            );

            try {
                $coordinator->apply($release, $install, 'v0.8.17');
                self::fail("migration {$scenario} debía fallar");
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('migration', $exception->getMessage());
            }

            $expected = $scenario === 'first' ? ['migration-1'] : ['migration-1', 'migration-2'];
            self::assertSame($expected, $database->query('SELECT name FROM witnesses ORDER BY name')->fetchAll(PDO::FETCH_COLUMN));
            $this->assertFailedMutationState($install, ReleaseUpdateState::PHASE_MIGRATIONS);
            self::assertSame('new a', $this->contents($install . '/a-first.txt'));
            $this->assertOldManifest($install);
            $this->assertSafetyWitnesses($install, $outside);
            $this->assertLockReleased($install);
            $this->assertRetryBlocked($release, $install);
        }
    }

    public function testFinalValidationAfterMigrationsKeepsDatabaseWitnessAndOldManifest(): void
    {
        [$release, $install, $outside] = $this->releaseFixture('final-validation');
        $database = new PDO('sqlite:' . $this->workspace . '/final-validation.sqlite');
        $coordinator = new ReleaseUpdateCoordinator(
            new ReleaseInstaller(),
            function () use ($database, $install): bool {
                $database->exec('CREATE TABLE witnesses (name TEXT PRIMARY KEY)');
                $database->exec("INSERT INTO witnesses VALUES ('migration-1')");
                $this->writeFile($install . '/a-first.txt', 'changed after migration');
                return true;
            }
        );

        try {
            $coordinator->apply($release, $install, 'v0.8.17');
            self::fail('La validación final debía fallar');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('verificación final', $exception->getMessage());
        }

        self::assertSame(['migration-1'], $database->query('SELECT name FROM witnesses')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertFailedMutationState($install, ReleaseUpdateState::PHASE_MIGRATIONS);
        self::assertSame('changed after migration', $this->contents($install . '/a-first.txt'));
        $this->assertOldManifest($install);
        $this->assertSafetyWitnesses($install, $outside);
        $this->assertLockReleased($install);
        $this->assertRetryBlocked($release, $install);
    }

    public function testManifestPromotionBeforeAndAfterRenameHasDifferentTreesButTheSamePersistedPhase(): void
    {
        foreach (['before', 'after'] as $timing) {
            [$release, $install, $outside] = $this->releaseFixture('manifest-' . $timing);
            $newManifest = $this->contents($release . '/' . ManagedFileManifest::FILENAME);
            $this->failNativeWhen(
                'rename',
                static fn (array $arguments): bool => $arguments[1]
                    === $install . '/' . ManagedFileManifest::FILENAME,
                $timing === 'after',
                'manifest rename ' . $timing
            );

            $this->expectCoordinatorFailure($release, $install, 'manifest rename ' . $timing);

            $this->assertFailedMutationState($install, ReleaseUpdateState::PHASE_PROMOTING_MANIFEST);
            if ($timing === 'before') {
                $this->assertOldManifest($install);
            } else {
                self::assertSame($newManifest, $this->contents($install . '/' . ManagedFileManifest::FILENAME));
            }
            $this->assertSafetyWitnesses($install, $outside);
            $this->assertLockReleased($install);
            $this->assertRetryBlocked($release, $install);
        }
    }

    public function testCompletedStatePersistenceFailureNeverReturnsSuccessAndLeavesManualBlock(): void
    {
        foreach (['before', 'after'] as $timing) {
            [$release, $install, $outside] = $this->releaseFixture('completed-' . $timing);
            $newManifest = $this->contents($release . '/' . ManagedFileManifest::FILENAME);
            $this->failNativeWhen(
                'rename',
                static function (array $arguments) use ($install): bool {
                    if ($arguments[1] !== $install . '/' . ReleaseUpdateStorage::STATE_PATH) {
                        return false;
                    }
                    $document = json_decode((string) \file_get_contents($arguments[0]), true);
                    return is_array($document) && ($document['status'] ?? null) === ReleaseUpdateState::STATUS_COMPLETED;
                },
                $timing === 'after',
                'completed persistence ' . $timing
            );

            $this->expectCoordinatorFailure($release, $install, 'completed persistence ' . $timing);

            $this->assertFailedMutationState($install, ReleaseUpdateState::PHASE_PROMOTING_MANIFEST);
            self::assertSame($newManifest, $this->contents($install . '/' . ManagedFileManifest::FILENAME));
            $this->assertSafetyWitnesses($install, $outside);
            $this->assertLockReleased($install);
            $this->assertRetryBlocked($release, $install);
        }
    }

    public function testAbruptInterruptionSnapshotsAreDetectedWithRealPersistentWitnesses(): void
    {
        foreach (['installing_files', 'publishing_cleanup', 'migrations', 'promoting_old', 'promoting_new'] as $scenario) {
            [$release, $install, $outside] = $this->releaseFixture('abrupt-' . $scenario);
            $state = ReleaseUpdateState::start('0.8.17', '0.8.17')
                ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true);
            $this->writeFile($install . '/a-first.txt', 'new a');
            if ($scenario !== 'installing_files') {
                $state = $state->advance(ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP, true);
                self::assertTrue(unlink($install . '/obsolete/a.txt'));
            }
            if (in_array($scenario, ['migrations', 'promoting_old', 'promoting_new'], true)) {
                $state = $state->advance(ReleaseUpdateState::PHASE_MIGRATIONS, true);
                $this->writeFile($install . '/var/migration-witness', 'migration committed');
            }
            if (str_starts_with($scenario, 'promoting_')) {
                $state = $state->advance(ReleaseUpdateState::PHASE_PROMOTING_MANIFEST, true);
                if ($scenario === 'promoting_new') {
                    $this->writeFile(
                        $install . '/' . ManagedFileManifest::FILENAME,
                        $this->contents($release . '/' . ManagedFileManifest::FILENAME)
                    );
                }
            }
            $storage = new ReleaseUpdateStorage($install);
            $storage->acquire();
            $storage->write($state);
            $storage->release();

            $this->assertRetryBlocked($release, $install);

            $interrupted = $this->readState($install);
            $expectedStatus = in_array($scenario, ['installing_files', 'publishing_cleanup'], true)
                ? ReleaseUpdateState::STATUS_ROLLBACK_FAILED
                : ReleaseUpdateState::STATUS_INTERRUPTED;
            self::assertSame($expectedStatus, $interrupted['status']);
            self::assertTrue($interrupted['mutations_started']);
            self::assertSame('new a', $this->contents($install . '/a-first.txt'));
            $this->assertSafetyWitnesses($install, $outside);
            $this->assertLockReleased($install);
        }
    }

    public function testCompletedIsPersistedBeforeSuccessCanBeReturned(): void
    {
        [$release, $install, $outside] = $this->releaseFixture('success-contract');
        $result = (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
            ->apply($release, $install, 'v0.8.17');

        self::assertSame(ReleaseUpdateState::STATUS_COMPLETED, $this->readState($install)['status']);
        self::assertSame('new a', $this->contents($install . '/a-first.txt'));
        self::assertGreaterThan(0, $result['copied']);
        $this->assertSafetyWitnesses($install, $outside);
        $this->assertLockReleased($install);

        $source = $this->contents(dirname(__DIR__, 2) . '/Modules/Chascarrillo/Service/UpdateService.php');
        $completedCall = strpos($source, '->prepareAndApply(');
        $successMessage = strpos($source, 'Messages::addMessage(');
        $successReturn = strpos($source, 'return true;', $successMessage === false ? 0 : $successMessage);
        self::assertIsInt($completedCall);
        self::assertIsInt($successMessage);
        self::assertIsInt($successReturn);
        self::assertLessThan($successMessage, $completedCall);
        self::assertLessThan($successReturn, $successMessage);
    }

    public function testSuccessMessageFailureReturnsFalseAfterCompletedInIsolatedProcess(): void
    {
        [$release, $install, $outside] = $this->releaseFixture('success-message-failure');
        $archive = $this->workspace . '/success-message-failure.zip';
        $this->zipDirectory($release, $archive);
        $script = $this->workspace . '/message-failure.php';
        $this->writeFile($script, <<<'PHP'
<?php
namespace Alxarafe\Infrastructure\Lib {
    abstract class Messages
    {
        public static array $messages = [];
        public static function addMessage(mixed $message): void
        {
            throw new \RuntimeException('success message failed');
        }
        public static function addError(mixed $message): void
        {
            self::$messages[] = ['danger' => $message];
        }
    }
}
namespace Alxarafe\Infrastructure\Persistence {
    abstract class Config
    {
        public static function doRunMigrations(): bool
        {
            return true;
        }
    }
}
namespace {
    require $argv[1];
    define('APP_PATH', $argv[2]);
    $result = \Modules\Chascarrillo\Service\UpdateService::applyUpdate('file://' . $argv[3], 'v0.8.17');
    echo json_encode([
        'result' => $result,
        'messages' => \Alxarafe\Infrastructure\Lib\Messages::$messages,
    ], JSON_THROW_ON_ERROR);
}
PHP);
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $script, dirname(__DIR__, 2) . '/vendor/autoload.php', $install, $archive],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);
        $outcome = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);

        self::assertFalse($outcome['result']);
        self::assertSame('danger', array_key_first($outcome['messages'][0]));
        self::assertStringContainsString('success message failed', $outcome['messages'][0]['danger']);
        self::assertSame(ReleaseUpdateState::STATUS_COMPLETED, $this->readState($install)['status']);
        self::assertSame(
            $this->contents($release . '/' . ManagedFileManifest::FILENAME),
            $this->contents($install . '/' . ManagedFileManifest::FILENAME)
        );
        $this->assertSafetyWitnesses($install, $outside);
        $this->assertLockReleased($install);
    }

    public function testJournalReconcilesAnAmbiguousPreMigrationOperationAfterInterruption(): void
    {
        foreach ([ReleaseUpdateState::PHASE_INSTALLING_FILES, ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP] as $phase) {
            [$release, $install] = $this->releaseFixture('journal-interruption-' . $phase);
            $state = ReleaseUpdateState::start('0.8.17', '0.8.17')
                ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true);
            if ($phase === ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP) {
                $state = $state->advance(ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP, true);
            }
            $storage = new ReleaseUpdateStorage($install);
            $storage->acquire();
            $storage->write($state);
            $storage->release();
            $journal = FilesystemRecoveryJournal::prepare(new SafePath($install), $state->attemptId(), [[
                'type' => 'copy',
                'path' => 'a-first.txt',
                'new_hash' => hash('sha256', 'new a'),
                'new_size' => strlen('new a'),
            ]]);
            try {
                $journal->apply(0, function () use ($install): void {
                    $this->writeFile($install . '/a-first.txt', 'new a');
                    throw new RuntimeException('simulated abrupt stop');
                });
                self::fail('La caída simulada debía interrumpir la confirmación');
            } catch (RuntimeException $exception) {
                self::assertSame('simulated abrupt stop', $exception->getMessage());
            }

            $observedOldTree = false;
            try {
                (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                    ->prepareAndApply($install, 'v0.8.17', function () use ($install, &$observedOldTree): string {
                        $observedOldTree = $this->contents($install . '/a-first.txt') === 'old a';
                        throw new RuntimeException('stop after recovered tree inspection');
                    });
                self::fail('La inspección debía detener el nuevo intento');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('stop after recovered', $exception->getMessage());
            }
            self::assertTrue($observedOldTree, $phase);
            self::assertTrue($journal->isRolledBack(), $phase);
        }
    }

    public function testMigrationPhaseNeverTriggersAutomaticFilesystemRollback(): void
    {
        [$release, $install] = $this->releaseFixture('journal-migrations-boundary');
        $state = ReleaseUpdateState::start('0.8.17', '0.8.17')
            ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true)
            ->advance(ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP, true)
            ->advance(ReleaseUpdateState::PHASE_MIGRATIONS, true);
        $journal = FilesystemRecoveryJournal::prepare(new SafePath($install), $state->attemptId(), [[
            'type' => 'copy',
            'path' => 'a-first.txt',
            'new_hash' => hash('sha256', 'new a'),
            'new_size' => strlen('new a'),
        ]]);
        $journal->apply(0, function () use ($install): void {
            $this->writeFile($install . '/a-first.txt', 'new a');
        });
        $storage = new ReleaseUpdateStorage($install);
        $storage->acquire();
        $storage->write($state);
        $storage->release();

        $this->assertRetryBlocked($release, $install);
        self::assertSame('new a', $this->contents($install . '/a-first.txt'));
        self::assertFalse($journal->isRolledBack());
        self::assertSame(ReleaseUpdateState::STATUS_INTERRUPTED, $this->readState($install)['status']);
    }

    public function testMissingSnapshotAndCorruptJournalBlockWithoutPartialRestore(): void
    {
        foreach (['snapshot', 'journal', 'transition'] as $scenario) {
            [$release, $install] = $this->releaseFixture('corrupt-recovery-' . $scenario);
            $state = ReleaseUpdateState::start('0.8.17', '0.8.17')
                ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true);
            $journal = FilesystemRecoveryJournal::prepare(new SafePath($install), $state->attemptId(), [[
                'type' => 'copy',
                'path' => 'a-first.txt',
                'new_hash' => hash('sha256', 'new a'),
                'new_size' => strlen('new a'),
            ]]);
            $journal->apply(0, function () use ($install): void {
                $this->writeFile($install . '/a-first.txt', 'new a');
            });
            $storage = new ReleaseUpdateStorage($install);
            $storage->acquire();
            $storage->write($state);
            $storage->release();
            $base = $install . '/var/update/recovery/' . $state->attemptId();
            if ($scenario === 'snapshot') {
                self::assertTrue(unlink($base . '/backups/000000.bin'));
            } elseif ($scenario === 'journal') {
                $this->writeFile($base . '/journal.json', '{corrupt');
            } else {
                $document = json_decode($this->contents($base . '/journal.json'), true, 512, JSON_THROW_ON_ERROR);
                $document['operations'][0]['state'] = 'invalid_transition';
                $this->writeFile(
                    $base . '/journal.json',
                    json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n"
                );
            }

            try {
                (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                    ->apply($release, $install);
                self::fail('Recovery corrupto debía bloquear');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('administrativa', $exception->getMessage());
            }
            self::assertSame('new a', $this->contents($install . '/a-first.txt'));
            self::assertSame(ReleaseUpdateState::STATUS_ROLLBACK_FAILED, $this->readState($install)['status']);
        }
    }

    public function testConcurrentModificationIsNotOverwrittenDuringRollback(): void
    {
        [$release, $install] = $this->releaseFixture('concurrent-recovery');
        $state = ReleaseUpdateState::start('0.8.17', '0.8.17')
            ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true);
        $journal = FilesystemRecoveryJournal::prepare(new SafePath($install), $state->attemptId(), [[
            'type' => 'copy',
            'path' => 'a-first.txt',
            'new_hash' => hash('sha256', 'new a'),
            'new_size' => strlen('new a'),
        ]]);
        $journal->apply(0, function () use ($install): void {
            $this->writeFile($install . '/a-first.txt', 'new a');
        });
        $this->writeFile($install . '/a-first.txt', 'third party edit');
        $storage = new ReleaseUpdateStorage($install);
        $storage->acquire();
        $storage->write($state);
        $storage->release();

        try {
            (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                ->apply($release, $install);
            self::fail('La modificación concurrente debía bloquear');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('administrativa', $exception->getMessage());
        }
        self::assertSame('third party edit', $this->contents($install . '/a-first.txt'));
        self::assertSame(ReleaseUpdateState::STATUS_ROLLBACK_FAILED, $this->readState($install)['status']);
    }

    public function testFailureDuringRollbackPersistsRollbackFailedAndBlocksNextAttempt(): void
    {
        [$release, $install] = $this->releaseFixture('rollback-failure');
        $state = ReleaseUpdateState::start('0.8.17', '0.8.17')
            ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true);
        $journal = FilesystemRecoveryJournal::prepare(new SafePath($install), $state->attemptId(), [[
            'type' => 'copy',
            'path' => 'a-first.txt',
            'new_hash' => hash('sha256', 'new a'),
            'new_size' => strlen('new a'),
        ]]);
        $journal->apply(0, function () use ($install): void {
            $this->writeFile($install . '/a-first.txt', 'new a');
        });
        $storage = new ReleaseUpdateStorage($install);
        $storage->acquire();
        $storage->write($state);
        $storage->release();
        $this->failNativeWhen(
            'copy',
            static fn (array $arguments): bool => str_contains($arguments[0], '/var/update/recovery/'),
            false,
            'rollback copy failed'
        );
        try {
            (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                ->apply($release, $install);
            self::fail('El rollback debía fallar');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('administrativa', $exception->getMessage());
        } finally {
            ReleaseFailureProbe::arm(null);
        }
        self::assertSame(ReleaseUpdateState::STATUS_ROLLBACK_FAILED, $this->readState($install)['status']);
        $this->assertRetryBlocked($release, $install);
    }

    public function testRecoverySnapshotAndJournalSymlinksAreRejectedWithoutTouchingWitnesses(): void
    {
        foreach (['snapshot', 'journal'] as $scenario) {
            [$release, $install, $outside] = $this->releaseFixture('recovery-symlink-' . $scenario);
            $state = ReleaseUpdateState::start('0.8.17', '0.8.17')
                ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true);
            $journal = FilesystemRecoveryJournal::prepare(new SafePath($install), $state->attemptId(), [[
                'type' => 'copy',
                'path' => 'a-first.txt',
                'new_hash' => hash('sha256', 'new a'),
                'new_size' => strlen('new a'),
            ]]);
            $journal->apply(0, function () use ($install): void {
                $this->writeFile($install . '/a-first.txt', 'new a');
            });
            $storage = new ReleaseUpdateStorage($install);
            $storage->acquire();
            $storage->write($state);
            $storage->release();
            $base = $install . '/var/update/recovery/' . $state->attemptId();
            $target = $scenario === 'snapshot' ? $base . '/backups/000000.bin' : $base . '/journal.json';
            self::assertTrue(unlink($target));
            self::assertTrue(symlink($outside, $target));

            try {
                (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                    ->apply($release, $install);
                self::fail('El symlink de recovery debía bloquear');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('administrativa', $exception->getMessage());
            }
            self::assertSame('outside untouched', $this->contents($outside));
            self::assertSame('new a', $this->contents($install . '/a-first.txt'));
            self::assertSame(ReleaseUpdateState::STATUS_ROLLBACK_FAILED, $this->readState($install)['status']);
        }
    }

    /** @return array{string,string,string} */
    private function releaseFixture(string $name): array
    {
        $project = dirname(__DIR__, 2);
        $release = $this->workspace . '/' . $name . '-release';
        $install = $this->workspace . '/' . $name . '-install';
        $outside = $this->workspace . '/' . $name . '-outside.txt';
        self::assertTrue(mkdir($install, 0755, true));
        $this->writeFile($outside, 'outside untouched');

        $this->writeFile($release . '/composer.lock', $this->contents($project . '/composer.lock'));
        $this->writeFile(
            $release . '/Modules/Chascarrillo/Service/UpdateService.php',
            $this->contents($project . '/Modules/Chascarrillo/Service/UpdateService.php')
        );
        $this->writeFile($release . '/a-first.txt', 'new a');
        $this->writeFile($release . '/z-second.txt', 'new z');
        $this->writeFile($release . '/public_html/index.php', '<?php // fixture');
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
        $this->writeFile(
            $release . '/vendor/alxarafe/alxarafe/templates/themes/default/css/a.css',
            'new asset a'
        );
        $this->writeFile(
            $release . '/vendor/alxarafe/alxarafe/templates/themes/default/css/z.css',
            'new asset z'
        );
        ManagedFileManifest::write($release, ManagedFileManifest::generate($release));

        $this->writeFile($install . '/a-first.txt', 'old a');
        $this->writeFile($install . '/z-second.txt', 'old z');
        $this->writeFile($install . '/obsolete/a.txt', 'old obsolete a');
        $this->writeFile($install . '/obsolete/z.txt', 'old obsolete z');
        $this->writeFile($install . '/public_html/themes/default/css/a.css', 'old asset a');
        $this->writeFile($install . '/public_html/themes/default/css/z.css', 'old asset z');
        $this->writeAssetManifest($install, [
            'public_html/themes/default/css/a.css' => hash('sha256', 'old asset a'),
            'public_html/themes/default/css/z.css' => hash('sha256', 'old asset z'),
        ]);
        $this->writeFile($install . '/var/cache/blade/a.php', 'old blade a');
        $this->writeFile($install . '/var/cache/blade/z.php', 'old blade z');
        $this->writeFile($install . '/config.json', 'protected config');
        $this->writeFile($install . '/Content/user.md', 'protected content');
        $this->writeFile($install . '/storage/upload.txt', 'protected storage');
        $this->writeFile($install . '/uploads/user.txt', 'protected upload');
        $this->writeFile($install . '/public_html/.htaccess', 'protected htaccess');
        ManagedFileManifest::write($install, [
            'format' => 2,
            'application_version' => '0.8.17',
            'files' => [
                'a-first.txt' => hash('sha256', 'old a'),
                'z-second.txt' => hash('sha256', 'old z'),
                'obsolete/a.txt' => hash('sha256', 'old obsolete a'),
                'obsolete/z.txt' => hash('sha256', 'old obsolete z'),
            ],
        ]);
        return [$release, $install, $outside];
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

    /** @param array<string,string> $files Absolute application path => hash */
    private function writeAssetManifest(string $install, array $files): void
    {
        $entries = [];
        foreach ($files as $path => $hash) {
            $prefix = 'public_html/themes/';
            self::assertStringStartsWith($prefix, $path);
            $entries[] = ['path' => substr($path, strlen($prefix)), 'sha256' => $hash];
        }
        $this->writeFile(
            $install . '/public_html/themes/.chascarrillo-theme-assets.json',
            json_encode(['format' => 2, 'files' => $entries], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n"
        );
    }

    /** @param callable(array<mixed>):bool $matches */
    private function failNativeWhen(
        string $operation,
        callable $matches,
        bool $afterNative,
        string $message
    ): void {
        $fired = false;
        ReleaseFailureProbe::arm(
            static function (
                string $actual,
                array $arguments,
                \Closure $native
            ) use (
                $operation,
                $matches,
                $afterNative,
                $message,
                &$fired
            ): mixed {
                if (!$fired && $actual === $operation && $matches($arguments)) {
                    $fired = true;
                    if ($afterNative) {
                        $native();
                    }
                    throw new RuntimeException($message);
                }
                return $native();
            }
        );
    }

    private function expectCoordinatorFailure(string $release, string $install, string $message): void
    {
        try {
            (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                ->apply($release, $install, 'v0.8.17');
            self::fail("{$message} debía propagarse");
        } catch (RuntimeException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        } finally {
            ReleaseFailureProbe::arm(null);
        }
    }

    private function assertFailedMutationState(string $install, string $phase): void
    {
        $state = $this->readState($install);
        $expectedStatus = in_array($phase, [
            ReleaseUpdateState::PHASE_INSTALLING_FILES,
            ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP,
        ], true) ? ReleaseUpdateState::STATUS_FILESYSTEM_ROLLED_BACK : ReleaseUpdateState::STATUS_FAILED;
        self::assertSame($expectedStatus, $state['status']);
        self::assertSame($phase, $state['phase']);
        self::assertTrue($state['mutations_started']);
        self::assertNotSame(ReleaseUpdateState::STATUS_COMPLETED, $state['status']);
    }

    private function assertRetryBlocked(string $release, string $install): void
    {
        if ($this->readState($install)['status'] === ReleaseUpdateState::STATUS_FILESYSTEM_ROLLED_BACK) {
            (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                ->apply($release, $install, 'v0.8.17');
            self::assertSame(ReleaseUpdateState::STATUS_COMPLETED, $this->readState($install)['status']);
            return;
        }
        try {
            (new ReleaseUpdateCoordinator(new ReleaseInstaller(), static fn (): bool => true))
                ->apply($release, $install, 'v0.8.17');
            self::fail('El siguiente intento debía quedar bloqueado');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('administrativa', $exception->getMessage());
        }
    }

    private function assertLockReleased(string $install): void
    {
        $storage = new ReleaseUpdateStorage($install);
        $storage->acquire();
        $storage->release();
    }

    private function assertOldManifest(string $install): void
    {
        $manifest = ManagedFileManifest::load($install, true, true);
        self::assertSame(hash('sha256', 'old a'), $manifest['files']['a-first.txt']);
        self::assertSame(hash('sha256', 'old z'), $manifest['files']['z-second.txt']);
    }

    private function assertSafetyWitnesses(string $install, string $outside): void
    {
        self::assertSame('outside untouched', $this->contents($outside));
        self::assertSame('protected config', $this->contents($install . '/config.json'));
        self::assertSame('protected content', $this->contents($install . '/Content/user.md'));
        self::assertSame('protected storage', $this->contents($install . '/storage/upload.txt'));
        self::assertSame('protected upload', $this->contents($install . '/uploads/user.txt'));
        self::assertSame('protected htaccess', $this->contents($install . '/public_html/.htaccess'));
    }

    /** @return array<string,mixed> */
    private function readState(string $install): array
    {
        $document = json_decode(
            $this->contents($install . '/' . ReleaseUpdateStorage::STATE_PATH),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($document);
        return $document;
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertNotFalse($contents, $path);
        return $contents;
    }

    private function writeFile(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) {
            self::assertTrue(mkdir(dirname($path), 0755, true));
        }
        self::assertNotFalse(file_put_contents($path, $contents));
    }

    private function zipDirectory(string $source, string $target): void
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && !$file->isLink()) {
                self::assertTrue($zip->addFile(
                    $file->getPathname(),
                    substr($file->getPathname(), strlen($source) + 1)
                ));
            }
        }
        self::assertTrue($zip->close());
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

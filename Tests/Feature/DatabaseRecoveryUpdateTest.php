<?php

declare(strict_types=1);

namespace Tests\Feature;

use Closure;
use InvalidArgumentException;
use Modules\Chascarrillo\Service\DatabaseRecoveryCapability;
use Modules\Chascarrillo\Service\DatabaseRecoveryJournal;
use Modules\Chascarrillo\Service\DatabaseRecoveryProvider;
use Modules\Chascarrillo\Service\FilesystemRecoveryJournal;
use Modules\Chascarrillo\Service\ManagedFileManifest;
use Modules\Chascarrillo\Service\ReleaseInstaller;
use Modules\Chascarrillo\Service\ReleaseMigrationExecutor;
use Modules\Chascarrillo\Service\ReleaseUpdateCoordinator;
use Modules\Chascarrillo\Service\ReleaseUpdateState;
use Modules\Chascarrillo\Service\ReleaseUpdateStorage;
use Modules\Chascarrillo\Service\SafePath;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseRecoveryUpdateTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = sys_get_temp_dir() . '/chascarrillo-db-recovery-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace, 0755, true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->workspace);
        parent::tearDown();
    }

    public function testReleaseWithoutPendingMigrationsContinuesWithoutRecoveryProvider(): void
    {
        [$release, $install] = $this->releaseFixture('no-migrations');
        $provider = new class implements DatabaseRecoveryProvider {
            public bool $called = false;

            public function verify(
                string $releaseRoot,
                string $installRoot,
                array $pending
            ): DatabaseRecoveryCapability {
                $this->called = true;
                return DatabaseRecoveryCapability::unavailable();
            }
        };

        (new ReleaseUpdateCoordinator(new ReleaseInstaller(), $this->executor([]), $provider))
            ->apply($release, $install);

        self::assertFalse($provider->called);
        $state = $this->state($install);
        self::assertSame(ReleaseUpdateState::STATUS_COMPLETED, $state['status']);
        $database = $this->databaseJournal($install, $state['attempt_id']);
        self::assertSame('not_required', $database['status']);
        self::assertNull($database['recovery']);
    }

    public function testPendingMigrationsWithoutRecoveryAbortBeforeManagedFilesystemOrDatabaseMutation(): void
    {
        [$release, $install] = $this->releaseFixture('missing-recovery');
        $executed = [];
        $executor = $this->executor(
            ['20260910_000001@One'],
            null,
            static function (string $migration) use (&$executed): void {
                $executed[] = $migration;
            }
        );

        try {
            (new ReleaseUpdateCoordinator(new ReleaseInstaller(), $executor))->apply($release, $install);
            self::fail('La ausencia de recovery debía abortar');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('recuperación de base de datos validada', $exception->getMessage());
        }

        self::assertSame([], $executed);
        self::assertFileDoesNotExist($install . '/composer.lock');
        self::assertSame(ReleaseUpdateState::PHASE_PREPARING, $this->state($install)['phase']);
        self::assertFalse($this->state($install)['mutations_started']);
    }

    public function testUnavailableOrInvalidRecoveryEvidenceFailsClosed(): void
    {
        self::assertFalse(DatabaseRecoveryCapability::unavailable('external')->allowsMigrations());
        $this->expectException(InvalidArgumentException::class);
        DatabaseRecoveryCapability::externalVerified(
            'cpanel',
            'mysql://user:password@host/database',
            '2026-09-10T12:00:00Z',
            '2026-09-10T12:05:00Z',
            false
        );
    }

    public function testFailureBeforeFirstMigrationKeepsDatabaseUntouchedAndRollsBackFilesystem(): void
    {
        [$release, $install] = $this->releaseFixture('before-first');
        $executed = [];
        $executor = $this->executor(
            ['20260910_000001@One'],
            static fn (): never => throw new RuntimeException('preparación de migración fallida'),
            static function (string $migration) use (&$executed): void {
                $executed[] = $migration;
            }
        );

        try {
            $this->coordinator($executor)->apply($release, $install);
            self::fail('La preparación debía fallar');
        } catch (RuntimeException $exception) {
            self::assertSame('preparación de migración fallida', $exception->getMessage());
        }

        self::assertSame([], $executed);
        self::assertFileDoesNotExist($install . '/composer.lock');
        self::assertSame(ReleaseUpdateState::STATUS_FILESYSTEM_ROLLED_BACK, $this->state($install)['status']);
    }

    public function testFailureDuringFirstMigrationBlocksAndRequiresDatabaseRecovery(): void
    {
        [$release, $install] = $this->releaseFixture('first-failure');
        $executor = $this->executor(
            ['20260910_000001@One'],
            null,
            static fn (): never => throw new RuntimeException('fallo dentro de up')
        );

        try {
            $this->coordinator($executor)->apply($release, $install);
            self::fail('La migración debía fallar');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('20260910_000001@One', $exception->getMessage());
        }

        $state = $this->state($install);
        self::assertSame(ReleaseUpdateState::STATUS_FAILED, $state['status']);
        self::assertSame(ReleaseUpdateState::PHASE_MIGRATIONS, $state['phase']);
        $database = $this->databaseJournal($install, $state['attempt_id']);
        self::assertSame('failed', $database['status']);
        self::assertSame('failed', $database['migrations'][0]['status']);
    }

    public function testFailureDuringLaterMigrationIdentifiesLastAppliedAndCurrentMigration(): void
    {
        [$release, $install] = $this->releaseFixture('later-failure');
        $ids = ['20260910_000001@One', '20260910_000002@One'];
        $executor = $this->executor(
            $ids,
            null,
            static function (string $migration) use ($ids): void {
                if ($migration === $ids[1]) {
                    throw new RuntimeException('segundo up fallido');
                }
            }
        );

        try {
            $this->coordinator($executor)->apply($release, $install);
            self::fail('La segunda migración debía fallar');
        } catch (RuntimeException) {
        }

        $state = $this->state($install);
        $journal = DatabaseRecoveryJournal::open(new SafePath($install), $state['attempt_id']);
        self::assertSame($ids[0], $journal->lastAppliedMigration());
        self::assertSame($ids[1], $journal->currentMigration());
    }

    public function testInterruptedStartedMigrationBecomesAmbiguousAndBlocksAnotherAttempt(): void
    {
        [, $install] = $this->releaseFixture('interrupted');
        $state = ReleaseUpdateState::start('0.8.17', '0.8.17')
            ->advance(ReleaseUpdateState::PHASE_INSTALLING_FILES, true)
            ->advance(ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP, true)
            ->advance(ReleaseUpdateState::PHASE_MIGRATIONS, true);
        FilesystemRecoveryJournal::prepare(new SafePath($install), $state->attemptId(), []);
        $journal = DatabaseRecoveryJournal::prepare(
            new SafePath($install),
            $state->attemptId(),
            $this->verifiedRecovery(),
            ['20260910_000001@One']
        );
        $journal->start('20260910_000001@One');
        $storage = new ReleaseUpdateStorage($install);
        $storage->acquire();
        $storage->write($state);
        $storage->release();

        try {
            $this->coordinator($this->executor([]))->apply('/unused', $install);
            self::fail('La interrupción ambigua debía bloquear');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('intervención administrativa', $exception->getMessage());
        }

        self::assertSame('ambiguous', $journal->toArray()['status']);
        self::assertSame(ReleaseUpdateState::STATUS_INTERRUPTED, $this->state($install)['status']);
    }

    public function testAppliedDatabaseWithMissingCheckpointRemainsStartedThenAmbiguous(): void
    {
        [, $install] = $this->releaseFixture('checkpoint-failure');
        $attempt = ReleaseUpdateState::start('0.8.17', '0.8.17')->attemptId();
        FilesystemRecoveryJournal::prepare(new SafePath($install), $attempt, []);
        $journal = DatabaseRecoveryJournal::prepare(
            new SafePath($install),
            $attempt,
            $this->verifiedRecovery(),
            ['20260910_000001@One']
        );
        $journal->start('20260910_000001@One');

        self::assertSame('20260910_000001@One', $journal->currentMigration());
        self::assertNull($journal->lastAppliedMigration());
        $journal->markAmbiguous();
        self::assertSame('ambiguous', $journal->toArray()['migrations'][0]['status']);
    }

    public function testSuccessfulMigrationsAreValidatedBeforeManifestPromotionAndCompleted(): void
    {
        [$release, $install] = $this->releaseFixture('success');
        $ids = ['20260910_000001@One', '20260910_000002@One'];
        $applied = [];
        $executor = $this->executor(
            $ids,
            null,
            static function (string $migration) use (&$applied): void {
                $applied[] = $migration;
            },
            static function (array $migrations) use (&$applied): void {
                self::assertSame($migrations, $applied);
            }
        );

        $this->coordinator($executor)->apply($release, $install);

        $state = $this->state($install);
        self::assertSame(ReleaseUpdateState::STATUS_COMPLETED, $state['status']);
        self::assertSame('applied', $this->databaseJournal($install, $state['attempt_id'])['status']);
        self::assertSame(
            file_get_contents($release . '/' . ManagedFileManifest::FILENAME),
            file_get_contents($install . '/' . ManagedFileManifest::FILENAME)
        );
    }

    public function testFailureAfterMigrationsBeforePromotionKeepsCoordinatedRecoveryReferences(): void
    {
        [$release, $install] = $this->releaseFixture('post-migrations');
        ManagedFileManifest::write($install, [
            'format' => 2,
            'application_version' => '0.8.17',
            'files' => [],
        ]);
        $previousManifest = file_get_contents($install . '/' . ManagedFileManifest::FILENAME);
        $relative = 'Modules/Chascarrillo/Application/AppContainer.php';
        $executor = $this->executor(
            ['20260910_000001@One'],
            null,
            static function (): void {
            },
            function () use ($install, $relative): void {
                file_put_contents($install . '/' . $relative, 'changed after database migration');
            }
        );

        try {
            $this->coordinator($executor)->apply($release, $install);
            self::fail('La validación final debía impedir la promoción');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('verificación final', $exception->getMessage());
        }

        $state = $this->state($install);
        self::assertSame(ReleaseUpdateState::STATUS_FAILED, $state['status']);
        self::assertSame(ReleaseUpdateState::PHASE_MIGRATIONS, $state['phase']);
        self::assertSame('applied', $this->databaseJournal($install, $state['attempt_id'])['status']);
        self::assertSame($previousManifest, file_get_contents($install . '/' . ManagedFileManifest::FILENAME));
        self::assertDirectoryExists(
            $install . '/' . FilesystemRecoveryJournal::ROOT . '/' . $state['attempt_id']
        );
    }

    public function testPersistedRecoveryContainsNoSqlCredentialsOrAbsolutePaths(): void
    {
        [$release, $install] = $this->releaseFixture('sanitized');
        $this->coordinator($this->executor(['20260910_000001@One']))->apply($release, $install);
        $state = $this->state($install);
        $encoded = json_encode(
            $this->databaseJournal($install, $state['attempt_id']),
            JSON_THROW_ON_ERROR
        );

        self::assertStringNotContainsString('password', strtolower($encoded));
        self::assertStringNotContainsString('select ', strtolower($encoded));
        self::assertStringNotContainsString($this->workspace, $encoded);
        self::assertStringContainsString('backup-20260910-120000', $encoded);
    }

    public function testMariaDbDdlIsNotClaimedAsTransactionallyRollbackable(): void
    {
        $host = getenv('DB_HOST') ?: 'chascarrillo_db';
        if ($host === '127.0.0.1') {
            $host = 'chascarrillo_db';
        }
        $database = getenv('MARIADB_DATABASE') ?: (getenv('DB_DATABASE') ?: 'chascarrillo');
        $user = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('MARIADB_ROOT_PASSWORD') ?: (getenv('DB_PASSWORD') ?: '');
        try {
            $pdo = new PDO("mysql:host={$host};dbname={$database}", $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Throwable $exception) {
            self::markTestSkipped('MariaDB de Docker no disponible para la prueba DDL: ' . $exception::class);
        }
        $table = 'b53_ddl_' . bin2hex(random_bytes(6));
        try {
            self::assertTrue($pdo->beginTransaction());
            $pdo->exec("CREATE TABLE `{$table}` (`id` INT NOT NULL)");
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
            );
            $statement->execute([$database, $table]);
            self::assertSame(1, (int) $statement->fetchColumn());
        } finally {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    private function coordinator(ReleaseMigrationExecutor $executor): ReleaseUpdateCoordinator
    {
        $provider = new class ($this->verifiedRecovery()) implements DatabaseRecoveryProvider {
            public function __construct(private readonly DatabaseRecoveryCapability $capability)
            {
            }

            public function verify(
                string $releaseRoot,
                string $installRoot,
                array $pending
            ): DatabaseRecoveryCapability {
                return $this->capability;
            }
        };
        return new ReleaseUpdateCoordinator(new ReleaseInstaller(), $executor, $provider);
    }

    private function verifiedRecovery(): DatabaseRecoveryCapability
    {
        return DatabaseRecoveryCapability::externalVerified(
            'external-test-provider',
            'backup-20260910-120000',
            '2026-09-10T12:00:00Z',
            '2026-09-10T12:05:00Z',
            false
        );
    }

    /**
     * @param list<string> $pending
     * @param (callable(string,array<string>):void)|null $prepare
     * @param (callable(string,string):void)|null $execute
     * @param (callable(array<string>):void)|null $assertApplied
     */
    private function executor(
        array $pending,
        ?callable $prepare = null,
        ?callable $execute = null,
        ?callable $assertApplied = null
    ): ReleaseMigrationExecutor {
        return new class ($pending, $prepare, $execute, $assertApplied) implements ReleaseMigrationExecutor {
            /** @var list<string> */
            private array $pending;

            /** @var Closure(string,array<string>):void */
            private Closure $prepareCallback;

            /** @var Closure(string,string):void */
            private Closure $executeCallback;

            /** @var Closure(array<string>):void */
            private Closure $assertCallback;

            public function __construct(
                array $pending,
                ?callable $prepare,
                ?callable $execute,
                ?callable $assertApplied
            ) {
                $this->pending = $pending;
                $this->prepareCallback = $prepare === null
                    ? static function (): void {
                    }
                    : Closure::fromCallable($prepare);
                $this->executeCallback = $execute === null
                    ? static function (): void {
                    }
                    : Closure::fromCallable($execute);
                $this->assertCallback = $assertApplied === null
                    ? static function (): void {
                    }
                    : Closure::fromCallable($assertApplied);
            }

            public function pending(string $releaseRoot): array
            {
                return $this->pending;
            }

            public function prepare(string $installRoot, array $migrations): void
            {
                ($this->prepareCallback)($installRoot, $migrations);
            }

            public function execute(string $migration, string $installRoot): void
            {
                ($this->executeCallback)($migration, $installRoot);
            }

            public function assertApplied(array $migrations): void
            {
                ($this->assertCallback)($migrations);
            }
        };
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
    private function state(string $install): array
    {
        $data = json_decode(
            (string) file_get_contents($install . '/' . ReleaseUpdateStorage::STATE_PATH),
            true,
            32,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($data);
        return $data;
    }

    /** @return array<string,mixed> */
    private function databaseJournal(string $install, string $attempt): array
    {
        return DatabaseRecoveryJournal::open(new SafePath($install), $attempt)->toArray();
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

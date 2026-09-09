<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modules\Chascarrillo\Service\ManagedFileManifest;
use Modules\Chascarrillo\Service\HistoricalManagedFileBaseline;
use Modules\Chascarrillo\Service\ReleaseInstallationConflictException;
use Modules\Chascarrillo\Service\ReleaseInstallationPlanner;
use Modules\Chascarrillo\Service\ReleaseInstaller;
use Modules\Chascarrillo\Service\ReleaseUpdateCoordinator;
use Modules\Chascarrillo\Service\SafePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReleaseInstallerLocalModificationTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = sys_get_temp_dir() . '/chascarrillo-local-change-test-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace, 0755, true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->workspace);
        parent::tearDown();
    }

    public function testLocallyModifiedManagedFileAbortsAndIsPreserved(): void
    {
        [$release, $install] = $this->releaseFixture('modified-managed');
        $relative = 'templates/partial/project_menu.blade.php';
        $this->writeFile($install . '/' . $relative, 'locally modified');
        $this->writeInstalledManifest($install, [$relative => hash('sha256', 'previous release')]);

        try {
            (new ReleaseInstaller())->install($release, $install);
            self::fail('La actualización debía rechazar la modificación local');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString($relative, $exception->getMessage());
        }

        self::assertSame('locally modified', file_get_contents($install . '/' . $relative));
    }

    public function testUnmodifiedManagedFileUpdatesSuccessfully(): void
    {
        [$release, $install] = $this->releaseFixture('unmodified-managed');
        $relative = 'templates/partial/project_menu.blade.php';
        $this->writeFile($install . '/' . $relative, 'previous release');
        $this->writeInstalledManifest($install, [$relative => hash('sha256', 'previous release')]);

        $result = (new ReleaseInstaller())->install($release, $install);

        self::assertGreaterThan(0, $result['copied']);
        self::assertSame('new release', file_get_contents($install . '/' . $relative));
    }

    public function testManagedFileAlreadyEqualToNewReleaseIsIdempotent(): void
    {
        [$release, $install] = $this->releaseFixture('already-new');
        $relative = 'templates/partial/project_menu.blade.php';
        $this->writeFile($install . '/' . $relative, 'new release');
        $this->writeInstalledManifest($install, [$relative => hash('sha256', 'previous release')]);

        (new ReleaseInstaller())->install($release, $install);
        $snapshot = $this->snapshot($install);
        $second = (new ReleaseInstaller())->install($release, $install);

        self::assertSame($snapshot, $this->snapshot($install));
        self::assertSame(0, $second['copied']);
        self::assertSame(0, $second['removed']);
        self::assertSame(0, $second['assets']['copied']);
        self::assertSame(0, $second['assets']['removed']);
    }

    public function testConflictLateInTraversalDoesNotModifyEarlierFile(): void
    {
        [$release, $install] = $this->releaseFixture('late-conflict');
        $early = 'Modules/Chascarrillo/Application/AppContainer.php';
        $late = 'vendor/composer/installed.json';
        $earlyPrevious = 'previous early file';
        $latePrevious = 'previous installed metadata';
        $this->writeFile($install . '/' . $early, $earlyPrevious);
        $this->writeFile($install . '/' . $late, 'locally modified late file');
        $this->writeInstalledManifest($install, [
            $early => hash('sha256', $earlyPrevious),
            $late => hash('sha256', $latePrevious),
        ]);

        try {
            (new ReleaseInstaller())->install($release, $install);
            self::fail('La actualización debía detectar el conflicto tardío');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString($late, $exception->getMessage());
        }

        self::assertSame($earlyPrevious, file_get_contents($install . '/' . $early));
        self::assertSame('locally modified late file', file_get_contents($install . '/' . $late));
    }

    public function testLocallyModifiedObsoleteManagedFileAbortsAndIsPreserved(): void
    {
        [$release, $install] = $this->releaseFixture('modified-obsolete');
        $relative = 'templates/obsolete.blade.php';
        $this->writeFile($install . '/' . $relative, 'locally modified obsolete');
        $this->writeInstalledManifest($install, [$relative => hash('sha256', 'original obsolete')]);

        try {
            (new ReleaseInstaller())->install($release, $install);
            self::fail('La actualización debía rechazar el obsoleto modificado');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString($relative, $exception->getMessage());
        }

        self::assertSame('locally modified obsolete', file_get_contents($install . '/' . $relative));
    }

    public function testUnmodifiedObsoleteManagedFileIsRemoved(): void
    {
        [$release, $install] = $this->releaseFixture('unmodified-obsolete');
        $relative = 'templates/obsolete.blade.php';
        $this->writeFile($install . '/' . $relative, 'original obsolete');
        $this->writeInstalledManifest($install, [$relative => hash('sha256', 'original obsolete')]);

        $result = (new ReleaseInstaller())->install($release, $install);

        self::assertSame(1, $result['removed']);
        self::assertFileDoesNotExist($install . '/' . $relative);
    }

    public function testMissingManagedFileIsRestored(): void
    {
        [$release, $install] = $this->releaseFixture('missing-managed');
        $relative = 'templates/partial/project_menu.blade.php';
        $this->writeInstalledManifest($install, [$relative => hash('sha256', 'previous release')]);

        (new ReleaseInstaller())->install($release, $install);

        self::assertSame('new release', file_get_contents($install . '/' . $relative));
    }

    public function testReportsEveryConflictInDeterministicPathOrder(): void
    {
        [$release, $install] = $this->releaseFixture('multiple-conflicts');
        $expected = [
            'templates/a-conflict.txt' => 'new a',
            'templates/z-conflict.txt' => 'new z',
        ];
        foreach ($expected as $relative => $contents) {
            $this->writeFile($release . '/' . $relative, $contents);
            $this->writeFile($install . '/' . $relative, 'local ' . $relative);
        }
        $this->regenerateManifest($release);
        $this->writeInstalledManifest($install, [
            'templates/z-conflict.txt' => hash('sha256', 'old z'),
            'templates/a-conflict.txt' => hash('sha256', 'old a'),
        ]);

        try {
            (new ReleaseInstaller())->install($release, $install);
            self::fail('La actualización debía informar ambos conflictos');
        } catch (ReleaseInstallationConflictException $exception) {
            self::assertSame(
                ['templates/a-conflict.txt', 'templates/z-conflict.txt'],
                array_map(static fn ($conflict): string => $conflict->path, $exception->conflicts())
            );
            self::assertStringContainsString(hash('sha256', 'old a'), $exception->getMessage());
            self::assertStringContainsString(hash('sha256', 'local templates/a-conflict.txt'), $exception->getMessage());
            self::assertStringContainsString(hash('sha256', 'new a'), $exception->getMessage());
        }
    }

    public function testDifferentUnmanagedCollisionAbortsAndIsPreserved(): void
    {
        [$release, $install] = $this->releaseFixture('unmanaged-collision');
        $relative = 'templates/new-managed.txt';
        $this->writeFile($release . '/' . $relative, 'new managed');
        $this->regenerateManifest($release);
        $this->writeFile($install . '/' . $relative, 'unknown local file');
        $this->writeInstalledManifest($install, []);

        $this->expectConflict($release, $install, $relative, 'unmanaged_file_collision');

        self::assertSame('unknown local file', file_get_contents($install . '/' . $relative));
    }

    public function testIdenticalUnmanagedCollisionIsAdoptedSafely(): void
    {
        [$release, $install] = $this->releaseFixture('identical-adoption');
        $relative = 'templates/new-managed.txt';
        $this->writeFile($release . '/' . $relative, 'already installed');
        $this->regenerateManifest($release);
        $this->writeFile($install . '/' . $relative, 'already installed');
        $this->writeInstalledManifest($install, []);

        (new ReleaseInstaller())->install($release, $install);
        $installed = ManagedFileManifest::load($install);

        self::assertSame('already installed', file_get_contents($install . '/' . $relative));
        self::assertSame(hash('sha256', 'already installed'), $installed['files'][$relative]);
    }

    public function testUnknownCustomFilesAndProtectedPathsRemainIntact(): void
    {
        [$release, $install] = $this->releaseFixture('protected-paths');
        $protected = [
            '.env' => 'secret',
            '.htaccess' => 'root rules',
            'config.json' => 'configuration',
            'Content/page.md' => 'content',
            'storage/private.dat' => 'storage',
            'uploads/legacy.txt' => 'root uploads',
            'public_html/.htaccess' => 'public rules',
            'public_html/uploads/photo.jpg' => 'upload',
            'var/cache/other/cache.bin' => 'unmanaged cache',
            'var/private.dat' => 'runtime',
            'public_html/themes/custom/css/custom.css' => 'custom theme',
            'custom/local.txt' => 'unknown custom file',
        ];
        foreach ($protected as $relative => $contents) {
            $this->writeFile($install . '/' . $relative, $contents);
        }
        $this->writeInstalledManifest($install, []);

        (new ReleaseInstaller())->install($release, $install);

        foreach ($protected as $relative => $contents) {
            self::assertSame($contents, file_get_contents($install . '/' . $relative), $relative);
        }
    }

    public function testDirectoryWhereNewFileIsRequiredIsAConflict(): void
    {
        [$release, $install] = $this->releaseFixture('directory-collision');
        $relative = 'templates/new-managed.txt';
        $this->writeFile($release . '/' . $relative, 'new managed');
        $this->regenerateManifest($release);
        self::assertTrue(mkdir($install . '/' . $relative, 0755, true));
        $this->writeInstalledManifest($install, []);

        $this->expectConflict($release, $install, $relative, 'unexpected_node_type');

        self::assertDirectoryExists($install . '/' . $relative);
    }

    public function testSymbolicLinkCollisionIsRejectedWithoutTouchingOutsideWitness(): void
    {
        [$release, $install] = $this->releaseFixture('symlink-collision');
        $relative = 'templates/new-managed.txt';
        $outside = $this->workspace . '/outside-witness.txt';
        $this->writeFile($release . '/' . $relative, 'new managed');
        $this->regenerateManifest($release);
        $this->writeFile($outside, 'outside witness');
        self::assertTrue(mkdir(dirname($install . '/' . $relative), 0755, true));
        self::assertTrue(symlink($outside, $install . '/' . $relative));
        $this->writeInstalledManifest($install, []);

        $this->expectConflict($release, $install, $relative, 'symbolic_link');

        self::assertSame('outside witness', file_get_contents($outside));
        self::assertTrue(is_link($install . '/' . $relative));
    }

    public function testControlledV0817InstallationWithoutManifestUsesAuthenticatedBaseline(): void
    {
        [$release, $install] = $this->releaseFixture('v0817-no-manifest');
        $relative = 'routes.php';
        $oldContents = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
        $baseline = (new HistoricalManagedFileBaseline())->load();
        self::assertSame($baseline[$relative], hash('sha256', $oldContents));
        $this->writeFile($install . '/' . $relative, $oldContents);
        $this->writeFile($release . '/' . $relative, '<?php // next routes');
        $this->regenerateManifest($release);

        (new ReleaseInstaller())->install($release, $install);

        self::assertSame('<?php // next routes', file_get_contents($install . '/' . $relative));
        self::assertFileExists($install . '/' . ManagedFileManifest::FILENAME);
    }

    public function testHistoricalBaselineRecordsAuthenticatedAssetProvenance(): void
    {
        $root = dirname(__DIR__, 2);
        $data = json_decode(
            (string) file_get_contents($root . '/' . HistoricalManagedFileBaseline::FILENAME),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $files = (new HistoricalManagedFileBaseline())->load();

        self::assertSame(HistoricalManagedFileBaseline::RELEASE_URL, $data['source']['release_url']);
        self::assertSame('v0.8.17', $data['source']['tag']);
        self::assertSame(HistoricalManagedFileBaseline::ASSET_NAME, $data['source']['asset_name']);
        self::assertSame(HistoricalManagedFileBaseline::ASSET_URL, $data['source']['asset_url']);
        self::assertSame(HistoricalManagedFileBaseline::ASSET_SHA256, $data['source']['expected_sha256']);
        self::assertSame(HistoricalManagedFileBaseline::ASSET_SHA256, $data['source']['obtained_sha256']);
        self::assertSame(HistoricalManagedFileBaseline::INVENTORY_SHA256, $data['inventory_sha256']);
        self::assertCount(3636, $data['files']);
        self::assertCount(3637, $files);
        self::assertSame(
            hash('sha256', "@include('partial.project_menu')\n"),
            $files['templates/partial/user_menu.blade.php']
        );
    }

    public function testModifiedV0817FileWithoutManifestAbortsAgainstAuthenticatedBaseline(): void
    {
        [$release, $install] = $this->releaseFixture('v0817-modified-no-manifest');
        $relative = 'routes.php';
        $this->writeFile($release . '/' . $relative, '<?php // next routes');
        $this->regenerateManifest($release);
        $this->writeFile($install . '/' . $relative, '<?php // local routes');

        $this->expectConflict($release, $install, $relative, 'managed_file_modified');

        self::assertSame('<?php // local routes', file_get_contents($install . '/' . $relative));
        self::assertFileDoesNotExist($install . '/' . ManagedFileManifest::FILENAME);
    }

    public function testSupportedHistoricalManifestUpdatesNormally(): void
    {
        [$release, $install] = $this->releaseFixture('historical-manifest');
        $relative = 'templates/partial/project_menu.blade.php';
        $this->writeFile($install . '/' . $relative, 'historical old');
        $this->writeHistoricalManifest($install, [$relative => hash('sha256', 'historical old')]);

        (new ReleaseInstaller())->install($release, $install);

        self::assertSame('new release', file_get_contents($install . '/' . $relative));
        self::assertSame('0.8.17', ManagedFileManifest::load($install)['application_version']);
    }

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function unsafePreviousManifests(): iterable
    {
        yield 'corrupt shape' => [['format' => 2, 'files' => 'not a list']];
        yield 'ambiguous versions' => [[
            'format' => 2,
            'application_version' => '0.8.17',
            'version' => 'v0.8.17',
            'files' => [],
        ]];
        yield 'unsupported historical version' => [[
            'format' => 2,
            'version' => 'v0.8.16',
            'files' => [],
        ]];
    }

    /** @param array<string,mixed> $document */
    #[DataProvider('unsafePreviousManifests')]
    public function testCorruptAmbiguousOrUnsupportedPreviousManifestFailsBeforeMutation(array $document): void
    {
        [$release, $install] = $this->releaseFixture('bad-manifest-' . bin2hex(random_bytes(3)));
        $witness = $install . '/witness.txt';
        $this->writeFile($witness, 'untouched');
        $this->writeFile(
            $install . '/' . ManagedFileManifest::FILENAME,
            json_encode($document, JSON_PRETTY_PRINT) ?: '{}'
        );

        try {
            (new ReleaseInstaller())->install($release, $install);
            self::fail('El manifiesto anterior inseguro debía rechazarse');
        } catch (RuntimeException) {
            self::assertSame('untouched', file_get_contents($witness));
            self::assertFileDoesNotExist($install . '/composer.lock');
        }
    }

    public function testConflictPreventsPublisherBladeCleanupAndMigrations(): void
    {
        [$release, $install] = $this->releaseFixture('no-post-effects');
        $relative = 'templates/partial/project_menu.blade.php';
        $this->writeFile($install . '/' . $relative, 'locally modified');
        $this->writeFile($install . '/var/cache/blade/default/stale.php', 'compiled witness');
        $this->writeInstalledManifest($install, [$relative => hash('sha256', 'previous release')]);
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
            self::fail('El conflicto debía detener el coordinador');
        } catch (ReleaseInstallationConflictException) {
            self::assertSame(0, $migrations);
            self::assertSame(
                'compiled witness',
                file_get_contents($install . '/var/cache/blade/default/stale.php')
            );
            self::assertFileDoesNotExist($install . '/public_html/themes/default/css/default.css');
            self::assertSame('locally modified', file_get_contents($install . '/' . $relative));
        }
    }

    public function testPlanRevalidationRejectsAChangedDestinationBeforeMutation(): void
    {
        $install = $this->workspace . '/revalidation-install';
        self::assertTrue(mkdir($install, 0755, true));
        $relative = 'managed.txt';
        $this->writeFile($install . '/' . $relative, 'old');
        $paths = new SafePath($install);
        $plan = (new ReleaseInstallationPlanner())->plan(
            $paths,
            [$relative => hash('sha256', 'new')],
            [$relative => hash('sha256', 'old')],
            SafePath::NODE_MISSING,
            null
        );
        $this->writeFile($install . '/' . $relative, 'changed after preflight');

        try {
            $plan->assertPreconditions($paths);
            self::fail('La revalidación debía detectar el cambio posterior al preflight');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString($relative, $exception->getMessage());
            self::assertSame('changed after preflight', file_get_contents($install . '/' . $relative));
        }
    }

    public function testNormalFullInstallationAndSecondExecutionRemainSafe(): void
    {
        [$release, $install] = $this->releaseFixture('normal-and-repeat');
        $this->writeFile($install . '/custom/witness.txt', 'custom');

        $first = (new ReleaseInstaller())->install($release, $install);
        $snapshot = $this->snapshot($install);
        $second = (new ReleaseInstaller())->install($release, $install);

        self::assertGreaterThan(0, $first['copied']);
        self::assertSame($snapshot, $this->snapshot($install));
        self::assertSame('custom', file_get_contents($install . '/custom/witness.txt'));
        self::assertSame(0, $second['copied']);
        self::assertSame(0, $second['removed']);
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
        $this->writeFile(
            $release . '/Modules/Chascarrillo/Application/AppContainer.php',
            'new early file'
        );
        $this->writeFile(
            $release . '/vendor/composer/installed.json',
            json_encode([
                'packages' => [[
                    'name' => 'alxarafe/alxarafe',
                    'version' => 'v0.6.11',
                    'source' => ['reference' => '4b5a6252750537280c04aa4378b3fcf2570f8efb'],
                ]],
            ], JSON_PRETTY_PRINT) ?: '{}'
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

    private function regenerateManifest(string $release): void
    {
        ManagedFileManifest::write($release, ManagedFileManifest::generate($release));
    }

    /** @return array<string,string> */
    private function essentialTemplates(): array
    {
        $menu = <<<'BLADE'
clock-display controller=Auth partial.lang_switcher partial.theme_switcher Auth::$user
BLADE;
        return [
            'vendor/alxarafe/alxarafe/templates/partial/layout/main.blade.php' => 'layout',
            'vendor/alxarafe/alxarafe/templates/partial/body_standard.blade.php' => 'standard',
            'vendor/alxarafe/alxarafe/templates/partial/body_empty.blade.php' => 'empty',
            'vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php' => $menu,
            'vendor/alxarafe/alxarafe/templates/partial/lang_switcher.blade.php' => 'lang',
            'vendor/alxarafe/alxarafe/templates/partial/theme_switcher.blade.php' => 'theme',
            'vendor/alxarafe/alxarafe/templates/partial/project_menu.blade.php' => 'project',
        ];
    }

    /** @param array<string,string> $files */
    private function writeInstalledManifest(string $install, array $files): void
    {
        ManagedFileManifest::write($install, [
            'format' => 2,
            'application_version' => '0.8.17',
            'files' => $files,
        ]);
    }

    /** @param array<string,string> $files */
    private function writeHistoricalManifest(string $install, array $files): void
    {
        $entries = [];
        foreach ($files as $path => $hash) {
            $entries[] = ['path' => $path, 'sha256' => $hash];
        }
        $this->writeFile($install . '/' . ManagedFileManifest::FILENAME, json_encode([
            'format' => 2,
            'version' => 'v0.8.17',
            'files' => $entries,
        ], JSON_PRETTY_PRINT) ?: '{}');
    }

    private function expectConflict(
        string $release,
        string $install,
        string $relative,
        string $classification
    ): void {
        try {
            (new ReleaseInstaller())->install($release, $install);
            self::fail('La actualización debía detectar un conflicto');
        } catch (ReleaseInstallationConflictException $exception) {
            self::assertStringContainsString($relative, $exception->getMessage());
            self::assertStringContainsString($classification, $exception->getMessage());
        }
    }

    /** @return array<string,string> */
    private function snapshot(string $root): array
    {
        $snapshot = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && !$file->isLink()) {
                $hash = hash_file('sha256', $file->getPathname());
                self::assertNotFalse($hash);
                $snapshot[substr($file->getPathname(), strlen($root) + 1)] = $hash;
            }
        }
        ksort($snapshot, SORT_STRING);
        return $snapshot;
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

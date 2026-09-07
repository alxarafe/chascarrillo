<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modules\Chascarrillo\Service\ManagedFileManifest;
use Modules\Chascarrillo\Service\ReleaseInstaller;
use Modules\Chascarrillo\Service\ThemeAssetPublisher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SymlinkContainmentTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = sys_get_temp_dir() . '/chascarrillo-symlink-test-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace, 0755, true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->workspace);
        parent::tearDown();
    }

    public function testReleaseInstallerRejectsIntermediateSymlinkOutsideInstallRoot(): void
    {
        [$release, $install, $outside] = $this->releaseFixture();
        self::assertTrue(symlink($outside, $install . '/templates'));

        $this->expectUnsafePath(function () use ($release, $install): void {
            (new ReleaseInstaller())->install($release, $install);
        });

        self::assertFileDoesNotExist($outside . '/partial/project_menu.blade.php');
        self::assertSame('outside witness', file_get_contents($outside . '/witness.txt'));
    }

    public function testReleaseInstallerRejectsFinalSymlink(): void
    {
        [$release, $install, $outside] = $this->releaseFixture();
        $this->writeFile($install . '/composer.lock.placeholder', 'placeholder');
        self::assertTrue(symlink($outside . '/witness.txt', $install . '/composer.lock'));

        $this->expectUnsafePath(function () use ($release, $install): void {
            (new ReleaseInstaller())->install($release, $install);
        });

        self::assertTrue(is_link($install . '/composer.lock'));
        self::assertSame('outside witness', file_get_contents($outside . '/witness.txt'));
    }

    public function testReleaseInstallerRejectsExternalBladeCacheSymlinkWithoutDeletingWitness(): void
    {
        [$release, $install, $outside] = $this->releaseFixture();
        $this->writeFile($install . '/templates/placeholder.txt', 'local');
        self::assertTrue(mkdir($install . '/var/cache', 0755, true));
        self::assertTrue(symlink($outside, $install . '/var/cache/blade'));

        $this->expectUnsafePath(function () use ($release, $install): void {
            (new ReleaseInstaller())->install($release, $install);
        });

        self::assertSame('outside witness', file_get_contents($outside . '/witness.txt'));
        self::assertTrue(is_link($install . '/var/cache/blade'));
    }

    public function testReleaseInstallerRejectsSymlinkBeforeDeletingObsoleteFile(): void
    {
        [$release, $install, $outside] = $this->releaseFixture();
        $this->writeFile($outside . '/obsolete.txt', 'old managed');
        self::assertTrue(symlink($outside, $install . '/obsolete'));
        $this->writeFile($install . '/' . ManagedFileManifest::FILENAME, json_encode([
            'format' => 2,
            'version' => 'v0.8.17',
            'files' => [[
                'path' => 'obsolete/obsolete.txt',
                'sha256' => hash('sha256', 'old managed'),
            ]],
        ], JSON_PRETTY_PRINT) ?: '{}');

        $this->expectUnsafePath(function () use ($release, $install): void {
            (new ReleaseInstaller())->install($release, $install);
        });

        self::assertSame('old managed', file_get_contents($outside . '/obsolete.txt'));
        self::assertTrue(is_link($install . '/obsolete'));
    }

    public function testThemePublisherRejectsIntermediateSymlinkOutsidePublicRoot(): void
    {
        [$app, $public, $outside] = $this->assetFixture();
        self::assertTrue(mkdir($public . '/themes/default', 0755, true));
        self::assertTrue(symlink($outside, $public . '/themes/default/css'));

        $this->expectUnsafePath(function () use ($app, $public): void {
            (new ThemeAssetPublisher())->publish($app, $public);
        });

        self::assertFileDoesNotExist($outside . '/default.css');
        self::assertSame('outside witness', file_get_contents($outside . '/witness.txt'));
    }

    public function testThemePublisherRejectsFinalSymlink(): void
    {
        [$app, $public, $outside] = $this->assetFixture();
        self::assertTrue(mkdir($public . '/themes/default/css', 0755, true));
        self::assertTrue(symlink($outside . '/witness.txt', $public . '/themes/default/css/default.css'));

        $this->expectUnsafePath(function () use ($app, $public): void {
            (new ThemeAssetPublisher())->publish($app, $public);
        });

        self::assertTrue(is_link($public . '/themes/default/css/default.css'));
        self::assertSame('outside witness', file_get_contents($outside . '/witness.txt'));
    }

    public function testThemePublisherRejectsSymlinkInsideSourceTree(): void
    {
        [$app, $public, $outside] = $this->assetFixture();
        $source = $app . '/vendor/alxarafe/alxarafe/templates/themes/default/css/default.css';
        self::assertTrue(unlink($source));
        self::assertTrue(symlink($outside . '/witness.txt', $source));

        $this->expectUnsafePath(function () use ($app, $public): void {
            (new ThemeAssetPublisher())->publish($app, $public);
        });

        self::assertSame('outside witness', file_get_contents($outside . '/witness.txt'));
    }

    public function testNormalReleaseAndAssetOperationsRemainAvailable(): void
    {
        [$release, $install] = $this->releaseFixture();
        $this->writeFile($install . '/templates/placeholder.txt', 'local');
        $releaseResult = (new ReleaseInstaller())->install($release, $install);
        self::assertGreaterThan(0, $releaseResult['copied']);

        [$app, $public] = $this->assetFixture('normal-assets');
        $assetResult = (new ThemeAssetPublisher())->publish($app, $public);
        self::assertSame(1, $assetResult['copied']);
        self::assertSame('framework asset', file_get_contents($public . '/themes/default/css/default.css'));
    }

    /** @return array{string,string,string} */
    private function releaseFixture(string $name = 'release'): array
    {
        $project = dirname(__DIR__, 2);
        $release = $this->workspace . '/' . $name;
        $install = $this->workspace . '/' . $name . '-install';
        $outside = $this->workspace . '/' . $name . '-outside';
        self::assertTrue(mkdir($install, 0755, true));
        self::assertTrue(mkdir($outside, 0755, true));
        $this->writeFile($outside . '/witness.txt', 'outside witness');
        $this->writeFile($release . '/composer.lock', (string) file_get_contents($project . '/composer.lock'));
        $this->writeFile(
            $release . '/Modules/Chascarrillo/Service/UpdateService.php',
            (string) file_get_contents($project . '/Modules/Chascarrillo/Service/UpdateService.php')
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
        $templates = [
            'partial/layout/main.blade.php',
            'partial/body_standard.blade.php',
            'partial/body_empty.blade.php',
            'partial/user_menu.blade.php',
            'partial/lang_switcher.blade.php',
            'partial/theme_switcher.blade.php',
            'partial/project_menu.blade.php',
        ];
        foreach ($templates as $template) {
            $this->writeFile(
                $release . '/vendor/alxarafe/alxarafe/templates/' . $template,
                (string) file_get_contents($project . '/vendor/alxarafe/alxarafe/templates/' . $template)
            );
        }
        $this->writeFile(
            $release . '/vendor/alxarafe/alxarafe/templates/themes/default/css/default.css',
            'framework asset'
        );
        $this->writeFile($release . '/templates/partial/project_menu.blade.php', 'project menu');
        $this->writeFile($release . '/public_html/index.php', '<?php // fixture');
        ManagedFileManifest::write($release, ManagedFileManifest::generate($release));
        return [$release, $install, $outside];
    }

    /** @return array{string,string,string} */
    private function assetFixture(string $name = 'assets'): array
    {
        $app = $this->workspace . '/' . $name;
        $public = $app . '/public_html';
        $outside = $this->workspace . '/' . $name . '-outside';
        self::assertTrue(mkdir($outside, 0755, true));
        $this->writeFile($outside . '/witness.txt', 'outside witness');
        $this->writeFile(
            $app . '/vendor/alxarafe/alxarafe/templates/themes/default/css/default.css',
            'framework asset'
        );
        return [$app, $public, $outside];
    }

    /** @param callable():void $operation */
    private function expectUnsafePath(callable $operation): void
    {
        try {
            $operation();
            self::fail('La operación insegura no fue rechazada');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('enlace', strtolower($exception->getMessage()));
        }
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

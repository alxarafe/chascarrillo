<?php

namespace Tests\Feature;

use Alxarafe\Infrastructure\Auth\Auth;
use Alxarafe\Infrastructure\Persistence\Config;
use Alxarafe\Infrastructure\Persistence\Template;
use Modules\Admin\Model\User;
use Modules\Chascarrillo\Service\ManagedFileManifest;
use Modules\Chascarrillo\Service\ReleaseInstaller;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class UpdateRemovesObsoleteUserMenuTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = sys_get_temp_dir() . '/chascarrillo-update-test-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace, 0755, true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->workspace);
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUpdateRemovesLegacyOverrideClearsCacheAndRendersFrameworkMenu(): void
    {
        $install = $this->workspace . '/install';
        $release = $this->workspace . '/release';
        $legacyOverride = $install . '/templates/partial/user_menu.blade.php';
        $cacheRoot = $install . '/var/cache/blade';

        $this->writeFile($legacyOverride, "@include('partial.project_menu')\n");
        $this->writeFile($cacheRoot . '/default/stale.php', 'compiled legacy menu');
        $this->writeFile(
            $install . '/config.json',
            json_encode(['main' => ['theme' => 'default', 'language' => 'es', 'timezone' => 'Europe/Madrid']]) ?: '{}'
        );
        $protected = [
            '.env' => 'secret',
            '.htaccess' => 'root rules',
            'Content/page.md' => 'user content',
            'storage/private.dat' => 'stored data',
            'var/private.dat' => 'runtime data',
            'public_html/.htaccess' => 'public rules',
            'public_html/uploads/photo.txt' => 'upload',
            'public_html/themes/custom/css/custom.css' => 'custom theme',
        ];
        foreach ($protected as $relative => $contents) {
            $this->writeFile($install . '/' . $relative, $contents);
        }
        $this->buildReleaseFixture($release);

        $result = (new ReleaseInstaller())->install($release, $install);

        self::assertSame(1, $result['removed']);
        self::assertFileDoesNotExist($legacyOverride);
        self::assertDirectoryDoesNotExist($cacheRoot);
        self::assertFileExists($install . '/vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php');

        foreach ($protected as $relative => $contents) {
            self::assertSame($contents, file_get_contents($install . '/' . $relative));
        }

        $frameworkRoot = $install . '/vendor/alxarafe/alxarafe/templates';
        self::assertSame(
            realpath($frameworkRoot . '/partial/user_menu.blade.php'),
            $this->resolveTemplate([$install . '/templates', $frameworkRoot], 'partial.user_menu')
        );

        define('APP_PATH', $install);
        define('BASE_PATH', $install . '/public_html');
        define('ALX_PATH', $install . '/vendor/alxarafe/alxarafe');
        Config::getConfig(true);
        $_COOKIE = [];
        $_SERVER['QUERY_STRING'] = '';
        Auth::$user = null;

        $blade = new Template('partial.user_menu');
        $blade->addPath($install . '/templates');
        $blade->addPath($frameworkRoot);
        $me = new class {
            public string $backUrl = '';
            public function _(string $message): string
            {
                return $message;
            }
        };
        $data = ['me' => $me, 'title' => 'Prueba', 'user_menu' => []];

        $coldHtml = $blade->render(null, $data);
        $warmHtml = $blade->render(null, $data);
        foreach ([$coldHtml, $warmHtml] as $html) {
            self::assertStringContainsString('id="clock-display"', $html);
            self::assertStringContainsString('controller=Auth', $html);
            self::assertStringContainsString('select_language', $html);
            self::assertStringContainsString('select_theme', $html);
        }

        $user = new User();
        $user->name = 'Tester';
        Auth::$user = $user;
        $authenticatedHtml = $blade->render(null, $data);
        self::assertStringContainsString('id="navbarUser"', $authenticatedHtml);
        self::assertStringContainsString('Tester', $authenticatedHtml);
    }

    private function buildReleaseFixture(string $release): void
    {
        $project = dirname(__DIR__, 2);
        $this->writeFile($release . '/composer.lock', (string) file_get_contents($project . '/composer.lock'));
        $installed = [
            'packages' => [[
                'name' => 'alxarafe/alxarafe',
                'version' => 'v0.6.11',
                'source' => ['reference' => '4b5a6252750537280c04aa4378b3fcf2570f8efb'],
            ]],
        ];
        $this->writeFile(
            $release . '/vendor/composer/installed.json',
            json_encode($installed, JSON_PRETTY_PRINT) ?: '{}'
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
            '/* framework asset */'
        );
        $this->writeFile(
            $release . '/templates/partial/project_menu.blade.php',
            '<nav data-project-menu="true">Chascarrillo</nav>'
        );
        $this->writeFile($release . '/public_html/index.php', '<?php // fixture');

        ManagedFileManifest::write($release, ManagedFileManifest::generate($release, 'v-test'));
    }

    /** @param list<string> $paths */
    private function resolveTemplate(array $paths, string $name): string|false
    {
        $relative = str_replace('.', '/', $name) . '.blade.php';
        foreach ($paths as $path) {
            if (is_file($path . '/' . $relative)) {
                return realpath($path . '/' . $relative);
            }
        }
        return false;
    }

    private function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            self::assertTrue(mkdir($directory, 0755, true));
        }
        self::assertNotFalse(file_put_contents($path, $contents));
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $child = $path . '/' . $entry;
            if (is_dir($child) && !is_link($child)) {
                $this->removeTree($child);
            } else {
                unlink($child);
            }
        }
        rmdir($path);
    }
}

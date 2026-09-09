<?php

namespace Tests\Feature;

use Alxarafe\Infrastructure\Auth\Auth;
use Alxarafe\Infrastructure\Persistence\Config;
use Alxarafe\Infrastructure\Persistence\Template;
use Modules\Admin\Model\User;
use Modules\Chascarrillo\Service\ManagedFileManifest;
use Modules\Chascarrillo\Service\ReleaseInstallationConflictException;
use Modules\Chascarrillo\Service\ReleaseInstaller;
use Modules\Chascarrillo\Service\ReleaseUpdateCoordinator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class UpdateRemovesObsoleteUserMenuTest extends TestCase
{
    private const LEGACY_MENU_HASH = '641721c80f976cf66d9b52e46b83fac04423003e237b8ae4ca24b1eb51c2fe65';
    private const LEGACY_BODY_HASH = '7d153b352d1b3f5eeb2412172979d0e84831cc68a5e407198eea3354acc7d9dc';

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
    public function testUpdateRemovesLegacyOverridesClearsCacheAndRendersFrameworkMenu(): void
    {
        $install = $this->workspace . '/install';
        $release = $this->workspace . '/release';
        $legacyOverride = $install . '/templates/partial/user_menu.blade.php';
        $cacheRoot = $install . '/var/cache/blade';

        $this->writeFile($legacyOverride, "@include('partial.project_menu')\n");
        foreach ($this->legacyProductionOverrides() as $relative => $contents) {
            $this->writeFile($install . '/' . $relative, $contents);
        }
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

        self::assertSame(6, $result['removed']);
        self::assertFileDoesNotExist($legacyOverride);
        foreach (array_keys($this->legacyProductionOverrides()) as $relative) {
            self::assertFileDoesNotExist($install . '/' . $relative);
        }
        self::assertDirectoryDoesNotExist($cacheRoot);
        self::assertFileExists($install . '/vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php');

        $themeSwitcher = $install . '/templates/partial/theme_switcher.blade.php';
        self::assertFileExists($themeSwitcher);
        self::assertSame(
            'e411a2edd320c881c9ab379917a44c9ce5c3de47acf2e2b9e5221496968e25aa',
            hash_file('sha256', $themeSwitcher)
        );
        $selector = (string) file_get_contents($themeSwitcher);
        self::assertStringContainsString('module=Chascarrillo&controller=Theme&action=switch', $selector);
        self::assertStringNotContainsString('module=Admin&controller=Auth&action=setTheme', $selector);
        $installedManifest = ManagedFileManifest::load($install);
        self::assertSame(
            hash_file('sha256', $themeSwitcher),
            $installedManifest['files']['templates/partial/theme_switcher.blade.php']
        );

        foreach ($protected as $relative => $contents) {
            self::assertSame($contents, file_get_contents($install . '/' . $relative));
        }

        $frameworkRoot = $install . '/vendor/alxarafe/alxarafe/templates';
        self::assertSame(
            realpath($frameworkRoot . '/partial/user_menu.blade.php'),
            $this->resolveTemplate([$install . '/templates', $frameworkRoot], 'partial.user_menu')
        );
        self::assertSame(
            realpath($frameworkRoot . '/partial/body_standard.blade.php'),
            $this->resolveTemplate([$install . '/templates', $frameworkRoot], 'partial.body_standard')
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
            self::assertStringContainsString('module=Chascarrillo&controller=Theme&action=switch', $html);
        }

        $user = new User();
        $user->name = 'Tester';
        Auth::$user = $user;
        $authenticatedHtml = $blade->render(null, $data);
        self::assertStringContainsString('id="navbarUser"', $authenticatedHtml);
        self::assertStringContainsString('Tester', $authenticatedHtml);

        $second = (new ReleaseInstaller())->install($release, $install);
        self::assertSame(0, $second['copied']);
        self::assertSame(0, $second['removed']);
        self::assertSame(
            'e411a2edd320c881c9ab379917a44c9ce5c3de47acf2e2b9e5221496968e25aa',
            hash_file('sha256', $themeSwitcher)
        );
    }

    public function testModifiedLegacyOverrideAbortsBeforeEveryMutation(): void
    {
        $install = $this->workspace . '/conflict-install';
        $release = $this->workspace . '/conflict-release';
        $overrides = $this->legacyProductionOverrides();
        $modified = 'templates/themes/cyberpunk/partial/user_menu.blade.php';
        $overrides[$modified] = "@include('partial.project_menu')\n{{-- local customization --}}\n";
        foreach ($overrides as $relative => $contents) {
            $this->writeFile($install . '/' . $relative, $contents);
        }
        $cache = $install . '/var/cache/blade/default/stale.php';
        $this->writeFile($cache, 'compiled witness');
        $this->buildReleaseFixture($release);
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
            self::fail('La personalización local debía abortar la actualización');
        } catch (ReleaseInstallationConflictException $exception) {
            self::assertStringContainsString($modified, $exception->getMessage());
            self::assertStringContainsString('obsolete_managed_file_modified', $exception->getMessage());
        }

        foreach ($overrides as $relative => $contents) {
            self::assertSame($contents, file_get_contents($install . '/' . $relative));
        }
        self::assertSame(0, $migrations);
        self::assertSame('compiled witness', file_get_contents($cache));
        self::assertFileDoesNotExist($install . '/composer.lock');
        self::assertFileDoesNotExist($install . '/templates/partial/theme_switcher.blade.php');
        self::assertFileDoesNotExist($install . '/public_html/themes/default/css/default.css');
        self::assertFileDoesNotExist($install . '/' . ManagedFileManifest::FILENAME);
    }

    public function testMissingLegacyOverridesDoNotCauseAnError(): void
    {
        $install = $this->workspace . '/missing-install';
        $release = $this->workspace . '/missing-release';
        $overrides = $this->legacyProductionOverrides();
        $present = 'templates/themes/high-contrast/partial/user_menu.blade.php';
        $this->writeFile($install . '/' . $present, $overrides[$present]);
        $this->buildReleaseFixture($release);

        $result = (new ReleaseInstaller())->install($release, $install);

        self::assertSame(1, $result['removed']);
        self::assertFileDoesNotExist($install . '/' . $present);
    }

    public function testLegacyOverrideSymlinkAndUnexpectedTypeAbortSafely(): void
    {
        $install = $this->workspace . '/unsafe-install';
        $release = $this->workspace . '/unsafe-release';
        $outside = $this->workspace . '/outside-witness.blade.php';
        $symlink = 'templates/themes/high-contrast/partial/user_menu.blade.php';
        $directory = 'templates/partial/body_standard.blade.php';
        $this->writeFile($outside, 'outside witness');
        self::assertTrue(mkdir(dirname($install . '/' . $symlink), 0755, true));
        self::assertTrue(symlink($outside, $install . '/' . $symlink));
        self::assertTrue(mkdir($install . '/' . $directory, 0755, true));
        $this->buildReleaseFixture($release);

        try {
            (new ReleaseInstaller())->install($release, $install);
            self::fail('Los nodos inseguros debían abortar la actualización');
        } catch (ReleaseInstallationConflictException $exception) {
            self::assertStringContainsString($symlink, $exception->getMessage());
            self::assertStringContainsString($directory, $exception->getMessage());
            self::assertStringContainsString('symbolic_link', $exception->getMessage());
            self::assertStringContainsString('unexpected_node_type', $exception->getMessage());
        }

        self::assertTrue(is_link($install . '/' . $symlink));
        self::assertDirectoryExists($install . '/' . $directory);
        self::assertSame('outside witness', file_get_contents($outside));
        self::assertFileDoesNotExist($install . '/composer.lock');
    }

    private function buildReleaseFixture(string $release): void
    {
        $project = dirname(__DIR__, 2);
        $this->writeFile($release . '/composer.lock', (string) file_get_contents($project . '/composer.lock'));
        $this->writeFile(
            $release . '/Modules/Chascarrillo/Service/UpdateService.php',
            (string) file_get_contents($project . '/Modules/Chascarrillo/Service/UpdateService.php')
        );
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
        $this->writeFile(
            $release . '/templates/partial/theme_switcher.blade.php',
            (string) file_get_contents($project . '/templates/partial/theme_switcher.blade.php')
        );
        $this->writeFile($release . '/public_html/index.php', '<?php // fixture');

        ManagedFileManifest::write($release, ManagedFileManifest::generate($release));
    }

    /** @return array<string,string> */
    private function legacyProductionOverrides(): array
    {
        $menu = "@include('partial.project_menu')\n";
        $body = implode("\n", [
            '@php',
            '    $hasSidebar = \\Alxarafe\\Infrastructure\\Auth\\Auth::isLogged() && !empty($main_menu);',
            '@endphp',
            '<div id="id_container" class="id_container {{ $hasSidebar ? \'has-sidebar\' : \'no-sidebar\' }}">',
            '    ',
            '    @if($hasSidebar)',
            "        @include('partial.main_menu')",
            '    @endif',
            '',
            '        <div id="id-right">',
            "            @include('partial.project_menu')",
            '            ',
            '            <div class="container-fluid mt-3 px-4">',
            '                 @if(!isset($hide_page_title) || !$hide_page_title)',
            '                 <div class="d-flex justify-content-between align-items-center mb-3">',
            '                    <h2 class="h4 mb-0 fw-bold">{!! $me->title !!}</h2>',
            '                    <div class="d-flex gap-2">',
            "                        @yield('header_actions')",
            '                    </div>',
            '                 </div>',
            '                 @endif',
            "                 @include('partial.alerts')",
            '            </div>',
            '',
            "            @yield('content')",
            '        </div>',
            '</div>',
            '',
            '',
        ]);

        self::assertSame(self::LEGACY_MENU_HASH, hash('sha256', $menu));
        self::assertSame(self::LEGACY_BODY_HASH, hash('sha256', $body));

        return [
            'templates/themes/high-contrast/partial/user_menu.blade.php' => $menu,
            'templates/themes/alternative/partial/user_menu.blade.php' => $menu,
            'templates/themes/alxarafe/partial/user_menu.blade.php' => $menu,
            'templates/themes/cyberpunk/partial/user_menu.blade.php' => $menu,
            'templates/partial/body_standard.blade.php' => $body,
        ];
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

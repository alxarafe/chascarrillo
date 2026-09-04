<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ThemeSwitchingContractTest extends TestCase
{
    public function testBootstrapRetainsTheLegacyThemeOverrideConsumedByTheApp(): void
    {
        $bootstrap = file_get_contents(__DIR__ . '/../../public_html/index.php');

        self::assertNotFalse($bootstrap);
        self::assertStringContainsString(
            "\$_SESSION['alx_theme_test'] ?? \$_COOKIE['alx_theme_test']",
            $bootstrap
        );
        self::assertStringContainsString("define('THEME_SKIN', \$config->main->theme);", $bootstrap);
    }

    public function testThemeControllerAcceptsOnlyPublishedThemes(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../Modules/Chascarrillo/Controller/ThemeController.php');

        self::assertNotFalse($controller);
        self::assertStringContainsString("\$themes = Functions::getThemes();", $controller);
        self::assertStringContainsString("array_key_exists(\$theme, \$themes)", $controller);
        self::assertStringContainsString("\$theme = 'default';", $controller);
    }

    public function testThemeControllerSynchronizesSessionAndSecureCookieContracts(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../Modules/Chascarrillo/Controller/ThemeController.php');

        self::assertNotFalse($controller);
        self::assertStringContainsString("\$_SESSION['alx_theme_test'] = \$theme;", $controller);
        self::assertStringContainsString("setcookie('alx_theme_test', \$theme", $controller);
        self::assertStringContainsString("setcookie('alx_theme', \$theme", $controller);
        self::assertStringContainsString("'secure' => \$isHttps", $controller);
        self::assertStringContainsString("'httponly' => true", $controller);
        self::assertStringContainsString("'samesite' => 'Lax'", $controller);
    }

    public function testDefaultAndCyberpunkSelectorsUseTheSameApplicationEndpoint(): void
    {
        $defaultSelector = file_get_contents(__DIR__ . '/../../templates/partial/theme_switcher.blade.php');
        $cyberpunkSelector = file_get_contents(__DIR__ . '/../../templates/themes/cyberpunk/partial/theme_switcher.blade.php');

        self::assertNotFalse($defaultSelector);
        self::assertNotFalse($cyberpunkSelector);
        foreach ([$defaultSelector, $cyberpunkSelector] as $selector) {
            self::assertStringContainsString('module=Chascarrillo&controller=Theme&action=switch', $selector);
        }
        self::assertStringContainsString("\$_COOKIE['alx_theme']", $cyberpunkSelector);
    }
}

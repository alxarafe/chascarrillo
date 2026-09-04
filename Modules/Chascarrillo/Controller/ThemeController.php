<?php

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Infrastructure\Http\Controller\GenericPublicController;
use Alxarafe\Infrastructure\Lib\Functions;

class ThemeController extends GenericPublicController
{
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    
    public static function getControllerName(): string
    {
        return 'Theme';
    }

    public function doSwitch(): bool
    {
        $theme = filter_input(INPUT_GET, 'id', FILTER_UNSAFE_RAW);
        $themes = Functions::getThemes();
        if (!is_string($theme) || !array_key_exists($theme, $themes)) {
            $theme = 'default';
        }

        // Save in session for immediate persistence without relying solely on cookies
        $_SESSION['alx_theme_test'] = $theme;

        // If the user is logged in, save preference to the database
        if (\Alxarafe\Infrastructure\Auth\Auth::isLogged()) {
            $user = \Alxarafe\Infrastructure\Auth\Auth::$user;
            $user->theme = $theme;
            $user->save();
        }

        // Keep the legacy override and the framework cookie aligned for guests.
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $cookieOptions = [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie('alx_theme_test', $theme, $cookieOptions);
        setcookie('alx_theme', $theme, $cookieOptions);

        // Prepare redirection: Avoid loops if referer is the switch action itself
        $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
        if (str_contains($referer, 'action=switch')) {
            $referer = BASE_URL;
        }

        // Explicitly save session before redirecting to avoid persistence issues
        session_write_close();

        Functions::httpRedirect($referer);

        return true;
    }
}

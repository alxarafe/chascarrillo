<?php

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Base\Controller\GenericPublicController;
use Alxarafe\Lib\Functions;

class ThemeController extends GenericPublicController
{
    #[\Override]
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    #[\Override]
    public static function getControllerName(): string
    {
        return 'Theme';
    }

    public function doSwitch(): bool
    {
        $theme = $_GET['id'] ?? 'chascarrillo';

        // Save in session for immediate persistence without relying solely on cookies
        $_SESSION['alx_theme_test'] = $theme;

        // If the user is logged in, save preference to the database
        if (\Alxarafe\Lib\Auth::isLogged()) {
            $user = \Alxarafe\Lib\Auth::$user;
            $user->theme = $theme;
            $user->save();
        }

        // We also use a cookie for persistence for guests or redundancy
        // When cookie consent is implemented, we will use this line instead of the session above.
        // setcookie('alx_theme_test', $theme, time() + (86400 * 30), '/'); // 30 days

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

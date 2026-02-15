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
        $theme = $_GET['id'] ?? 'alxarafe';

        // No necesitamos sesión compleja, usaremos una cookie para máxima compatibilidad
        setcookie('alx_theme_test', $theme, time() + (86400 * 30), '/'); // 30 días

        // Volver a la página anterior o a la home
        $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        Functions::httpRedirect($referer);

        return true;
    }
}

<?php

namespace Modules\FrameworkTest\Controller;

use Alxarafe\Base\Controller\GenericPublicController;
use Alxarafe\Attribute\Menu;

#[Menu(
    menu: 'main_menu',
    label: 'Prueba Framework',
    icon: 'fas fa-vial',
    order: 10,
    visibility: 'public'
)]
class TestController extends GenericPublicController
{
    public static function getModuleName(): string
    {
        return 'FrameworkTest';
    }

    public static function getControllerName(): string
    {
        return 'Test';
    }

    public function doIndex(): bool
    {
        $this->title = 'Framework Test Page';

        $this->addVariable('server_info', $_SERVER);
        $this->addVariable('php_version', PHP_VERSION);

        return true;
    }
}

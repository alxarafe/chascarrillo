<?php

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Infrastructure\Http\Controller\Controller;
use Modules\Chascarrillo\Service\UpdateService;
use Alxarafe\Infrastructure\Attribute\Menu;
use Alxarafe\Infrastructure\Lib\Functions;

#[Menu(
    menu: 'main_menu',
    label: 'Actualización',
    icon: 'fas fa-sync-alt',
    order: 100,
    permission: 'Chascarrillo.Maintenance.doIndex'
)]
#[Menu(
    menu: 'header_user',
    label: 'Actualizar Sistema',
    icon: 'fas fa-sync-alt',
    order: 50,
    permission: 'Chascarrillo.Maintenance.doIndex'
)]
class MaintenanceController extends Controller
{
    public function doIndex(): bool
    {
        $this->addVariable('currentVersion', UpdateService::VERSION);
        $this->addVariable('updateInfo', UpdateService::checkUpdate());

        $this->setDefaultTemplate('page/chascarrillo/maintenance/index');
        return true;
    }

    public function doUpdate(): bool
    {
        $updateInfo = UpdateService::checkUpdate();
        if ($updateInfo && isset($updateInfo['zipball_url'])) {
            if (UpdateService::applyUpdate($updateInfo['zipball_url'])) {
                Functions::httpRedirect(static::url());
            }
        } else {
            \Alxarafe\Infrastructure\Lib\Messages::addError("No hay actualizaciones disponibles.");
        }

        Functions::httpRedirect('index.php?module=Chascarrillo&controller=Maintenance');
        return true;
    }
}

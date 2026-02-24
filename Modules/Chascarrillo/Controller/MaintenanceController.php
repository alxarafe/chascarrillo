<?php

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Base\Controller\Controller;
use Modules\Chascarrillo\Service\UpdateService;
use Alxarafe\Attribute\Menu;
use Alxarafe\Lib\Functions;

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
            \Alxarafe\Lib\Messages::addError("No hay actualizaciones disponibles.");
        }

        Functions::httpRedirect('index.php?module=Chascarrillo&controller=Maintenance');
        return true;
    }
}

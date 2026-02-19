<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Base\Config;
use Alxarafe\Base\Controller\ResourceController;
use Alxarafe\Lib\Trans;
use Alxarafe\Attribute\Menu;
use Alxarafe\Component\Fields\RelationList;
use Alxarafe\Component\Fields\Text;
use stdClass;

#[Menu(
    menu: 'admin_sidebar',
    parent: 'config',
    label: 'Worldsites',
    icon: 'fas fa-globe',
    order: 95,
    permission: 'Admin.Config.doIndex'
)]
class WorldsitesController extends ResourceController
{
    #[\Override]
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    #[\Override]
    public static function getControllerName(): string
    {
        return 'Worldsites';
    }

    #[\Override]
    protected function getModelClass(): string
    {
        return 'Config';
    }

    #[\Override]
    protected function detectMode()
    {
        $this->mode = self::MODE_EDIT;
        $this->recordId = 'current';
    }

    #[\Override]
    protected function getEditFields(): array
    {
        return [
            'config_general' => [
                new \Alxarafe\Component\Fields\Boolean('main.enableWorldsites', 'Activar sugerencias por localización'),
            ],
            'sites' => new RelationList('sites', 'Sitios Regionales (Worldsites)', [
                ['field' => 'lang', 'label' => 'Cód. Idioma (es, en...)', 'type' => 'text'],
                ['field' => 'domain', 'label' => 'Dominio (ej: misitio.es)', 'type' => 'text'],
                ['field' => 'message', 'label' => 'Mensaje de sugerencia', 'type' => 'text'],
            ]),
        ];
    }

    #[\Override]
    protected function fetchRecordData(): array
    {
        $config = Config::getConfig(true);
        $sitesMap = (array)($config->sites ?? []);

        $sitesList = [];
        foreach ($sitesMap as $lang => $data) {
            $data = (array)$data;
            $sitesList[] = [
                'lang' => $lang,
                'domain' => $data['domain'] ?? '',
                'message' => $data['message'] ?? '',
            ];
        }

        return [
            'id' => 'current',
            'data' => [
                'sites' => $sitesList
            ],
            'meta' => [
                'model' => 'Worldsites'
            ]
        ];
    }

    #[\Override]
    protected function saveRecord()
    {
        $data = $_POST['data'] ?? [];
        $sitesList = $data['sites'] ?? [];
        $enableWorldsites = ($data['main.enableWorldsites'] ?? '0') === '1';

        $sitesMap = new stdClass();
        foreach ($sitesList as $site) {
            $lang = $site['lang'] ?? '';
            if (empty($lang)) continue;

            $sitesMap->$lang = new stdClass();
            $sitesMap->$lang->domain = $site['domain'] ?? '';
            $sitesMap->$lang->message = $site['message'] ?? '';
        }

        $configData = Config::getConfig(true) ?? new stdClass();
        $configData->sites = $sitesMap;
        if (!isset($configData->main)) {
            $configData->main = new stdClass();
        }
        $configData->main->enableWorldsites = $enableWorldsites;

        if (Config::setConfig($configData) && Config::saveConfig()) {
            $this->jsonResponse([
                'status' => 'success',
                'message' => Trans::_('settings_saved_successfully')
            ]);
        } else {
            $this->jsonResponse(['status' => 'error', 'error' => Trans::_('error_saving_settings')]);
        }
    }
}

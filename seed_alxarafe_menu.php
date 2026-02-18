<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Alxarafe\Base\Config;
use Modules\Chascarrillo\Model\Menu;
use Modules\Chascarrillo\Model\MenuItem;

// Initialize Config and DB
$config = Config::getConfig();
if (isset($config->db)) {
    \Alxarafe\Base\Database::createConnection($config->db);
}

echo "--- Seeding Alxarafe.es Menu ---\n";

// Use slug logic to find or create the menu
$mainMenu = Menu::updateOrCreate(['slug' => 'header-menu'], ['name' => 'Menú Principal']);

// Clear existing items if any to avoid duplicates during migration tests
MenuItem::where('menu_id', $mainMenu->id)->delete();

$items = [
    ['label' => 'Inicio', 'url' => '/', 'order' => 1],
    ['label' => 'Framework', 'url' => '/el-framework-alxarafe', 'order' => 2],
    ['label' => 'Manifiesto IA', 'url' => '/manifiesto-ia', 'order' => 3],
    ['label' => 'Laboratorio', 'url' => '/blog', 'order' => 4],
    ['label' => 'Documentación', 'url' => '#', 'order' => 5, 'children' => [
        ['label' => 'Manual (ES)', 'url' => 'https://docs.alxarafe.com/es', 'order' => 1],
        ['label' => 'Manual (EN)', 'url' => 'https://docs.alxarafe.com/en', 'order' => 2],
    ]],
];

foreach ($items as $data) {
    $children = $data['children'] ?? [];
    unset($data['children']);

    $data['menu_id'] = $mainMenu->id;
    $parent = MenuItem::create($data);

    foreach ($children as $childData) {
        $childData['menu_id'] = $mainMenu->id;
        $childData['parent_id'] = $parent->id;
        MenuItem::create($childData);
    }
}

echo "Menu 'header-menu' created with " . count($items) . " main items.\n";

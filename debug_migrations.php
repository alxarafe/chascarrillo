<?php
define('BASE_PATH', __DIR__ . '/public');
require_once __DIR__ . '/vendor/autoload.php';

use Alxarafe\Base\Config;
use Alxarafe\Lib\Routes;
use Alxarafe\Base\Database;
use CoreModules\Admin\Model\Migration;

// Mock environment
$config = Config::getConfig();
if ($config && isset($config->db)) {
    Database::createConnection($config->db);
}

echo "Base Path: " . BASE_PATH . "\n";
echo "Config DB Name: " . ($config->db->name ?? 'N/A') . "\n";

echo "--- Discovered Migrations ---\n";
$migrations = Config::getMigrations();
print_r($migrations);

echo "\n--- Executed Migrations (in DB) ---\n";
try {
    $ran = Migration::all()->toArray();
    print_r($ran);
} catch (\Exception $e) {
    echo "Error fetching migrations: " . $e->getMessage() . "\n";
}

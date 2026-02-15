<?php
define('BASE_PATH', __DIR__ . '/public');
require_once __DIR__ . '/vendor/autoload.php';

use Alxarafe\Base\Config;
use Alxarafe\Lib\Messages;
use Alxarafe\Lib\Routes;

// Initialize Config
$config = Config::getConfig();
if (!$config) {
    die("CRITICAL: Configuration file not loaded.\n");
}

// Initialize Database Connection
if (isset($config->db)) {
    \Alxarafe\Base\Database::createConnection($config->db);
}

echo "--- Run Seeders ---\n";

// Mock environment for Routes to find paths relative to skeleton/public
if (!defined('ALX_PATH')) {
    define('ALX_PATH', realpath(__DIR__ . '/../../'));
}

// Ensure Routes knows where to look if running from CLI skeleton root
// The default search_routes in Routes.php assumes BASE_PATH is public/
// And looks in ../Modules/ and ../vendor/alxarafe/alxarafe/src/Modules/

// Override Config::runSeeders logic to debug why it might fail or not run
$routes = Routes::getAllRoutes();
$seeders = $routes['Seeders'] ?? [];

echo "Found Seeders:\n";
print_r($seeders);

echo "\nExecuting Seeders...\n";
if (Config::runSeeders()) {
    echo "SUCCESS: Seeders executed (if any).\n";
} else {
    echo "ERROR: Seeder execution failed.\n";
    print_r(Messages::getErrors());
}

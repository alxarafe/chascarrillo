<?php
define('BASE_PATH', __DIR__ . '/public');
require_once __DIR__ . '/vendor/autoload.php';
$config = \Alxarafe\Base\Config::getConfig();
if (!$config) {
    // Try manual load
    $config = json_decode(file_get_contents(__DIR__ . '/config.json'));
}
\Alxarafe\Base\Database::createConnection($config->db);
echo "Seeding post...\n";
$seeder = new \Modules\Chascarrillo\Seeders\PostSeeder();
echo "Done.\n";

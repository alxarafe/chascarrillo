<?php
define('BASE_PATH', __DIR__ . '/public_html');
define('APP_PATH', __DIR__);
require_once __DIR__ . '/vendor/autoload.php';

use Alxarafe\Infrastructure\Persistence\Config;
use Alxarafe\Infrastructure\Lib\Messages;

echo "--- Starting Migration Process ---\n";

// Initialize Config
$config = Config::getConfig();
if (!$config) {
    die("CRITICAL: Configuration file not loaded.\n");
}

echo "Database: " . ($config->db->name ?? 'Unknown') . "\n";

// Run Migrations
if (Config::doRunMigrations()) {
    echo "SUCCESS: Migrations executed.\n";
} else {
    echo "ERROR: Migration failed.\n";
    print_r(Messages::getMessages());
}

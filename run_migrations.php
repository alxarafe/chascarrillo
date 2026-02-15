<?php
define('BASE_PATH', __DIR__ . '/public');
require_once __DIR__ . '/vendor/autoload.php';

use Alxarafe\Base\Config;
use Alxarafe\Lib\Messages;

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
    print_r(Messages::getErrors());
}

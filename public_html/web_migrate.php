<?php

/**
 * Chascarrillo Web Migration Tool
 * 
 * This script allows running database migrations via browser.
 * IMPORTANT: Delete this file after use for security!
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Alxarafe\Base\Config;
use Alxarafe\Lib\Messages;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Simple security check: Only allow if a secret key matches (optional)
// Or just inform the user to delete it.

define('BASE_PATH', __DIR__);
define('APP_PATH', realpath(__DIR__ . '/../'));

// Initialize Config
$config = Config::getConfig();

echo "<html><head><title>Chascarrillo Migration Tool</title>";
echo "<style>body{font-family:sans-serif; padding:20px; line-height:1.6; background:#f4f4f4;} .container{background:white; padding:20px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1); max-width:800px; margin:auto;} h1{color:#333;} .success{color:green; font-weight:bold;} .error{color:red; font-weight:bold;} pre{background:#eee; padding:10px; border-radius:4px; overflow-x:auto;}</style>";
echo "</head><body><div class='container'>";
echo "<h1>Migration Tool</h1>";

if (!$config) {
    echo "<p class='error'>CRITICAL: Configuration file not loaded.</p>";
    die();
}

echo "<p><strong>Database:</strong> " . htmlspecialchars($config->db->name ?? 'Unknown') . "</p>";

if (isset($_POST['run_migration'])) {
    echo "<h2>Execution Results:</h2>";
    echo "<pre>";

    // Alxarafe's Migration system
    ob_start();
    $result = Config::doRunMigrations();
    $output = ob_get_clean();

    echo htmlspecialchars($output);

    if ($result) {
        echo "\n<span class='success'>SUCCESS: Migrations executed successfully.</span>";
    } else {
        echo "\n<span class='error'>ERROR: Migration failed.</span>\n";
        print_r(Messages::getMessages());
    }
    echo "</pre>";
    echo "<p><a href='web_migrate.php'>&laquo; Back</a></p>";
} else {
    echo "<p>Click the button below to run database migrations. This will check all modules for pending SQL changes.</p>";
    echo "<form method='POST'><input type='submit' name='run_migration' value='Run Migrations' style='padding:10px 20px; background:#007bff; color:white; border:none; border-radius:4px; cursor:pointer;'></form>";
    echo "<p style='color:#666; font-size:0.8em;'>Note: This script uses the configuration defined in your config.json file.</p>";
}

echo "<hr><p style='color:red;'><strong>WARNING:</strong> Remember to delete this file (<code>public_html/web_migrate.php</code>) from your server once you have finished the migration.</p>";
echo "</div></body></html>";

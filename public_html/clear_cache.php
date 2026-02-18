<?php

/**
 * Chascarrillo Cache Clear Tool
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Alxarafe\Base\Config;
use Alxarafe\Lib\Messages;

define('BASE_PATH', __DIR__);
define('APP_PATH', realpath(__DIR__ . '/../'));

$config = Config::getConfig();

echo "<html><head><title>Chascarrillo Cache Tool</title>";
echo "<style>body{font-family:sans-serif; padding:20px; background:#f4f4f4;} .container{background:white; padding:20px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1); max-width:600px; margin:auto;} .button{padding:10px 20px; background:#28a745; color:white; border:none; border-radius:4px; cursor:pointer; text-decoration:none; display:inline-block;}</style>";
echo "</head><body><div class='container'><h1>Cache Management</h1>";

if (isset($_POST['clear_cache'])) {

    $tmpDir = APP_PATH . '/tmp';
    $files = glob($tmpDir . '/*');
    $count = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $count++;
        } elseif (is_dir($file) && !in_array(basename($file), ['.', '..'])) {
            // Simple recursive delete for subdirs if needed
            // For now just files in tmp
        }
    }

    echo "<p class='success'>Cleared $count files from tmp directory.</p>";
    echo "<p><a href='clear_cache.php'>&laquo; Back</a></p>";
} else {
    echo "<p>Use this tool to clear the application cache (contents of the <code>tmp/</code> folder).</p>";
    echo "<form method='POST'><input type='submit' name='clear_cache' value='Clear Cache' class='button'></form>";
}

echo "</div></body></html>";

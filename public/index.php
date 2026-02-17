<?php

require __DIR__ . '/../vendor/autoload.php';

use Alxarafe\Tools\Dispatcher\WebDispatcher;
use Alxarafe\Tools\Dispatcher\ApiDispatcher;
use Alxarafe\Base\Config;
use Alxarafe\Lib\Trans;
use Alxarafe\Tools\Debug;

// Load Configuration first to get base settings
define('APP_PATH', realpath(__DIR__ . '/../'));
define('BASE_PATH', __DIR__);
define('ALX_PATH', APP_PATH . '/vendor/alxarafe/alxarafe');

$config = Config::getConfig();

// Determine BASE_URL
if (!defined('BASE_URL')) {
    $baseUrl = $config->main->url ?? null;
    if (!$baseUrl && isset($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $baseUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}";
    }
    define('BASE_URL', rtrim($baseUrl ?? 'http://localhost', '/'));
}

// Temporary App Branding (User Request)
if ($config && isset($config->main)) {
    $config->main->appName = 'Alxarafe';
    $config->main->appIcon = 'fas fa-cubes';

    // Theme Override for Testing
    if (isset($_COOKIE['alx_theme_test'])) {
        $config->main->theme = $_COOKIE['alx_theme_test'];
    }
}

// Bootstrapping
Debug::initialize();
Trans::initialize();
class_alias(\Illuminate\Support\Str::class, 'Str');

if ($config && isset($config->main->language)) {
    Trans::setLang($config->main->language);
}

// Check if assets are installed. If not, try to install them.
// This is useful for Docker environments where volumes might obscure built assets.
if (!is_dir(__DIR__ . '/themes') || !is_dir(__DIR__ . '/css')) {
    // Only attempt in specific environments to avoid performance hit on prod?
    // For now, check if we are in dev/local.
    if (class_exists(\Alxarafe\Scripts\ComposerScripts::class)) {
        // We'll use a mocked IO for runtime execution
        $io = new class {
            public function write($msg)
            {
                error_log("[AssetAutoPublish] " . $msg);
            }
            public function getIO()
            {
                return $this;
            }
        };

        // Wrap in a mock event
        $event = new class($io) {
            private $io;
            public function __construct($io)
            {
                $this->io = $io;
            }
            public function getIO()
            {
                return $this->io;
            }
        };

        error_log("Assets missing. Triggering auto-publication...");
        \Alxarafe\Scripts\ComposerScripts::postUpdate($event);
    }
}

// Load Routes
require_once APP_PATH . '/routes.php';

// Simple Routing for Chascarrillo
if (php_sapi_name() === 'cli') {
    $module = $argv[1] ?? 'Chascarrillo';
    $controller = $argv[2] ?? 'Blog';
    $method = $argv[3] ?? 'index';
} else {
    // Try Router first, but only if no module is explicitly provided in the query string
    $match = !isset($_GET['module']) ? \Alxarafe\Lib\Router::match($_SERVER['REQUEST_URI']) : null;
    if ($match) {
        $module = $match['module'];
        $controller = $match['controller'];
        $method = $match['action'];
        // Merge params into $_GET for transparency
        $_GET = array_merge($_GET, $match['params']);
        $_GET['action'] = $method;
        $_GET['route_name'] = $match['name'];
    } else {
        $module = $_GET['module'] ?? 'Chascarrillo';
        $controller = $_GET['controller'] ?? 'Blog';
        $method = $_GET['method'] ?? 'index';
    }
}

try {
    WebDispatcher::run($module, $controller, $method);
} catch (Throwable $e) {
    if (class_exists(\CoreModules\Admin\Controller\ErrorController::class) && !headers_sent()) {
        $trace = $e->getTraceAsString();
        $url = \CoreModules\Admin\Controller\ErrorController::url(true, false) . '&message=' . urlencode($e->getMessage()) . '&trace=' . urlencode($trace);
        \Alxarafe\Lib\Functions::httpRedirect($url);
    }

    echo "<h1>Error Fatal en la Aplicación</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

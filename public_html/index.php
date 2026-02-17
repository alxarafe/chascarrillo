<?php

require __DIR__ . '/../vendor/autoload.php';

use Alxarafe\Tools\Dispatcher\WebDispatcher;
use Alxarafe\Base\Config;
use Alxarafe\Lib\Trans;

// Step 1: Core Path and Environment definitions
define('APP_PATH', realpath(__DIR__ . '/../'));
define('BASE_PATH', __DIR__);
define('PUBLIC_DIR', basename(BASE_PATH));
define('ALX_PATH', APP_PATH . '/vendor/alxarafe/alxarafe');

$config = Config::getConfig();

// Determine BASE_URL for the app
if (!defined('BASE_URL')) {
    $baseUrl = $config->main->url ?? null;
    if (!$baseUrl && isset($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $baseUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}";
    }
    define('BASE_URL', rtrim($baseUrl ?? 'http://localhost', '/'));
}

class_alias(\Illuminate\Support\Str::class, 'Str');

// Step 2: Custom Multi-domain / Language Logic (App Specific)
if ($config && isset($config->main->language)) {
    $lang = $config->main->language;
    if (class_exists(\Modules\Chascarrillo\Service\DomainService::class)) {
        $currentDomain = \Modules\Chascarrillo\Service\DomainService::getCurrentDomain();
        if (str_contains($currentDomain, 'chascarrillo.com')) {
            $lang = 'en';
        } elseif (str_contains($currentDomain, 'chascarrillo.es')) {
            $lang = 'es';
        }
    }
    Trans::setLang($lang);
}

// Step 3: Global Branding and Testing overrides
if ($config && isset($config->main)) {
    $config->main->appName = 'Alxarafe';
    $config->main->appIcon = 'fas fa-cubes';
    if (isset($_COOKIE['alx_theme_test'])) {
        $config->main->theme = $_COOKIE['alx_theme_test'];
    }
}

// Step 4: Run the Application!
WebDispatcher::dispatch('Chascarrillo', 'Blog', 'index');

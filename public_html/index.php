<?php

session_start();

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

// --- Stability Guardian: If no config exists, redirect to the Installation/Config page ---
if (!$config && (($_GET['controller'] ?? '') !== 'Config')) {
    header('Location: index.php?module=Admin&controller=Config');
    exit;
}

// --- Initial Guardian: Auto-Migration & Health Check ---
if ($config && isset($config->db)) {
    $varDir = APP_PATH . '/var';
    if (!is_dir($varDir)) {
        @mkdir($varDir, 0755, true);
    }

    $appVersion = \Modules\Chascarrillo\Service\UpdateService::VERSION ?? 'unknown';
    $flagFile = $varDir . '/.migrated_' . $appVersion;

    if (!file_exists($flagFile)) {
        // Try to run migrations silently
        if (Config::doRunMigrations()) {
            @touch($flagFile);
        }
    }

    // --- Safety Seeder: Ensure at least one admin exists if the table is empty ---
    try {
        // Initialize connection and get the Capsule instance
        $capsule = \Alxarafe\Base\Database::createConnection($config->db);

        if ($capsule->schema()->hasTable('users')) {
            if (\CoreModules\Admin\Model\User::count() === 0) {
                $admin = new \CoreModules\Admin\Model\User();
                $admin->name = 'admin';
                $admin->email = 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'example.com');
                $admin->password = password_hash('password', PASSWORD_DEFAULT);
                $admin->is_admin = true;
                $admin->save();
            }
        }
    } catch (\Exception $e) {
        // Log error and report if necessary
        @error_log("Guardian Safety Seeder Error: " . $e->getMessage());
    }
}

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
    // Ensure the main module is set for Trans to load its translations
    if (!isset($_GET['module'])) {
        $_GET['module'] = 'Chascarrillo';
    }
    Trans::setLang($lang);
}

// Step 3: Global Branding and Testing overrides
if ($config && isset($config->main)) {
    $config->main->appName = 'Alxarafe';
    $config->main->appIcon = 'fas fa-cubes';

    // Check for theme in session first, then cookie
    $selectedTheme = $_SESSION['alx_theme_test'] ?? $_COOKIE['alx_theme_test'] ?? null;
    if ($selectedTheme) {
        $config->main->theme = $selectedTheme;
    }

    // We define the active theme for the ThemeManager and other framework components
    define('THEME_SKIN', $config->main->theme);
}

// Step 4: Run the Application!
WebDispatcher::dispatch('Chascarrillo', 'Blog', 'index');

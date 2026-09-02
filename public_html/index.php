<?php

session_start();

require __DIR__ . '/../vendor/autoload.php';

// Load Alxarafe Legacy Aliases for Hexagonal Architecture backward compatibility
if (file_exists(__DIR__ . '/../vendor/alxarafe/alxarafe/src/Infrastructure/Legacy/aliases.php')) {
    require_once __DIR__ . '/../vendor/alxarafe/alxarafe/src/Infrastructure/Legacy/aliases.php';
}

use Alxarafe\Infrastructure\Tools\Dispatcher\WebDispatcher;
use Alxarafe\Infrastructure\Persistence\Config;
use Alxarafe\Infrastructure\Lib\Trans;
use Alxarafe\Infrastructure\Tools\Debug;

// Step 1: Core Path and Environment definitions
define('APP_PATH', realpath(__DIR__ . '/../'));
define('BASE_PATH', __DIR__);
define('PUBLIC_DIR', basename(BASE_PATH));
define('ALX_PATH', APP_PATH . '/vendor/alxarafe/alxarafe');

$config = Config::getConfig();

// Determine BASE_URL for the app (needed early by Debug and other components)
if (!defined('BASE_URL')) {
    $baseUrl = $config->main->url ?? null;
    if (!$baseUrl && isset($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ||
            ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? "https" : "http";
        $baseUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}";
    }
    define('BASE_URL', rtrim($baseUrl ?? 'http://localhost', '/'));
}

// Initialize DebugBar
Debug::initialize();

// --- Stability Guardian: If no config exists, redirect to the Installation/Config page ---
if (!$config && (($_GET['controller'] ?? '') !== 'Config')) {
    header('Location: /index.php?module=Admin&controller=Config');
    exit;
}

// --- Initial Guardian: Auto-Migration & Health Check ---
if ($config && isset($config->db)) {
    // 1. Ensure 'var' directory exists for flags
    $varDir = APP_PATH . '/var';
    if (!is_dir($varDir)) {
        @mkdir($varDir, 0755, true);
    }

    $appVersion = \Modules\Chascarrillo\Service\UpdateService::VERSION ?? 'unknown';
    $flagFile = $varDir . '/.migrated_' . $appVersion;

    // 2. Fast Health Check: See if migrations table exists
    $dbIsInitialized = false;
    try {
        $capsule = \Alxarafe\Infrastructure\Persistence\Database::createConnection($config->db);
        $dbIsInitialized = $capsule->schema()->hasTable('migrations');
    } catch (\Exception $e) {
        // The server might be unreachable or database missing. Handled by UI below.
    }

    // 3. Handle Initialization Request (sent from the setup screen)
    if (isset($_POST['alx_initialize_database'])) {
        if (\Alxarafe\Infrastructure\Persistence\Database::createDatabaseIfNotExists($config->db)) {
            if (Config::doRunMigrations()) {
                Config::runSeeders();
                @touch($flagFile);
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            }
        }
    }

    // 4. Evaluate First Run / Pending Migrations
    if (!file_exists($flagFile) || !$dbIsInitialized) {
        $silentSuccess = false;
        // If the database core seems to exist but the flag is just missing (version update)
        // Try to perform a silent migration to update schema
        if ($dbIsInitialized && !file_exists($flagFile)) {
            if (Config::doRunMigrations()) {
                @touch($flagFile);
                $silentSuccess = true;
            }
        }

        // If STILL not initialized (missing 'migrations' table), we must stop and ask
        if (!$dbIsInitialized && !$silentSuccess) {
            $initScreen = __DIR__ . '/alxarafe/initialize_db.php';
            if (file_exists($initScreen)) {
                include $initScreen;
                exit;
            } else {
                // Fallback: If no screen file, try auto-run as a last resort
                if (Config::doRunMigrations()) {
                    @touch($flagFile);
                    Config::runSeeders();
                }
            }
        }
    }

    // --- Safety Seeder: Ensure at least one admin exists if the table is empty ---
    try {
        if ($dbIsInitialized && $capsule->schema()->hasTable('users')) {
            if (\Modules\Admin\Model\User::count() === 0) {
                $admin = new \Modules\Admin\Model\User();
                $admin->name = 'admin';
                $admin->email = 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'example.com');
                $admin->password = password_hash('password', PASSWORD_DEFAULT);
                $admin->is_admin = true;
                $admin->save();
            }
        }
    } catch (\Exception $e) {
        @error_log("Guardian Safety Seeder Error: " . $e->getMessage());
    }
}

class_alias(\Illuminate\Support\Str::class, 'Str');

// Step 2: Custom Multi-domain / Language Logic (App Specific)
if ($config && isset($config->main->language)) {
    $lang = $config->main->language;
    // Simple language detection if requested or needed
    if (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        if (str_ends_with($host, '.com')) {
            $lang = 'en';
        } elseif (str_ends_with($host, '.es')) {
            $lang = 'es';
        }
    }
    Trans::setLang($lang);
}

// Step 3: Global Branding and Testing overrides
if ($config && isset($config->main)) {
    // Default branding if not set
    $config->main->appName ??= 'Chascarrillo';
    $config->main->appIcon ??= 'fas fa-feather-alt';

    // Check for theme in session first, then cookie
    $selectedTheme = $_SESSION['alx_theme_test'] ?? $_COOKIE['alx_theme_test'] ?? null;
    if ($selectedTheme) {
        $config->main->theme = $selectedTheme;
    }

    // We define the active theme for the ThemeManager and other framework components
    define('THEME_SKIN', $config->main->theme);
}

// Step 4: Run the Application!
WebDispatcher::dispatch('Chascarrillo', 'Page', 'show');

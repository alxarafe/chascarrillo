<?php

namespace Modules\Chascarrillo\Service;

use Alxarafe\Base\Config;
use Alxarafe\Lib\Messages;

class UpdateService
{
    public const VERSION = 'v0.6.18';
    public const UPDATE_URL = 'https://api.github.com/repos/alxarafe/chascarrillo/releases/latest';

    /**
     * Check if a new version is available.
     * Returns the latest release data or null if up to date.
     */
    public static function checkUpdate(): ?array
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Chascarrillo-Updater'
                ]
            ]
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents(self::UPDATE_URL, false, $context);

        if ($response === false) {
            return null;
        }

        /** @var array|null $data */
        $data = json_decode($response, true);
        if (is_array($data) && isset($data['tag_name'])) {
            $latest = ltrim($data['tag_name'], 'v');
            $current = ltrim(self::VERSION, 'v');
            if (version_compare($latest, $current, '>')) {
                // Find the deployment ZIP in assets instead of the source code zip
                if (isset($data['assets']) && is_array($data['assets'])) {
                    foreach ($data['assets'] as $asset) {
                        if (str_starts_with($asset['name'], 'chascarrillo-deploy-') && str_ends_with($asset['name'], '.zip')) {
                            $data['zipball_url'] = $asset['browser_download_url'];
                            break;
                        }
                    }
                }
                return $data;
            }
        }

        return null;
    }

    /**
     * Download and apply the update.
     */
    public static function applyUpdate(string $zipUrl): bool
    {
        $tmpZip = sys_get_temp_dir() . '/chascarrillo_update.zip';
        $extractPath = sys_get_temp_dir() . '/chascarrillo_update_extracted';

        // 1. Download
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => ['User-Agent: Chascarrillo-Updater']
            ]
        ];
        $context = stream_context_create($opts);
        $content = @file_get_contents($zipUrl, false, $context);
        if ($content === false) {
            Messages::addError("No se pudo descargar el archivo de actualización.");
            return false;
        }
        file_put_contents($tmpZip, $content);

        // 2. Extract
        $zip = new \ZipArchive();
        if ($zip->open($tmpZip) === true) {
            if (is_dir($extractPath)) {
                self::recursiveRmdir($extractPath);
            }
            mkdir($extractPath);
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            Messages::addError("No se pudo abrir el archivo ZIP.");
            return false;
        }

        // 3. Replace Files
        $source = $extractPath;
        $subfolders = array_diff(scandir($source), ['.', '..', '__MACOSX']);
        if (count($subfolders) === 1) {
            $first = reset($subfolders);
            if (is_dir($source . '/' . $first)) {
                $source = $source . '/' . $first;
            }
        }

        $publicDir = defined('PUBLIC_DIR') ? constant('PUBLIC_DIR') : 'public';
        $success = self::recursiveCopy($source, constant('APP_PATH'), [
            'config.json',
            '.env',
            "$publicDir/.htaccess",
            "$publicDir/uploads",
            "Content",
            "storage",
            "var",
            "vendor" // Usually vendor should be handled by composer, but if it is a deploy zip it might be there
        ]);

        if ($success) {
            // 4. Run Migrations
            Config::doRunMigrations();
            Messages::addMessage("¡Actualización aplicada con éxito a " . self::VERSION . "!");
            return true;
        }

        Messages::addError("Hubo un error al copiar los archivos del sistema.");
        return false;
    }

    private static function recursiveCopy(string $src, string $dst, array $skip = []): bool
    {
        if (!is_dir($dst)) {
            if (!@mkdir($dst, 0755, true)) {
                error_log("No se pudo crear el directorio: $dst");
                return false;
            }
        }

        $dir = opendir($src);
        $allSuccess = true;

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (in_array($file, $skip)) {
                continue;
            }

            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;

            // Check nested skip
            $relativePath = ltrim(str_replace(constant('APP_PATH'), '', $dstFile), '/');
            if (in_array($relativePath, $skip)) {
                continue;
            }

            if (is_dir($srcFile)) {
                if (!self::recursiveCopy($srcFile, $dstFile, $skip)) {
                    $allSuccess = false;
                }
            } else {
                if (!@copy($srcFile, $dstFile)) {
                    error_log("Error al copiar $srcFile -> $dstFile");
                    $allSuccess = false;
                } else {
                    // Invalidate OPcache if possible
                    if (function_exists('opcache_invalidate')) {
                        @opcache_invalidate($dstFile, true);
                    }
                }
            }
        }
        closedir($dir);
        return $allSuccess;
    }

    private static function recursiveRmdir(string $dir): bool
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::recursiveRmdir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}

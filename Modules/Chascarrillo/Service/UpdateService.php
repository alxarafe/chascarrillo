<?php

namespace Modules\Chascarrillo\Service;

use Alxarafe\Base\Config;
use Alxarafe\Lib\Messages;

class UpdateService
{
    public const VERSION = 'v0.5.0';
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
        if (is_array($data) && isset($data['tag_name']) && version_compare($data['tag_name'], self::VERSION, '>')) {
            return $data;
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

        // 3. Replace Files (This is the critical part)
        // For a simple implementation, we copy everything from extracted folder to APP_PATH
        // We might want to skip config.json and .env
        $source = $extractPath;
        // GitHub zips often contain a subfolder 'repo-name-tag/'
        $subfolders = array_diff(scandir($source), ['.', '..']);
        if (count($subfolders) === 1) {
            $first = reset($subfolders);
            if (is_dir($source . '/' . $first)) {
                $source = $source . '/' . $first;
            }
        }

        $success = self::recursiveCopy($source, APP_PATH, ['config.json', '.env', 'public/.htaccess']);

        if ($success) {
            // 4. Run Migrations
            Config::doRunMigrations();
            Messages::addMessage("¡Actualización aplicada con éxito!");
            return true;
        }

        Messages::addError("Hubo un error al copiar los archivos del sistema.");
        return false;
    }

    private static function recursiveCopy(string $src, string $dst, array $skip = []): bool
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (in_array($file, $skip)) {
                continue;
            }

            // Check nested skip (e.g. public/.htaccess)
            $relativePath = ltrim(str_replace(APP_PATH, '', $dst . '/' . $file), '/');
            if (in_array($relativePath, $skip)) {
                continue;
            }

            if (is_dir($src . '/' . $file)) {
                self::recursiveCopy($src . '/' . $file, $dst . '/' . $file, $skip);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
        closedir($dir);
        return true;
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

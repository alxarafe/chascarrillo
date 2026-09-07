<?php

namespace Modules\Chascarrillo\Service;

use Alxarafe\Infrastructure\Lib\Messages;
use Alxarafe\Infrastructure\Persistence\Config;
use RuntimeException;
use Throwable;
use ZipArchive;

class UpdateService
{
    public const VERSION = 'v0.8.17';
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
                'header' => ['User-Agent: Chascarrillo-Updater'],
                'timeout' => 30,
            ],
        ];
        $response = @file_get_contents(self::UPDATE_URL, false, stream_context_create($opts));
        if ($response === false) {
            return null;
        }

        /** @var array<string,mixed>|null $data */
        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['tag_name'])) {
            return null;
        }
        $latest = ltrim((string) $data['tag_name'], 'v');
        if (!version_compare($latest, ltrim(self::VERSION, 'v'), '>')) {
            return null;
        }

        foreach (is_array($data['assets'] ?? null) ? $data['assets'] : [] as $asset) {
            $name = (string) ($asset['name'] ?? '');
            if (str_starts_with($name, 'chascarrillo-deploy-') && str_ends_with($name, '.zip')) {
                $data['zipball_url'] = (string) ($asset['browser_download_url'] ?? '');
                return $data;
            }
        }
        return null;
    }

    /** Download, verify and apply a self-contained deployment package. */
    public static function applyUpdate(string $zipUrl, string $targetVersion = ''): bool
    {
        $tmpZip = tempnam(sys_get_temp_dir(), 'chascarrillo-update-');
        $extractPath = sys_get_temp_dir() . '/chascarrillo-update-' . bin2hex(random_bytes(8));
        if ($tmpZip === false) {
            Messages::addError('No se pudo crear el archivo temporal de actualización.');
            return false;
        }

        try {
            if (!mkdir($extractPath, 0700, true)) {
                throw new RuntimeException('No se pudo crear el directorio temporal de actualización.');
            }
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => ['User-Agent: Chascarrillo-Updater'],
                    'timeout' => 60,
                ],
            ];
            $content = @file_get_contents($zipUrl, false, stream_context_create($opts));
            if ($content === false || file_put_contents($tmpZip, $content) === false) {
                throw new RuntimeException(
                    'No se pudo descargar el archivo de actualización. Verifique la conexión con GitHub.'
                );
            }

            $zip = new ZipArchive();
            if ($zip->open($tmpZip) !== true) {
                throw new RuntimeException('No se pudo abrir el archivo ZIP.');
            }
            try {
                (new ReleaseArchiveValidator())->validate($zip);
                if (!$zip->extractTo($extractPath)) {
                    throw new RuntimeException('No se pudo extraer el archivo ZIP.');
                }
            } finally {
                $zip->close();
            }

            $source = $extractPath;
            $entries = array_values(array_diff(scandir($source) ?: [], ['.', '..', '__MACOSX']));
            if (count($entries) === 1 && is_dir($source . '/' . $entries[0])) {
                $source .= '/' . $entries[0];
            }

            (new ReleaseInstaller())->install($source, constant('APP_PATH'));
            if (!Config::doRunMigrations()) {
                throw new RuntimeException(
                    'La actualización de archivos terminó, pero fallaron las migraciones. Revise el registro.'
                );
            }

            $versionLabel = $targetVersion ?: self::VERSION;
            Messages::addMessage("¡Actualización aplicada y verificada con éxito a {$versionLabel}!");
            return true;
        } catch (Throwable $exception) {
            Messages::addError('Actualización cancelada: ' . $exception->getMessage());
            return false;
        } finally {
            if (is_file($tmpZip)) {
                unlink($tmpZip);
            }
            if (is_dir($extractPath)) {
                self::recursiveRmdir($extractPath);
            }
        }
    }

    private static function recursiveRmdir(string $directory): bool
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return false;
        }
        $success = true;
        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $success = self::recursiveRmdir($path) && $success;
            } else {
                $success = unlink($path) && $success;
            }
        }
        return rmdir($directory) && $success;
    }
}

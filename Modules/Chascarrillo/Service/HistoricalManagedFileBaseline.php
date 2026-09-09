<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class HistoricalManagedFileBaseline
{
    /**
     * A pre-v0.8.17 override which could remain after the old copy-only updater.
     * It was not shipped by the authenticated v0.8.17 asset and stays explicit.
     *
     * @var array<string,string>
     */
    private const EXPLICIT_LEGACY_FILES = [
        'templates/partial/user_menu.blade.php' =>
            '641721c80f976cf66d9b52e46b83fac04423003e237b8ae4ca24b1eb51c2fe65',
    ];

    public const VERSION = '0.8.17';
    public const FILENAME = 'resources/update-baselines/v0.8.17.json';
    public const RELEASE_URL = 'https://api.github.com/repos/alxarafe/chascarrillo/releases/tags/v0.8.17';
    public const ASSET_URL = 'https://github.com/alxarafe/chascarrillo/releases/download/'
        . 'v0.8.17/chascarrillo-deploy-v0.8.17.zip';
    public const ASSET_NAME = 'chascarrillo-deploy-v0.8.17.zip';
    public const ASSET_SHA256 = '84a64c84db7b8eb70e16a057c3d36a4559023f9089edf1875b8b07bd43470c87';
    public const INVENTORY_SHA256 = 'a66daa1485e6b3fa27a214ca302a562ab9760b37610aead3f24d0325f698d227';

    /** @return array<string,string> */
    public function load(): array
    {
        $root = dirname(__DIR__, 3);
        $paths = new SafePath($root);
        $data = json_decode($paths->read(self::FILENAME), true);
        $source = is_array($data) && is_array($data['source'] ?? null) ? $data['source'] : [];
        if (
            !is_array($data)
            || ($data['format'] ?? null) !== 1
            || ($data['application_version'] ?? null) !== self::VERSION
            || ($source['release_url'] ?? null) !== self::RELEASE_URL
            || ($source['tag'] ?? null) !== 'v' . self::VERSION
            || ($source['asset_name'] ?? null) !== self::ASSET_NAME
            || ($source['asset_url'] ?? null) !== self::ASSET_URL
            || ($source['expected_sha256'] ?? null) !== self::ASSET_SHA256
            || ($source['obtained_sha256'] ?? null) !== self::ASSET_SHA256
            || !is_array($data['files'] ?? null)
            || !array_is_list($data['files'])
        ) {
            throw new RuntimeException('La base histórica v0.8.17 no es válida o no acredita su procedencia');
        }

        $files = [];
        foreach ($data['files'] as $entry) {
            if (!is_array($entry) || !is_string($entry['path'] ?? null)) {
                throw new RuntimeException('Entrada no válida en la base histórica v0.8.17');
            }
            $path = RelativePath::canonical($entry['path']);
            $hash = $entry['sha256'] ?? null;
            if (
                ManagedFileManifest::isProtected($path)
                || !is_string($hash)
                || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1
                || isset($files[$path])
            ) {
                throw new RuntimeException("Entrada no válida en la base histórica v0.8.17: {$path}");
            }
            $files[$path] = $hash;
        }
        ksort($files, SORT_STRING);
        $canonical = '';
        foreach ($files as $path => $hash) {
            $canonical .= $path . "\0" . $hash . "\n";
        }
        if (
            ($data['inventory_sha256'] ?? null) !== self::INVENTORY_SHA256
            || hash('sha256', $canonical) !== self::INVENTORY_SHA256
        ) {
            throw new RuntimeException('La base histórica v0.8.17 está corrupta o ha sido sustituida');
        }
        $files = array_replace($files, self::EXPLICIT_LEGACY_FILES);
        ksort($files, SORT_STRING);
        return $files;
    }
}

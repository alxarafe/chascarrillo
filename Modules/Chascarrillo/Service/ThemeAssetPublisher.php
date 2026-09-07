<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class ThemeAssetPublisher
{
    public const MANIFEST = '.chascarrillo-theme-assets.json';

    /** @var list<string> */
    private const ASSET_DIRECTORIES = ['css', 'js', 'img', 'images', 'fonts', 'assets'];

    /** @var list<string> */
    private const ASSET_EXTENSIONS = [
        'avif', 'css', 'eot', 'gif', 'ico', 'jpeg', 'jpg', 'js', 'json', 'map',
        'otf', 'png', 'svg', 'ttf', 'webp', 'woff', 'woff2',
    ];

    /**
     * Publishes framework assets first and application overrides second.
     *
     * @return array{copied:int,removed:int,preserved:int,files:array<string,string>}
     */
    public function publish(string $appRoot, ?string $publicRoot = null): array
    {
        $paths = new SafePath($appRoot);
        $appRoot = $paths->root();
        $publicRoot ??= $appRoot . '/public_html';
        $publicRelative = $paths->relativeFromAbsolute($publicRoot);
        $paths->ensureDirectory($publicRelative);
        $targetRoot = ($publicRelative === '' ? '' : $publicRelative . '/') . 'themes';
        $paths->ensureDirectory($targetRoot);
        $framework = 'vendor/alxarafe/alxarafe/templates/themes';
        if (!$paths->isDirectory($framework)) {
            throw new RuntimeException("No existe el origen de assets de Alxarafe: {$framework}");
        }

        $desired = [];
        foreach ([$framework, 'templates/themes'] as $source) {
            if ($paths->isDirectory($source)) {
                $desired = array_replace($desired, $this->discover($paths, $source));
            }
        }
        ksort($desired);

        $previous = $this->loadManifest($paths, $targetRoot);
        $copied = 0;
        foreach ($desired as $relative => $source) {
            $destination = $targetRoot . '/' . $relative;
            $expectedHash = $paths->hash($source);
            if (!$paths->isFile($destination) || $paths->hash($destination) !== $expectedHash) {
                $paths->atomicCopyFrom($paths, $source, $destination);
                $copied++;
            }
        }

        $removed = 0;
        $preserved = 0;
        foreach (array_diff_key($previous, $desired) as $relative => $oldHash) {
            $destination = $targetRoot . '/' . $relative;
            if (!$paths->isFile($destination)) {
                continue;
            }
            if ($paths->hash($destination) === $oldHash) {
                $paths->unlinkFile($destination);
                $paths->removeEmptyParents(dirname($destination), $targetRoot);
                $removed++;
            } else {
                $preserved++;
            }
        }

        $files = [];
        foreach ($desired as $relative => $source) {
            $files[$relative] = $paths->hash($source);
        }
        $entries = [];
        foreach ($files as $relative => $hash) {
            $entries[] = ['path' => $relative, 'sha256' => $hash];
        }
        $json = json_encode(['format' => 2, 'files' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar el manifiesto de assets');
        }
        $paths->atomicWrite($targetRoot . '/' . self::MANIFEST, $json . "\n");

        return compact('copied', 'removed', 'preserved', 'files');
    }

    /** @return array<string,string> relative target => source file */
    private function discover(SafePath $paths, string $sourceRoot): array
    {
        $result = [];
        foreach ($paths->entries($sourceRoot) as $theme) {
            $themeRoot = $sourceRoot . '/' . $theme;
            if (!$paths->isDirectory($themeRoot)) {
                continue;
            }
            foreach (self::ASSET_DIRECTORIES as $assetDirectory) {
                $root = $themeRoot . '/' . $assetDirectory;
                if (!$paths->isDirectory($root)) {
                    continue;
                }
                $this->discoverDirectory($paths, $root, "{$theme}/{$assetDirectory}", $result);
            }
        }
        return $result;
    }

    /** @param array<string,string> $result */
    private function discoverDirectory(SafePath $paths, string $source, string $target, array &$result): void
    {
        foreach ($paths->entries($source) as $entry) {
            $sourceChild = $source . '/' . $entry;
            $targetChild = $target . '/' . $entry;
            if ($paths->isDirectory($sourceChild)) {
                $this->discoverDirectory($paths, $sourceChild, $targetChild, $result);
                continue;
            }
            if (!$paths->isFile($sourceChild)) {
                continue;
            }
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (in_array($extension, self::ASSET_EXTENSIONS, true)) {
                $result[$targetChild] = $sourceChild;
            }
        }
    }

    /** @return array<string,string> */
    private function loadManifest(SafePath $paths, string $targetRoot): array
    {
        $filename = $targetRoot . '/' . self::MANIFEST;
        if (!$paths->isFile($filename)) {
            return [];
        }
        $data = json_decode($paths->read($filename), true);
        if (
            !is_array($data)
            || ($data['format'] ?? null) !== 2
            || !is_array($data['files'] ?? null)
            || !array_is_list($data['files'])
        ) {
            throw new RuntimeException("Manifiesto de assets no válido: {$filename}");
        }
        $files = [];
        foreach ($data['files'] as $entry) {
            if (!is_array($entry) || !is_string($entry['path'] ?? null)) {
                throw new RuntimeException("Entrada no válida en el manifiesto de assets: {$filename}");
            }
            $relative = RelativePath::canonical($entry['path']);
            $hash = $entry['sha256'] ?? null;
            $parts = explode('/', $relative);
            if (
                count($parts) < 3
                || !in_array($parts[1], self::ASSET_DIRECTORIES, true)
                || !in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), self::ASSET_EXTENSIONS, true)
                || !is_string($hash)
                || !preg_match('/^[a-f0-9]{64}$/', $hash)
            ) {
                throw new RuntimeException("Entrada no válida en el manifiesto de assets: {$relative}");
            }
            if (isset($files[$relative])) {
                throw new RuntimeException("Entrada duplicada en el manifiesto de assets: {$relative}");
            }
            $files[$relative] = $hash;
        }
        ksort($files);
        return $files;
    }
}

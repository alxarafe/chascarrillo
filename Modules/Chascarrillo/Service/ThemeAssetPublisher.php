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
     * @param array<string,?string> $virtualHashes State after the managed-file plan.
     * @return array{target_root:string,copies:list<array<string,mixed>>,removals:list<array<string,mixed>>,manifest:?array<string,mixed>,copied:int,removed:int,preserved:int,files:array<string,string>}
     */
    public function plan(
        string $sourceRoot,
        string $appRoot,
        ?string $publicRoot = null,
        array $virtualHashes = []
    ): array {
        $sourcePaths = new SafePath($sourceRoot);
        $paths = new SafePath($appRoot);
        $appRoot = $paths->root();
        $publicRoot ??= $appRoot . '/public_html';
        $publicRelative = $paths->relativeFromAbsolute($publicRoot);
        $targetRoot = ($publicRelative === '' ? '' : $publicRelative . '/') . 'themes';
        foreach ([$publicRelative, $targetRoot] as $directory) {
            $type = $paths->nodeType($directory);
            if (!in_array($type, [SafePath::NODE_MISSING, SafePath::NODE_DIRECTORY], true)) {
                throw new RuntimeException("Destino de assets inseguro: {$directory}");
            }
        }

        $framework = 'vendor/alxarafe/alxarafe/templates/themes';
        if (!$sourcePaths->isDirectory($framework)) {
            throw new RuntimeException("No existe el origen de assets de Alxarafe: {$framework}");
        }
        $desired = [];
        foreach ([$framework, 'templates/themes'] as $source) {
            if ($sourcePaths->isDirectory($source)) {
                $desired = array_replace($desired, $this->discover($sourcePaths, $source));
            }
        }
        ksort($desired);

        $previous = $this->loadManifest($paths, $targetRoot);
        $copies = [];
        foreach ($desired as $relative => $source) {
            $destination = $targetRoot . '/' . $relative;
            $expectedHash = $sourcePaths->hash($source);
            $currentHash = $this->virtualHash($paths, $destination, $virtualHashes);
            if ($currentHash !== $expectedHash) {
                $size = filesize($sourcePaths->requireFile($source));
                if ($size === false) {
                    throw new RuntimeException("No se pudo medir el asset {$source}");
                }
                $copies[] = [
                    'type' => 'asset_copy',
                    'path' => $destination,
                    'source' => $source,
                    'new_hash' => $expectedHash,
                    'new_size' => $size,
                ];
                $virtualHashes[$destination] = $expectedHash;
            }
        }

        $removals = [];
        $preserved = 0;
        foreach (array_diff_key($previous, $desired) as $relative => $oldHash) {
            $destination = $targetRoot . '/' . $relative;
            $currentHash = $this->virtualHash($paths, $destination, $virtualHashes);
            if ($currentHash === null) {
                continue;
            }
            if ($currentHash === $oldHash) {
                $removals[] = [
                    'type' => 'asset_remove',
                    'path' => $destination,
                    'new_hash' => null,
                    'new_size' => 0,
                ];
                $virtualHashes[$destination] = null;
            } else {
                $preserved++;
            }
        }

        $files = [];
        foreach ($desired as $relative => $source) {
            $files[$relative] = $sourcePaths->hash($source);
        }
        $entries = [];
        foreach ($files as $relative => $hash) {
            $entries[] = ['path' => $relative, 'sha256' => $hash];
        }
        $json = json_encode(['format' => 2, 'files' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar el manifiesto de assets');
        }
        $contents = $json . "\n";
        $manifestPath = $targetRoot . '/' . self::MANIFEST;
        $manifestHash = hash('sha256', $contents);
        $manifest = $this->virtualHash($paths, $manifestPath, $virtualHashes) === $manifestHash
            ? null
            : [
                'type' => 'asset_manifest',
                'path' => $manifestPath,
                'contents' => $contents,
                'new_hash' => $manifestHash,
                'new_size' => strlen($contents),
            ];

        return [
            'target_root' => $targetRoot,
            'copies' => $copies,
            'removals' => $removals,
            'manifest' => $manifest,
            'copied' => count($copies),
            'removed' => count($removals),
            'preserved' => $preserved,
            'files' => $files,
        ];
    }

    /**
     * @param array{target_root:string,copies:list<array<string,mixed>>,removals:list<array<string,mixed>>,manifest:?array<string,mixed>,copied:int,removed:int,preserved:int,files:array<string,string>} $plan
     * @param callable(array<string,mixed>,callable():void):void $apply
     * @return array{copied:int,removed:int,preserved:int,files:array<string,string>}
     */
    public function publishPrepared(string $sourceRoot, string $appRoot, array $plan, callable $apply): array
    {
        $sourcePaths = new SafePath($sourceRoot);
        $paths = new SafePath($appRoot);
        foreach ($plan['copies'] as $operation) {
            $apply(
                $operation,
                static function () use ($paths, $sourcePaths, $operation): void {
                    $paths->atomicCopyFrom($sourcePaths, $operation['source'], $operation['path']);
                }
            );
        }
        foreach ($plan['removals'] as $operation) {
            $apply($operation, static function () use ($paths, $operation): void {
                $paths->unlinkFile($operation['path']);
            });
        }
        if ($plan['manifest'] !== null) {
            $operation = $plan['manifest'];
            $apply($operation, static function () use ($paths, $operation): void {
                $paths->atomicWrite($operation['path'], $operation['contents']);
            });
        }
        return [
            'copied' => $plan['copied'],
            'removed' => $plan['removed'],
            'preserved' => $plan['preserved'],
            'files' => $plan['files'],
        ];
    }

    /**
     * Publishes framework assets first and application overrides second.
     *
     * @return array{copied:int,removed:int,preserved:int,files:array<string,string>}
     */
    public function publish(string $appRoot, ?string $publicRoot = null): array
    {
        $plan = $this->plan($appRoot, $appRoot, $publicRoot);
        return $this->publishPrepared(
            $appRoot,
            $appRoot,
            $plan,
            static function (array $operation, callable $mutation): void {
                $mutation();
            }
        );
    }

    /** @param array<string,?string> $virtualHashes */
    private function virtualHash(SafePath $paths, string $path, array $virtualHashes): ?string
    {
        if (array_key_exists($path, $virtualHashes)) {
            return $virtualHashes[$path];
        }
        $type = $paths->nodeType($path);
        if ($type === SafePath::NODE_MISSING) {
            return null;
        }
        if ($type !== SafePath::NODE_FILE) {
            if ($type === SafePath::NODE_SYMLINK) {
                throw new RuntimeException("Enlace simbólico de asset inseguro: {$path}");
            }
            throw new RuntimeException("Nodo de asset inseguro: {$path}");
        }
        return $paths->hash($path);
    }

    /** @return array<string,string> relative target => source file */
    private function discover(SafePath $paths, string $sourceRoot): array
    {
        $result = [];
        foreach ($paths->entries($sourceRoot) as $theme) {
            $themeRoot = $sourceRoot . '/' . $theme;
            if (!$paths->isDirectory($themeRoot)) {
                if ($paths->nodeType($themeRoot) === SafePath::NODE_SYMLINK) {
                    throw new RuntimeException("Enlace simbólico en origen de assets: {$themeRoot}");
                }
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
                if ($paths->nodeType($sourceChild) === SafePath::NODE_SYMLINK) {
                    throw new RuntimeException("Enlace simbólico en origen de assets: {$sourceChild}");
                }
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
            !is_array($data) || ($data['format'] ?? null) !== 2 || !is_array($data['files'] ?? null)
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
                count($parts) < 3 || !in_array($parts[1], self::ASSET_DIRECTORIES, true)
                || !in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), self::ASSET_EXTENSIONS, true)
                || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)
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

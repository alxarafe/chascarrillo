<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class ManagedFileManifest
{
    public const FILENAME = '.chascarrillo-managed-files.json';

    /**
     * Paths which always belong to the installation owner, never to an update.
     * A directory entry protects the complete subtree.
     *
     * @var list<string>
     */
    private const PROTECTED_PATHS = [
        '.env',
        '.htaccess',
        'config.json',
        'Content',
        'storage',
        'var',
        'public_html/uploads',
        'public_html/.htaccess',
    ];

    /** @var list<string> */
    private const BUILD_EXCLUDES = [
        '.git',
        '.gitattributes',
        '.gitignore',
        '.github',
        '.agents',
        '.codex',
        '.phpunit.cache',
        '.DS_Store',
        'coverage',
        'docs/audit',
        'reports',
        'tmp',
        'Tests',
        'node_modules',
        'phpcs.xml',
        'phpstan-bootstrap.php',
        'phpstan.neon',
        'phpunit.xml',
        'psalm-stubs.php',
        'psalm.xml',
    ];

    /**
     * @return array{format:int,application_version:string,files:array<string,string>}
     */
    public static function generate(string $root): array
    {
        $root = self::realDirectory($root);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }

            $relative = RelativePath::canonical(substr($file->getPathname(), strlen($root) + 1));
            if ($relative === self::FILENAME || self::isProtected($relative) || self::isBuildExcluded($relative)) {
                continue;
            }
            if (preg_match('/.(?:zip|tmp|log)$/i', $relative) === 1) {
                continue;
            }

            $hash = hash_file('sha256', $file->getPathname());
            if ($hash === false) {
                throw new RuntimeException("No se pudo calcular la huella de {$relative}");
            }
            $files[$relative] = $hash;
        }

        ksort($files);
        return ['format' => 2, 'application_version' => ApplicationVersion::canonical(), 'files' => $files];
    }

    /**
     * @return array{format:int,application_version:string,files:array<string,string>}
     */
    public static function load(string $root, bool $required = true, bool $allowInstalledLegacy = false): array
    {
        $filename = rtrim($root, '/') . '/' . self::FILENAME;
        if (!is_file($filename)) {
            if ($required) {
                throw new RuntimeException('El paquete no contiene ' . self::FILENAME);
            }
            return ['format' => 2, 'application_version' => '', 'files' => []];
        }

        $contents = file_get_contents($filename);
        $data = $contents === false ? null : json_decode($contents, true);
        if (
            !is_array($data)
            || ($data['format'] ?? null) !== 2
            || !is_array($data['files'] ?? null)
            || !array_is_list($data['files'])
        ) {
            throw new RuntimeException('El manifiesto de archivos administrados no es válido');
        }

        $applicationVersion = $data['application_version'] ?? null;
        if (!is_string($applicationVersion)) {
            if (!$allowInstalledLegacy || !is_string($data['version'] ?? null)) {
                throw new RuntimeException('El manifiesto no contiene application_version válida');
            }
            ApplicationVersion::assertTagMatches($data['version'], ApplicationVersion::canonical());
            $applicationVersion = '';
        } else {
            ApplicationVersion::assertValid($applicationVersion, 'application_version del manifiesto');
        }

        $files = [];
        foreach ($data['files'] as $entry) {
            if (!is_array($entry) || !is_string($entry['path'] ?? null)) {
                throw new RuntimeException('Entrada no válida en el manifiesto de archivos administrados');
            }
            $path = RelativePath::canonical($entry['path']);
            $hash = $entry['sha256'] ?? null;
            if (!self::isSafeRelativePath($path) || self::isProtected($path)) {
                throw new RuntimeException("Ruta no permitida en el manifiesto: {$path}");
            }
            if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
                throw new RuntimeException("Huella no válida en el manifiesto: {$path}");
            }
            if (isset($files[$path])) {
                throw new RuntimeException("Ruta duplicada en el manifiesto: {$path}");
            }
            $files[$path] = $hash;
        }

        ksort($files);
        return [
            'format' => 2,
            'application_version' => $applicationVersion,
            'files' => $files,
        ];
    }

    /** @param array{format:int,application_version:string,files:array<string,string>} $manifest */
    public static function write(string $root, array $manifest): void
    {
        $target = rtrim($root, '/') . '/' . self::FILENAME;
        self::atomicWrite($target, self::encode($manifest));
    }

    /** @param array{format:int,application_version:string,files:array<string,string>} $manifest */
    public static function encode(array $manifest): string
    {
        $files = [];
        foreach ($manifest['files'] as $path => $hash) {
            $path = RelativePath::canonical((string) $path);
            if (self::isProtected($path) || !self::isSha256($hash)) {
                throw new RuntimeException("Entrada no válida al escribir el manifiesto: {$path}");
            }
            $files[$path] = $hash;
        }
        ksort($files);
        $entries = [];
        foreach ($files as $path => $hash) {
            $entries[] = ['path' => $path, 'sha256' => $hash];
        }
        $document = [
            'format' => 2,
            'application_version' => (string) $manifest['application_version'],
            'files' => $entries,
        ];
        ApplicationVersion::assertValid($document['application_version'], 'application_version del manifiesto');
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar el manifiesto de distribución');
        }
        return $json . "\n";
    }

    public static function isProtected(string $path): bool
    {
        $path = RelativePath::canonical($path);
        if (basename($path) === '.htaccess') {
            return true;
        }

        foreach (self::PROTECTED_PATHS as $protected) {
            if ($path === $protected || str_starts_with($path, $protected . '/')) {
                return true;
            }
        }
        return false;
    }

    public static function isSafeRelativePath(string $path): bool
    {
        try {
            return RelativePath::canonical($path) === $path;
        } catch (RuntimeException) {
            return false;
        }
    }

    public static function atomicWrite(string $target, string $contents): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("No se pudo crear {$directory}");
        }

        $temporary = tempnam($directory, '.chascarrillo-');
        if ($temporary === false || file_put_contents($temporary, $contents) === false || !rename($temporary, $target)) {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            throw new RuntimeException("No se pudo escribir {$target}");
        }
    }

    private static function isBuildExcluded(string $path): bool
    {
        foreach (explode('/', $path) as $segment) {
            if (str_starts_with($segment, '.git') || $segment === '.DS_Store') {
                return true;
            }
        }
        foreach (self::BUILD_EXCLUDES as $excluded) {
            if ($path === $excluded || str_starts_with($path, $excluded . '/')) {
                return true;
            }
        }
        return false;
    }

    private static function isSha256(mixed $hash): bool
    {
        return is_string($hash) && preg_match('/^[a-f0-9]{64}$/', $hash) === 1;
    }

    private static function realDirectory(string $root): string
    {
        $real = realpath($root);
        if ($real === false || !is_dir($real)) {
            throw new RuntimeException("Directorio no encontrado: {$root}");
        }
        return rtrim($real, '/');
    }
}

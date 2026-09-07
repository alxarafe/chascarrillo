<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

/** Filesystem operations confined to one canonical directory without following symlinks. */
final class SafePath
{
    private const TYPE_MASK = 0170000;
    private const TYPE_DIRECTORY = 0040000;
    private const TYPE_REGULAR = 0100000;
    private const TYPE_SYMLINK = 0120000;

    private string $root;

    public function __construct(string $root)
    {
        $real = realpath($root);
        if ($real === false) {
            throw new RuntimeException("Directorio autorizado no encontrado: {$root}");
        }
        $stat = @lstat($real);
        if ($stat === false || (($stat['mode'] & self::TYPE_MASK) !== self::TYPE_DIRECTORY)) {
            throw new RuntimeException("La raíz autorizada no es un directorio: {$root}");
        }
        $this->root = rtrim($real, '/');
    }

    public function root(): string
    {
        return $this->root;
    }

    public function relativeFromAbsolute(string $path): string
    {
        $path = rtrim($path, '/');
        if ($path === $this->root) {
            return '';
        }
        if (!str_starts_with($path, $this->root . '/')) {
            throw new RuntimeException("Ruta fuera de la raíz autorizada: {$path}");
        }
        $relative = substr($path, strlen($this->root) + 1);
        $this->segments($relative);
        return $relative;
    }

    public function exists(string $relative): bool
    {
        return $this->resolve($relative, false) !== null;
    }

    public function requireFile(string $relative): string
    {
        $path = $this->resolve($relative, true);
        $stat = $this->safeLstat($path);
        if (($stat['mode'] & self::TYPE_MASK) !== self::TYPE_REGULAR) {
            throw new RuntimeException("La ruta no es un archivo regular: {$relative}");
        }
        return $path;
    }

    public function requireDirectory(string $relative): string
    {
        if ($relative === '') {
            return $this->root;
        }
        $path = $this->resolve($relative, true);
        $stat = $this->safeLstat($path);
        if (($stat['mode'] & self::TYPE_MASK) !== self::TYPE_DIRECTORY) {
            throw new RuntimeException("La ruta no es un directorio: {$relative}");
        }
        return $path;
    }

    public function ensureDirectory(string $relative): string
    {
        $current = $this->root;
        foreach ($this->segments($relative) as $segment) {
            $current .= '/' . $segment;
            $stat = @lstat($current);
            if ($stat === false) {
                if (!mkdir($current, 0755)) {
                    throw new RuntimeException("No se pudo crear el directorio seguro {$current}");
                }
                $stat = $this->safeLstat($current);
            }
            $this->rejectSymlink($current, $stat);
            if (($stat['mode'] & self::TYPE_MASK) !== self::TYPE_DIRECTORY) {
                throw new RuntimeException("Un componente de la ruta no es un directorio: {$current}");
            }
            $this->assertPhysicalContainment($current);
        }
        return $current;
    }

    public function hash(string $relative): string
    {
        $path = $this->requireFile($relative);
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException("No se pudo calcular la huella de {$relative}");
        }
        return $hash;
    }

    public function read(string $relative): string
    {
        $contents = file_get_contents($this->requireFile($relative));
        if ($contents === false) {
            throw new RuntimeException("No se pudo leer {$relative}");
        }
        return $contents;
    }

    public function atomicCopyFrom(self $source, string $sourceRelative, string $destinationRelative): void
    {
        $sourceRelative = RelativePath::canonical($sourceRelative);
        $destinationRelative = RelativePath::canonical($destinationRelative);
        $sourcePath = $source->requireFile($sourceRelative);
        $directoryRelative = $this->parent($destinationRelative);
        $directory = $this->ensureDirectory($directoryRelative);
        $destination = $this->pathForWrite($destinationRelative);
        $temporary = tempnam($directory, '.safe-copy-');
        if ($temporary === false) {
            throw new RuntimeException("No se pudo crear el temporal para {$destinationRelative}");
        }
        try {
            $this->assertTemporary($temporary, $directory);
            if (!copy($sourcePath, $temporary)) {
                throw new RuntimeException("No se pudo copiar {$destinationRelative}");
            }
            $this->pathForWrite($destinationRelative);
            if (!rename($temporary, $destination)) {
                throw new RuntimeException("No se pudo instalar {$destinationRelative}");
            }
            $this->requireFile($destinationRelative);
        } finally {
            if (is_file($temporary) && !is_link($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function atomicWrite(string $relative, string $contents): void
    {
        $relative = RelativePath::canonical($relative);
        $directory = $this->ensureDirectory($this->parent($relative));
        $destination = $this->pathForWrite($relative);
        $temporary = tempnam($directory, '.safe-write-');
        if ($temporary === false) {
            throw new RuntimeException("No se pudo crear el temporal para {$relative}");
        }
        try {
            $this->assertTemporary($temporary, $directory);
            if (file_put_contents($temporary, $contents) === false) {
                throw new RuntimeException("No se pudo escribir el temporal para {$relative}");
            }
            $this->pathForWrite($relative);
            if (!rename($temporary, $destination)) {
                throw new RuntimeException("No se pudo escribir {$relative}");
            }
            $this->requireFile($relative);
        } finally {
            if (is_file($temporary) && !is_link($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function unlinkFile(string $relative): void
    {
        $path = $this->requireFile($relative);
        $this->requireFile($relative);
        if (!unlink($path)) {
            throw new RuntimeException("No se pudo retirar {$relative}");
        }
    }

    public function removeTree(string $relative): int
    {
        if (!$this->exists($relative)) {
            return 0;
        }
        $this->requireDirectory($relative);
        $count = 0;
        $this->removeDirectory($relative, $count);
        return $count;
    }

    public function removeEmptyParents(string $relative, string $stop = ''): void
    {
        $relative = RelativePath::canonical($relative);
        if ($stop !== '') {
            $stop = RelativePath::canonical($stop);
        }
        while ($relative !== '' && $relative !== $stop) {
            if (!$this->exists($relative)) {
                return;
            }
            $directory = $this->requireDirectory($relative);
            if ($this->entries($relative) !== []) {
                return;
            }
            $this->requireDirectory($relative);
            if (!rmdir($directory)) {
                throw new RuntimeException("No se pudo retirar el directorio vacío {$relative}");
            }
            $relative = $this->parent($relative);
        }
    }

    /** @return list<string> */
    public function entries(string $relative): array
    {
        $directory = $this->requireDirectory($relative);
        $entries = scandir($directory);
        if ($entries === false) {
            throw new RuntimeException("No se pudo recorrer {$relative}");
        }
        return array_values(array_diff($entries, ['.', '..']));
    }

    public function isDirectory(string $relative): bool
    {
        $path = $this->resolve($relative, false);
        if ($path === null) {
            return false;
        }
        return (($this->safeLstat($path)['mode'] & self::TYPE_MASK) === self::TYPE_DIRECTORY);
    }

    public function isFile(string $relative): bool
    {
        $path = $this->resolve($relative, false);
        if ($path === null) {
            return false;
        }
        return (($this->safeLstat($path)['mode'] & self::TYPE_MASK) === self::TYPE_REGULAR);
    }

    /** @return list<string> */
    public function regularFiles(string $relative = ''): array
    {
        if ($relative !== '') {
            $this->requireDirectory($relative);
        }
        $files = [];
        $this->collectRegularFiles($relative, $files);
        sort($files, SORT_STRING);
        return $files;
    }

    /** @param list<string> $files */
    private function collectRegularFiles(string $relative, array &$files): void
    {
        foreach ($this->entries($relative) as $entry) {
            $child = $relative === '' ? $entry : $relative . '/' . $entry;
            if ($this->isDirectory($child)) {
                $this->collectRegularFiles($child, $files);
            } elseif ($this->isFile($child)) {
                $files[] = $child;
            } else {
                throw new RuntimeException("Tipo de archivo no permitido al recorrer {$child}");
            }
        }
    }

    private function removeDirectory(string $relative, int &$count): void
    {
        foreach ($this->entries($relative) as $entry) {
            $child = $relative . '/' . $entry;
            $path = $this->resolve($child, true);
            $stat = $this->safeLstat($path);
            $type = $stat['mode'] & self::TYPE_MASK;
            if ($type === self::TYPE_DIRECTORY) {
                $this->removeDirectory($child, $count);
                continue;
            }
            if ($type !== self::TYPE_REGULAR) {
                throw new RuntimeException("Tipo de archivo no permitido al recorrer {$child}");
            }
            $this->unlinkFile($child);
            $count++;
        }
        $directory = $this->requireDirectory($relative);
        if (!rmdir($directory)) {
            throw new RuntimeException("No se pudo retirar el directorio {$relative}");
        }
    }

    private function pathForWrite(string $relative): string
    {
        $segments = $this->segments($relative);
        if ($segments === []) {
            throw new RuntimeException('No se puede escribir sobre la raíz autorizada');
        }
        $parent = $this->parent($relative);
        $this->requireDirectory($parent);
        $path = $this->root . '/' . implode('/', $segments);
        $stat = @lstat($path);
        if ($stat !== false) {
            $this->rejectSymlink($path, $stat);
            $this->assertPhysicalContainment($path);
        }
        return $path;
    }

    private function resolve(string $relative, bool $required): ?string
    {
        $segments = $this->segments($relative);
        if ($segments === []) {
            return $this->root;
        }
        $current = $this->root;
        foreach ($segments as $index => $segment) {
            $current .= '/' . $segment;
            $stat = @lstat($current);
            if ($stat === false) {
                if ($required) {
                    throw new RuntimeException("Ruta segura no encontrada: {$relative}");
                }
                return null;
            }
            $this->rejectSymlink($current, $stat);
            if ($index < count($segments) - 1 && (($stat['mode'] & self::TYPE_MASK) !== self::TYPE_DIRECTORY)) {
                throw new RuntimeException("Un componente de la ruta no es un directorio: {$current}");
            }
            $this->assertPhysicalContainment($current);
        }
        return $current;
    }

    /** @return list<string> */
    private function segments(string $relative): array
    {
        if ($relative === '') {
            return [];
        }
        return explode('/', RelativePath::canonical($relative));
    }

    private function parent(string $relative): string
    {
        $relative = RelativePath::canonical($relative);
        $parent = dirname($relative);
        return $parent === '.' ? '' : trim($parent, '/');
    }

    /** @param array<string|int,mixed> $stat */
    private function rejectSymlink(string $path, array $stat): void
    {
        if (($stat['mode'] & self::TYPE_MASK) === self::TYPE_SYMLINK) {
            throw new RuntimeException("Enlace simbólico no permitido: {$path}");
        }
    }

    /** @return array<string|int,mixed> */
    private function safeLstat(string $path): array
    {
        $stat = @lstat($path);
        if ($stat === false) {
            throw new RuntimeException("No se pudo inspeccionar de forma segura {$path}");
        }
        $this->rejectSymlink($path, $stat);
        return $stat;
    }

    private function assertPhysicalContainment(string $path): void
    {
        $real = realpath($path);
        if ($real === false || ($real !== $this->root && !str_starts_with($real, $this->root . '/'))) {
            throw new RuntimeException("Ruta fuera de la raíz autorizada: {$path}");
        }
    }

    private function assertTemporary(string $temporary, string $directory): void
    {
        $stat = $this->safeLstat($temporary);
        if (($stat['mode'] & self::TYPE_MASK) !== self::TYPE_REGULAR) {
            throw new RuntimeException("El temporal no es un archivo regular: {$temporary}");
        }
        $realDirectory = realpath(dirname($temporary));
        if ($realDirectory !== $directory) {
            throw new RuntimeException("El temporal quedó fuera del directorio autorizado: {$temporary}");
        }
    }
}

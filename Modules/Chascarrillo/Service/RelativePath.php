<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

/** Defines the single accepted representation for distribution-relative paths. */
final class RelativePath
{
    public static function canonical(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('La ruta relativa no puede estar vacía');
        }
        if (str_contains($path, '\\')) {
            throw new RuntimeException("La ruta contiene barras inversas: {$path}");
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new RuntimeException('La ruta contiene caracteres de control');
        }
        if ($path[0] === '/' || preg_match('/^[A-Za-z]:/', $path) === 1) {
            throw new RuntimeException("La ruta no es relativa: {$path}");
        }
        if (str_ends_with($path, '/')) {
            throw new RuntimeException("La ruta termina en un separador: {$path}");
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException("La ruta no es canónica: {$path}");
            }
        }
        return $path;
    }

    public static function archiveEntry(string $name, bool $directory): string
    {
        if ($directory) {
            if (!str_ends_with($name, '/')) {
                throw new RuntimeException("El directorio ZIP no tiene marcador final: {$name}");
            }
            $name = substr($name, 0, -1);
        } elseif (str_ends_with($name, '/')) {
            throw new RuntimeException("Un archivo ZIP termina en separador: {$name}");
        }
        return self::canonical($name);
    }
}

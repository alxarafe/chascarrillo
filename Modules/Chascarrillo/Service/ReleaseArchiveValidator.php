<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;
use ZipArchive;

/** Validates ZIP entry names and Unix file types before any extraction occurs. */
final class ReleaseArchiveValidator
{
    private const TYPE_MASK = 0170000;
    private const TYPE_DIRECTORY = 0040000;
    private const TYPE_REGULAR = 0100000;

    /** @return list<string> canonical entry names */
    public function validate(ZipArchive $zip): array
    {
        $entries = [];
        $seen = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!is_string($name)) {
                throw new RuntimeException("No se pudo determinar el nombre de la entrada ZIP {$index}");
            }

            $attributes = 0;
            $operatingSystem = 0;
            if (!$zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
                throw new RuntimeException("No se pudieron determinar los atributos ZIP de {$name}");
            }
            if ($operatingSystem !== ZipArchive::OPSYS_UNIX) {
                throw new RuntimeException("Sistema de atributos ZIP no permitido para {$name}");
            }

            $type = ($attributes >> 16) & self::TYPE_MASK;
            if ($type !== self::TYPE_DIRECTORY && $type !== self::TYPE_REGULAR) {
                throw new RuntimeException("Tipo de entrada ZIP no permitido para {$name}");
            }
            $canonical = RelativePath::archiveEntry($name, $type === self::TYPE_DIRECTORY);
            if (ManagedFileManifest::isProtected($canonical)) {
                throw new RuntimeException("Ruta protegida incluida en el ZIP: {$canonical}");
            }
            if (isset($seen[$canonical])) {
                throw new RuntimeException("Entradas ZIP duplicadas o colisionadas: {$canonical}");
            }
            $seen[$canonical] = true;
            $entries[] = $canonical;
        }
        return $entries;
    }
}

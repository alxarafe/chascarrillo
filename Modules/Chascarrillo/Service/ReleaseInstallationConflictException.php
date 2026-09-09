<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class ReleaseInstallationConflictException extends RuntimeException
{
    /** @param list<ManagedFileConflict> $conflicts */
    public function __construct(private readonly array $conflicts)
    {
        $lines = array_map(
            static fn (ManagedFileConflict $conflict): string => '- ' . $conflict->describe(),
            $conflicts
        );
        parent::__construct(
            "Conflictos locales detectados; actualización cancelada antes de modificar la instalación:\n"
            . implode("\n", $lines)
        );
    }

    /** @return list<ManagedFileConflict> */
    public function conflicts(): array
    {
        return $this->conflicts;
    }
}

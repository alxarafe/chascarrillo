<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use Alxarafe\Infrastructure\Persistence\Config;
use Closure;
use RuntimeException;

final class ReleaseUpdateCoordinator
{
    /** @var Closure():bool */
    private readonly Closure $migrate;

    /** @param (callable():bool)|null $migrate */
    public function __construct(
        private readonly ReleaseInstaller $installer = new ReleaseInstaller(),
        ?callable $migrate = null
    ) {
        $this->migrate = $migrate === null
            ? static fn (): bool => Config::doRunMigrations()
            : Closure::fromCallable($migrate);
    }

    /** @return array{copied:int,removed:int,preserved:int,cache_removed:int,assets:array<string,mixed>} */
    public function apply(string $releaseRoot, string $installRoot, ?string $releaseTag = null): array
    {
        $result = $this->installer->install($releaseRoot, $installRoot, $releaseTag);
        if (!(($this->migrate)())) {
            throw new RuntimeException(
                'La actualización de archivos terminó, pero fallaron las migraciones. Revise el registro.'
            );
        }
        return $result;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

/** Vendor-neutral verification boundary for recovery prepared outside the updater. */
interface DatabaseRecoveryProvider
{
    /** @param list<string> $pending */
    public function verify(
        string $releaseRoot,
        string $installRoot,
        array $pending
    ): DatabaseRecoveryCapability;
}

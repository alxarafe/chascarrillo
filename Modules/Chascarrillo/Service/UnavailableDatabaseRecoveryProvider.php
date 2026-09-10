<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

/** Safe default: the updater has no evidence of an externally recoverable database. */
final class UnavailableDatabaseRecoveryProvider implements DatabaseRecoveryProvider
{
    public function verify(
        string $releaseRoot,
        string $installRoot,
        array $pending
    ): DatabaseRecoveryCapability {
        return DatabaseRecoveryCapability::unavailable('external');
    }
}

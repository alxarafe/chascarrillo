<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

/** Provides deterministic migration boundaries to the release coordinator. */
interface ReleaseMigrationExecutor
{
    /** @return list<string> */
    public function pending(string $releaseRoot): array;

    /** @param list<string> $migrations */
    public function prepare(string $installRoot, array $migrations): void;

    public function execute(string $migration, string $installRoot): void;

    /** @param list<string> $migrations */
    public function assertApplied(array $migrations): void;
}

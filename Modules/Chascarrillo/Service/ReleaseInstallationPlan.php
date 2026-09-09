<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class ReleaseInstallationPlan
{
    /**
     * @param list<ReleaseFileOperation> $copies
     * @param list<ReleaseFileOperation> $removals
     */
    public function __construct(
        private readonly array $copies,
        private readonly array $removals,
        private readonly string $manifestNodeType,
        private readonly ?string $manifestHash
    ) {
    }

    /** @return list<ReleaseFileOperation> */
    public function copies(): array
    {
        return $this->copies;
    }

    /** @return list<ReleaseFileOperation> */
    public function removals(): array
    {
        return $this->removals;
    }

    public function assertPreconditions(SafePath $paths): void
    {
        $this->assertManifestPrecondition($paths);
        foreach ([...$this->copies, ...$this->removals] as $operation) {
            $operation->assertPrecondition($paths);
        }
    }

    public function assertManifestPrecondition(SafePath $paths): void
    {
        $type = $paths->nodeType(ManagedFileManifest::FILENAME);
        if ($type !== $this->manifestNodeType) {
            throw new RuntimeException('El manifiesto instalado cambió durante la actualización');
        }
        if (
            $type === SafePath::NODE_FILE
            && ($this->manifestHash === null || $paths->hash(ManagedFileManifest::FILENAME) !== $this->manifestHash)
        ) {
            throw new RuntimeException('El manifiesto instalado cambió durante la actualización');
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

final class ReleaseInstallationPlanner
{
    public function __construct(
        private readonly HistoricalManagedFileBaseline $historicalBaseline = new HistoricalManagedFileBaseline()
    ) {
    }

    /**
     * @param array<string,string> $nextFiles
     * @param array<string,string>|null $previousFiles Null means an installation without a manifest.
     */
    public function plan(
        SafePath $installPaths,
        array $nextFiles,
        ?array $previousFiles,
        string $manifestNodeType,
        ?string $manifestHash
    ): ReleaseInstallationPlan {
        $managedBefore = $previousFiles ?? $this->historicalBaseline->load();
        ksort($managedBefore, SORT_STRING);
        ksort($nextFiles, SORT_STRING);
        $copies = [];
        $removals = [];
        $conflicts = [];

        foreach ($nextFiles as $relative => $newHash) {
            $oldHash = $managedBefore[$relative] ?? null;
            $type = $installPaths->nodeType($relative);
            if ($type === SafePath::NODE_MISSING) {
                $copies[] = new ReleaseFileOperation(
                    ReleaseFileOperation::COPY,
                    $relative,
                    SafePath::NODE_MISSING,
                    null,
                    $newHash
                );
                continue;
            }
            if ($type !== SafePath::NODE_FILE) {
                $conflicts[] = $this->nodeConflict($relative, $type, $oldHash, $newHash);
                continue;
            }

            $actualHash = $installPaths->hash($relative);
            if ($actualHash === $newHash) {
                continue;
            }
            if ($oldHash !== null && $actualHash === $oldHash) {
                $copies[] = new ReleaseFileOperation(
                    ReleaseFileOperation::COPY,
                    $relative,
                    SafePath::NODE_FILE,
                    $actualHash,
                    $newHash
                );
                continue;
            }
            $conflicts[] = new ManagedFileConflict(
                $relative,
                $oldHash === null
                    ? ManagedFileConflict::UNMANAGED_COLLISION
                    : ManagedFileConflict::MODIFIED_MANAGED,
                $oldHash,
                $actualHash,
                $newHash
            );
        }

        foreach (array_diff_key($managedBefore, $nextFiles) as $relative => $oldHash) {
            $type = $installPaths->nodeType($relative);
            if ($type === SafePath::NODE_MISSING) {
                continue;
            }
            if ($type !== SafePath::NODE_FILE) {
                $conflicts[] = $this->nodeConflict($relative, $type, $oldHash, null);
                continue;
            }
            $actualHash = $installPaths->hash($relative);
            if ($actualHash === $oldHash) {
                $removals[] = new ReleaseFileOperation(
                    ReleaseFileOperation::REMOVE,
                    $relative,
                    SafePath::NODE_FILE,
                    $actualHash,
                    null
                );
            } else {
                $conflicts[] = new ManagedFileConflict(
                    $relative,
                    ManagedFileConflict::MODIFIED_OBSOLETE,
                    $oldHash,
                    $actualHash,
                    null
                );
            }
        }

        foreach ($installPaths->unexpectedTreeNodes('var/cache/blade') as $relative => $type) {
            $conflicts[] = new ManagedFileConflict(
                $relative,
                $type === SafePath::NODE_SYMLINK
                    ? ManagedFileConflict::SYMBOLIC_LINK
                    : ManagedFileConflict::UNSAFE_CACHE_NODE,
                null,
                null,
                null
            );
        }

        usort(
            $conflicts,
            static fn (ManagedFileConflict $left, ManagedFileConflict $right): int =>
                [$left->path, $left->classification] <=> [$right->path, $right->classification]
        );
        if ($conflicts !== []) {
            throw new ReleaseInstallationConflictException($conflicts);
        }

        return new ReleaseInstallationPlan($copies, $removals, $manifestNodeType, $manifestHash);
    }

    private function nodeConflict(
        string $relative,
        string $type,
        ?string $oldHash,
        ?string $newHash
    ): ManagedFileConflict {
        return new ManagedFileConflict(
            $relative,
            $type === SafePath::NODE_SYMLINK
                ? ManagedFileConflict::SYMBOLIC_LINK
                : ManagedFileConflict::UNEXPECTED_NODE,
            $oldHash,
            null,
            $newHash
        );
    }
}

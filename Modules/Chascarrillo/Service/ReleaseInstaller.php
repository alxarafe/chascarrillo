<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class ReleaseInstaller
{
    public function __construct(
        private readonly ReleaseValidator $validator = new ReleaseValidator(),
        private readonly ThemeAssetPublisher $assetPublisher = new ThemeAssetPublisher(),
        private readonly ReleaseInstallationPlanner $planner = new ReleaseInstallationPlanner()
    ) {
    }

    /**
     * @return array{copied:int,removed:int,preserved:int,cache_removed:int,assets:array<string,mixed>}
     */
    public function install(string $releaseRoot, string $installRoot, ?string $releaseTag = null): array
    {
        $releasePaths = new SafePath($releaseRoot);
        $installPaths = new SafePath($installRoot);
        $releaseRoot = $releasePaths->root();
        $installRoot = $installPaths->root();

        $releasePaths->requireFile(ManagedFileManifest::FILENAME);
        $next = ManagedFileManifest::load($releaseRoot);
        foreach (array_keys($next['files']) as $relative) {
            $releasePaths->requireFile($relative);
        }
        $releasePaths->requireFile('composer.lock');
        $releasePaths->requireFile('vendor/composer/installed.json');
        $releasePaths->requireDirectory('vendor');
        $releasePaths->requireDirectory('public_html');
        foreach (ReleaseValidator::ESSENTIAL_TEMPLATES as $relative) {
            $releasePaths->requireFile($relative);
        }
        $this->validator->validate($releaseRoot, true, true, $releaseTag);

        $manifestNodeType = $installPaths->nodeType(ManagedFileManifest::FILENAME);
        if (!in_array($manifestNodeType, [SafePath::NODE_MISSING, SafePath::NODE_FILE], true)) {
            $conflict = new ManagedFileConflict(
                ManagedFileManifest::FILENAME,
                $manifestNodeType === SafePath::NODE_SYMLINK
                    ? ManagedFileConflict::SYMBOLIC_LINK
                    : ManagedFileConflict::UNEXPECTED_NODE,
                null,
                null,
                null
            );
            throw new ReleaseInstallationConflictException([$conflict]);
        }
        $previous = $manifestNodeType === SafePath::NODE_FILE
            ? ManagedFileManifest::load($installRoot, true, true)
            : null;
        $manifestHash = $manifestNodeType === SafePath::NODE_FILE
            ? $installPaths->hash(ManagedFileManifest::FILENAME)
            : null;
        $plan = $this->planner->plan(
            $installPaths,
            $next['files'],
            $previous['files'] ?? null,
            $manifestNodeType,
            $manifestHash
        );

        // Close the preflight-to-apply window before making the first change.
        $plan->assertPreconditions($installPaths);
        $copied = 0;
        foreach ($plan->copies() as $operation) {
            $operation->assertPrecondition($installPaths);
            $installPaths->atomicCopyFrom($releasePaths, $operation->path, $operation->path);
            $destination = $installPaths->requireFile($operation->path);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($destination, true);
            }
            $copied++;
        }

        $removed = 0;
        foreach ($plan->removals() as $operation) {
            $operation->assertPrecondition($installPaths);
            $installPaths->unlinkFile($operation->path);
            $parent = dirname($operation->path);
            if ($parent !== '.') {
                $installPaths->removeEmptyParents($parent);
            }
            $removed++;
        }

        $assets = $this->assetPublisher->publish($installRoot, $installRoot . '/public_html');
        $cacheRemoved = $this->clearBladeCache($installRoot);

        foreach ($next['files'] as $relative => $expectedHash) {
            if (!$installPaths->isFile($relative) || $installPaths->hash($relative) !== $expectedHash) {
                throw new RuntimeException("La verificación final falló para {$relative}");
            }
        }

        $this->validator->validate($installRoot, false);
        $plan->assertManifestPrecondition($installPaths);
        $installPaths->atomicWrite(ManagedFileManifest::FILENAME, ManagedFileManifest::encode($next));

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return [
            'copied' => $copied,
            'removed' => $removed,
            'preserved' => 0,
            'cache_removed' => $cacheRemoved,
            'assets' => $assets,
        ];
    }

    public function clearBladeCache(string $installRoot): int
    {
        return (new SafePath($installRoot))->removeTree('var/cache/blade');
    }
}

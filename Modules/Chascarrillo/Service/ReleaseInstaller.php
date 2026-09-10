<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class ReleaseInstaller
{
    private ?SafePath $pendingInstallPaths = null;
    private ?ReleaseInstallationPlan $pendingPlan = null;
    private ?string $pendingManifest = null;

    /** @var array<string,string>|null */
    private ?array $pendingFileHashes = null;

    public function __construct(
        private readonly ReleaseValidator $validator = new ReleaseValidator(),
        private readonly ThemeAssetPublisher $assetPublisher = new ThemeAssetPublisher(),
        private readonly ReleaseInstallationPlanner $planner = new ReleaseInstallationPlanner()
    ) {
    }

    /**
     * @return array{copied:int,removed:int,preserved:int,cache_removed:int,assets:array<string,mixed>}
     */
    public function install(
        string $releaseRoot,
        string $installRoot,
        ?string $releaseTag = null,
        ?callable $phaseObserver = null
    ): array {
        $result = $this->prepareInstallation($releaseRoot, $installRoot, $releaseTag, $phaseObserver);
        $this->promotePreparedManifest($phaseObserver);
        return $result;
    }

    /**
     * @return array{copied:int,removed:int,preserved:int,cache_removed:int,assets:array<string,mixed>}
     */
    public function prepareInstallation(
        string $releaseRoot,
        string $installRoot,
        ?string $releaseTag = null,
        ?callable $phaseObserver = null
    ): array {
        $this->discardPreparedManifest();
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
        if ($phaseObserver !== null) {
            $phaseObserver(ReleaseUpdateState::PHASE_INSTALLING_FILES, true);
        }
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

        if ($phaseObserver !== null) {
            $phaseObserver(ReleaseUpdateState::PHASE_PUBLISHING_CLEANUP, true);
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

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        $this->pendingInstallPaths = $installPaths;
        $this->pendingPlan = $plan;
        $this->pendingManifest = ManagedFileManifest::encode($next);
        $this->pendingFileHashes = $next['files'];

        return [
            'copied' => $copied,
            'removed' => $removed,
            'preserved' => 0,
            'cache_removed' => $cacheRemoved,
            'assets' => $assets,
        ];
    }

    public function validatePreparedInstallation(): void
    {
        [$installPaths, $plan, , $fileHashes] = $this->requirePreparedManifest();
        foreach ($fileHashes as $relative => $expectedHash) {
            if (!$installPaths->isFile($relative) || $installPaths->hash($relative) !== $expectedHash) {
                throw new RuntimeException("La verificación final falló para {$relative}");
            }
        }
        $this->validator->validate($installPaths->root(), false);
        $plan->assertManifestPrecondition($installPaths);
    }

    public function promotePreparedManifest(?callable $phaseObserver = null): void
    {
        [$installPaths, $plan, $manifest] = $this->requirePreparedManifest();
        $this->discardPreparedManifest();
        $plan->assertManifestPrecondition($installPaths);
        if ($phaseObserver !== null) {
            $phaseObserver(ReleaseUpdateState::PHASE_PROMOTING_MANIFEST, true);
        }
        $installPaths->atomicWrite(ManagedFileManifest::FILENAME, $manifest);
    }

    public function discardPreparedManifest(): void
    {
        $this->pendingInstallPaths = null;
        $this->pendingPlan = null;
        $this->pendingManifest = null;
        $this->pendingFileHashes = null;
    }

    public function clearBladeCache(string $installRoot): int
    {
        return (new SafePath($installRoot))->removeTree('var/cache/blade');
    }

    /**
     * @return array{SafePath,ReleaseInstallationPlan,string,array<string,string>}
     */
    private function requirePreparedManifest(): array
    {
        if (
            $this->pendingInstallPaths === null
            || $this->pendingPlan === null
            || $this->pendingManifest === null
            || $this->pendingFileHashes === null
        ) {
            throw new RuntimeException('No hay una instalación validada pendiente de promoción');
        }
        return [
            $this->pendingInstallPaths,
            $this->pendingPlan,
            $this->pendingManifest,
            $this->pendingFileHashes,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class ReleaseInstaller
{
    /**
     * Files shipped by old releases before the distribution manifest existed.
     * They are removed only when their exact known hash is still present.
     *
     * @var array<string,string>
     */
    private const LEGACY_FILES = [
        'templates/partial/user_menu.blade.php' => '641721c80f976cf66d9b52e46b83fac04423003e237b8ae4ca24b1eb51c2fe65',
        'public_html/themes/alternative/form/boolean.blade.php' => '71f01e423395606fba45869fd94e3e74e9f69ce1c19afca61ef667403475ecbd',
        'public_html/themes/alternative/form/select.blade.php' => '506eea16fc6f9a9fca1fba98310db7d645b88df4ca641c9df65791e7c15daac3',
        'public_html/themes/alternative/component/fields/edit/boolean.blade.php' => '0bb55a7619464ce48657b5bbe438234cd7302c479b5c32cb5b49fa9f65cbc80a',
        'public_html/themes/alternative/component/fields/list/boolean.blade.php' => '93b0ef4be22c6a7a1fd33267cab066ab68d7a82957f677cf66debcb245d082fb',
        'public_html/themes/high-contrast/component/card.blade.php' => '856af325711275320c7c2ba96fab52ab533f7742dc40cfbc4f9c48892eb95246',
    ];

    public function __construct(
        private readonly ReleaseValidator $validator = new ReleaseValidator(),
        private readonly ThemeAssetPublisher $assetPublisher = new ThemeAssetPublisher()
    ) {
    }

    /**
     * @return array{copied:int,removed:int,preserved:int,cache_removed:int,assets:array<string,mixed>}
     */
    public function install(string $releaseRoot, string $installRoot): array
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
        $this->validator->validate($releaseRoot, true, true);

        $installPaths->exists(ManagedFileManifest::FILENAME);
        $previous = ManagedFileManifest::load($installRoot, false);
        $copied = 0;
        foreach ($next['files'] as $relative => $expectedHash) {
            if ($releasePaths->hash($relative) !== $expectedHash) {
                throw new RuntimeException("Archivo ausente o alterado en el paquete: {$relative}");
            }
            if ($installPaths->isFile($relative) && $installPaths->hash($relative) === $expectedHash) {
                continue;
            }
            $installPaths->atomicCopyFrom($releasePaths, $relative, $relative);
            $destination = $installPaths->requireFile($relative);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($destination, true);
            }
            $copied++;
        }

        $removed = 0;
        $preserved = 0;
        foreach (array_diff_key($previous['files'], $next['files']) as $relative => $oldHash) {
            $this->removeManagedFile($installPaths, $relative, $oldHash, $removed, $preserved);
        }
        foreach (self::LEGACY_FILES as $relative => $knownHash) {
            if (!isset($next['files'][$relative])) {
                $this->removeManagedFile($installPaths, $relative, $knownHash, $removed, $preserved);
            }
        }

        $assets = $this->assetPublisher->publish($installRoot, $installRoot . '/public_html');
        $cacheRemoved = $this->clearBladeCache($installRoot);

        foreach ($next['files'] as $relative => $expectedHash) {
            if (!$installPaths->isFile($relative) || $installPaths->hash($relative) !== $expectedHash) {
                throw new RuntimeException("La verificación final falló para {$relative}");
            }
        }

        $this->validator->validate($installRoot, false);
        $installPaths->atomicWrite(ManagedFileManifest::FILENAME, ManagedFileManifest::encode($next));

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return [
            'copied' => $copied,
            'removed' => $removed,
            'preserved' => $preserved,
            'cache_removed' => $cacheRemoved,
            'assets' => $assets,
        ];
    }

    public function clearBladeCache(string $installRoot): int
    {
        return (new SafePath($installRoot))->removeTree('var/cache/blade');
    }

    private function removeManagedFile(
        SafePath $paths,
        string $relative,
        string $knownHash,
        int &$removed,
        int &$preserved
    ): void {
        if (!ManagedFileManifest::isSafeRelativePath($relative) || ManagedFileManifest::isProtected($relative)) {
            throw new RuntimeException("El manifiesto intentó retirar una ruta protegida: {$relative}");
        }
        if (!$paths->isFile($relative)) {
            return;
        }
        if ($paths->hash($relative) !== $knownHash) {
            $preserved++;
            return;
        }
        $paths->unlinkFile($relative);
        $parent = dirname($relative);
        if ($parent !== '.') {
            $paths->removeEmptyParents($parent);
        }
        $removed++;
    }
}

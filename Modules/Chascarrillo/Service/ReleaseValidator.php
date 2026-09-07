<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class ReleaseValidator
{
    public const ALXARAFE_PACKAGE = 'alxarafe/alxarafe';

    /** @var list<string> */
    public const ESSENTIAL_TEMPLATES = [
        'vendor/alxarafe/alxarafe/templates/partial/layout/main.blade.php',
        'vendor/alxarafe/alxarafe/templates/partial/body_standard.blade.php',
        'vendor/alxarafe/alxarafe/templates/partial/body_empty.blade.php',
        'vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php',
        'vendor/alxarafe/alxarafe/templates/partial/lang_switcher.blade.php',
        'vendor/alxarafe/alxarafe/templates/partial/theme_switcher.blade.php',
        'vendor/alxarafe/alxarafe/templates/partial/project_menu.blade.php',
    ];

    /** @var list<string> */
    private const ALLOWED_UNMANAGED_FILES = [ManagedFileManifest::FILENAME];

    /**
     * @return array{version:string,reference:string}
     */
    public function validate(
        string $root,
        bool $requireManifest = true,
        bool $strictArtifact = false,
        ?string $tag = null
    ): array {
        $root = rtrim($root, '/');
        if (!is_dir($root . '/vendor') || !is_dir($root . '/public_html')) {
            throw new RuntimeException('Paquete incompleto: deben existir vendor/ y public_html/');
        }

        $locked = $this->lockedAlxarafe($root . '/composer.lock');
        $installed = $this->installedAlxarafe($root . '/vendor/composer/installed.json');
        if ($installed['version'] !== $locked['version']) {
            throw new RuntimeException(
                "Alxarafe instalado ({$installed['version']}) no coincide con composer.lock ({$locked['version']})"
            );
        }
        if ($installed['reference'] !== $locked['reference']) {
            throw new RuntimeException(
                "La referencia instalada de Alxarafe ({$installed['reference']}) no coincide con composer.lock ({$locked['reference']})"
            );
        }

        foreach (self::ESSENTIAL_TEMPLATES as $relative) {
            if (!is_file($root . '/' . $relative)) {
                throw new RuntimeException("Falta la plantilla esencial {$relative}");
            }
        }

        $menu = (string) file_get_contents(
            $root . '/vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php'
        );
        $requirements = [
            'clock-display' => 'reloj',
            'controller=Auth' => 'login',
            "partial.lang_switcher" => 'selector de idioma',
            "partial.theme_switcher" => 'selector de tema',
            'Auth::$user' => 'menú autenticado',
        ];
        foreach ($requirements as $needle => $label) {
            if (!str_contains($menu, $needle)) {
                throw new RuntimeException("El user_menu de Alxarafe no contiene {$label}");
            }
        }

        $publicBlades = glob($root . '/public_html/**/*.blade.php', GLOB_BRACE) ?: [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/public_html', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $publicBlades[] = $file->getPathname();
            }
        }
        if ($publicBlades !== []) {
            throw new RuntimeException('Hay plantillas Blade dentro de public_html: ' . $publicBlades[0]);
        }

        if ($strictArtifact && !$requireManifest) {
            throw new RuntimeException('La validación estricta del artefacto requiere manifiesto');
        }
        if ($requireManifest) {
            $paths = new SafePath($root);
            $paths->requireFile(ManagedFileManifest::FILENAME);
            $manifest = ManagedFileManifest::load($root);
            $canonicalVersion = ApplicationVersion::fromRoot($root);
            ApplicationVersion::assertManifestMatches($manifest['application_version'], $canonicalVersion);
            ApplicationVersion::assertTagMatches($tag, $manifest['application_version']);
            foreach ($manifest['files'] as $relative => $hash) {
                if (!$paths->isFile($relative) || $paths->hash($relative) !== $hash) {
                    throw new RuntimeException("El manifiesto no coincide con {$relative}");
                }
            }
            if ($strictArtifact) {
                $this->validateArtifactInventory($paths, $manifest['files']);
            }
        }

        return $locked;
    }

    /** @param array<string,string> $managed */
    private function validateArtifactInventory(SafePath $paths, array $managed): void
    {
        foreach ($paths->regularFiles() as $relative) {
            if (isset($managed[$relative])) {
                continue;
            }
            if (in_array($relative, self::ALLOWED_UNMANAGED_FILES, true)) {
                continue;
            }
            if (ManagedFileManifest::isProtected($relative)) {
                throw new RuntimeException("El artefacto contiene una ruta protegida: {$relative}");
            }
            throw new RuntimeException("El artefacto contiene un archivo no administrado: {$relative}");
        }
    }

    /** @return array{version:string,reference:string} */
    public function lockedAlxarafe(string $lockFile): array
    {
        $data = $this->readJson($lockFile);
        foreach ($data['packages'] ?? [] as $package) {
            if (($package['name'] ?? '') === self::ALXARAFE_PACKAGE) {
                $reference = (string) ($package['source']['reference'] ?? $package['dist']['reference'] ?? '');
                if ($reference === '') {
                    throw new RuntimeException('composer.lock no fija la referencia de Alxarafe');
                }
                return ['version' => (string) $package['version'], 'reference' => $reference];
            }
        }
        throw new RuntimeException('composer.lock no contiene alxarafe/alxarafe');
    }

    /** @return array{version:string,reference:string} */
    private function installedAlxarafe(string $installedFile): array
    {
        $data = $this->readJson($installedFile);
        $packages = isset($data['packages']) ? $data['packages'] : $data;
        foreach ($packages as $package) {
            if (($package['name'] ?? '') === self::ALXARAFE_PACKAGE) {
                $reference = (string) ($package['source']['reference'] ?? $package['dist']['reference'] ?? '');
                return ['version' => (string) $package['version'], 'reference' => $reference];
            }
        }
        throw new RuntimeException('vendor no contiene metadatos de alxarafe/alxarafe');
    }

    /** @return array<mixed> */
    private function readJson(string $filename): array
    {
        if (!is_file($filename)) {
            throw new RuntimeException("No existe {$filename}");
        }
        $data = json_decode((string) file_get_contents($filename), true);
        if (!is_array($data)) {
            throw new RuntimeException("JSON no válido: {$filename}");
        }
        return $data;
    }
}

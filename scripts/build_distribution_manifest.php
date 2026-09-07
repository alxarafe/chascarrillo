#!/usr/bin/env php
<?php

declare(strict_types=1);

use Modules\Chascarrillo\Service\ManagedFileManifest;
use Modules\Chascarrillo\Service\ApplicationVersion;

$appRoot = realpath(__DIR__ . '/..');
if ($appRoot === false) {
    fwrite(STDERR, "No se pudo determinar la raíz de Chascarrillo.\n");
    exit(1);
}
require $appRoot . '/vendor/autoload.php';

try {
    $tag = null;
    if (isset($argv[1])) {
        if ($argv[1] !== '--tag' || !isset($argv[2]) || isset($argv[3])) {
            throw new RuntimeException('Uso: build_distribution_manifest.php [--tag TAG]');
        }
        $tag = $argv[2];
    }
    $version = ApplicationVersion::canonical();
    ApplicationVersion::assertTagMatches($tag, $version);
    $manifest = ManagedFileManifest::generate($appRoot);
    ManagedFileManifest::write($appRoot, $manifest);
    printf(
        "Manifiesto %s generado para %s con %d archivos.\n",
        ManagedFileManifest::FILENAME,
        $version,
        count($manifest['files'])
    );
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error al generar el manifiesto: ' . $exception->getMessage() . "\n");
    exit(1);
}

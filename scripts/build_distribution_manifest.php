#!/usr/bin/env php
<?php

declare(strict_types=1);

use Modules\Chascarrillo\Service\ManagedFileManifest;
use Modules\Chascarrillo\Service\UpdateService;

$appRoot = realpath(__DIR__ . '/..');
if ($appRoot === false) {
    fwrite(STDERR, "No se pudo determinar la raíz de Chascarrillo.\n");
    exit(1);
}
require $appRoot . '/vendor/autoload.php';

$version = $argv[1] ?? UpdateService::VERSION;
try {
    $manifest = ManagedFileManifest::generate($appRoot, $version);
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

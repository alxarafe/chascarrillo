#!/usr/bin/env php
<?php

declare(strict_types=1);

use Modules\Chascarrillo\Service\ReleaseValidator;

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "No se pudo determinar la raíz del proyecto.\n");
    exit(1);
}
require $projectRoot . '/vendor/autoload.php';

$rootArgument = isset($argv[1]) && $argv[1] !== '--artifact' ? $argv[1] : null;
$root = $rootArgument !== null ? realpath($rootArgument) : $projectRoot;
$strictArtifact = in_array('--artifact', $argv, true);
if ($root === false) {
    fwrite(STDERR, "No existe la raíz de release indicada.\n");
    exit(1);
}

try {
    $alxarafe = (new ReleaseValidator())->validate($root, true, $strictArtifact);
    printf("Release válida. Alxarafe %s, referencia %s.\n", $alxarafe['version'], $alxarafe['reference']);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Release no válida: ' . $exception->getMessage() . "\n");
    exit(1);
}

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

/** @var list<string> $argv */
$rootArgument = null;
$tag = null;
$strictArtifact = false;
for ($index = 1; $index < count($argv); $index++) {
    $argument = $argv[$index];
    if ($argument === '--artifact') {
        $strictArtifact = true;
        continue;
    }
    if ($argument === '--tag') {
        if (!isset($argv[++$index])) {
            fwrite(STDERR, "Falta el valor de --tag.\n");
            exit(1);
        }
        $tag = $argv[$index];
        continue;
    }
    if (str_starts_with($argument, '--') || $rootArgument !== null) {
        fwrite(STDERR, "Uso: validate_release.php [raíz] [--artifact] [--tag TAG]\n");
        exit(1);
    }
    $rootArgument = $argument;
}
$root = $rootArgument !== null ? realpath($rootArgument) : $projectRoot;
if ($root === false) {
    fwrite(STDERR, "No existe la raíz de release indicada.\n");
    exit(1);
}

try {
    $alxarafe = (new ReleaseValidator())->validate($root, true, $strictArtifact, $tag);
    printf("Release válida. Alxarafe %s, referencia %s.\n", $alxarafe['version'], $alxarafe['reference']);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Release no válida: ' . $exception->getMessage() . "\n");
    exit(1);
}

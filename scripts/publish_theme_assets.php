#!/usr/bin/env php
<?php

/**
 * Publish Theme Assets
 *
 * Copies CSS/JS/img/fonts from framework themes (vendor/alxarafe/alxarafe/templates/themes/)
 * and app themes (templates/themes/) to the public web directory (public_html/themes/).
 *
 * Only static assets are published (css, js, img, fonts, assets).
 * Blade templates (.blade.php) are NOT copied — they stay in templates/.
 */

$appRoot = realpath(__DIR__ . '/..');
if ($appRoot === false) {
    fwrite(STDERR, "No se pudo determinar la raíz de Chascarrillo.\n");
    exit(1);
}

require $appRoot . '/vendor/autoload.php';

use Modules\Chascarrillo\Service\ThemeAssetPublisher;

try {
    $result = (new ThemeAssetPublisher())->publish($appRoot);
    printf(
        "Assets publicados: %d copiados, %d obsoletos retirados, %d personalizados preservados.\n",
        $result['copied'],
        $result['removed'],
        $result['preserved']
    );
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error al publicar assets: ' . $exception->getMessage() . "\n");
    exit(1);
}

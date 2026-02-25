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

$appRoot = realpath(__DIR__ . '/../');
$publicDir = $appRoot . '/public_html';
$targetBase = $publicDir . '/themes';

$sources = [
    // Framework themes (lower priority — copied first)
    $appRoot . '/vendor/alxarafe/alxarafe/templates/themes',
    // App themes (higher priority — overwrite framework assets if same name)
    $appRoot . '/templates/themes',
];

$assetFolders = ['css', 'js', 'img', 'fonts', 'assets'];

echo "Publishing theme assets to: {$targetBase}\n";

foreach ($sources as $sourceBase) {
    if (!is_dir($sourceBase)) {
        echo "  [SKIP] Source not found: {$sourceBase}\n";
        continue;
    }

    $themes = array_diff(scandir($sourceBase), ['.', '..']);
    foreach ($themes as $theme) {
        $themeSource = $sourceBase . '/' . $theme;
        if (!is_dir($themeSource)) {
            continue;
        }

        foreach ($assetFolders as $folder) {
            $src = $themeSource . '/' . $folder;
            if (!is_dir($src)) {
                continue;
            }

            $dst = $targetBase . '/' . $theme . '/' . $folder;
            if (!is_dir($dst)) {
                mkdir($dst, 0755, true);
            }

            echo "  [COPY] {$theme}/{$folder}\n";
            recursiveCopy($src, $dst);
        }
    }
}

echo "Done.\n";

function recursiveCopy(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }

    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;

        if (is_dir($srcPath)) {
            recursiveCopy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

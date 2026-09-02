<?php

declare(strict_types=1);

/*
 * Copyright (C) 2024-2026 Rafael San José <rsanjose@alxarafe.com>
 */

namespace Modules\Chascarrillo\Service;

/**
 * Gestión de referencias a archivos en los documentos Markdown.
 *
 * En el Markdown (y en la base de datos) los archivos se referencian con el
 * token @path/ seguido de la ruta relativa bajo public_html/uploads.
 * La única conversión existe al renderizar: @path/ -> UPLOADS_URL.
 * Así la importación/exportación no transforma nada y no hay errores de ida y vuelta.
 *
 *   Ej.: @path/images/portada.png  ->  /uploads/images/portada.png
 *
 * Los archivos se guardan en una carpeta espejo de uploads dentro de Content:
 *   Content/import/files/images/portada.png
 *   Content/export/files/images/portada.png
 */
final class ContentFilePath
{
    /**
     * Token que identifica la raíz de subida en el Markdown.
     */
    public const TOKEN = '@path';

    /**
     * URL pública a la que resuelve el token.
     */
    public const UPLOADS_URL = '/uploads/';

    /**
     * Carpeta espejo de uploads dentro de Content/import y Content/export.
     */
    public const FILES_DIR = 'files';

    /**
     * Resuelve el token @path/ a UPLOADS_URL en un texto.
     * Es la única conversión del sistema; el resto de direccionalidad conserva el literal.
     */
    public static function resolve(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }
        return str_replace(self::TOKEN . '/', self::UPLOADS_URL, $text);
    }

    /**
     * Directorio espejo de archivos en Content/import.
     */
    public static function importFilesDir(): string
    {
        return constant('APP_PATH') . '/Content/import/' . self::FILES_DIR;
    }

    /**
     * Directorio espejo de archivos en Content/export.
     */
    public static function exportFilesDir(): string
    {
        return constant('APP_PATH') . '/Content/export/' . self::FILES_DIR;
    }

    /**
     * Directorio físico de subida (public_html/uploads).
     */
    public static function uploadsBaseDir(): string
    {
        return (defined('BASE_PATH') ? constant('BASE_PATH') : constant('APP_PATH') . '/public_html') . '/uploads';
    }

    /**
     * Extrae las rutas relativas referenciadas con el token @path/ en un texto.
     * Busca solo referencias en paréntesis de Markdown `(@path/...)` y entre
     * comillas `"@path/..."` / `'@path/...'`, que es como se usan en los documentos.
     *
     * @return string[] Rutas relativas únicas (p.ej. 'images/portada.png')
     */
    public static function extractReferences(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        $paths = [];
        preg_match_all('/\(@path\/([^)]+)\)/', $text, $matchParens);
        preg_match_all('/"@path\/([^"]+)"/', $text, $matchDouble);
        preg_match_all("/'@path\/([^']+)'/", $text, $matchSingle);

        foreach (array_merge($matchParens[1], $matchDouble[1], $matchSingle[1]) as $path) {
            $path = trim($path);
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Tipo de medio a partir de una ruta relativa (images/ -> image, videos/ -> video, ...).
     */
    public static function mediaTypeFor(string $relativePath): string
    {
        if (preg_match('#^images?/#', $relativePath)) {
            return 'image';
        }
        if (preg_match('#^videos?/#', $relativePath)) {
            return 'video';
        }
        if (preg_match('#^audios?/#', $relativePath)) {
            return 'audio';
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        return match (true) {
            in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'avif', 'bmp', 'ico'], true) => 'image',
            in_array($extension, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'], true) => 'video',
            in_array($extension, ['mp3', 'wav', 'flac', 'aac', 'm4a', 'opus'], true) => 'audio',
            default => 'document',
        };
    }
}
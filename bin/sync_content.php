<?php

/**
 * Script de sincronización de contenido Markdown.
 * Utiliza SyncService para procesar Content/import (pages, posts) y multimedia.
 * Reconstruye el contenido en Content/export con solo los archivos referenciados.
 */

define('BASE_PATH', __DIR__ . '/../public_html');

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_PATH')) {
    define('APP_PATH', realpath(__DIR__ . '/../'));
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', APP_PATH . '/public_html');
}

// Inicializar Alxarafe
$config = \Alxarafe\Infrastructure\Persistence\Config::getConfig();
if (!$config || !isset($config->db)) {
    die("Error: No se pudo cargar la configuración de la base de datos.\n");
}

\Alxarafe\Infrastructure\Persistence\Database::createConnection($config->db);

use Modules\Chascarrillo\Service\SyncService;

echo "--- Iniciando Sincronización Completa ---\n";

$results = SyncService::syncAll();

if ($results['success']) {
    echo "Páginas: Procesadas: {$results['pages']['processed']}, Creadas: {$results['pages']['created']}, Actualizadas: {$results['pages']['updated']}\n";
    echo "Posts: Procesados: {$results['posts']['processed']}, Creados: {$results['posts']['created']}, Actualizados: {$results['posts']['updated']}\n";
    echo "Recursos multimedia sincronizados: {$results['assets']}\n";
    echo "Menú principal sincronizado.\n";
    echo "\nSincronización finalizada con éxito.\n";
} else {
    echo "Error durante la sincronización: " . $results['error'] . "\n";
    exit(1);
}

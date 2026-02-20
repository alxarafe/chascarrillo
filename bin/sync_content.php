<?php

/**
 * Script de sincronización de contenido Markdown.
 * Utiliza SyncService para procesar Content/pages, Content/posts y multimedia.
 */

define('BASE_PATH', __DIR__ . '/../public_html');

require_once __DIR__ . '/../vendor/autoload.php';

// Inicializar Alxarafe
$config = \Alxarafe\Base\Config::getConfig();
if (!$config || !isset($config->db)) {
    die("Error: No se pudo cargar la configuración de la base de datos.\n");
}

define('APP_PATH', realpath(__DIR__ . '/../'));

\Alxarafe\Base\Database::createConnection($config->db);

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

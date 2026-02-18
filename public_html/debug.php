<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico de Error 500</h1>";
echo "<b>Versión PHP:</b> " . PHP_VERSION . "<br>";

$baseDir = realpath(__DIR__ . '/..');
echo "<b>Directorios:</b><br>";
echo "- Raíz Proyecto: $baseDir (" . (is_dir($baseDir) ? "OK" : "NO EXISTE") . ")<br>";
echo "- Carpeta Pública: " . __DIR__ . "<br>";

$filesToCheck = [
    '../config.json',
    'config.json',
    '../vendor/autoload.php',
    '../vendor/alxarafe/alxarafe/src/Core/Base/Config.php',
    '../Modules/Chascarrillo/Service/UpdateService.php'
];

echo "<b>Verificación de Archivos:</b><br>";
foreach ($filesToCheck as $file) {
    $path = realpath(__DIR__ . '/' . $file);
    echo "- $file: " . ($path ? "EXISTE en $path" : "NO ENCONTRADO") . "<br>";
}

if (file_exists($baseDir . '/config.json')) {
    echo "<b>Contenido de config.json (resumido):</b><br>";
    $config = json_decode(file_get_contents($baseDir . '/config.json'), true);
    if ($config) {
        echo "- DB Host: " . ($config['db']['host'] ?? 'N/A') . "<br>";
        echo "- DB Name: " . ($config['db']['name'] ?? 'N/A') . "<br>";
    } else {
        echo "- Error al leer JSON: " . json_last_error_msg() . "<br>";
    }
}

echo "<b>Intentando cargar autoload...</b><br>";
if (file_exists($baseDir . '/vendor/autoload.php')) {
    try {
        require $baseDir . '/vendor/autoload.php';
        echo "Autoload: OK<br>";
    } catch (Throwable $e) {
        echo "ERROR EN AUTOLOAD: " . $e->getMessage() . "<br>";
        echo "Línea: " . $e->getLine() . " en " . $e->getFile() . "<br>";
    }
}

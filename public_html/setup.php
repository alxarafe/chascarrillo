<?php

/**
 * Chascarrillo Setup & Update Bootstrapper
 * 
 * This file is designed to be uploaded as a single file to a hosting environment.
 * It will download the latest version of Chascarrillo from GitHub and configure it.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('SETUP_DIR', __DIR__);
define('TARGET_DIR', realpath(__DIR__ . '/..') ?: __DIR__);

// Try to find config.js in root or public
$possibleConfigs = [
    TARGET_DIR . '/config.json',
    SETUP_DIR . '/config.json'
];

$configFile = null;
foreach ($possibleConfigs as $path) {
    if (file_exists($path)) {
        $configFile = $path;
        break;
    }
}
define('CONFIG_FILE', $configFile ?? TARGET_DIR . '/config.json');
define('REPO_URL', 'https://api.github.com/repos/alxarafe/chascarrillo/releases/latest');
define('SELF_FILE', basename(__FILE__));
define('UNLOCK_FILE', SETUP_DIR . '/setup.unlock');

// --- 1. Load Existing Config ---
$existingConfig = null;
if (file_exists(CONFIG_FILE)) {
    $existingConfig = json_decode(file_get_contents(CONFIG_FILE), true);
}

// --- 2. Security Check ---
$isInstalled = ($existingConfig !== null);
$isUnlocked = file_exists(UNLOCK_FILE);

// The lock ONLY applies if the site is already installed.
// New installations are always unlocked.
$isLocked = ($isInstalled && !$isUnlocked);

// --- 3. Handle Installation Request ---
$error = null;
$success = false;
$logs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    if ($isLocked) {
        $error = "Acceso Denegado: El instalador está bloqueado por seguridad. Para proceder, cree un archivo vacío llamado 'setup.unlock' en la carpeta pública de su servidor.";
    } else {
        try {
            $dbHost = $_POST['db_host'] ?? 'localhost';
            $dbName = $_POST['db_name'] ?? '';
            $dbUser = $_POST['db_user'] ?? '';
            $dbPass = $_POST['db_pass'] ?? '';
            $dbPrefix = $_POST['db_prefix'] ?? 'alx_';
            $dbCharset = $_POST['db_charset'] ?? 'utf8mb4';
            $dbCollation = $_POST['db_collation'] ?? 'utf8mb4_unicode_ci';
            $publicFolder = $_POST['public_dir'] ?? basename(SETUP_DIR);

            // Validation
            if (empty($dbName) || empty($dbUser)) {
                throw new Exception("El nombre de la base de datos y el usuario son obligatorios.");
            }

            // --- Step 1: Download Latest Release ---
            $logs[] = "Consultando última versión en GitHub...";
            $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
            $releaseJson = null;
            $errorDetail = '';

            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, REPO_URL);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                $releaseJson = curl_exec($ch);
                $errorDetail = curl_error($ch);
                curl_close($ch);
            }

            if (!$releaseJson && ini_get('allow_url_fopen')) {
                $opts = ['http' => ['method' => 'GET', 'header' => ["User-Agent: $userAgent"], 'timeout' => 20]];
                $context = stream_context_create($opts);
                $releaseJson = @file_get_contents(REPO_URL, false, $context);
                if (!$releaseJson) {
                    $errorDetail = "file_get_contents falló";
                }
            }

            if (!$releaseJson) {
                throw new Exception("No se pudo conectar con GitHub. Error: " . ($errorDetail ?: "Acceso denegado por el servidor"));
            }

            $release = json_decode($releaseJson, true);
            $zipUrl = null;

            // Try to find a pre-built deploy package in assets (includes vendor)
            if (!empty($release['assets'])) {
                foreach ($release['assets'] as $asset) {
                    if (str_contains(strtolower($asset['name']), 'deploy') || str_contains(strtolower($asset['name']), 'full')) {
                        $zipUrl = $asset['browser_download_url'];
                        $logs[] = "¡Paquete optimizado detectado! Usando " . $asset['name'];
                        break;
                    }
                }
            }

            // Fallback to source zip if no asset found
            if (!$zipUrl) {
                $zipUrl = $release['zipball_url'] ?? null;
            }

            if (!$zipUrl) {
                throw new Exception("Error en respuesta de GitHub: No se encontró un archivo de descarga.");
            }

            $currentTag = $release['tag_name'] ?? 'actual';
            $logs[] = "Descargando versión $currentTag...";
            $zipData = null;

            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $zipUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $zipData = curl_exec($ch);
                curl_close($ch);
            } else if (ini_get('allow_url_fopen')) {
                $zipData = @file_get_contents($zipUrl, false, $context);
            }

            if (!$zipData) {
                throw new Exception("Error al descargar los archivos desde GitHub. El servidor de hosting podría estar bloqueando el acceso saliente.");
            }

            $tmpZip = TARGET_DIR . '/chascarrillo_temp.zip';
            file_put_contents($tmpZip, $zipData);

            // --- Step 2: Database Preparation ---
            $logs[] = "Preparando base de datos...";
            try {
                $dsn = "mysql:host=$dbHost;charset=$dbCharset";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET $dbCharset COLLATE $dbCollation");
                $logs[] = "Base de datos verificada/creada.";
            } catch (PDOException $e) {
                try {
                    $dsnDirect = "mysql:host=$dbHost;dbname=$dbName;charset=$dbCharset";
                    new PDO($dsnDirect, $dbUser, $dbPass);
                    $logs[] = "Conexión a base de datos existente confirmada.";
                } catch (PDOException $e2) {
                    throw new Exception("Error de base de datos: " . $e2->getMessage());
                }
            }

            // --- Step 3: Extract & Map ---
            $logs[] = "Extrayendo y mapeando archivos...";
            $zip = new ZipArchive();
            if ($zip->open($tmpZip) === true) {
                $extractTemp = TARGET_DIR . '/_temp_install';
                if (!is_dir($extractTemp)) mkdir($extractTemp);
                $zip->extractTo($extractTemp);
                $zip->close();
                unlink($tmpZip);

                $subfolders = array_diff(scandir($extractTemp), ['.', '..']);
                $sourcePath = $extractTemp . '/' . reset($subfolders);

                // 3.1 Identify public folder in ZIP
                $zipPublicFolder = 'public_html'; // Default in Chascarrillo
                if (!is_dir($sourcePath . '/' . $zipPublicFolder)) {
                    foreach (['public', 'public_html', 'www', 'web'] as $p) {
                        if (is_dir($sourcePath . '/' . $p)) {
                            $zipPublicFolder = $p;
                            break;
                        }
                    }
                }

                // 3.2 Copy core files (excluding setup.php and the ZIP's public folder)
                $skipList = ['config.json', '.env', 'setup.php', $zipPublicFolder];
                recursiveCopy($sourcePath, TARGET_DIR, $skipList);

                // 3.3 Copy public files specifically to SETUP_DIR
                if (is_dir($sourcePath . '/' . $zipPublicFolder)) {
                    recursiveCopy($sourcePath . '/' . $zipPublicFolder, SETUP_DIR, ['setup.php', '.htaccess']);
                }

                recursiveRmdir($extractTemp);
            } else {
                throw new Exception("El servidor no pudo abrir el archivo ZIP.");
            }

            // --- Step 4: Create/Update Config ---
            $logs[] = "Configurando base de datos...";
            $config = [
                'main' => [
                    'theme' => $existingConfig['main']['theme'] ?? 'alxarafe',
                    'language' => $existingConfig['main']['language'] ?? 'es',
                    'timezone' => $existingConfig['main']['timezone'] ?? 'Europe/Madrid'
                ],
                'db' => [
                    'type' => 'mysql',
                    'host' => $dbHost,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                    'port' => 3306,
                    'prefix' => $dbPrefix,
                    'charset' => $dbCharset,
                    'collation' => $dbCollation
                ]
            ];
            file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $logs[] = "¡Operación completada con éxito!";
            $success = true;

            // --- Step 5: Self-Destruction ---
            if (isset($_POST['self_delete'])) {
                @unlink(__FILE__);
            } else {
                @rename(__FILE__, SETUP_DIR . '/setup_' . time() . '.php.bak');
            }
            @unlink(UNLOCK_FILE);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// --- Helper Functions ---
function recursiveCopy($src, $dst, $skip = [])
{
    if (!is_dir($src)) return;
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (in_array($file, $skip)) continue;
            if (is_dir($src . '/' . $file)) {
                recursiveCopy($src . '/' . $file, $dst . '/' . $file, $skip);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function recursiveRmdir($dir)
{
    if (!is_dir($dir)) return false;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? recursiveRmdir("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chascarrillo Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-icons/6.4.0/css/all.min.css">
    <style>
        body {
            background: #0a0a0c;
            color: #e0e0e0;
            font-family: 'Segoe UI', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
        }

        .setup-card {
            background: #141417;
            border: 1px solid #2d2d33;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 500px;
            margin: auto;
            overflow: hidden;
        }

        .setup-header {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            padding: 30px;
            text-align: center;
        }

        .setup-header h1 {
            margin: 0;
            font-weight: 800;
            letter-spacing: -1px;
            font-size: 1.8rem;
            color: #fff;
        }

        .setup-body {
            padding: 30px;
        }

        .form-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #a0a0a8;
            margin-bottom: 5px;
        }

        .form-control {
            background: #1f1f23;
            border: 1px solid #3d3d45;
            color: #fff;
            padding: 12px;
        }

        .form-control:focus {
            background: #25252b;
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            color: #fff;
        }

        .form-text.text-muted,
        .text-muted {
            color: #b0b0b8 !important;
        }

        .form-check-input {
            background-color: #1f1f23;
            border-color: #4b4b55;
        }

        .form-check-input:checked {
            background-color: #6366f1;
            border-color: #6366f1;
        }

        .btn-install {
            background: #6366f1;
            border: none;
            padding: 12px;
            font-weight: 700;
            width: 100%;
            margin-top: 10px;
            transition: 0.3s;
        }

        .btn-install:hover {
            background: #4f46e5;
            transform: translateY(-2px);
        }

        .log-container {
            background: #000;
            border-radius: 6px;
            padding: 15px;
            font-family: monospace;
            font-size: 0.85rem;
            max-height: 200px;
            overflow-y: auto;
            color: #0f0;
            margin-top: 20px;
        }

        .error-alert {
            background: #441a1a;
            border-left: 4px solid #ef4444;
            color: #fca5a5;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="setup-card">
        <div class="setup-header">
            <h1>CHASCARRILLO_SETUP</h1>
            <p class="small text-white-50 mt-1 mb-0"><?php echo $isInstalled ? 'Actualización de Sistema' : 'Instalación Automática'; ?></p>
        </div>
        <div class="setup-body">
            <?php if ($success): ?>
                <div class="text-center">
                    <div class="display-1 text-success mb-3"><i class="fas fa-check-circle"></i></div>
                    <h3>¡Listo!</h3>
                    <p class="text-muted">Operación completada con éxito.</p>
                    <div class="log-container mb-4">
                        <?php foreach ($logs as $log) echo "<div>> " . htmlspecialchars($log) . "</div>"; ?>
                    </div>
                    <a href="index.php" class="btn btn-primary px-5 rounded-pill">Ir al Sitio</a>
                </div>
            <?php else: ?>
                <?php if ($isLocked): ?>
                    <div class="error-alert mb-4">
                        <h4 class="h6 mb-3 text-white"><i class="fas fa-lock me-2"></i> Instalador Bloqueado</h4>
                        <p class="small mb-0">Esta web ya está instalada. Para autorizar una actualización o cambios en la configuración, debe crear un archivo vacío llamado <code class="text-white">setup.unlock</code> en la carpeta pública de su servidor.</p>
                    </div>
                    <div class="text-center">
                        <p class="text-muted small">Por seguridad, el acceso a los datos de configuración está oculto hasta que se abra el candado.</p>
                        <a href="?" class="btn btn-outline-secondary btn-sm mt-3 px-4 rounded-pill">Refrescar tras desbloquear</a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="error-alert">
                            <strong>Atención:</strong> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="db_charset" value="<?php echo htmlspecialchars($existingConfig['db']['charset'] ?? 'utf8mb4'); ?>">
                        <input type="hidden" name="db_collation" value="<?php echo htmlspecialchars($existingConfig['db']['collation'] ?? 'utf8mb4_unicode_ci'); ?>">

                        <div class="mb-3">
                            <label class="form-label">Servidor DB</label>
                            <input type="text" name="db_host" class="form-control" value="<?php echo htmlspecialchars($existingConfig['db']['host'] ?? 'localhost'); ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Base Datos</label>
                                <input type="text" name="db_name" class="form-control" value="<?php echo htmlspecialchars($existingConfig['db']['name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Prefijo Tablas</label>
                                <input type="text" name="db_prefix" class="form-control" value="<?php echo htmlspecialchars($existingConfig['db']['prefix'] ?? 'alx_'); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Usuario DB</label>
                            <input type="text" name="db_user" class="form-control" value="<?php echo htmlspecialchars($existingConfig['db']['user'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña DB</label>
                            <input type="password" name="db_pass" class="form-control" value="<?php echo htmlspecialchars($existingConfig['db']['pass'] ?? ''); ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Nombre Carpeta Pública</label>
                            <input type="text" name="public_dir" class="form-control" value="<?php echo htmlspecialchars(basename(SETUP_DIR)); ?>">
                            <div class="form-text text-muted small">Detectada automáticamente: <strong><?php echo basename(SETUP_DIR); ?></strong></div>
                        </div>

                        <div class="form-check mb-4 small">
                            <input class="form-check-input" type="checkbox" name="self_delete" id="selfDelete" checked>
                            <label class="form-check-label text-muted" for="selfDelete">
                                Eliminar este instalador tras finalizar (Recomendado)
                            </label>
                        </div>

                        <button type="submit" name="install" class="btn btn-install text-white shadow">
                            <i class="fas fa-rocket me-2"></i> <?php echo $isInstalled ? 'AUTORIZAR ACTUALIZACIÓN' : 'INICIAR INSTALACIÓN'; ?>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
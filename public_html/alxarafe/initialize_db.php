<?php

/**
 * Alxarafe Database Initialization Screen
 * Beautifully prompts the user to initialize the database if it's missing.
 */

$dbName = $config->db->name ?? 'unknown';
$lang = $config->main->language ?? 'es';

$strings = [
    'en' => [
        'title' => 'Initial Setup',
        'heading' => 'Initialize Database',
        'subheading' => 'It looks like this is the first execution or the database is not initialized. Shall we set up the tables for you?',
        'info' => 'Database',
        'button' => 'Run Migrations & Seeders',
        'footer' => 'Already ran migrations?',
        'refresh' => 'Click here to refresh'
    ],
    'es' => [
        'title' => 'Configuración Inicial',
        'heading' => 'Inicializar Base de Datos',
        'subheading' => 'Parece que es la primera vez que se ejecuta el sistema o la base de datos no está inicializada. ¿Quieres que preparemos las tablas por ti?',
        'info' => 'Base de Datos',
        'button' => 'Ejecutar Migraciones y Datos Iniciales',
        'footer' => '¿Ya has ejecutado las migraciones?',
        'refresh' => 'Haz clic aquí para refrescar'
    ]
];

$t = $strings[$lang] ?? $strings['en'];

?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chascarrillo | <?php echo $t['title']; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --border: #334155;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 500px;
            padding: 20px;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border);
            padding: 40px;
            text-align: center;
            transform: translateY(0);
            transition: transform 0.3s ease;
        }

        .icon-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
            color: white;
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);
        }

        h1 {
            font-size: 24px;
            font-weight: 800;
            margin: 0 0 12px;
            letter-spacing: -0.025em;
        }

        p {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 32px;
            font-size: 15px;
        }

        .db-info {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            padding: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            margin-bottom: 32px;
            color: #818cf8;
            border: 1px dashed var(--border);
        }

        .btn {
            display: inline-block;
            background-color: var(--primary);
            color: white;
            font-weight: 600;
            padding: 12px 32px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }

        .btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .footer {
            margin-top: 24px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .footer a {
            color: var(--primary);
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="icon-circle">
                <i class="fas fa-magic"></i>
            </div>
            <h1><?php echo $t['heading']; ?></h1>
            <p><?php echo $t['subheading']; ?></p>
            
            <div class="db-info">
                <i class="fas fa-info-circle me-1"></i> <?php echo $t['info']; ?>: <strong><?php echo htmlspecialchars($dbName); ?></strong>
            </div>

            <form method="POST">
                <input type="hidden" name="alx_initialize_database" value="1">
                <button type="submit" class="btn">
                    <?php echo $t['button']; ?>
                </button>
            </form>

            <div class="footer">
                <?php echo $t['footer']; ?> <a href="?reload"><?php echo $t['refresh']; ?></a>
            </div>
        </div>
    </div>
</body>
</html>

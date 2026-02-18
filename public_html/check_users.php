<?php
define('BASE_PATH', __DIR__);
require __DIR__ . '/../vendor/autoload.php';

use Alxarafe\Base\Config;
use Alxarafe\Base\Database;
use Illuminate\Support\Facades\Schema;
use CoreModules\Admin\Model\User;

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Diagnóstico de Usuarios</h3>";

$config = Config::getConfig();
if (!$config) {
    die("Error: No se encontró config.json");
}

try {
    $capsule = Database::createConnection($config->db);
    echo "Conexión DB: OK<br>";

    if (!$capsule->schema()->hasTable('users')) {
        die("Error: La tabla 'users' no existe.");
    }
    echo "Tabla 'users': OK<br>";

    $count = User::count();
    echo "Usuarios actuales: $count<br>";

    if ($count === 0) {
        echo "Intentando crear admin...<br>";
        $admin = new User();
        $admin->name = 'admin';
        $admin->email = 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $admin->password = password_hash('password', PASSWORD_DEFAULT);
        $admin->is_admin = true;

        if ($admin->save()) {
            echo "<b>¡Usuario admin creado con éxito!</b><br>";
            echo "Login: admin / password<br>";
        } else {
            echo "Error al guardar el usuario.<br>";
        }
    } else {
        echo "No es necesario crear usuario, ya existen registros.<br>";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/vendor/autoload.php';

define('BASE_PATH', __DIR__ . '/public');
define('APP_PATH', __DIR__);

$config = \Alxarafe\Base\Config::getConfig();

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => $config->db->type ?? 'mysql',
    'host'      => $config->db->host,
    'database'  => $config->db->name,
    'username'  => $config->db->user,
    'password'  => $config->db->pass,
    'charset'   => $config->db->charset ?? 'utf8mb4',
    'collation' => $config->db->collation ?? 'utf8mb4_unicode_ci',
    'prefix'    => $config->db->prefix ?? '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "Starting sync...\n";
$postsDir = APP_PATH . '/src/posts';
$results = \Alxarafe\Service\MarkdownSyncService::sync($postsDir, \Modules\Chascarrillo\Model\Post::class);

echo "Results:\n";
print_r($results);

echo "Running Manual Seeder...\n";
if (class_exists('Modules\Chascarrillo\Model\Seed\PostSeeder')) {
    new \Modules\Chascarrillo\Model\Seed\PostSeeder();
} else {
    echo "PostSeeder class not found.\n";
}

// Set all synced posts to published
\Modules\Chascarrillo\Model\Post::where('is_published', 0)->update([
    'is_published' => 1,
    'published_at' => \Carbon\Carbon::now()
]);
echo "Posts set to published.\n";

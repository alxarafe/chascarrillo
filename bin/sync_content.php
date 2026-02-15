<?php

/**
 * Script de sincronización de contenido Markdown.
 * Lee los archivos en Content/pages y Content/posts y los sincroniza con la base de datos.
 */

define('BASE_PATH', __DIR__ . '/../public');

require_once __DIR__ . '/../vendor/autoload.php';

// Inicializar Alxarafe
$config = \Alxarafe\Base\Config::getConfig();
if (!$config || !isset($config->db)) {
    die("Error: No se pudo cargar la configuración de la base de datos en BASE_PATH: " . BASE_PATH . "\n");
}

\Alxarafe\Base\Database::createConnection($config->db);

use Modules\Chascarrillo\Model\Post;

/**
 * Extrae Frontmatter (YAML) y Contenido de un archivo MD.
 */
function parseMarkdownFile($path)
{
    $content = file_get_contents($path);
    $pattern = '/^---\s*\n(.*?)\n---\s*\n(.*)$/s';

    $metadata = [];
    $body = $content;

    if (preg_match($pattern, $content, $matches)) {
        $yaml = $matches[1];
        $body = $matches[2];

        // Use Symfony Yaml if available
        if (class_exists('Symfony\Component\Yaml\Yaml')) {
            $metadata = Symfony\Component\Yaml\Yaml::parse($yaml);
        } else {
            // Very basic fallback
            foreach (explode("\n", $yaml) as $line) {
                if (str_contains($line, ':')) {
                    list($key, $value) = explode(':', $line, 2);
                    $metadata[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
                }
            }
        }
    } else {
        // Simple fallback: detect first H1 as title
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            $metadata['title'] = $matches[1];
            $body = trim(preg_replace('/^#\s+.+$/m', '', $content, 1));
        }
    }

    return [$metadata, trim($body)];
}

function syncDirectory($dir, $type)
{
    $path = __DIR__ . '/../Content/' . $dir;
    if (!is_dir($path)) {
        echo "Directorio no encontrado: $path\n";
        return;
    }

    $files = glob($path . '/*.md');
    foreach ($files as $file) {
        $slug = basename($file, '.md');
        list($meta, $content) = parseMarkdownFile($file);

        $title = $meta['title'] ?? $slug;

        echo "Sincronizando $type: $title ($slug)... ";

        $post = Post::where('slug', $slug)->first();
        if (!$post) {
            $post = new Post();
            $post->slug = $slug;
        }

        $post->type = $meta['type'] ?? $type;
        $post->title = $title;
        $post->content = $content;
        $post->meta_title = $meta['meta_title'] ?? $title;
        $post->meta_description = $meta['meta_description'] ?? ($meta['summary'] ?? '');
        $post->meta_keywords = $meta['keywords'] ?? '';
        $post->featured_image = $meta['image'] ?? '';
        $post->is_published = isset($meta['published']) ? (bool)$meta['published'] : true;
        $post->in_menu = isset($meta['in_menu']) ? (bool)$meta['in_menu'] : false;
        $post->menu_order = isset($meta['menu_order']) ? (int)$meta['menu_order'] : 0;

        $date = $meta['date'] ?? null;
        if ($date) {
            $post->published_at = date('Y-m-d H:i:s', is_numeric($date) ? (int)$date : strtotime((string)$date));
        } elseif (empty($post->published_at)) {
            $post->published_at = date('Y-m-d H:i:s');
        }

        // Final fallback if strtotime failed (01 Jan 1970)
        if ($post->published_at->format('Y') < 2000) {
            $post->published_at = date('Y-m-d H:i:s');
        }

        $post->save();
        echo "OK\n";
    }
}

echo "--- Sincronizando Contenido Markdown ---\n";
syncDirectory('pages', 'page');
syncDirectory('posts', 'post');

echo "\nProceso finalizado.\n";

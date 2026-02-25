<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use Alxarafe\Lib\Functions;
use Modules\Chascarrillo\Model\Post;
use Modules\Chascarrillo\Model\Media;
use Modules\Chascarrillo\Model\Tag;
use Modules\Chascarrillo\Model\Menu;
use Modules\Chascarrillo\Model\MenuItem;
use ZipArchive;

class BackupService
{
    /**
     * Exporta config.json y el directorio Content a un archivo ZIP.
     */
    public static function exportToZip(): string
    {
        $tmpDir = constant('APP_PATH') . '/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }
        $zipPath = $tmpDir . '/backup_' . date('Ymd_His') . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Cannot create zip file at $zipPath. Ensure the tmp directory is writable.");
        }

        // Add config.json
        $configFile = constant('APP_PATH') . '/config.json';
        if (file_exists($configFile)) {
            $zip->addFile($configFile, 'config.json');
        }

        // Add Content directory
        $contentPath = constant('APP_PATH') . '/Content';
        self::addDirToZip($zip, $contentPath, 'Content');

        $zip->close();

        return $zipPath;
    }

    /**
     * Importa contenido desde un archivo ZIP.
     */
    public static function importFromZip(string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $extractPath = constant('APP_PATH') . '/tmp/import_' . time();
            @mkdir($extractPath, 0755, true);
            $zip->extractTo($extractPath);
            $zip->close();

            // 1. Update Content
            if (is_dir($extractPath . '/Content')) {
                self::recursiveRmdir(constant('APP_PATH') . '/Content');
                self::recursiveCopy($extractPath . '/Content', constant('APP_PATH') . '/Content');
            }

            // 2. Update config.json (Merge logic if needed, or just replace)
            // Warning: replacing config.json might break DB connection if the zip has different credentials.
            // Let's at least keep a backup of the current one.
            if (file_exists($extractPath . '/config.json')) {
                $currentConfig = json_decode(file_get_contents(constant('APP_PATH') . '/config.json'), true);
                $newConfig = json_decode(file_get_contents($extractPath . '/config.json'), true);

                // We preserve current DB settings to avoid disconnection issues during import
                if (isset($currentConfig['db'])) {
                    $newConfig['db'] = $currentConfig['db'];
                }

                file_put_contents(constant('APP_PATH') . '/config.json', json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            self::recursiveRmdir($extractPath);
        } else {
            throw new \Exception("Failed to open ZIP file");
        }
    }

    /**
     * Elimina la base de datos e importa el contenido desde Content.
     */
    public static function resetDbFromContent(): array
    {
        // Truncate tables
        $capsule = \Illuminate\Database\Capsule\Manager::connection();
        $capsule->statement('SET FOREIGN_KEY_CHECKS=0;');
        MenuItem::truncate();
        Menu::truncate();
        $capsule->table('post_tag')->truncate();
        Tag::truncate();
        Media::truncate();
        Post::truncate();
        $capsule->statement('SET FOREIGN_KEY_CHECKS=1;');

        // Run Sync
        return SyncService::syncAll();
    }

    /**
     * Elimina el contenido de Content y lo reconstruye desde la base de datos.
     */
    public static function rebuildContentFromDb(): void
    {
        $contentPath = constant('APP_PATH') . '/Content';
        self::recursiveRmdir($contentPath);
        @mkdir($contentPath . '/posts', 0755, true);
        @mkdir($contentPath . '/pages', 0755, true);
        @mkdir($contentPath . '/images', 0755, true);
        @mkdir($contentPath . '/videos', 0755, true);

        // Export Posts and Pages
        $posts = Post::with('tags')->get();
        foreach ($posts as $post) {
            $dir = ($post->type === 'page') ? 'pages' : 'posts';
            $filePath = $contentPath . '/' . $dir . '/' . $post->slug . '.md';

            $tags = $post->tags->where('type', 'tag')->pluck('name')->toArray();
            $categories = $post->tags->where('type', 'category')->pluck('name')->toArray();

            $frontMatter = array_filter([
                'title' => $post->title,
                'slug' => $post->slug,
                'type' => $post->type,
                'published_at' => $post->published_at ? $post->published_at->format('Y-m-d H:i:s') : null,
                'is_published' => $post->is_published,
                'feature_image' => $post->featured_image,
                'meta_description' => $post->meta_description,
                'meta_title' => $post->meta_title,
                'meta_keywords' => $post->meta_keywords,
                'in_menu' => $post->in_menu,
                'menu_label' => $post->menu_label,
                'menu_order' => $post->menu_order,
                'tags' => !empty($tags) ? $tags : null,
                'categories' => !empty($categories) ? $categories : null,
            ], fn($v) => $v !== null);

            $content = "---\n";
            foreach ($frontMatter as $key => $value) {
                if ($value === null) continue;
                if (is_array($value)) {
                    $content .= "$key: [" . implode(', ', array_map(fn($v) => '"' . str_replace('"', '\"', (string)$v) . '"', $value)) . "]\n";
                } elseif (is_bool($value)) {
                    $content .= "$key: " . ($value ? 'true' : 'false') . "\n";
                } else {
                    $content .= "$key: \"$value\"\n";
                }
            }
            $content .= "---\n\n";
            $content .= $post->content;

            file_put_contents($filePath, $content);
        }

        // Export Media
        $mediaRecords = Media::all();
        $uploadsBase = (defined('BASE_PATH') ? constant('BASE_PATH') : constant('APP_PATH') . '/public_html') . '/uploads';

        foreach ($mediaRecords as $media) {
            $source = $uploadsBase . '/' . $media->path;
            if (file_exists($source)) {
                $target = $contentPath . '/' . $media->path;
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }
                @copy($source, $target);
            }
        }
    }

    private static function addDirToZip(ZipArchive $zip, string $path, string $zipDir = ''): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $zipDir . '/' . substr($filePath, strlen($path) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    private static function recursiveRmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::recursiveRmdir("$dir/$file") : unlink("$dir/$file");
        }
        @rmdir($dir);
    }

    private static function recursiveCopy(string $src, string $dst): void
    {
        @mkdir($dst, 0755, true);
        foreach (scandir($src) as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($src . '/' . $file)) {
                    self::recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
    }
}

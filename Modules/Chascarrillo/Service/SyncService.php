<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use Alxarafe\Service\MarkdownSyncService;
use Modules\Chascarrillo\Model\Post;
use Modules\Chascarrillo\Model\Media;
use Alxarafe\Infrastructure\Lib\Messages;

class SyncService
{
    public static function syncAll(bool $clean = false): array
    {
        $contentBase = constant('APP_PATH') . '/Content/import';
        $results = [
            'posts' => ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []],
            'pages' => ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []],
            'assets' => 0,
            'success' => true,
            'error' => null,
            'cleaned' => $clean
        ];

        try {
            if ($clean) {
                // Clear existing content tables
                $connection = \Illuminate\Database\Capsule\Manager::connection();
                $connection->statement('SET FOREIGN_KEY_CHECKS=0;');
                \Modules\Chascarrillo\Model\MenuItem::truncate();
                \Modules\Chascarrillo\Model\Menu::truncate();
                \Modules\Chascarrillo\Model\Tag::truncate();
                $connection->table('post_tag')->truncate();
                \Modules\Chascarrillo\Model\Post::truncate();
                \Modules\Chascarrillo\Model\Media::truncate();
                $connection->statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            // Ensure directories and initial content
            self::ensureContentDirectory($contentBase . '/posts', constant('APP_PATH') . '/Modules/Chascarrillo/posts');
            self::ensureContentDirectory($contentBase . '/pages', constant('APP_PATH') . '/Modules/Chascarrillo/pages');
            self::ensureDirectory($contentBase . '/' . \Modules\Chascarrillo\Service\ContentFilePath::FILES_DIR);

            // Sync Content
            $results['posts'] = self::syncContent($contentBase . '/posts', 'post');
            $results['pages'] = self::syncContent($contentBase . '/pages', 'page');

            // Sync Assets (Content/import/files -> public_html/uploads)
            $results['assets'] = self::syncImportFiles();

            // Sync Menus
            self::syncMenus();
        } catch (\Throwable $t) {
            $results['success'] = false;
            $results['error'] = $t->getMessage();
            error_log("SyncService Error: " . $t->getMessage() . "\n" . $t->getTraceAsString());
        }

        return $results;
    }

    private static function syncContent(string $directoryPath, string $type): array
    {
        $summary = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        if (!is_dir($directoryPath)) {
            return $summary;
        }

        $files = glob($directoryPath . '/*.md');

        foreach ($files as $file) {
            try {
                $data = \Alxarafe\Infrastructure\Service\MarkdownService::parse($file);
                $meta = $data['meta'];

                $slug = $meta['slug'] ?? pathinfo($file, PATHINFO_FILENAME);

                // Map attributes to DB schema
                $attributes = [
                    'title' => $meta['title'] ?? ucfirst(str_replace('-', ' ', $slug)),
                    'slug' => $slug,
                    'content' => $data['content'],
                    'type' => $type,
                    'is_published' => $meta['published'] ?? $meta['is_published'] ?? true,
                    'published_at' => $meta['date'] ?? $meta['published_at'] ?? date('Y-m-d H:i:s'),
                    'meta_description' => $meta['summary'] ?? $meta['meta_description'] ?? null,
                    'meta_title' => $meta['meta_title'] ?? $meta['title'] ?? null,
                    'meta_keywords' => $meta['meta_keywords'] ?? $meta['keywords'] ?? null,
                    'featured_image' => $meta['image'] ?? $meta['featured_image'] ?? $meta['feature_image'] ?? null,
                    'in_menu' => $meta['in_menu'] ?? false,
                    'menu_label' => $meta['menu_label'] ?? null,
                    'menu_order' => $meta['menu_order'] ?? 0,
                ];

                /** @var \Modules\Chascarrillo\Model\Post|null $record */
                $record = Post::where('slug', $slug)->first();

                if ($record instanceof Post) {
                    $record->update($attributes);
                    $summary['updated']++;
                } else {
                    /** @var \Modules\Chascarrillo\Model\Post $record */
                    $record = Post::create($attributes);
                    $summary['created']++;
                }

                // Sincronizar tags si existen en el meta
                if (isset($meta['tags'])) {
                    self::syncTags($record, $meta['tags'], 'tag');
                }

                // Sincronizar categorías si existen en el meta
                if (isset($meta['categories'])) {
                    self::syncTags($record, $meta['categories'], 'category');
                }

                if (isset($meta['category'])) {
                    self::syncTags($record, $meta['category'], 'category');
                }

                $summary['processed']++;
            } catch (\Throwable $t) {
                $summary['failed']++;
                $summary['errors'][] = "Error en " . basename($file) . ": " . $t->getMessage();
            }
        }

        return $summary;
    }

    private static function ensureContentDirectory(string $target, string $source): void
    {
        if (!is_dir($target)) {
            @mkdir($target, 0755, true);
        }

        // If empty, copy from source
        if (is_dir($source) && is_dir($target) && count(array_diff(scandir($target), ['.', '..'])) === 0) {
            $files = array_diff(scandir($source), ['.', '..']);
            foreach ($files as $file) {
                @copy($source . '/' . $file, $target . '/' . $file);
            }
        }
    }

    private static function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }

    /**
     * Sincroniza la carpeta espejo Content/import/files hacia public_html/uploads.
     * Copia cada archivo conservando su subruta y registra/actualiza su Media.
     */
    public static function syncImportFiles(): int
    {
        $sourceDir = \Modules\Chascarrillo\Service\ContentFilePath::importFilesDir();
        $uploadsBase = \Modules\Chascarrillo\Service\ContentFilePath::uploadsBaseDir();

        if (!is_dir($sourceDir)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getPathname();
            $relativePath = ltrim(substr($filePath, strlen($sourceDir) + 1), '/\\');
            $targetPath = $uploadsBase . '/' . $relativePath;

            if (!is_dir(dirname($targetPath))) {
                @mkdir(dirname($targetPath), 0755, true);
            }

            // Sync to disk
            if (!file_exists($targetPath) || filemtime($filePath) > filemtime($targetPath)) {
                @copy($filePath, $targetPath);
            }

            $media = \Modules\Chascarrillo\Model\Media::where('path', $relativePath)->first();
            if (!$media) {
                $media = new \Modules\Chascarrillo\Model\Media();
                $media->path = $relativePath;
            }

            $media->filename = basename($relativePath);
            $media->type = \Modules\Chascarrillo\Service\ContentFilePath::mediaTypeFor($relativePath);
            $media->size = filesize($filePath);
            $media->mime_type = function_exists('mime_content_type') ? @mime_content_type($filePath) : null;
            $media->save();
            $count++;
        }

        return $count;
    }

    private static function syncMenus(): void
    {
        /** @var \Modules\Chascarrillo\Model\Menu $menu */
        $menu = \Modules\Chascarrillo\Model\Menu::firstOrCreate(
            ['slug' => 'header-menu'],
            ['name' => 'Menú Principal']
        );

        // Clear existing items for a fresh sync
        \Modules\Chascarrillo\Model\MenuItem::where('menu_id', $menu->id)->delete();

        // 1. Home (Inicio) - We use the 'index' page if it exists
        /** @var \Modules\Chascarrillo\Model\Post|null $indexPage */
        $indexPage = Post::where('slug', 'index')->first();
        \Modules\Chascarrillo\Model\MenuItem::create([
            'menu_id' => $menu->id,
            'label' => $indexPage->menu_label ?? 'Inicio',
            'url' => '/',
            'order' => 1
        ]);

        // 2. Add pages marked as 'in_menu' (except index as it's already 'Inicio')
        $pages = Post::where('type', 'page')
            ->where('in_menu', true)
            ->where('slug', '!=', 'index')
            ->orderBy('menu_order', 'ASC')
            ->get();

        $order = 10;
        foreach ($pages as $page) {
            \Modules\Chascarrillo\Model\MenuItem::create([
                'menu_id' => $menu->id,
                'label' => $page->menu_label ?? $page->title,
                'url' => '/' . $page->slug,
                'order' => $order
            ]);
            $order += 10;
        }

        // 3. Laboratorio (Blog)
        \Modules\Chascarrillo\Model\MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Laboratorio',
            'url' => '/blog',
            'order' => $order
        ]);

        $order += 10;

        // 4. Documentación (Links externos)
        \Modules\Chascarrillo\Model\MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Documentación',
            'url' => 'https://docs.alxarafe.com/es',
            'target' => '_blank',
            'order' => $order
        ]);
    }

    private static function syncTags(Post $record, mixed $tags, string $type): void
    {
        $tags = is_array($tags) ? $tags : explode(',', (string)$tags);
        $tagIds = [];
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }
            $tagSlug = \Illuminate\Support\Str::slug($tagName);
            /** @var \Modules\Chascarrillo\Model\Tag $tag */
            $tag = \Modules\Chascarrillo\Model\Tag::firstOrCreate(
                ['slug' => $tagSlug],
                ['name' => $tagName, 'type' => $type]
            );
            $tagIds[] = $tag->id;
        }

        if ($type === 'tag') {
            /** @phpstan-ignore-next-line */
            $record->tags()->where('type', 'tag')->detach();
        } else {
            /** @phpstan-ignore-next-line */
            $record->tags()->where('type', 'category')->detach();
        }
        $record->tags()->attach($tagIds);
    }
}

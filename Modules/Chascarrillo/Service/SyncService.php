<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use Alxarafe\Service\MarkdownSyncService;
use Modules\Chascarrillo\Model\Post;
use Modules\Chascarrillo\Model\Media;
use Alxarafe\Lib\Messages;

class SyncService
{
    public static function syncAll(): array
    {
        $contentBase = constant('APP_PATH') . '/Content';
        $results = [
            'posts' => ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []],
            'pages' => ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []],
            'assets' => 0,
            'success' => true,
            'error' => null
        ];

        try {
            // Ensure directories and initial content
            self::ensureContentDirectory($contentBase . '/posts', constant('APP_PATH') . '/Modules/Chascarrillo/posts');
            self::ensureContentDirectory($contentBase . '/pages', constant('APP_PATH') . '/Modules/Chascarrillo/pages');
            self::ensureContentDirectory($contentBase . '/images', constant('APP_PATH') . '/Modules/Chascarrillo/images');
            self::ensureDirectory($contentBase . '/videos');

            // Sync Content
            $results['posts'] = self::syncContent($contentBase . '/posts', 'post');
            $results['pages'] = self::syncContent($contentBase . '/pages', 'page');

            // Sync Assets
            $results['assets'] = self::syncAssets($contentBase);

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
                $data = \Alxarafe\Service\MarkdownService::parse($file);
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
                    'featured_image' => $meta['image'] ?? $meta['featured_image'] ?? null,
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
                    $record = Post::create($attributes);
                    $summary['created']++;
                }

                // Sincronizar tags si existen en el meta
                if (isset($meta['tags'])) {
                    $tags = is_array($meta['tags']) ? $meta['tags'] : explode(',', (string)$meta['tags']);
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
                            ['name' => $tagName, 'type' => 'tag']
                        );
                        $tagIds[] = $tag->id;
                    }
                    if ($record instanceof Post) {
                        $record->tags()->sync($tagIds);
                    }
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

    private static function syncAssets(string $contentBase): int
    {
        $basePath = defined('BASE_PATH') ? constant('BASE_PATH') : constant('APP_PATH') . '/public_html';
        $count = 0;

        $count += self::syncAssetDir($contentBase . '/images', 'image', $basePath . '/uploads/images');
        $count += self::syncAssetDir($contentBase . '/videos', 'video', $basePath . '/uploads/videos');

        return $count;
    }

    private static function syncAssetDir(string $sourceDir, string $type, string $targetDir): int
    {
        if (!is_dir($sourceDir)) {
            return 0;
        }

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $files = glob($sourceDir . '/*.*');
        $count = 0;
        foreach ($files as $file) {
            $filename = basename($file);
            $targetPath = $targetDir . '/' . $filename;

            if (!file_exists($targetPath) || filemtime($file) > filemtime($targetPath)) {
                @copy($file, $targetPath);
            }

            $relativePath = $type . 's/' . $filename;
            $media = \Modules\Chascarrillo\Model\Media::where('path', $relativePath)->first();
            if (!$media) {
                $media = new \Modules\Chascarrillo\Model\Media();
                $media->path = $relativePath;
            }

            $media->filename = $filename;
            $media->type = $type;
            $media->size = filesize($file);
            $media->mime_type = function_exists('mime_content_type') ? @mime_content_type($file) : null;
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
    }
}

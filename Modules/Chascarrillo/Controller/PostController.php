<?php

declare(strict_types=1);

/*
 * Copyright (C) 2024-2026 Rafael San José <rsanjose@alxarafe.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Base\Controller\ResourceController;
use Modules\Chascarrillo\Model\Post;
use Modules\Chascarrillo\Model\Tag;
use Alxarafe\Attribute\Menu;

#[Menu(
    menu: 'main_menu',
    label: 'Gestión Chascarrillos',
    icon: 'fas fa-laugh-squint',
    order: 40,
    permission: 'Chascarrillo.Post.doIndex'
)]
class PostController extends ResourceController
{
    protected array $with = ['tags'];

    #[\Override]
    protected function setup()
    {
        parent::setup();
        $this->addListButton(
            'sync_md',
            'Sincronizar Markdown',
            'fas fa-sync',
            'info',
            'right',
            'url',
            'index.php?module=Chascarrillo&controller=Post&method=sync'
        );
    }

    #[\Override]
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    #[\Override]
    public static function getControllerName(): string
    {
        return 'Post';
    }

    #[\Override]
    protected function getModelClass(): array
    {
        return [
            'general' => Post::class,
        ];
    }

    #[\Override]
    protected function beforeList()
    {
        $status = $_GET['filter_general_status'] ?? '';
        $query = Post::query();

        if ($status === 'published') {
            $query->where('is_published', true)
                ->where('published_at', '<=', date('Y-m-d H:i:s'));
        } elseif ($status === 'draft') {
            $query->where('is_published', false);
        } elseif ($status === 'scheduled') {
            $query->where('is_published', true)
                ->where('published_at', '>', date('Y-m-d H:i:s'));
        }

        $this->addVariable('posts', $query->orderBy('published_at', 'DESC')->get());

        // This will be used by our custom templates/post/index.blade.php
        $this->setDefaultTemplate('post/index');
    }

    #[\Override]
    protected function getListColumns(): array
    {
        return [
            'id',
            'title',
            'slug',
            'is_published' => [
                'type' => 'boolean',
                'label' => 'Publicado'
            ],
            'published_at' => [
                'type' => 'datetime',
                'label' => 'Fecha de Publicación'
            ],
        ];
    }

    #[\Override]
    protected function getEditFields(): array
    {
        return [
            'id' => new \Alxarafe\Component\Fields\Text('id', 'ID', ['readonly' => true]),
            'title' => new \Alxarafe\Component\Fields\Text('title', 'Título'),
            'slug' => new \Alxarafe\Component\Fields\Text('slug', 'Slug'),
            'is_published' => new \Alxarafe\Component\Fields\Boolean('is_published', 'Publicado'),
            'published_at' => new \Alxarafe\Component\Fields\DateTime('published_at', 'Fecha de Publicación'),
            'meta_title' => new \Alxarafe\Component\Fields\Text('meta_title', 'Meta Título (SEO)'),
            'meta_description' => new \Alxarafe\Component\Fields\Textarea('meta_description', 'Meta Descripción (SEO)', ['rows' => 3]),
            'meta_keywords' => new \Alxarafe\Component\Fields\Text('meta_keywords', 'Meta Keywords (SEO)'),
            'featured_image' => new \Alxarafe\Component\Fields\Text('featured_image', 'URL Imagen Destacada'),
            'tags' => new \Alxarafe\Component\Fields\Select2('tags', 'Tags', Tag::where('type', 'tag')->pluck('name', 'id')->toArray(), ['multiple' => true]),
            'categories' => new \Alxarafe\Component\Fields\Select2('categories', 'Categorías', Tag::where('type', 'category')->pluck('name', 'id')->toArray(), ['multiple' => true]),
            'content' => new \Alxarafe\Component\Fields\Textarea('content', 'Contenido', ['rows' => 20]),
            'created_at' => new \Alxarafe\Component\Fields\Text('created_at', 'Fecha de Creación', ['readonly' => true]),
        ];
    }

    #[\Override]
    protected function getFilters(): array
    {
        return [
            new \Modules\Chascarrillo\Lib\Filter\PostStatusFilter('status', 'Estado', [
                'options' => [
                    '' => 'Todos',
                    'published' => 'Publicados',
                    'scheduled' => 'Programados',
                    'draft' => 'Borradores'
                ]
            ]),
        ];
    }

    #[\Override]
    protected function beforeEdit()
    {
        // This will be used by our custom templates/post/edit.blade.php
        $this->setDefaultTemplate('post/edit');

        if ($this->recordId && $this->recordId !== 'new') {
            $post = Post::find($this->recordId);
            if ($post instanceof Post) {
                $data = $post->toArray();

                /** @phpstan-ignore-next-line */
                $data['tags'] = $post->tags()->where('type', 'tag')->pluck('tags.id')->toArray();

                /** @phpstan-ignore-next-line */
                $data['categories'] = $post->tags()->where('type', 'category')->pluck('tags.id')->toArray();

                $this->addVariable('data', $data);
            }
        }
    }

    #[\Override]
    protected function afterSaveRecord(\Alxarafe\Base\Model\Model $model, array $data)
    {
        /** @var Post $model */
        $tags = $data['tags'] ?? [];
        $categories = $data['categories'] ?? [];
        $allTagIds = array_merge($tags, $categories);

        $model->tags()->sync($allTagIds);
    }

    #[\Override]
    protected function handleRequest()
    {
        if (isset($_GET['ajax']) && $_GET['ajax'] === 'upload_image') {
            $url = \Modules\Chascarrillo\Lib\UploadHelper::upload('file', 'posts');
            if ($url) {
                $this->jsonResponse(['status' => 'success', 'url' => $url]);
            } else {
                $this->jsonResponse(['status' => 'error', 'message' => 'Upload failed']);
            }
        }

        if (isset($_GET['ajax']) && $_GET['ajax'] === 'render_markdown') {
            $content = $_POST['content'] ?? '';
            $html = \Alxarafe\Service\MarkdownService::render($content);
            $this->jsonResponse(['status' => 'success', 'html' => $html]);
        }

        parent::handleRequest();
    }

    public function doSync(): bool
    {
        $contentBase = constant('APP_PATH') . '/Content';

        // Ensure Content directories exist
        $this->ensureContentDirectory($contentBase . '/posts', constant('APP_PATH') . '/Modules/Chascarrillo/posts');
        $this->ensureContentDirectory($contentBase . '/pages', constant('APP_PATH') . '/Modules/Chascarrillo/pages');
        if (!is_dir($contentBase . '/images')) mkdir($contentBase . '/images', 0755, true);
        if (!is_dir($contentBase . '/videos')) mkdir($contentBase . '/videos', 0755, true);

        $results = [];

        // Sync Content
        $results['posts'] = \Alxarafe\Service\MarkdownSyncService::sync($contentBase . '/posts', Post::class);
        $results['pages'] = \Alxarafe\Service\MarkdownSyncService::sync($contentBase . '/pages', Post::class);

        // Sync Assets
        $mediaController = new MediaController();
        $mediaController->doSync();

        \Alxarafe\Lib\Messages::addMessage("Sincronización completa: Contenido y Multimedia.");

        if (isset($_GET['ajax'])) {
            $this->jsonResponse(['status' => 'success', 'results' => $results]);
        } else {
            \Alxarafe\Lib\Functions::httpRedirect(static::url());
        }
        return true;
    }

    /**
     * Ensures a content directory exists. If not, copies content from an example directory.
     */
    private function ensureContentDirectory(string $target, string $source): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        // If empty, copy from source
        if (count(array_diff(scandir($target), ['.', '..'])) === 0 && is_dir($source)) {
            $files = array_diff(scandir($source), ['.', '..']);
            foreach ($files as $file) {
                copy($source . '/' . $file, $target . '/' . $file);
            }
        }
    }
}

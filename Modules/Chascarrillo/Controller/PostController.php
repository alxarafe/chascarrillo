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

if (is_dir(__DIR__ . '/../../../Content')) {
    define('CHASCARRILLO_SYNC_MENU', 'main_menu');
} else {
    define('CHASCARRILLO_SYNC_MENU', 'none');
}

use Alxarafe\Attribute\Menu;
use Alxarafe\Base\Controller\ResourceController;
use Modules\Chascarrillo\Model\Post;
use Modules\Chascarrillo\Model\Tag;
use Modules\Chascarrillo\Service\SyncService;

#[Menu(
    menu: 'main_menu',
    label: 'Chascarrillos (Posts)',
    icon: 'fas fa-newspaper',
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
            '/index.php?module=Chascarrillo&controller=Post&action=sync'
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
    protected function getModelClass(): string
    {
        return Post::class;
    }

    #[\Override]
    protected function beforeList()
    {
        $status = $_GET['filter_general_status'] ?? '';
        $query = Post::where('type', 'post');

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
        } elseif ($this->recordId === 'new') {
            $this->addVariable('data', ['type' => 'post', 'is_published' => false]);
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

    #[Menu(
        menu: CHASCARRILLO_SYNC_MENU,
        label: 'Sincronizar Markdown',
        icon: 'fas fa-sync',
        order: 45,
        url: '/index.php?module=Chascarrillo&controller=Post&action=sync',
        permission: 'Chascarrillo.Post.doSync'
    )]
    public function doSync(): bool
    {
        $confirm = $_POST['confirm'] ?? $_GET['confirm'] ?? false;
        $rebuild = $_POST['rebuild'] ?? $_GET['rebuild'] ?? false;

        if (!$confirm) {
            $this->setDefaultTemplate('post/sync');
            return true;
        }

        $results = SyncService::syncAll((bool)$rebuild);

        if (!$results['success']) {
            \Alxarafe\Lib\Messages::addError("Error crítico: " . $results['error']);
        } else {
            \Alxarafe\Lib\Messages::addMessage("Sincronización completa: Contenido y Multimedia.");
        }

        $this->addVariable('results', $results);

        if (isset($_GET['ajax'])) {
            $this->jsonResponse(['status' => $results['success'] ? 'success' : 'error', 'results' => $results]);
        } else {
            $this->setDefaultTemplate('post/sync');
        }
        return true;
    }
}

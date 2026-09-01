<?php

declare(strict_types=1);

/*
 * Copyright (C) 2024-2026 Rafael San José <rsanjose@alxarafe.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace Modules\Chascarrillo\Controller;

if (is_dir(__DIR__ . '/../../../Content')) {
    define('CHASCARRILLO_SYNC_MENU', 'main_menu');
} else {
    define('CHASCARRILLO_SYNC_MENU', 'none');
}

use Alxarafe\Infrastructure\Attribute\Menu;
use Alxarafe\Infrastructure\Http\Controller\ResourceController;
use Modules\Chascarrillo\Model\Post;
use Modules\Chascarrillo\Model\Tag;
use Modules\Chascarrillo\Service\SyncService;
use Modules\Chascarrillo\Application\AppContainer;
use Modules\Chascarrillo\Domain\Port\Driven\PostRepositoryInterface;
use Alxarafe\Application\Bus\SimpleCommandBus;
use Modules\Chascarrillo\Application\Bus\Command\CreatePostCommand;

#[Menu(
    menu: 'main_menu',
    label: 'Chascarrillos (Posts)',
    icon: 'fas fa-newspaper',
    order: 40,
    permission: 'Chascarrillo.Post.doIndex'
)]
class PostController extends ResourceController
{
    protected bool $useTabs = true;
    protected array $with = ['tags'];
    
    // For Hexagonal DI fallback
    private PostRepositoryInterface $repository;
    private SimpleCommandBus $commandBus;

    public function __construct()
    {
        parent::__construct();
        $this->repository = AppContainer::get()->get(PostRepositoryInterface::class);
        $this->commandBus = AppContainer::get()->get(SimpleCommandBus::class);
    }

    
    protected function setup(): void
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

    
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    
    public static function getControllerName(): string
    {
        return 'Post';
    }

    
    protected function getModelClassName(): string
    {
        // Return the Eloquent Model so the framework can introspect the schema.
        return Post::class;
    }

    /**
     * @return void
     */
    
    protected function beforeList(): void
    {
        $status = $_GET['filter_general_status'] ?? '';
        
        $filters = ['type' => 'post'];
        
        if ($status === 'published') {
            $filters['is_published'] = 1;
            // Strict date comparison is simplified for this demo, 
            // In a real Hexagonal we'd query by status 'published' via Repository
            $filters['status'] = 2; 
        } elseif ($status === 'draft') {
            $filters['is_published'] = 0;
            $filters['status'] = 0;
        }

        $posts = $this->repository->findByFilters($filters);

        $this->addVariable('posts', $posts);
    }

    // OVERRIDE: Prevent Eloquent grid listing
    
    protected function fetchListData(string $tabId): array
    {
        $status = (string) ($_GET['filter_general_status'] ?? '');
        $filters = ['type' => 'post'];
        if (in_array($status, ['published', 'draft'], true)) {
            $filters['is_published'] = $status === 'published' ? 1 : 0;
            $filters['status'] = $status === 'published' ? 2 : 0;
        }
        $posts = $this->repository->findByFilters($filters);
        
        $data = [];
        foreach ($posts as $post) {
            $data[] = $post->toArray();
        }

        return [
            'data' => $data,
            'meta' => [
                'total' => count($data),
                'limit' => $this->structConfig['list']['limit'] ?? 50,
                'offset' => $this->offset,
            ],
        ];
    }
    
    // OVERRIDE: Prevent Eloquent fetching
    
    protected function fetchRecordData(): array
    {
        if ($this->recordId === 'new') {
            return ['id' => 'new', 'data' => ['type' => 'post', 'is_published' => false], 'meta' => ['is_new' => true]];
        }

        $post = $this->repository->findById((int) $this->recordId);
        if (!$post) {
            return ['error' => 'record_not_found'];
        }

        return ['id' => $this->recordId, 'data' => $post->toArray()];
    }

    // OVERRIDE: Handle saving Hexagonal logic
    /**
     * @return never
     */
    
    protected function saveRecord(): void
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $rawInput = file_get_contents('php://input');
        $isAjax = str_contains($contentType, 'application/json') || isset($_GET['ajax']);

        if (str_contains($contentType, 'application/json') || ($rawInput && $rawInput[0] === '{')) {
            $json = json_decode($rawInput, true);
            if (is_array($json)) {
                $_POST = array_merge($_POST, $json);
            }
        }

        $data = $_POST['data'] ?? [];

        // Command Bus Pattern: Create or Update using Handlers mapping
        $cmd = new CreatePostCommand(
            $data['title'] ?? 'Sin título',
            $data['slug'] ?? '',
            $data['content'] ?? '',
            'post',
            !empty($data['is_published']),
            (int) ($data['status'] ?? 0)
        );

        // If ID exists, we should update (which theoretically requires UpdatePostCommand,
        // but for this phase we simulate it directly via repository if no command created yet)
        $isUpdate = !empty($this->recordId) && $this->recordId !== 'new';
        $id = null;
        $saved = $data;

        if ($isUpdate) {
            $post = $this->repository->findById((int) $this->recordId);
            if ($post) {
                // Update Entity
                $post->updateContent($cmd->title, $cmd->slug, $cmd->content);
                $post->updateStatus($cmd->isPublished, $cmd->status);
                $this->repository->save($post);
                $id = (string) $post->getId();
                $saved = $post->toArray();
            }
        } else {
            $createdId = $this->commandBus->dispatch($cmd);
            $id = (string) $createdId;
            $created = $this->repository->findById((int) $createdId);
            if ($created) {
                $saved = $created->toArray();
            }
        }

        $message = $isUpdate ? 'Registro modificado con éxito.' : 'Registro creado con éxito.';

        if ($isAjax) {
            $this->jsonResponse([
                'status' => 'success',
                'message' => $message,
                'id' => (string) $id,
                'data' => $saved,
            ]);
        }

        \Alxarafe\Infrastructure\Lib\Messages::addMessage($message);
        header('Location: ' . static::url());
        exit;
    }
    
    // OVERRIDE: Handle Deletion
    
    public function doDelete(): bool
    {
        if ($this->recordId && $this->recordId !== 'new') {
            $this->repository->delete((int) $this->recordId);
            \Alxarafe\Infrastructure\Lib\Messages::addMessage('Registro borrado con éxito.');
        }
        
        header('Location: ' . static::url());
        exit;
    }

    public function doEdit(): bool
    {
        return $this->doIndex();
    }

    
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
            'status' => [
                'type' => 'text',
                'label' => 'Workflow'
            ],
        ];
    }

    
    protected function getEditFields(): array
    {
        return [
            'content' => [
                'label' => 'Contenido',
                'col'   => 'col-md-8',
                'fields' => [
                    'title' => new \Alxarafe\ResourceController\Component\Fields\Text('title', 'Título'),
                    'slug' => new \Alxarafe\ResourceController\Component\Fields\Text('slug', 'Slug'),
                    'content' => new \Alxarafe\ResourceController\Component\Fields\Textarea('content', 'Contenido', ['rows' => 15]),
                ]
            ],
            'settings' => [
                'label' => 'Configuración',
                'col'   => 'col-md-4',
                'fields' => [
                    'id' => new \Alxarafe\ResourceController\Component\Fields\Text('id', 'ID', ['readonly' => true]),
                    'is_published' => new \Alxarafe\ResourceController\Component\Fields\Boolean('is_published', 'Publicado'),
                    'published_at' => new \Alxarafe\ResourceController\Component\Fields\DateTime('published_at', 'Fecha de Publicación'),
                    'featured_image' => new \Alxarafe\ResourceController\Component\Fields\Text('featured_image', 'URL Imagen Destacada'),
                    // For now removed advanced Tag relations as they require fixing Tag architecture to pure Domain too.
                    'status' => new \Alxarafe\ResourceController\Component\Fields\Select(
                        'status',
                        'Estado Workflow',
                        [0 => 'Borrador', 1 => 'Validado', 2 => 'Publicado', 9 => 'Archivado']
                    ),
                    'meta_title' => new \Alxarafe\ResourceController\Component\Fields\Text('meta_title', 'Meta Título (SEO)'),
                    'meta_description' => new \Alxarafe\ResourceController\Component\Fields\Textarea('meta_description', 'Meta Descripción (SEO)', ['rows' => 3]),
                ]
            ]
        ];
    }

    
    protected function getFilters(): array
    {
        return [
            new \Alxarafe\Infrastructure\Component\Filter\SelectFilter('status', 'Estado', 'select', [
                'options' => [
                    '' => 'Todos',
                    'published' => 'Publicados',
                    'scheduled' => 'Programados',
                    'draft' => 'Borradores'
                ]
            ]),
        ];
    }

    /**
     * @return void
     */
    
    protected function beforeEdit(): void
    {
        if ($this->recordId && $this->recordId !== 'new') {
            $post = $this->repository->findById((int) $this->recordId);
            if ($post) {
                $data = $post->toArray();
                $this->addVariable('data', $data);
            }
        } elseif ($this->recordId === 'new') {
            $this->addVariable('data', ['type' => 'post', 'is_published' => false]);
        }
    }

    /**
     * @return void
     */
    
    protected function handleRequest(): void
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
            $html = \Alxarafe\Infrastructure\Service\MarkdownService::render($content);
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
            \Alxarafe\Infrastructure\Lib\Messages::addError("Error crítico: " . $results['error']);
        } else {
            \Alxarafe\Infrastructure\Lib\Messages::addMessage("Sincronización completa: Contenido y Multimedia.");
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

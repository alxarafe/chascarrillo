<?php

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Base\Controller\ResourceController;
use Modules\Chascarrillo\Model\Post;
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
            'id' => ['readonly' => true],
            'title',
            'slug',
            'is_published' => ['type' => 'boolean', 'label' => 'Publicado'],
            'published_at' => ['type' => 'datetime', 'label' => 'Fecha de Publicación'],
            'meta_title' => ['type' => 'text', 'label' => 'Meta Título (SEO)'],
            'meta_description' => ['type' => 'textarea', 'label' => 'Meta Descripción (SEO)', 'rows' => 3],
            'meta_keywords' => ['type' => 'text', 'label' => 'Meta Keywords (SEO)'],
            'featured_image' => ['type' => 'text', 'label' => 'URL Imagen Destacada', 'description' => 'Ej: /assets/posts/mi-imagen.jpg'],
            'content' => ['type' => 'textarea', 'multiline' => true, 'rows' => 10, 'description' => 'Soporta Markdown (Negritas **, Encabezados #, etc.)'],
            'created_at' => ['readonly' => true],
        ];
    }

    #[\Override]
    protected function beforeEdit()
    {
        $this->setDefaultTemplate('page/chascarrillo/post/edit');

        if ($this->recordId && $this->recordId !== 'new') {
            $post = Post::find($this->recordId);
            if ($post) {
                $this->addVariable('data', $post->toArray());
            }
        }
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

        parent::handleRequest();
    }

    public function doSync(): bool
    {
        $postsDir = ALX_PATH . '/skeleton/Modules/Chascarrillo/posts';
        $results = \Alxarafe\Service\MarkdownSyncService::sync($postsDir, Post::class);

        if (empty($results['errors'])) {
            \Alxarafe\Lib\Messages::addMessage("Sincronización completada: {$results['created']} creados, {$results['updated']} actualizados.");
        } else {
            foreach ($results['errors'] as $error) {
                \Alxarafe\Lib\Messages::addError($error);
            }
        }

        \Alxarafe\Lib\Functions::httpRedirect(static::url());
        return true;
    }
}

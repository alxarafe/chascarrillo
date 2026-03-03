<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Attribute\Menu;
use Alxarafe\Base\Controller\ResourceController;
use Modules\Chascarrillo\Model\Post;

#[Menu(
    menu: 'main_menu',
    label: 'Páginas Estáticas',
    icon: 'fas fa-file-alt',
    order: 42,
    permission: 'Chascarrillo.PageAdmin.doIndex'
)]
class PageAdminController extends ResourceController
{
    protected bool $useTabs = true;

    #[\Override]
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    #[\Override]
    public static function getControllerName(): string
    {
        return 'PageAdmin';
    }

    #[\Override]
    protected function getModelClass(): string
    {
        return Post::class;
    }

    #[\Override]
    protected function beforeList()
    {
        $this->setDefaultTemplate('page_admin/index');
        $this->addVariable('posts', Post::where('type', 'page')->orderBy('menu_order', 'ASC')->get());
    }

    #[\Override]
    protected function getListColumns(): array
    {
        return [
            'id',
            'title',
            'slug',
            'in_menu' => [
                'type' => 'boolean',
                'label' => 'En Menú'
            ],
            'is_published' => [
                'type' => 'boolean',
                'label' => 'Publicada'
            ],
            'status' => [
                'type' => 'text',
                'label' => 'Workflow'
            ],
        ];
    }

    #[\Override]
    protected function getEditFields(): array
    {
        return [
            'content' => [
                'label' => 'Contenido',
                'fields' => [
                    'title' => new \Alxarafe\Component\Fields\Text('title', 'Título'),
                    'slug' => new \Alxarafe\Component\Fields\Text('slug', 'Slug'),
                    'content' => new \Alxarafe\Component\Fields\Textarea('content', 'Contenido (Markdown)', ['rows' => 20]),
                ]
            ],
            'settings' => [
                'label' => 'Ajustes',
                'fields' => [
                    'id' => new \Alxarafe\Component\Fields\Text('id', 'ID', ['readonly' => true]),
                    'in_menu' => new \Alxarafe\Component\Fields\Boolean('in_menu', 'Mostrar en Menú Superior'),
                    'menu_order' => new \Alxarafe\Component\Fields\Integer('menu_order', 'Orden en el Menú'),
                    'is_published' => new \Alxarafe\Component\Fields\Boolean('is_published', 'Publicada'),
                    'status' => new \Alxarafe\Component\Fields\Select(
                        'status',
                        'Estado Workflow',
                        collect((new Post())->getStates())->mapWithKeys(fn($s, $k) => [$k => $s['label']])->toArray()
                    ),
                    'featured_image' => new \Alxarafe\Component\Fields\Text('featured_image', 'Imagen Destacada (URL)'),
                ]
            ],
            'seo' => [
                'label' => 'SEO',
                'fields' => [
                    'meta_title' => new \Alxarafe\Component\Fields\Text('meta_title', 'Meta Título (SEO)'),
                    'meta_description' => new \Alxarafe\Component\Fields\Textarea('meta_description', 'Meta Descripción (SEO)', ['rows' => 3]),
                    'meta_keywords' => new \Alxarafe\Component\Fields\Text('meta_keywords', 'Meta Keywords (SEO)'),
                ]
            ]
        ];
    }

    #[\Override]
    protected function beforeEdit()
    {
        // $this->setDefaultTemplate('page_admin/edit');

        if ($this->recordId === 'new') {
            $this->addVariable('data', ['type' => 'page', 'is_published' => true, 'in_menu' => false]);
        }
    }
}

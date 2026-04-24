<?php

namespace Modules\Chascarrillo\Controller;

use Modules\Admin\Controller\ConfigController as BaseConfigController;
use Alxarafe\ResourceController\Component\Container\Tab;
use Alxarafe\ResourceController\Component\Fields\Text;
use Alxarafe\Infrastructure\Lib\Trans;
use Alxarafe\Infrastructure\Attribute\Menu;

#[Menu(
    menu: 'main_menu',
    label: 'blog_settings',
    icon: 'fas fa-blog',
    order: 15,
    permission: 'Admin.Config.doIndex',
    parent: 'Configuration'
)]
class ChascarrilloConfigController extends BaseConfigController
{
    protected function getTabs(): array
    {
        // 1. Obtener las pestañas base de Alxarafe (Misc, Connection, DB Prefs, Database)
        $tabs = parent::getTabs();

        // 2. Añadir pestaña de Blog
        $tabs[] = new Tab('blog', Trans::_('blog_settings'), 'fas fa-blog', [
            new Text('blog.title', Trans::_('blog_title')),
            new Text('blog.posts_per_page', Trans::_('posts_per_page'), ['type' => 'number']),
            new Text('blog.excerpt_length', Trans::_('excerpt_length'), ['type' => 'number']),
        ]);

        // 3. Añadir pestaña de Redes Sociales
        $tabs[] = new Tab('social', Trans::_('social_networks'), 'fas fa-share-alt', [
            new Text('social.github', 'GitHub'),
            new Text('social.linkedin', 'LinkedIn'),
            new Text('social.twitter', 'Twitter / X'),
            new Text('social.instagram', 'Instagram'),
            new Text('social.facebook', 'Facebook'),
        ]);

        return $tabs;
    }
}

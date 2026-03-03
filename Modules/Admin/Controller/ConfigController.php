<?php

namespace Modules\Admin\Controller;

use CoreModules\Admin\Controller\ConfigController as BaseConfigController;
use Alxarafe\Component\Container\Tab;
use Alxarafe\Component\Fields\Text;
use Alxarafe\Lib\Trans;

class ConfigController extends BaseConfigController
{
    #[\Override]
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

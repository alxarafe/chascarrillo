<?php

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Base\Controller\GenericPublicController;
use Modules\Chascarrillo\Model\Post;
use Alxarafe\Attribute\Menu;

#[Menu(
    menu: 'main_menu',
    label: 'Ver Chascarrillos',
    icon: 'fas fa-newspaper',
    order: 41,
    visibility: 'public',
    url: 'index.php?module=Chascarrillo&controller=Blog&action=index'
)]
class BlogController extends GenericPublicController
{
    #[\Override]
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    #[\Override]
    public static function getControllerName(): string
    {
        return 'Blog';
    }

    public function doIndex(): bool
    {
        $this->title = 'Chascarrillo Blog';

        try {
            $posts = Post::where('is_published', true)
                ->where('published_at', '<=', date('Y-m-d H:i:s'))
                ->orderBy('published_at', 'DESC')
                ->get();
        } catch (\Exception $e) {
            $posts = collect();
        }

        // Diferenciamos si estamos en la home o en el índice del blog (Laboratorio)
        $isBlogIndex = str_contains($_SERVER['REQUEST_URI'] ?? '', '/blog');

        if ($isBlogIndex) {
            $this->title = 'Laboratorio de Chascarrillos';
            $this->setDefaultTemplate('blog/index');
        }

        foreach ($posts as $post) {
            // We only need this for the excerpt if meta_description is missing
            if (empty($post->meta_description)) {
                $post->content = \Alxarafe\Service\MarkdownService::render($post->content);
            }
        }

        $this->addVariable('posts', $posts);
        $this->addVariable('is_blog_index', $isBlogIndex);

        return true;
    }

    public function doShow(): bool
    {
        $slug = $_GET['slug'] ?? '';

        try {
            $post = Post::where('slug', $slug)
                ->where('is_published', true)
                ->first();
        } catch (\Exception $e) {
            $post = null;
        }

        if (!$post) {
            \Alxarafe\Lib\Functions::httpRedirect(\CoreModules\Admin\Controller\ErrorController::url(true));
            return false;
        }

        $this->title = !empty($post->meta_title) ? $post->meta_title : $post->title;
        $this->addVariable('meta_description', $post->meta_description);
        $this->addVariable('meta_keywords', $post->meta_keywords);
        $this->addVariable('post', $post);
        $this->addVariable('content', $post->getRenderedContent());

        return true;
    }
}

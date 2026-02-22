<?php

namespace Modules\Chascarrillo\Controller;

use Alxarafe\Base\Controller\GenericPublicController;
use Modules\Chascarrillo\Model\Post;

class PageController extends GenericPublicController
{
    #[\Override]
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    #[\Override]
    public static function getControllerName(): string
    {
        return 'Page';
    }

    public function doIndex(): bool
    {
        return $this->doShow();
    }

    public function doShow(): bool
    {
        $slug = $_GET['slug'] ?? 'index';

        try {
            $query = Post::where('slug', $slug);

            // Si no es admin, solo ver publicados
            if (!\Alxarafe\Lib\Auth::$user?->is_admin) {
                $query->where('is_published', true)
                    ->where('published_at', '<=', date('Y-m-d H:i:s'));
            }

            $page = $query->first();
        } catch (\Exception $e) {
            $page = null;
        }

        if (!$page) {
            \Alxarafe\Lib\Messages::addError("Página no encontrada: $slug");
            \Alxarafe\Lib\Functions::httpRedirect('/index.php?module=Chascarrillo&controller=Blog');
            return false;
        }

        if ($slug === 'index') {
            $this->setDefaultTemplate('page/chascarrillo/index');
            // Load latest posts for the home page
            $query = Post::where('type', 'post')
                ->orderBy('published_at', 'DESC');

            if (!\Alxarafe\Lib\Auth::$user?->is_admin) {
                $query->where('is_published', true)
                    ->where('published_at', '<=', date('Y-m-d H:i:s'));
            }

            $posts = $query->take(3)->get();
            $this->addVariable('posts', $posts);
        } else {
            $this->setDefaultTemplate('page/show');
        }

        $this->title = !empty($page->meta_title) ? $page->meta_title : $page->title;
        $this->addVariable('meta_description', $page->meta_description);
        $this->addVariable('meta_keywords', $page->meta_keywords);
        $this->addVariable('page', $page);

        return true;
    }
}

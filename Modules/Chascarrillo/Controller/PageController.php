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

    public function doShow(): bool
    {
        $slug = $_GET['slug'] ?? '';

        try {
            $page = Post::where('slug', $slug)
                ->where('is_published', true)
                ->first();
        } catch (\Exception $e) {
            $page = null;
        }

        if (!$page) {
            \Alxarafe\Lib\Functions::httpRedirect(\CoreModules\Admin\Controller\ErrorController::url(true));
            return false;
        }

        $this->title = !empty($page->meta_title) ? $page->meta_title : $page->title;
        $this->addVariable('meta_description', $page->meta_description);
        $this->addVariable('meta_keywords', $page->meta_keywords);
        $this->addVariable('page', $page);

        return true;
    }
}

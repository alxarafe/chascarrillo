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

use Alxarafe\Infrastructure\Http\Controller\GenericPublicController;
use Modules\Chascarrillo\Model\Post;
use Alxarafe\Infrastructure\Attribute\Menu;

class BlogController extends GenericPublicController
{
    public static function getModuleName(): string
    {
        return 'Chascarrillo';
    }

    
    public static function getControllerName(): string
    {
        return 'Blog';
    }

    public function doIndex(): bool
    {
        $this->title = 'Chascarrillo Blog';
        $tagSlug = $_GET['tag'] ?? null;
        $catSlug = $_GET['category'] ?? null;

        try {
            /** @var \Illuminate\Database\Eloquent\Builder $query */
            $query = Post::with('tags')
                ->where('type', 'post')
                ->orderBy('published_at', 'DESC');

            if (!\Alxarafe\Infrastructure\Auth\Auth::$user?->is_admin) {
                $query->where('is_published', true)
                    ->where('published_at', '<=', date('Y-m-d H:i:s'));
            }

            if ($tagSlug) {
                $query->whereHas('tags', function ($q) use ($tagSlug) {
                    $q->where('slug', $tagSlug);
                });
                $tag = \Modules\Chascarrillo\Model\Tag::where('slug', $tagSlug)->first();
                if ($tag) {
                    $this->title = $tag->type === 'category'
                        ? 'Posts en la categoría: ' . $tag->name
                        : 'Posts con el tag: ' . $tag->name;
                } else {
                    $this->title = 'Posts con el tag: ' . $tagSlug;
                }
            }

            if ($catSlug) {
                $query->whereHas('tags', function ($q) use ($catSlug) {
                    $q->where('slug', $catSlug)->where('type', 'category');
                });
                $cat = \Modules\Chascarrillo\Model\Tag::where('slug', $catSlug)->first();
                $this->title = 'Posts en la categoría: ' . ($cat ? $cat->name : $catSlug);
            }

            $config = \Alxarafe\Infrastructure\Persistence\Config::getConfig();
            $postsPerPage = (int)($config->blog->posts_per_page ?? 10);

            $posts = $query->limit($postsPerPage)->get();
        } catch (\Exception $e) {
            $posts = collect();
        }

        // Diferenciamos si estamos en la home o en el índice del blog (Laboratorio)
        $isBlogIndex = str_contains($_SERVER['REQUEST_URI'] ?? '', '/blog') || $tagSlug || $catSlug;

        if ($isBlogIndex) {
            // Solo poner el título genérico si no hay filtro activo por tag/categoría
            if (!$tagSlug && !$catSlug) {
                $this->title = $config->blog->title ?? 'Laboratorio de Chascarrillos';
            }
            $this->setDefaultTemplate('blog/index');
        }

        /** @var Post $post */
        foreach ($posts as $post) {
            // We only need this for the excerpt if meta_description is missing
            if (empty($post->meta_description)) {
                $post->content = \Alxarafe\Infrastructure\Service\MarkdownService::render($post->content);
            }
        }

        $this->addVariable('posts', $posts);
        $this->addVariable('is_blog_index', $isBlogIndex);
        $this->addVariable('hide_page_title', true);
        
        $this->addVariable('hero_title', $this->title);
        $this->addVariable('hero_subtitle', ($tagSlug || $catSlug) ? 'Mostrando resultados filtrados.' : \Alxarafe\Infrastructure\Lib\Trans::_('laboratory_subtitle'));
        $this->addVariable('tag_filter', $tagSlug);
        $this->addVariable('cat_filter', $catSlug);

        return true;
    }

    public function doShow(): bool
    {
        $slug = $_GET['slug'] ?? '';

        try {
            $query = Post::where('slug', $slug);

            // Si no es admin, solo ver publicados
            if (!\Alxarafe\Infrastructure\Auth\Auth::$user?->is_admin) {
                $query->where('is_published', true)
                    ->where('published_at', '<=', date('Y-m-d H:i:s'));
            }

            $post = $query->first();
        } catch (\Exception $e) {
            $post = null;
        }

        if (!$post) {
            \Alxarafe\Infrastructure\Lib\Functions::httpRedirect(\Modules\Admin\Controller\ErrorController::url(true));
            return false;
        }

        $this->title = !empty($post->meta_title) ? $post->meta_title : $post->title;
        $this->addVariable('meta_description', $post->meta_description);
        $this->addVariable('meta_keywords', $post->meta_keywords);
        $this->addVariable('post', $post);
        $this->addVariable('content', $post->getRenderedContent());
        $this->addVariable('hide_page_title', true);
        $this->setDefaultTemplate('blog/show');

        return true;
    }
}

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

namespace Modules\Chascarrillo\Model;

use Alxarafe\Base\Model\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $type
 * @property bool $in_menu
 * @property string|null $menu_label
 * @property int $menu_order
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 * @property string|null $featured_image
 * @property string|null $content
 * @property bool $is_published
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = [
        'title',
        'slug',
        'type', // post, page
        'in_menu',
        'menu_label',
        'menu_order',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'featured_image',
        'content',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'in_menu' => 'boolean',
        'menu_order' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * Devuelve las páginas que deben aparecer en el menú.
     */
    public static function getMenuPages()
    {
        try {
            return self::where('type', 'page')
                ->where('is_published', true)
                ->where('in_menu', true)
                ->orderBy('menu_order', 'ASC')
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Devuelve un extracto del contenido.
     */
    public function getExcerpt(int $limit = 140): string
    {
        $text = strip_tags($this->content ?? '');
        return \Illuminate\Support\Str::limit($text, $limit);
    }

    /**
     * Devuelve el contenido renderizado (Markdown a HTML)
     */
    public function getRenderedContent(): string
    {
        return \Alxarafe\Service\MarkdownService::render($this->content);
    }

    /**
     * Relación con los tags.
     */
    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }
}

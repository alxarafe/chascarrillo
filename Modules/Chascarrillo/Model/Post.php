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
use Modules\Chascarrillo\Traits\HasWorkflow;

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
 * @property int $status
 * @property \Illuminate\Database\Eloquent\Collection|\Modules\Chascarrillo\Model\Tag[] $tags
 */
class Post extends Model
{
    use HasWorkflow;

    protected $table = 'posts';

    protected string $stateField = 'status';

    #[\Override]
    protected function getWorkflowDefinition(): array
    {
        return [
            'states' => [
                0 => 'Borrador',
                1 => 'Validado',
                2 => 'Publicado',
                9 => 'Archivado',
            ],
            'transitions' => [
                'validate' => ['from' => [0, 2], 'to' => 1],
                'publish' => ['from' => [1], 'to' => 2],
                'draft' => ['from' => [1, 9], 'to' => 0],
                'archive' => ['from' => [0, 1, 2], 'to' => 9],
            ]
        ];
    }

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
        'status',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'in_menu' => 'boolean',
        'menu_order' => 'integer',
        'published_at' => 'datetime',
        'status' => 'integer',
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
     * Devuelve un extracto del contenido basado en la configuración.
     */
    public function getExcerpt(?int $limit = null): string
    {
        if ($limit === null) {
            $config = \Alxarafe\Base\Config::getConfig();
            $limit = (int)($config->blog->excerpt_length ?? 140);
        }
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
     * Devuelve la URL pública del post o página.
     */
    public function getUrl(): string
    {
        if ($this->type === 'page') {
            return '/' . $this->slug;
        }
        return '/blog/' . $this->slug;
    }

    /**
     * Relación con los tags.
     */
    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }
}

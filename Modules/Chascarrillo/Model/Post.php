<?php

namespace Modules\Chascarrillo\Model;

use Alxarafe\Base\Model\Model;
use Parsedown;

class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = [
        'title',
        'slug',
        'type', // post, page
        'in_menu',
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
        return self::where('type', 'page')
            ->where('is_published', true)
            ->where('in_menu', true)
            ->orderBy('menu_order', 'ASC')
            ->get();
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
        $content = $this->content ?? '';

        // Soporte para bloques estilo Quarto/Pandoc: ::: callout-type
        $content = preg_replace_callback('/:::\s+callout-(\w+)(.*?):::/s', function ($matches) {
            $type = $matches[1];
            $body = trim($matches[2]);

            // Extraer título si existe (primera línea del body)
            $title = '';
            if (str_starts_with($body, '## ')) {
                preg_match('/^##\s+(.*)$/m', $body, $titleMatch);
                $title = $titleMatch[1] ?? '';
                $body = trim(preg_replace('/^##\s+.*$/m', '', $body, 1));
            }

            $icon = match ($type) {
                'info' => 'fas fa-info-circle',
                'warn' => 'fas fa-exclamation-triangle',
                'note' => 'fas fa-sticky-note',
                default => 'fas fa-lightbulb'
            };

            $html = '<div class="callout callout-' . $type . '">';
            if ($title) {
                $html .= '<div class="callout-title"><i class="' . $icon . '"></i> ' . $htmlentities = htmlspecialchars($title) . '</div>';
            }
            $html .= (new Parsedown())->text($body);
            $html .= '</div>';

            return $html;
        }, $content);

        $parsedown = new Parsedown();
        return $parsedown->text($content);
    }
}

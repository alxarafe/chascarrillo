<?php

namespace Modules\Chascarrillo\Model\Seed;

use Alxarafe\Lib\Trans;
use Modules\Chascarrillo\Model\Post;

class PostSeeder
{
    public function __construct()
    {
        $this->seed();
    }

    public function seed(): void
    {
        $postData = [
            'title' => 'Día 0: Arquitectura, PHP 8.5 y el factor humano',
            'slug' => 'dia-0-arquitectura-php-8-5-y-el-factor-humano',
            'meta_title' => 'Día 0: Arquitectura, PHP 8.5 y el factor humano',
            'meta_description' => 'Alxarafe nace de una necesidad técnica y vital: construir un framework PHP moderno centrado en la arquitectura limpia y el control humano sobre la IA.',
            'meta_keywords' => 'php 8.5, arquitectura, framework, desarrollo web',
            'content' => "Este no es un blog al uso. Es un Laboratorio.

Alxarafe nace de una necesidad técnica y vital: construir un software robusto donde la Inteligencia Artificial sea una herramienta poderosa, pero subordinada al criterio del arquitecto.

En un mundo saturado de librerías, dependencias mágicas y frameworks que ocultan la realidad del HTTP, volvemos a los cimientos. PHP 8.5, estándares PSR, y una negativa rotunda a la complejidad accidental.

Este proyecto documenta el viaje de modernizar sistemas legacy, crear desde cero con las mejores prácticas y, sobre todo, disfrutar del arte de programar bien.",
            'is_published' => true,
            'published_at' => date('Y-m-d H:i:s'),
        ];

        $post = Post::where('slug', $postData['slug'])->first();

        if (!$post) {
            Post::create($postData);
            echo "Seeded Post: {$postData['title']}\n";
        } else {
            // Optional: Update existing post to match seed
            // $post->update($postData); 
            echo "Post already exists: {$postData['title']}\n";
        }
    }
}

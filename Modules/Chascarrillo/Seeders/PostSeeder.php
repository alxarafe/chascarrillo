<?php

namespace Modules\Chascarrillo\Seeders;

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
        // Blog Post de prueba
        Post::updateOrCreate(['slug' => 'bienvenido-al-laboratorio'], [
            'title' => 'Bienvenido al Laboratorio',
            'meta_title' => 'Bienvenido al Laboratorio de Chascarrillo',
            'meta_description' => 'Tu primer paso en el mundo de la gestión de contenidos ligera y potente.',
            'meta_keywords' => 'cms, blog, prueba',
            'content' => "## ¡Hola Mundo!
            
Este es tu primer Chascarrillo. Un espacio dinámico donde puedes publicar tus reflexiones, noticias o investigaciones técnicas. 

Chascarrillo utiliza **Markdown** nativo, lo que significa que puedes usar:
- Listas de tareas.
- Bloques de código.
- Citas inspiradoras.

¡Empieza a escribir hoy mismo!",
            'is_published' => true,
            'published_at' => date('Y-m-d H:i:s'),
            'type' => 'post',
        ]);

        // Página fija 1
        Post::updateOrCreate(['slug' => 'acerca-de-nosotros'], [
            'title' => 'Acerca de Nosotros',
            'meta_title' => 'Sobre Chascarrillo CMS',
            'meta_description' => 'Conoce la filosofía de simplicidad y control total que impulsa este proyecto.',
            'content' => "## Nuestra Filosofía
            
Chascarrillo nace como una respuesta a la complejidad innecesaria de los CMS tradicionales. Creemos en el control total del desarrollador y en la simplicidad para el creador de contenido.

### ¿Por qué elegirnos?
1. **Velocidad**: Sin sobrecarga de plugins innecesarios.
2. **Seguridad**: Código limpio y trazable.
3. **Flexibilidad**: Diseñado para crecer con tus necesidades.

::: callout-info
## Software con Alma
Cada línea de este sistema ha sido escrita pensando en la elegancia y la eficiencia. No buscamos ser el CMS más grande, sino el más honesto.
:::
",
            'is_published' => true,
            'published_at' => date('Y-m-d H:i:s'),
            'in_menu' => true,
            'menu_order' => 10,
            'type' => 'page',
        ]);

        // Página fija 2
        Post::updateOrCreate(['slug' => 'servicios-digitales'], [
            'title' => 'Servicios Digitales',
            'meta_title' => 'Nuestros Servicios',
            'meta_description' => 'Descubre cómo podemos ayudarte a construir tu presencia en la web de forma sólida.',
            'content' => "## ¿Qué podemos hacer por ti?

Ofrecemos soluciones directas y sin rodeos:

- **Desarrollo Web a Medida**: Aplicaciones rápidas y escalables.
- **Consultoría Técnica**: Mentoría para equipos de desarrollo.
- **Optimización de Sistemas**: Modernización de código legacy.

::: callout-note
## Resultados Tangibles
Nos enfocamos en la arquitectura y los cimientos. Si la base es sólida, el crecimiento es natural.
:::

#### ¿Interesado?
Echa un vistazo a nuestro [Laboratorio](/blog) para ver en qué estamos trabajando.",
            'is_published' => true,
            'published_at' => date('Y-m-d H:i:s'),
            'in_menu' => true,
            'menu_order' => 20,
            'type' => 'page',
        ]);

        echo "Seeded/Updated Test Content: Bienvenido, Acerca de y Servicios\n";
    }
}

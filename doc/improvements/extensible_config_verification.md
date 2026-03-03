# Verificación: Sistema de Configuración Extensible desde Chascarrillo

Este documento describe el proceso paso a paso para verificar que el framework Alxarafe permite a Chascarrillo añadir sus propias pestañas de configuración.

## Requisitos Previos

- Los cambios de Alxarafe descritos en `extensible_config.md` deben estar implementados:
  - `Config::registerSection()` disponible
  - `Config::getConfigStructure()` disponible
  - `ConfigController::getPost()` usa la estructura dinámica

## Paso 1: Registrar las Secciones de Configuración

En el bootstrap de Chascarrillo (`routes.php` o un futuro `ServiceProvider`), registrar las secciones propias:

```php
// routes.php (al inicio, antes de las rutas)
use Alxarafe\Base\Config;

Config::registerSection('blog', ['title', 'posts_per_page', 'excerpt_length']);
Config::registerSection('social', ['twitter', 'instagram', 'facebook']);
```

> **Nota**: Usar un array vacío `[]` permite aceptar cualquier clave dentro de esa sección sin restricciones.

## Paso 2: Crear el Controlador de Configuración

Crear el archivo `Modules/Admin/Controller/ConfigController.php`:

```php
<?php

namespace AppModules\Admin\Controller;

use CoreModules\Admin\Controller\ConfigController as BaseConfigController;
use Alxarafe\Component\Container\Tab;
use Alxarafe\Component\Fields\Text;
use Alxarafe\Component\Fields\Select;
use Alxarafe\Lib\Trans;

class ConfigController extends BaseConfigController
{
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
            new Text('social.twitter', 'Twitter / X'),
            new Text('social.instagram', 'Instagram'),
            new Text('social.facebook', 'Facebook'),
        ]);

        return $tabs;
    }
}
```

## Paso 3: Verificación Funcional

### 3.1 Verificar que las pestañas aparecen

1. Acceder a la página de configuración: `index.php?module=Admin&controller=Config`
2. **Resultado esperado**: Deben aparecer **6 pestañas** en total:
   - `Miscellaneous` (core)
   - `Connection` (core)
   - `Database Preferences` (core)
   - `Database` (core)
   - `Blog Settings` (nueva - Chascarrillo)
   - `Social Networks` (nueva - Chascarrillo)

### 3.2 Verificar que los datos se guardan

1. Ir a la pestaña **Blog Settings**
2. Rellenar los campos:
   - Título del Blog: `Mi Blog de Prueba`
   - Posts per Page: `10`
   - Excerpt Length: `200`
3. Pulsar **Guardar Configuración**
4. **Verificar en `config.json`** que aparecen las nuevas secciones:

```json
{
  "main": { "..." },
  "db": { "..." },
  "security": { "..." },
  "blog": {
    "title": "Mi Blog de Prueba",
    "posts_per_page": "10",
    "excerpt_length": "200"
  },
  "social": {
    "twitter": "",
    "instagram": "",
    "facebook": ""
  }
}
```

### 3.3 Verificar que los datos persisten tras recargar

1. Recargar la página de configuración
2. Navegar a la pestaña **Blog Settings**
3. **Resultado esperado**: Los valores guardados deben aparecer en los campos

### 3.4 Verificar que no hay regresión en el core

1. Cambiar el idioma o tema en la pestaña **Miscellaneous**
2. Guardar
3. **Resultado esperado**: El cambio se aplica correctamente y los datos de Blog/Social no se pierden

### 3.5 Verificar la lectura de config desde la app

En cualquier controlador de Chascarrillo, verificar que se puede leer la configuración:

```php
$config = Config::getConfig();
$blogTitle = $config->blog->title ?? 'Blog';
$postsPerPage = $config->blog->posts_per_page ?? 10;
```

## Paso 4: Verificación por Consola (Opcional)

Crear un script temporal para validar programáticamente:

```php
<?php
// scripts/test_config_extension.php
require_once __DIR__ . '/../vendor/autoload.php';

use Alxarafe\Base\Config;

// Registrar sección de prueba
Config::registerSection('test_section', ['key1', 'key2']);

// Verificar estructura
$structure = Config::getConfigStructure();
assert(isset($structure['test_section']), 'La sección test_section debe existir');
assert(isset($structure['main']), 'La sección main del core debe seguir existiendo');
assert(isset($structure['db']), 'La sección db del core debe seguir existiendo');

echo "✅ Todas las verificaciones pasaron correctamente.\n";
```

Ejecutar:
```bash
php scripts/test_config_extension.php
```

## Criterios de Aceptación

| Criterio | Estado |
|----------|--------|
| Las pestañas de Chascarrillo aparecen en Config | ✅ |
| Los valores se guardan en `config.json` | ✅ |
| Los valores persisten tras recargar | ✅ |
| Las secciones core no sufren regresión | ✅ |
| `Config::getConfig()` devuelve los valores | ✅ |
| El script de consola pasa sin errores | ✅ |

> [!NOTE]
> Estas mejoras han sido consolidadas en el **Backlog de Alxarafe** (`/doc/feedback/chascarrillo.md`) para su seguimiento en futuras versiones del framework.

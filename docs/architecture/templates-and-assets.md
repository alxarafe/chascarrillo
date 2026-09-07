# Plantillas Blade y assets públicos

## Topología

La raíz de la aplicación contiene `Modules/`, `templates/`, `vendor/`, `Content/`, `storage/` y `var/`. El document root del servidor es únicamente `public_html/`. En producción, `vendor/` debe permanecer junto a `public_html/`, nunca dentro de él.

Las plantillas Blade son código de servidor y solo pueden residir en `templates/`, `Modules/*/Templates/` o `vendor/alxarafe/alxarafe/templates/`. `public_html/` contiene únicamente CSS, JavaScript, imágenes, fuentes y otros ficheros que descarga el navegador. Una búsqueda previa a release debe devolver vacío:

```bash
find public_html -type f -name '*.blade.php' -print
```

## Cadena de renderizado de una página pública

`PageController::doShow()` selecciona `page/chascarrillo/index` para la portada y `page/show` para otra página. Ambas extienden `partial.layout.main`.

El layout base de Alxarafe incluye `partial.body_empty` cuando `$empty` es verdadero y `partial.body_standard` en otro caso. Una página pública normal no define `$empty`, por lo que usa `partial.body_standard`. Tanto `body_standard` como `body_empty` incluyen `partial.user_menu`. Cyberpunk sustituye el layout completo e incluye directamente `partial.project_menu` y `partial.user_menu`.

## Orden de rutas

`WebDispatcher` añade, en este orden, para el módulo y tema resueltos:

1. `templates/themes/<tema>/`
2. `vendor/alxarafe/alxarafe/templates/themes/<tema>/`
3. `Modules/<Modulo>/templates/`
4. `Modules/<Modulo>/Templates/`
5. `vendor/alxarafe/alxarafe/src/Modules/<Modulo>/templates/`
6. `vendor/alxarafe/alxarafe/src/Modules/<Modulo>/Templates/`
7. `templates/`
8. `vendor/alxarafe/alxarafe/templates/`

El primer fichero encontrado gana. Hay un detalle de Alxarafe v0.6.11: el constructor de `ViewController` registra antes las rutas del tema configurado, `templates/` y las plantillas base; el dispatcher añade después la lista anterior (con barra final, por lo que algunas rutas aparecen dos veces). Para el tema configurado habitual el resultado observado es el de la tabla siguiente. La divergencia entre el tema resuelto por cookie/usuario y el usado por el constructor debe corregirse en Alxarafe antes de 1.0; Chascarrillo no modifica `vendor/`.

| Vista | Default | High Contrast | Cyberpunk |
|---|---|---|---|
| `partial.layout.main` | Alxarafe base | Alxarafe base | Alxarafe Cyberpunk |
| `partial.body_standard` | Alxarafe base | Alxarafe base | Alxarafe base, no usada por su layout |
| `partial.body_empty` | Alxarafe base | Alxarafe base | Alxarafe base, no usada por su layout |
| `partial.user_menu` | Alxarafe base | Alxarafe base | Alxarafe Cyberpunk |
| `partial.theme_switcher` | Chascarrillo general | Chascarrillo general | Chascarrillo Cyberpunk |
| `partial.lang_switcher` | Alxarafe base | Alxarafe base | Chascarrillo Cyberpunk |
| `partial.project_menu` | Chascarrillo general | Chascarrillo general | Chascarrillo general |

Un `templates/partial/user_menu.blade.php` local eclipsa el menú completo de Alxarafe. El override histórico contenía únicamente `@include('partial.project_menu')`; por eso desaparecían reloj, login, idioma, tema y usuario a la vez.

## Caché

`Template` compila en `var/cache/blade/<tema>/`, usando primero el tema de configuración y, si existe, la cookie `alx_theme`. La separación evita contaminación ordinaria entre temas. No soluciona un override obsoleto que siga físicamente en la primera ruta. Después de actualizar se elimina de forma explícita todo `var/cache/blade/`; el resto de `var/` permanece protegido.

OPcache puede conservar bytecode de PHP antiguo, incluido código compilado de Blade o clases sustituidas durante la petición. El instalador invalida cada fichero copiado y ejecuta `opcache_reset()` cuando está disponible. Su ausencia no es un error; en hosting puede ser necesario reiniciar PHP desde el panel.

## Publicación

`ThemeAssetPublisher` recorre solo `css`, `js`, `img`, `images`, `fonts` y `assets`, y solo extensiones estáticas permitidas. Publica Alxarafe primero y Chascarrillo después. `public_html/themes/.chascarrillo-theme-assets.json` registra huellas: se retiran únicamente assets antes administrados y no modificados. Ficheros desconocidos o personalizados se conservan. Dos ejecuciones consecutivas producen el mismo árbol.

## Cambio requerido en Alxarafe antes de 1.0

No se ha modificado `../alxarafe` ni `vendor/`. La corrección estructural corresponde a:

- `src/Infrastructure/Http/Controller/ViewController.php`: dejar de registrar rutas antes de conocer la preferencia efectiva resuelta por dispatcher;
- `src/Infrastructure/Tools/Dispatcher/WebDispatcher.php`: construir una sola lista canónica, sin duplicados con/sin barra final, y pasar el tema efectivo al controlador;
- `src/Infrastructure/Persistence/Template.php`: usar el mismo tema efectivo para rutas y caché, y validar el nombre contra los temas disponibles antes de incorporarlo a un path.

La propuesta es extraer un `TemplatePathResolver` probado con configuración, usuario y cookie. Debe devolver app-theme, framework-theme, módulo app, módulo framework, app general y framework general en ese orden. `Template` recibirá además una clave de caché ya validada. El impacto es transversal a todas las aplicaciones Alxarafe, por lo que necesita pruebas en el repositorio del framework y una nota de compatibilidad; copiar ese cambio en Chascarrillo ocultaría el defecto y no es aceptable.

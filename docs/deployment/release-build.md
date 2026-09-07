# Construcción reproducible de releases

Una release desplegable se construye desde un tag en un checkout limpio. `composer.lock` es parte del artefacto y no se elimina ni regenera.

## Secuencia canónica

```bash
composer validate --strict
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php scripts/publish_theme_assets.php
php scripts/build_distribution_manifest.php vX.Y.Z
php scripts/validate_release.php
```

Después se crea `chascarrillo-deploy-vX.Y.Z.zip`, incluyendo `vendor/`, `.chascarrillo-managed-files.json` y los assets ya publicados. Se extrae el ZIP en un directorio temporal y se vuelve a ejecutar `scripts/validate_release.php <directorio>`.

La validación compara la versión y referencia de `alxarafe/alxarafe` en `composer.lock` con `vendor/composer/installed.json`. También exige:

- `vendor/alxarafe/alxarafe/templates/partial/layout/main.blade.php`;
- `body_standard.blade.php`, `body_empty.blade.php` y `user_menu.blade.php`;
- los selectores de idioma, tema y proyecto;
- los contratos de reloj, login y usuario del menú;
- ausencia de `.blade.php` dentro de `public_html/`;
- coincidencia hash por hash con el manifiesto.

El workflow `.github/workflows/deploy-package.yml` implementa esta secuencia y falla ante cualquier discrepancia. Nunca usa `composer update`.

## Composer y el actualizador

`composer install` instala exactamente lo fijado en `composer.lock`. `composer update` resuelve versiones nuevas, modifica el lock y no pertenece al build de una release reproducible.

Solo Composer ejecuta los eventos `post-install-cmd` y `post-update-cmd` declarados por el proyecto raíz. Los scripts definidos por dependencias no se heredan como scripts del proyecto. `UpdateService` no ejecuta Composer ni sus eventos: recibe un ZIP autocontenido y llama directamente al instalador y al publicador de Chascarrillo.

## Contenido del ZIP

El ZIP excluye configuración y datos de la instalación (`config.json`, `.env`, `Content/`, `storage/`, `var/`, uploads y `.htaccess`) y herramientas de QA. Incluye el runtime completo, todo `vendor/`, plantillas privadas y assets públicos. No debe construirse a partir del source zip automático de GitHub.

## Checklist de release 1.0

- checkout limpio y tag coherente con `UpdateService::VERSION`;
- `composer.lock` versionado e inalterado tras `composer install`;
- versión y referencia instaladas de Alxarafe coincidentes con el lock;
- PHPUnit, PHPCS, PHPStan, Psalm y `composer validate --strict` en verde;
- publicador ejecutado dos veces sin cambios en la segunda;
- validación del árbol y del ZIP extraído;
- prueba fría/caliente de Blade en Default, High Contrast y Cyberpunk;
- navegación pública y login en 360, 768, 1024, 1280 y 1440 px;
- reloj esperado solo a partir de 1200 px por `d-none d-xl-flex`;
- ensayo de actualización y recuperación en una copia de producción;
- release solo después de conservar evidencia del ensayo.

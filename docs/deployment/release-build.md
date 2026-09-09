# Construcción reproducible de releases

Una release desplegable se construye desde un tag en un checkout limpio. `composer.lock` es parte del artefacto y no se elimina ni regenera.

## Secuencia canónica

```bash
composer validate --strict
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php scripts/publish_theme_assets.php
php scripts/build_distribution_manifest.php
php scripts/validate_release.php
```

Después se crea `chascarrillo-deploy-vX.Y.Z.zip`, incluyendo `vendor/`, `.chascarrillo-managed-files.json` y los assets ya publicados. Se extrae el ZIP en un directorio temporal y se vuelve a ejecutar `scripts/validate_release.php <directorio>`.

`UpdateService::VERSION` es la única fuente canónica y se expresa sin prefijo `v`. El generador no recibe una versión para copiar: incorpora directamente esa constante como `application_version`. Para una publicación por tag, build y validación reciben `--tag "$TAG_NAME"`; solo se retira una `v` inicial y el resultado debe coincidir exactamente con la constante y el manifiesto. La opción es deliberadamente opcional para permitir validaciones locales sin inventar un tag.

Formato mínimo del manifiesto de distribución 2:

```json
{
  "format": 2,
  "application_version": "0.8.17",
  "files": []
}
```

La validación compara la versión y referencia de `alxarafe/alxarafe` en `composer.lock` con `vendor/composer/installed.json`. También exige:

- `vendor/alxarafe/alxarafe/templates/partial/layout/main.blade.php`;
- `body_standard.blade.php`, `body_empty.blade.php` y `user_menu.blade.php`;
- los selectores de idioma, tema y proyecto;
- los contratos de reloj, login y usuario del menú;
- ausencia de `.blade.php` dentro de `public_html/`;
- coincidencia hash por hash con el manifiesto.
- `application_version` con formato semántico y coincidente con `UpdateService::VERSION` del propio artefacto;
- tag coincidente, cuando se proporciona mediante `--tag`.

El workflow `.github/workflows/deploy-package.yml` implementa esta secuencia y falla ante cualquier discrepancia. Nunca usa `composer update`.

Un error que mencione `application_version`, `UpdateService::VERSION` o la versión del tag indica que se intentó construir o publicar un artefacto incoherente. Corrija la constante o use el tag exacto `v<UpdateService::VERSION>`; no edite el manifiesto a mano.

## Composer y el actualizador

`composer install` instala exactamente lo fijado en `composer.lock`. `composer update` resuelve versiones nuevas, modifica el lock y no pertenece al build de una release reproducible.

Solo Composer ejecuta los eventos `post-install-cmd` y `post-update-cmd` declarados por el proyecto raíz. Los scripts definidos por dependencias no se heredan como scripts del proyecto. `UpdateService` no ejecuta Composer ni sus eventos: recibe un ZIP autocontenido y llama directamente al instalador y al publicador de Chascarrillo.

## Contenido del ZIP

El ZIP excluye configuración y datos de la instalación (`config.json`, `.env`, `Content/`, `storage/`, `var/`, uploads y `.htaccess`) y herramientas de QA. Incluye el runtime completo, todo `vendor/`, plantillas privadas y assets públicos. No debe construirse a partir del source zip automático de GitHub.

## Base histórica autenticada v0.8.17

`resources/update-baselines/v0.8.17.json` procede del asset publicado, no del checkout ni del
estado de una instalación:

- metadata independiente: `https://api.github.com/repos/alxarafe/chascarrillo/releases/tags/v0.8.17`;
- tag: `v0.8.17`;
- asset: `chascarrillo-deploy-v0.8.17.zip`;
- URL: `https://github.com/alxarafe/chascarrillo/releases/download/v0.8.17/chascarrillo-deploy-v0.8.17.zip`;
- SHA-256 esperado por GitHub: `84a64c84db7b8eb70e16a057c3d36a4559023f9089edf1875b8b07bd43470c87`;
- SHA-256 obtenido localmente antes de extraer:
  `84a64c84db7b8eb70e16a057c3d36a4559023f9089edf1875b8b07bd43470c87`.

El inventario contiene 3.636 ficheros después de excluir rutas permanentemente protegidas y tiene
su propia huella canónica de entradas,
`a66daa1485e6b3fa27a214ca302a562ab9760b37610aead3f24d0325f698d227`. El ZIP descargado se
conserva fuera del repositorio y no forma parte de la release.

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

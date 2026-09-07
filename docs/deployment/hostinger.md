# Despliegue y recuperación en Hostinger

## Estructura

Configure el document root del dominio en `<APP_ROOT>/public_html`. Mantenga `vendor/`, `templates/`, `Modules/`, `Content/`, `storage/`, `var/`, `composer.json` y `composer.lock` en `<APP_ROOT>`, fuera de la zona pública.

El usuario PHP necesita lectura del runtime y escritura en `Content/`, `storage/`, `var/` y uploads. No conceda acceso web directo a plantillas, configuración o vendor.

## Reparación exacta de chascarrillo.es

No ejecutar desde este repositorio; realizar primero en una copia o ventana de mantenimiento:

1. Activar mantenimiento y anotar la versión actual.
2. Crear un backup restaurable de `<APP_ROOT>` y una exportación de la base de datos.
3. Obtener el asset desplegable oficial de la misma versión o de la versión objetivo; no usar `Source code (zip)`.
4. Extraerlo fuera de `<APP_ROOT>` y comprobar que incluye:

   ```text
   vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php
   vendor/alxarafe/alxarafe/templates/partial/body_standard.blade.php
   vendor/alxarafe/alxarafe/templates/partial/body_empty.blade.php
   public_html/themes/default/css/default.css
   ```

5. Comparar `composer.lock` y `vendor/composer/installed.json`: Alxarafe debe ser v0.6.11 y referencia `4b5a6252750537280c04aa4378b3fcf2570f8efb` para Chascarrillo v0.8.17.
6. Sin tocar configuración, contenido, almacenamiento, uploads ni `.htaccess`, sincronizar el runtime completo del asset, incluido todo `vendor/`. La actualización parcial de solo `public_html/` no es válida.
7. Confirmar que `<APP_ROOT>/templates/partial/user_menu.blade.php` no existe. Si reaparece y contiene solo `@include('partial.project_menu')`, retirarlo. El `theme_switcher` general puede heredarse de Alxarafe o ser el override actual de Chascarrillo; no causa la desaparición conjunta del bloque.
8. Ejecutar desde `<APP_ROOT>`:

   ```bash
   php scripts/publish_theme_assets.php
   rm -rf var/cache/blade
   ```

   Antes del `rm`, comprobar literalmente que el directorio resuelto es `<APP_ROOT>/var/cache/blade`. No borrar el resto de `var/`.
9. Reiniciar PHP/OPcache desde el panel de Hostinger. Si la configuración permite CLI con el mismo pool, `opcache_reset()` es opcional; no asumir que el CLI comparte OPcache con PHP-FPM.
10. Probar una página pública y login en Default, High Contrast y Cyberpunk, primero como invitado y después autenticado. A 1280/1440 debe existir `#clock-display`; a anchuras inferiores el reloj está oculto por diseño. Login, idioma y tema deben seguir presentes.
11. Revisar logs, salir de mantenimiento y mantener backup y paquete anterior durante la observación.

Con el nuevo formato de release, ejecutar además `php scripts/validate_release.php` antes de sincronizar, y usar `UpdateService` para que el manifiesto retire el residuo automáticamente.

## Recuperación de instalación parcial

Si falla antes de migraciones, mantener mantenimiento, conservar logs y restaurar el árbol desde el backup; volver a enlazar o copiar únicamente los directorios persistentes. Si ya se ejecutaron migraciones, restaurar conjuntamente ficheros y base de datos del mismo punto temporal. No mezclar `vendor/` de una versión con código o lock de otra.

No se considera recuperada hasta validar versión/referencia, plantillas, assets, caché fría y caliente y los tres temas. Una página que abre sin error no basta.

## Recomendación 1.0

Validar con Hostinger si se puede cambiar el document root o un symlink. Si es posible, adoptar `releases/<version>` + `current`. Si no, automatizar mantenimiento, backup, reemplazos por fichero, marcador de estado y restauración conjunta. Un ensayo cronometrado en un clon de producción es requisito de salida.

# Diagnóstico de plantillas y menú superior

## Síntoma: falta toda la zona derecha

Si aparece la navegación izquierda pero desaparecen a la vez login/usuario, reloj, idioma y tema, inspeccione primero qué fichero resuelve `partial.user_menu`. En Chascarrillo v0.8.17, `partial.body_standard` incluye esa vista una sola vez.

La causa demostrada en la regresión es un fichero heredado de releases antiguas:

```text
templates/partial/user_menu.blade.php
```

con contenido único:

```blade
@include('partial.project_menu')
```

El actualizador anterior solo copiaba; como la release nueva ya no contenía el fichero, no lo eliminaba. `templates/` precede a las plantillas base de Alxarafe y el override seguía ganando en producción.

## Secuencia de diagnóstico

Desde la raíz privada de la aplicación:

```bash
git status --short
find public_html -type f -name '*.blade.php' -print
find templates Modules -type f -name '*.blade.php' -print
php -r '$d=json_decode(file_get_contents("vendor/composer/installed.json"),true); foreach(($d["packages"]??$d) as $p){if(($p["name"]??"")==="alxarafe/alxarafe"){echo $p["version"]." ".($p["source"]["reference"]??"").PHP_EOL;}}'
```

Compruebe después los siete ficheros esenciales documentados en `ReleaseValidator::ESSENTIAL_TEMPLATES`. No copie plantillas a `public_html/`.

Para cada tema, registre las rutas en el orden real y el path elegido. Default y High Contrast deben resolver el `user_menu` base de Alxarafe; Cyberpunk debe resolver el suyo. El selector de tema general de Chascarrillo es un override intencionado y completo, no el residuo de una línea.

## Caché y OPcache

Eliminar un override no garantiza que una petición PHP ya iniciada abandone inmediatamente bytecode antiguo. Después de sincronizar todos los ficheros:

1. retirar únicamente `var/cache/blade/`;
2. reiniciar el pool PHP/OPcache o usar la opción del panel;
3. cargar una vez (caché fría) y recargar (caché caliente);
4. comparar ambos HTML.

La caché Blade se separa por tema. Una caché antigua es un factor contribuyente, no explica por sí sola que el override físico sobreviva a la release. OPcache tampoco recrea un fichero borrado; puede mantener código ya cargado hasta reiniciar el proceso.

## Comprobaciones HTML

En invitado, buscar:

- `id="clock-display"` a partir de 1200 px;
- enlace que contiene `controller=Auth` fuera de la propia pantalla de login;
- inclusión efectiva de `partial.lang_switcher`;
- inclusión efectiva de `partial.theme_switcher`.

En autenticado, comprobar `id="navbarUser"` en Default/High Contrast y el avatar o menú de usuario Cyberpunk. Probar 360, 768, 1024, 1280 y 1440 px. El reloj usa `d-none d-xl-flex`, por lo que su ausencia bajo 1200 px es correcta; los otros controles no deben desaparecer.

## Interpretación

- Si falta todo el bloque: resolución de `user_menu`, actualización incompleta de `vendor/` o caché antigua.
- Si falta solo un selector: inspeccionar el partial específico y su ruta de tema.
- Si el HTML contiene controles pero no se ven: revisar CSS publicado, viewport y overflow del topbar.
- Si cambia entre primera y segunda carga: caché Blade/OPcache o rutas de tema incoherentes.
- Si solo falla Cyberpunk: comprobar su layout y `templates/themes/cyberpunk/partial/`.

`WorldsitesController` y su error `Class "Config" not found` son independientes salvo que interrumpan la ruta exacta usada como smoke test.

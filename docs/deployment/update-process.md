# Proceso de actualización

`UpdateService` solo acepta el asset `chascarrillo-deploy-*.zip`; un source zip no es instalable. Descarga y extrae el paquete, y `ReleaseInstaller` valida el origen antes de modificar la instalación.

## Manifiesto, preflight y modificaciones locales

Cada paquete nuevo contiene `.chascarrillo-managed-files.json`, con `format: 2`, `application_version`, rutas relativas y SHA-256. `application_version` debe coincidir con `UpdateService::VERSION` dentro del propio paquete. Tras una actualización correcta se conserva como manifiesto de la versión instalada.

Antes de la primera mutación, el instalador valida por completo el artefacto, carga el manifiesto
anterior, construye el plan completo y calcula SHA-256 sobre cada destino afectado. La decisión usa:

- huella anterior: contenido declarado por el manifiesto instalado o, solo en la transición
  controlada, por la base oficial v0.8.17;
- huella real: contenido que existe en la instalación al ejecutar el preflight;
- huella nueva: contenido declarado por el manifiesto del artefacto entrante.

Para un fichero que continúa administrado:

| Estado real | Decisión |
|---|---|
| ausente | instalarlo |
| igual a la huella anterior | actualizarlo |
| igual a la huella nueva | dejarlo intacto y adoptarlo de forma idempotente |
| distinto de ambas | conflicto; abortar toda la actualización |

Si una ruta nueva no estaba administrada, solo se adopta cuando su contenido ya es binariamente
idéntico a la huella nueva. Una colisión distinta es un conflicto. Un fichero obsoleto solo se
elimina si coincide con su huella anterior; si fue modificado, se conserva y se aborta. Directorios,
otros tipos de nodo y cualquier enlace simbólico donde se espera un fichero también son conflictos.

El algoritmo es:

1. validar estructura, Composer, plantillas, versión de aplicación y manifiesto del paquete;
2. cargar y validar el manifiesto instalado, o seleccionar la base histórica admitida si falta;
3. clasificar todos los destinos, acumular conflictos y ordenar el informe por ruta;
4. abortar si existe cualquier conflicto, sin copiar, borrar, publicar assets, limpiar Blade,
   migrar, promover el manifiesto ni invalidar OPcache;
5. revalidar todas las precondiciones y, justo antes de cada operación, el tipo y la huella esperados;
6. copiar mediante temporal y `rename`, y retirar los obsoletos intactos;
7. publicar assets, limpiar `var/cache/blade/` e invalidar OPcache si está disponible;
8. verificar todas las huellas y la salud del runtime;
9. guardar el manifiesto nuevo;
10. ejecutar migraciones de base de datos y solo entonces anunciar éxito.

### Transición controlada desde v0.8.17

El asset oficial v0.8.17 no contenía un manifiesto administrado. B2 usa un inventario explícito de
sus 3.636 ficheros no protegidos, generado después de verificar el ZIP contra el digest publicado
por la API de GitHub. No calcula supuestas huellas anteriores desde la instalación.

El `UpdateService` incluido en el tag v0.8.17 sigue siendo el copiador recursivo permisivo y no puede
proteger la petición en la que él mismo se sustituye. Por ello, en las instalaciones conocidas y
controladas, primero se debe desplegar manualmente el runtime B2 completo —incluidos
`ReleaseInstaller`, sus clases auxiliares y la base histórica— mediante el procedimiento
administrativo revisado. Solo después puede iniciarse la transición protegida.

Sin manifiesto, cada fichero oficial intacto se reconoce por la base v0.8.17; uno ya idéntico al
nuevo se adopta; cualquier divergencia se informa y aborta. El override anterior a v0.8.17
`templates/partial/user_menu.blade.php` conserva una única regla adicional por su huella exacta y
documentada. No existe adopción automática general para instalaciones de terceros desconocidas.

El campo histórico `version` se admite únicamente como `v0.8.17` en un manifiesto ya instalado y se
normaliza a `0.8.17`. Un artefacto entrante siempre necesita `application_version`. Mezclar ambos
campos, usar otra versión histórica o entregar un manifiesto corrupto produce un fallo seguro.

## Rutas permanentemente protegidas

- `config.json` y `.env`;
- `Content/` y `storage/`;
- `uploads/`;
- `var/`, salvo la limpieza explícita de `var/cache/blade/`;
- `public_html/uploads/`;
- cualquier `.htaccess`;
- temas y ficheros personalizados no presentes en el manifiesto de distribución.

## Resolución de conflictos

El diagnóstico muestra solo la ruta relativa, la clase y las huellas anterior, real y nueva que
procedan; no incluye contenido ni rutas absolutas. El administrador debe:

1. mantener la instalación en modo mantenimiento y conservar un backup;
2. revisar cada ruta del informe y guardar fuera del árbol cualquier cambio que quiera conservar;
3. restaurar exactamente la versión anterior, aceptar manualmente la nueva, o mover el fichero si
   ya no debe ocupar una ruta administrada;
4. volver a ejecutar la actualización sin editar ni fabricar el manifiesto instalado.

Un aborto del preflight deja intactos archivos, assets, caché Blade, manifiesto, versión, OPcache y
base de datos.

## Fallos y límites pendientes

Un fallo de lectura, copia, publicación, limpieza o validación lanza un error y evita el mensaje de éxito. Una versión ausente, inválida o distinta del tag o de `UpdateService::VERSION` se rechaza antes de copiar el primer fichero. Las migraciones también deben devolver éxito. El log y el mensaje de mantenimiento deben conservar el primer error accionable.

El proceso actual hace reemplazos atómicos por fichero y revalida precondiciones para reducir
carreras, pero no es una transacción de árbol completo. B3 debe añadir estados persistentes y
recuperación tras interrupciones; B4 debe decidir la promoción definitiva del manifiesto, que por
ahora ocurre antes de las migraciones; B5 debe abordar actualización atómica del árbol, rollback y
recuperación de base de datos, y autenticidad criptográfica de artefactos/manifiestos. Hoy las
huellas detectan corrupción y cambios locales, pero un atacante con permiso para sustituir a la vez
el código y su manifiesto queda fuera del modelo B2. Por ello una copia de seguridad sigue siendo
obligatoria.

## Diseño de actualización atómica para 1.0

En un hosting compatible, preparar `releases/<version>/`, enlazar directorios persistentes (`Content`, `storage`, uploads y configuración), ejecutar las comprobaciones y cambiar un symlink `current` de forma atómica. Hostings compartidos que fijan `public_html` pueden usar dos árboles hermanos y un pequeño bootstrap estable, o una ventana de mantenimiento con copia completa y restauración ensayada.

Antes de 1.0 deben definirse: layout exacto permitido por Hostinger, presupuesto de disco para dos releases, tratamiento transaccional/rollback de migraciones, retención de backups y un marcador de estado que detecte y reanude o revierta una actualización interrumpida.

## Checklist de actualización

- backup verificable de ficheros y base de datos;
- espacio libre suficiente para ZIP, extracción y backup;
- paquete validado y versión objetivo confirmada;
- modo mantenimiento;
- actualización, publicación, cachés y migraciones sin errores;
- smoke tests guest/autenticado en los tres temas;
- comprobar logs y desactivar mantenimiento;
- conservar el paquete anterior y el backup hasta cerrar la observación.

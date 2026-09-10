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
7. publicar assets y limpiar `var/cache/blade/`;
8. verificar todas las huellas y la salud del runtime, invalidando cada script copiado y haciendo el
   reset global de OPcache antes de ejecutar código nuevo durante las migraciones;
9. persistir `migrations` y ejecutar las migraciones de base de datos;
10. repetir la validación aplicable del árbol instalado;
11. persistir `promoting_manifest` y, solo entonces, promover el manifiesto ya validado mediante un
    temporal regular verificado y `rename`;
12. persistir `completed` y solo después anunciar éxito.

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

## Estado persistente y exclusión mutua

El coordinador adquiere con `flock(LOCK_EX | LOCK_NB)` el bloqueo local
`var/update/update.lock` antes de leer estado, validar o mutar la instalación, y lo mantiene hasta
terminar. Que el archivo exista no significa que haya un proceso activo: solo el resultado de
`flock` lo determina. El sistema operativo libera el bloqueo si el proceso muere.

`var/update/state.json` se escribe con un temporal regular verificado y `rename`, siempre mediante
`SafePath`. Tanto los enlaces simbólicos como los componentes intermedios o tipos de nodo
inesperados se rechazan. El JSON usa `format_version: 1` y contiene solo: identificador aleatorio,
versiones anterior y objetivo, estado, última fase alcanzada, instantes UTC de inicio y
actualización, `mutations_started`, error sanitizado opcional y el intento interrumpido/fallido del
que procede un reinicio seguro. No contiene trazas, credenciales, contenido ni rutas absolutas.

Las fases observables, en orden, son:

1. `preparing`: validación, planificación y preflight, todavía sin mutaciones de aplicación;
2. `installing_files`: copias de ficheros;
3. `publishing_cleanup`: eliminaciones, assets, limpieza de Blade, verificaciones del árbol y
   preparación de OPcache;
4. `migrations`: migraciones;
5. `promoting_manifest`: validación final superada y promoción atómica del manifiesto;
6. `completed`.

El estado general incorpora además `filesystem_rolled_back` y `rollback_failed` para B5.2. Las transiciones fuera de
ese orden y cualquier esquema desconocido o incompleto fallan de forma segura. `completed`,
`failed` e `interrupted` son terminales para su intento. `mutations_started` pasa a `true` antes de
que pueda comenzar la primera copia y nunca vuelve a `false`; por tanto es deliberadamente
conservador.

Un intento `completed` o `filesystem_rolled_back` permite otro intento completo. También pueden repetirse un `failed` o `interrupted` con `mutations_started: false`. Los estados mutados anteriores a migraciones se reconcilian con el journal; `rollback_failed` y toda fase desde `migrations` bloquean. No existe un comando ni una ruta web para borrar o forzar el marcador.

## Journal y rollback de filesystem anterior a migraciones

Antes de la primera mutación, el plan B2 se amplía con las copias, retiradas y manifiesto del
publicador de assets. `SafePath` valida tipos y rutas; después se crea
`var/update/recovery/<attempt-id>/`. Esa ruta está protegida, no pertenece al manifiesto de
distribución y no participa en limpiezas ordinarias. Contiene `journal.json` (`format_version: 1`)
y `backups/NNNNNN.bin`: no almacena configuración, contenido, uploads, credenciales ni rutas
absolutas.

Cada operación registra tipo, ruta canónica, existencia o ausencia original, hash SHA-256 anterior
y nuevo, modo original y referencia al backup. Sus estados son `pending`,
`precondition_checked`, `applying`, `applied`, `restoring` y `restored`. Tanto el journal como cada
cambio de estado se escriben atómicamente. Las copias anteriores se crean mediante temporal y
`rename`, se vuelven a hashear y solo entonces se habilita la primera mutación. El presupuesto de
espacio usa los tamaños reales de backups y nuevos temporales, dos temporales máximos, dos copias
del JSON y un margen del 10 % con mínimo de 1 MiB.

Una excepción controlada en `installing_files` o `publishing_cleanup` detiene las mutaciones y
recorre el journal al revés. Para una operación `applying`, la reconciliación acepta únicamente
ausencia, hash anterior o alguno de los hashes producidos por el intento; nunca usa fechas ni
tamaños. Restaura backups verificados, retira un fichero originalmente ausente solo si aún coincide
con el hash instalado, restaura obsoletos y assets, y retira únicamente directorios creados que
continúan vacíos. Una divergencia posterior se considera conflicto y no se sobrescribe. Blade se
limpia después del rollback; OPcache se resetea por el mecanismo disponible, sin interpretar que el
CLI haya limpiado un PHP-FPM remoto.

El resultado íntegro se persiste como `filesystem_rolled_back` y permite un intento nuevo completo.
Cualquier ausencia, corrupción, symlink, transición inválida o fallo de restauración produce
`rollback_failed`, conserva journal/snapshot y bloquea. Al encontrar un `in_progress` con lock ya
libre, el coordinador completa la misma reconciliación solo si la fase es `installing_files` o
`publishing_cleanup` y el journal está íntegro.

Antes de instalar archivos, B5.3 compara las migraciones del artefacto con el registro aplicado.
Si no hay pendientes, omite la fase de base de datos y no exige snapshot. Si hay pendientes, el
proveedor predeterminado cierra en seguro: solo evidencia externa validada mediante el contrato
portable `DatabaseRecoveryProvider` permite continuar. El detalle operativo y los estados están en
[`database-recovery.md`](database-recovery.md).

`migrations` es una frontera estricta cuando se ha persistido `started`: desde entonces no hay
rollback automático de filesystem. Se conservan código, snapshots, ambos journals y estado, se
bloquean intentos y se exige recuperación coordinada. Un fallo anterior a `started` mantiene el
rollback B5.2. Restaurar código anterior sobre una base posiblemente nueva es inseguro. El
manifiesto principal anterior sigue intacto hasta después de migraciones.

Los snapshots de fallos y rollbacks se conservan. No se eliminan antes de `completed`; una
anotación o limpieza posterior fallida no cambia un `completed` a fallo. Provisionalmente, el
administrador debe mantener mantenimiento, copiar `state.json` y el directorio del intento para
la auditoría, verificar hashes y estado terminal y retirar manualmente solo directorios de intentos
`completed` cuya observación haya cerrado. No existe todavía retención automática avanzada ni una
acción web para borrar recovery.

## Fallos y límites pendientes

Un fallo de lectura, copia, publicación, limpieza o validación lanza un error y evita el mensaje de
éxito. Una versión ausente, inválida o distinta del tag o de `UpdateService::VERSION` se rechaza
antes de copiar el primer fichero. Cada migración debe terminar `up()`, persistir su fila de registro
y confirmar su checkpoint. Si falla, el estado queda `failed` en `migrations`, y el journal indica
la última aplicada y la actual fallida o ambigua. El manifiesto anterior permanece idéntico byte a
byte o, si no existía, continúa ausente. El intento siguiente queda bloqueado. Los ficheros no se
restauran automáticamente desde esta frontera y la base de datos no se revierte.

Si falla la promoción después de migrar, el estado queda `failed` en `promoting_manifest` y no se
anuncia éxito ni se revierten migraciones o ficheros. Mientras el `rename` no haya sustituido el
destino, el manifiesto anterior permanece intacto. Si la promoción termina, el manifiesto coincide
exactamente con el artefacto verificado; solo después se persiste `completed`. El log y el mensaje
de mantenimiento deben conservar el primer error accionable.

El proceso actual hace reemplazos atómicos por fichero y revalida precondiciones para reducir
carreras, y B4 retrasa la promoción definitiva del manifiesto hasta después de las migraciones y la
validación final. No es una transacción de árbol completo. B5.2 restaura el filesystem administrado
cuando B5.3 demuestra que la base no comenzó; B5.3 registra los límites y exige recuperación externa
validada para el DDL actual. B5.4 inspecciona y registra únicamente rollback o finalización
plenamente demostrados; no existe transacción de árbol completo. La autenticidad criptográfica de artefactos/manifiestos también queda
pendiente. Hoy las
huellas detectan corrupción y cambios locales, pero un atacante con permiso para sustituir a la vez
el código y su manifiesto queda fuera del modelo B2. Por ello una copia de seguridad sigue siendo
obligatoria.

## Diseño de actualización atómica para 1.0

En un hosting compatible, preparar `releases/<version>/`, enlazar directorios persistentes (`Content`, `storage`, uploads y configuración), ejecutar las comprobaciones y cambiar un symlink `current` de forma atómica. Hostings compartidos que fijan `public_html` pueden usar dos árboles hermanos y un pequeño bootstrap estable, o una ventana de mantenimiento con copia completa y restauración ensayada.

Antes de 1.0 deben definirse: layout exacto permitido por Hostinger, presupuesto de disco para dos
releases, tratamiento transaccional/rollback de migraciones, retención definitiva de backups y una
atestación verificable de restauraciones externas posteriores a DDL.

## Checklist de actualización

- backup verificable de ficheros y base de datos;
- espacio libre suficiente para ZIP, extracción y backup;
- paquete validado y versión objetivo confirmada;
- modo mantenimiento;
- actualización, publicación, cachés y migraciones sin errores;
- smoke tests guest/autenticado en los tres temas;
- comprobar logs y desactivar mantenimiento;
- conservar el paquete anterior y el backup hasta cerrar la observación.

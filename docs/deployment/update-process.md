# Proceso de actualización

`UpdateService` solo acepta el asset `chascarrillo-deploy-*.zip`; un source zip no es instalable. Descarga y extrae el paquete, y `ReleaseInstaller` valida el origen antes de modificar la instalación.

## Manifiesto y eliminación segura

Cada paquete nuevo contiene `.chascarrillo-managed-files.json`, con `format: 2`, `application_version`, rutas relativas y SHA-256. `application_version` debe coincidir con `UpdateService::VERSION` dentro del propio paquete. Tras una actualización correcta se conserva como manifiesto de la versión instalada.

El algoritmo es:

1. validar estructura, Composer, plantillas, versión de aplicación y manifiesto del paquete;
2. copiar cada fichero administrado mediante temporal y `rename`;
3. calcular `manifiesto anterior - manifiesto nuevo`;
4. retirar solo ficheros que estaban en el manifiesto anterior y cuya huella no ha sido modificada localmente;
5. ejecutar migraciones de compatibilidad conocidas para releases anteriores al manifiesto;
6. publicar assets con su propio manifiesto;
7. limpiar `var/cache/blade/` e invalidar OPcache si está disponible;
8. verificar todas las huellas y la salud del runtime;
9. guardar el manifiesto nuevo;
10. ejecutar migraciones de base de datos y solo entonces anunciar éxito.

Un fichero administrado que el usuario modificó no se borra como obsoleto: se contabiliza como preservado. Un fichero desconocido nunca se elimina.

La transición desde versiones sin manifiesto incluye una migración acotada: elimina el antiguo `templates/partial/user_menu.blade.php` únicamente si su SHA-256 coincide exactamente con el override de una línea conocido. También retira cinco copias Blade históricas de `public_html/themes/` solo cuando conservan su contenido original. Una variante personalizada queda intacta y requiere revisión manual.

Durante la transición desde 0.8.17, el instalador puede leer el campo histórico `version` únicamente del manifiesto que ya está instalado y solo para recuperar su lista de ficheros. Los paquetes entrantes siempre deben incluir `application_version` válida; la compatibilidad no se aplica a artefactos nuevos.

## Rutas permanentemente protegidas

- `config.json` y `.env`;
- `Content/` y `storage/`;
- `var/`, salvo la limpieza explícita de `var/cache/blade/`;
- `public_html/uploads/`;
- cualquier `.htaccess`;
- temas y ficheros personalizados no presentes en el manifiesto de distribución.

## Fallos

Un fallo de lectura, copia, publicación, limpieza o validación lanza un error y evita el mensaje de éxito. Una versión ausente, inválida o distinta del tag o de `UpdateService::VERSION` se rechaza antes de copiar el primer fichero. Las migraciones también deben devolver éxito. El log y el mensaje de mantenimiento deben conservar el primer error accionable.

El proceso actual hace reemplazos atómicos por fichero y pospone eliminaciones hasta terminar las copias, pero no es una transacción de árbol completo ni puede deshacer migraciones. Por ello una copia de seguridad sigue siendo obligatoria.

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

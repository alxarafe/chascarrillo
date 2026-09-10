# B5.3: protección y recuperación de base de datos

## Garantía implementada

El actualizador inspecciona el artefacto extraído antes de modificar el árbol administrado. Descubre
las migraciones de `Modules/*/Migrations` y del Alxarafe incluido en el paquete, conserva la misma
precedencia aplicación/framework y compara sus identificadores ordenados con la tabla `migrations`
sin crearla durante el preflight.

Un release sin migraciones pendientes continúa sin snapshot de base de datos. Si existen
migraciones pendientes, la política por defecto aborta en `preparing`, antes de la primera copia y
antes de mutar la base de datos. Solo una implementación inyectada de
`DatabaseRecoveryProvider` puede autorizar el paso aportando evidencia externa validada: proveedor,
referencia opaca, fecha de creación, fecha de verificación y si la restauración fue ensayada. El
núcleo no ejecuta `mysqldump`, comandos de panel o shell, ni almacena conexiones o credenciales.

El proveedor predeterminado es deliberadamente `unavailable`. Por tanto, esta versión bloquea en
producción cualquier actualización automática con migraciones hasta integrar un proveedor capaz de
verificar el mecanismo externo disponible en esa instalación. Una declaración, una casilla o la
mera presencia de un fichero no son prueba suficiente.

Las migraciones autorizadas se ejecutan una a una. El journal
`var/update/recovery/<attempt-id>/database.json` persiste únicamente la política saneada, los
identificadores y sus estados. No contiene SQL, trazas, rutas absolutas ni secretos.

## Estados y límites

Los estados por migración son:

- `pending`: todavía no se persistió el comienzo; la base de datos no empezó a mutar por esa
  migración;
- `started`: el comienzo se persistió antes de llamar a `up()`; una interrupción deja resultado
  desconocido;
- `applied`: `up()` terminó, la fila se insertó en `migrations` y el checkpoint se persistió;
- `failed`: el proceso observó una excepción después de `started`; la base puede haber cambiado;
- `ambiguous`: se recuperó un intento interrumpido con una migración `started`.

El journal general usa `not_required`, `pending`, `in_progress`, `applied`, `failed` o `ambiguous`.
Si falla la preparación anterior a `started`, B5.2 revierte el filesystem. Desde `started`, no se
revierte automáticamente el filesystem. Un fallo al persistir `applied` conserva duraderamente
`started`; no se vuelve a declarar la migración pendiente ni se anuncia éxito. Los estados
`failed` y `ambiguous` bloquean nuevos intentos.

Después de todas las migraciones se consulta otra vez el registro. Solo entonces se valida el árbol,
se promueve el manifiesto y se persiste `completed`. Si falla después de migrar y antes de promover,
el manifiesto anterior permanece, y los journals de filesystem y base de datos se conservan para
una restauración coordinada.

## Límites MySQL/MariaDB y transacciones

Las migraciones actuales de Chascarrillo y Alxarafe usan DDL mediante el schema builder. La prueba
de integración sobre MariaDB 10.11 demuestra que `CREATE TABLE` provoca commit implícito: envolver
ese DDL en una transacción no lo hace reversible mediante `ROLLBACK`. B5.3 no clasifica ninguna
migración actual como transaccional y reversible. Soportar esa categoría exigiría un contrato
explícito por migración y pruebas por motor; no se infiere de que exista `down()`.

Las diez migraciones de Chascarrillo son DDL. Las ocho migraciones incluidas por Alxarafe también
contienen DDL; `20260228090000_create_languages_table@Admin` mezcla además inserciones DML.

La aplicación anuncia realmente los drivers `mysql` y `pgsql`; MariaDB entra por el driver MySQL.
Esta entrega prueba la garantía conservadora sobre MariaDB. No afirma equivalencia transaccional de
DDL entre motores.

## Evidencia que debe conservar el administrador

Antes de autorizar una actualización con migraciones, conserve fuera del árbol actualizado:

1. identificador opaco del backup y proveedor;
2. instante y zona horaria del mismo punto de recuperación para base de datos y filesystem;
3. confirmación de que corresponde a la base y al sitio correctos;
4. estado de preparación/descarga o disponibilidad en el panel;
5. resultado del último ensayo de restauración, si existe;
6. paquete anterior, `state.json` y el directorio completo del intento.

`validated` significa que el proveedor comprobó los metadatos y la disponibilidad que su API o
mecanismo permite. `restore_tested` añade un ensayo de restauración registrado. Disponer de una
copia no equivale a haber probado que puede restaurarse.

## Hostinger/hPanel

El procedimiento operativo no forma parte del núcleo:

1. active mantenimiento y anote la fecha/hora exacta;
2. en **Websites → Dashboard → Backups → Restore and download**, seleccione por separado
   **Files backups** y **Database backups** del mismo punto temporal;
3. compruebe la base concreta y espere a que la preparación/descarga termine; conserve los
   identificadores o nombres que muestre hPanel;
4. mantenga una copia descargada fuera de la cuenta cuando el plan lo permita;
5. no autorice migraciones mientras no exista un proveedor B5.3 que valide esa referencia;
6. para recuperar, restaure primero la base de datos y después los ficheros del mismo punto, revise
   **Restore History**, limpie cachés y haga smoke tests antes de retirar mantenimiento.

Hostinger indica que una restauración parcial de un sitio debe acompañarse de la base del mismo
momento y que el historial muestra el resultado. Consulte la documentación oficial vigente:
<https://www.hostinger.com/support/4283700-how-to-restore-backups-at-hostinger/>.

## cPanel/WHM

1. active mantenimiento y anote el mismo punto temporal para DB y home/files;
2. en **Files → Backup** o **Backup Wizard**, descargue la base concreta y el home/directorio
   correspondiente; algunas funciones dependen de lo habilitado por el proveedor;
3. conserve nombres de los artefactos, fecha, cuenta, base y confirmación de descarga;
4. no autorice migraciones mientras no exista un proveedor B5.3 que valide esa referencia;
5. para recuperar con backups parciales, restaure la base y después el filesystem del mismo punto;
   una restauración completa puede requerir intervención del proveedor o privilegios WHM;
6. verifique cola, avisos y logs de restauración, luego pruebe la aplicación antes de retirar
   mantenimiento.

Documentación oficial: <https://docs.cpanel.net/cpanel/files/backup-for-cpanel/> y
<https://docs.cpanel.net/whm/backup/backup-restoration/>.

## Desarrollo local Docker

El núcleo usa el mismo contrato. La suite ejecuta migraciones falsas deterministas para cada límite
y una prueba real contra `chascarrillo_db` para la semántica DDL. Un backup de volumen, dump o
snapshot externo solo puede autorizar el flujo si un proveedor inyectado verifica su referencia;
la aplicación no presupone que Docker aporta rollback de base de datos.

## Reintento y restauración coordinada

Puede reintentarse automáticamente tras `completed`, `filesystem_rolled_back` o un fallo en
`preparing` sin mutaciones. No reintente tras `failed` o `ambiguous` de base de datos.

En esos casos:

1. mantenga mantenimiento y preserve ambos journals;
2. seleccione un único punto de recuperación anterior al intento;
3. restaure la base de datos de ese punto;
4. restaure el filesystem del mismo punto, incluido su manifiesto administrado;
5. invalide caches derivados;
6. verifique versión, esquema y tabla `migrations` antes de decidir un nuevo intento.

Invertir o mezclar el orden/punto puede dejar código viejo con esquema nuevo. B5.4 aporta la
inspección y el cierre administrativo seguro descritos en `update-reconciliation.md`; no convierte
una declaración de restauración en evidencia ni desbloquea un DDL iniciado que siga siendo ambiguo.

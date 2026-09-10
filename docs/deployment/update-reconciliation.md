# Reconciliación administrativa de actualizaciones

Esta herramienta se usa cuando var/update/state.json conserva un intento fallido o
interrumpido y el actualizador rechaza un nuevo intento. B5.4 no restaura archivos ni
bases de datos: inspecciona evidencias y, únicamente cuando todas coinciden, registra
que el intento puede cerrarse.

Conserve antes de actuar state.json, el directorio completo
var/update/recovery/<attempt-id>/, sus backups, el manifiesto instalado y la
referencia del backup de base de datos. No edite esos archivos para intentar obtener
otra clasificación.

## Inspeccionar

La inspección es de solo lectura y no toma ni crea el lock:

    php scripts/reconcile_update.php inspect
    php scripts/reconcile_update.php inspect --attempt=<id>
    php scripts/reconcile_update.php inspect --attempt=<id> --json

Ejecute el comando desde la raíz de la instalación. El identificador solo admite
32 caracteres hexadecimales y debe ser el intento activo de state.json; no se
aceptan rutas. La salida incluye una huella SHA-256 determinista de estado, journals,
backups, árbol administrado, manifiesto y tabla de migraciones.

Clasificaciones:

- SAFE_RETRY: el intento no empezó a mutar materialmente.
- CONFIRMED_ROLLBACK: el rollback B5.2, el árbol anterior, el manifiesto anterior
  y la ausencia de mutación DB quedan demostrados.
- CONFIRMED_COMPLETION: árbol y manifiesto objetivo, migraciones y validación final
  demuestran que el release terminó materialmente.
- RECOVERY_REQUIRED: existe un estado parcial y hace falta restauración externa.
- CONFLICT: alguna evidencia contradice ambos estados conocidos, incluido un
  archivo cambiado externamente, un árbol mezclado o un nodo inseguro.
- AMBIGUOUS: faltan pruebas o existe un checkpoint de migración iniciado que no
  permite afirmar rollback ni finalización.

RECOVERY_REQUIRED, CONFLICT y AMBIGUOUS permanecen bloqueados. Preserve la
evidencia, anote el diagnóstico y escale la incidencia; no edite journals ni
manifiestos para hacerlos coincidir.

Códigos de salida de inspect: 0 para una clasificación aplicable, 20 para
RECOVERY_REQUIRED, 21 para CONFLICT, 22 para AMBIGUOUS, 64 para uso
incorrecto y 65 para un rechazo de seguridad.

## Restaurar externamente no es aplicar

Una restauración externa es una operación del proveedor o del administrador sobre
filesystem y base de datos. apply no la realiza ni ejecuta SQL. Después de una
restauración se vuelve a ejecutar inspect; con la evidencia actual, un DDL que llegó
a started, failed o ambiguous no puede convertirse en rollback confirmado solo
porque falte su fila en migrations. La fila tampoco demuestra por sí sola que el DDL
terminara.

Si es necesaria una restauración, use filesystem y base de datos del mismo punto
lógico:

1. Ponga el sitio en mantenimiento y conserve la evidencia del intento.
2. Verifique la referencia y fecha UTC del backup externo ya registrada.
3. Restaure la base de datos correspondiente a esa referencia.
4. Restaure el filesystem completo de la misma referencia, incluido su manifiesto.
5. Compruebe manifiesto, permisos y conectividad sin modificar los journals.
6. Ejecute de nuevo inspect. Si sigue bloqueado, no ejecute apply.

B5.4 no invoca herramientas de Hostinger, cPanel, MySQL/MariaDB ni PostgreSQL.

## Aplicar una resolución demostrada

Copie literalmente resolución y huella de la inspección inmediatamente anterior:

    php scripts/reconcile_update.php apply \
      --attempt=<id> --resolution=rollback --expect=<sha256>

También pueden aparecer las resoluciones retry y completion. No se puede elegir
completed o rollback manualmente: la resolución debe ser la única autorizada por
la clasificación. apply adquiere el flock, repite toda la inspección bajo el lock
y rechaza si la huella cambió. La mera presencia de update.lock no bloquea; solo lo
hace un lock realmente adquirido.

Primero se guarda atómicamente
var/update/recovery/<attempt-id>/reconciliation.json; después se actualiza
state.json. Si falla el segundo paso, el estado anterior continúa bloqueado y la
evidencia inmutable permite reintentar la misma aplicación. Repetir un apply
idéntico no copia archivos, no borra nada y no ejecuta migraciones.

Códigos adicionales de apply: 30 si cambió la huella, 31 si otro proceso posee
el lock, 64 para uso incorrecto y 65 para cualquier otro rechazo seguro.

No existe --force: una confirmación humana no sustituye hashes, tipos de nodo,
manifiestos, checkpoints y filas reales.

## Hostinger, cPanel y Docker

En Hostinger o cPanel use únicamente el terminal PHP disponible, situado en la raíz
de la instalación:

    php scripts/reconcile_update.php inspect --json

Las funciones de backup/restauración del panel son un paso externo y deben usar la
misma referencia para archivos y DB. No pegue credenciales ni SQL en el comando.

En el entorno Docker de desarrollo:

    docker exec chascarrillo_php \
      php scripts/reconcile_update.php inspect --json

La herramienta solo necesita PHP, el código instalado y la conexión de aplicación
ya configurada; no depende de utilidades específicas del proveedor.

## Cierre de incidencia y retención

Tras una resolución aplicable:

1. Verifique que state.json contiene la reconciliación y que el registro conserva
   intento, UTC, clasificación, huella y evidencia saneada.
2. Para rollback/retry, ejecute un nuevo ciclo completo solo cuando proceda.
3. Para completion, no repita el mismo release.
4. Quite el mantenimiento y compruebe página pública, login administrativo, acceso
   de solo lectura a datos y logs de aplicación sin revelar información sensible.
5. Documente referencia de backup, resultado y comprobaciones operativas.

Política provisional: conserve siempre, sin fecha de caducidad automática, toda la
evidencia del intento activo, fallido, interrumpido o bloqueado. Para intentos
reconciliados conserve como mínimo state.json, ambos journals, backups, referencia
DB y reconciliation.json hasta que una política de retención 1.0 haya sido aprobada
y probada. B5.4 no elimina nada.

## Límites hasta 1.0

No hay snapshot verificable del esquema o contenido DB ni atestación posterior de
una restauración externa. Tampoco se conserva una copia independiente de ambos
manifiestos para todos los intentos históricos, ni existe firma criptográfica de
journals. Por ello algunos estados seguirán siendo AMBIGUOUS o
RECOVERY_REQUIRED; no deben desbloquearse. La limpieza automática y una política
definitiva de retención quedan para 1.0.

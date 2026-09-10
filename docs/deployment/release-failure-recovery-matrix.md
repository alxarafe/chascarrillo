# B5.1: matriz de fallos y recuperación del actualizador

Fecha de caracterización: 2026-09-10. Código verificado: `36e7aa617071a837e4e1cf23595b49a0d5fbce34`, rama `fix/reliable-release-updates`, versión `0.8.17`.

## Flujo y fronteras persistentes observados

El flujo efectivo es:

```text
flock
→ state: preparing
→ descarga, validación ZIP y extracción
→ validación de release
→ plan y doble preflight
→ state: installing_files
→ copias atómicas e invalidación OPcache por fichero
→ state: publishing_cleanup
→ eliminación de obsoletos
→ publicación y manifiesto de assets
→ eliminación de caché Blade
→ verificación de hashes, release y manifiesto previo
→ reset OPcache
→ state: migrations
→ migraciones
→ validación final del árbol
→ state: promoting_manifest
→ promoción atómica del manifiesto
→ state: completed
→ liberación del lock
→ mensaje de éxito y `true`
```

Los efectos persistentes son `var/update/state.json`, el fichero de lock y su `flock`, los temporales de descarga/extracción, cada fichero administrado, los obsoletos, assets y su manifiesto, caché Blade, OPcache, base de datos, manifiesto de archivos administrados y mensajes de sesión. Los temporales de cada copia, del estado y del manifiesto se sustituyen mediante `rename`; el árbol completo no es atómico.

La prueba usa hashes SHA-256 y contenidos deterministas en directorios temporales, un SQLite real como testigo de migraciones y un proceso PHP aislado para la respuesta de `UpdateService`. En todos los casos se comprueban configuración, `Content`, `storage`, uploads, `.htaccess` y un testigo exterior. Las regresiones específicas mantienen además el rechazo de symlinks y nodos inesperados.

## Matriz verificable

`FS` resume ficheros administrados, obsoletos, assets y Blade. `DB` indica el testigo de migración. Todos los fallos controlados liberan `flock`; todos los estados con `mutations_started: true` bloquean el siguiente intento salvo el caso en que ya quedó `completed`.

Salvo indicación contraria, “anterior” significa hash y contenido idénticos al fixture previo; “nuevo” significa hash idéntico al release. En #1–#16 no se genera mensaje de éxito: la excepción llega a `UpdateService` como “Actualización cancelada”; #17 devuelve `false` y mensaje de error pese a que `completed` ya es autoritativo. El manifiesto permanece anterior hasta #14, puede ser anterior o nuevo en #15 y es nuevo en #16–#17. Las migraciones no se invocan hasta #10 inclusive.

| # | Fallo solicitado | Estado persistido | Evidencia después del fallo | Siguiente intento | Clasificación | Recuperación actual |
|---:|---|---|---|---|---|---|
| 1 | Descarga | `failed/preparing`, mutaciones `false` | FS, assets, Blade, manifiesto y DB anteriores | permitido | **SAFE_RETRY** | repetir descarga |
| 2 | ZIP o release inválido | `failed/preparing`, `false` | igual que #1; el release real rechaza hash/estructura | permitido | **SAFE_RETRY** | obtener artefacto válido |
| 3 | Plan o preflight | `failed/preparing`, `false` | conflicto conservado, sin copias ni migraciones | permitido tras resolver conflicto | **SAFE_RETRY** | resolver causa y repetir |
| 4 | Antes de primera copia | `failed/installing_files`, `true` | ninguna copia alcanzó destino; manifiesto anterior | bloqueado | **SAFE_RETRY** + **BLOCKED_MANUAL_RECOVERY** | verificar testigos y desbloquear administrativamente; B3 es conservador |
| 5 | Copia posterior | `failed/installing_files`, `true` | primera copia nueva, copia fallida anterior, obsoletos y assets anteriores | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM** | restauración manual desde backup |
| 6 | Eliminación de obsoleto posterior | `failed/publishing_cleanup`, `true` | copias nuevas, primer obsoleto ausente, segundo conservado | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM** | restauración manual |
| 7 | Publicación de assets | `failed/publishing_cleanup`, `true` | assets mezclados anterior/nuevo; manifiesto principal anterior | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM** | restaurar árbol y manifiesto de assets |
| 8 | Limpieza Blade | `failed/publishing_cleanup`, `true` | assets nuevos; caché parcialmente eliminada | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM** | restaurar ficheros y regenerar/borrar Blade de forma segura |
| 9 | Verificación del árbol | `failed/publishing_cleanup`, `true` | la retirada de un asset administrado es detectada; DB no iniciada | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM** | restaurar árbol administrado |
| 10 | Reset OPcache | `failed/publishing_cleanup`, `true` | invalidaciones por fichero observadas; reset falla; DB no iniciada | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM** | restaurar FS y reiniciar/resetear runtime; OPcache CLI está deshabilitado en desarrollo |
| 11 | Primera migración tras mutar | `failed/migrations`, `true` | FS nuevo, manifiesto anterior, primer testigo DB persistente | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM**, **DATABASE_RECOVERY_REQUIRED** | backup/restauración manual de FS y DB |
| 12 | Migración posterior | `failed/migrations`, `true` | testigos DB de migración previa y actual; manifiesto anterior | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM**, **DATABASE_RECOVERY_REQUIRED** | restauración coordinada de FS y DB |
| 13 | Validación final tras migrar | `failed/migrations`, `true` | DB modificada y árbol divergente; manifiesto anterior | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM**, **DATABASE_RECOVERY_REQUIRED** | restauración coordinada y diagnóstico del cambio |
| 14 | Promoción antes de `rename` | `failed/promoting_manifest`, `true` | DB aplicada; manifiesto anterior byte a byte | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM**, **DATABASE_RECOVERY_REQUIRED** | restaurar o completar manualmente tras verificar todo |
| 15 | Promoción después de `rename` / interrupción en promoción | `failed/promoting_manifest` o `interrupted/promoting_manifest`, `true` | la misma fase admite manifiesto anterior o nuevo; FS y DB pudieron terminar | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **AMBIGUOUS_COMMIT**, posible **DATABASE_RECOVERY_REQUIRED** | comparar hash del manifiesto, árbol y registro DB antes de decidir |
| 16 | Persistencia de `completed` antes/después de `rename` | `failed/promoting_manifest`, `true` tras excepción controlada | manifiesto nuevo; nunca se devuelve éxito; el catch vuelve a estado fallido | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **AMBIGUOUS_COMMIT** | reconciliar manifiesto/árbol/DB y escribir decisión administrativa |
| 17 | Generación del mensaje de éxito | `completed/completed`, `true` | manifiesto y árbol nuevos; fallo de mensaje produce error y `false` | permitido por estado | **AMBIGUOUS_COMMIT** para el llamador; el estado lo resuelve | leer `state.json`; no repetir ni restaurar si sigue siendo `completed` |

Las interrupciones abruptas se simulan sin matar procesos: se dejan testigos reales y un estado `in_progress` en `installing_files`, `publishing_cleanup`, `migrations` y `promoting_manifest`, se libera el lock como haría el sistema operativo y se inicia un nuevo coordinador. B3 convierte el intento en `interrupted` y bloquea si hubo posibles mutaciones. En promoción se demostraron ambos resultados posibles, manifiesto anterior y nuevo, bajo la misma fase persistida.

## Seguridad e invariantes

- No se observó escritura exterior ni alteración de rutas protegidas en ningún escenario.
- Symlinks, manifiestos/locks inseguros y nodos inesperados siguen fallando cerrados en las regresiones B2/B3.
- El manifiesto principal no se promueve antes de migraciones y validación final.
- Ningún fallo anterior a la persistencia final devuelve éxito ni deja `completed`.
- Una excepción controlada libera el lock. Una interrupción simulada conserva suficiente estado y el siguiente intento la marca `interrupted`.
- `mutations_started` es deliberadamente conservador: cambia antes de la primera copia. Por ello #4 necesita inspección aunque la prueba demuestre cero cambios.
- La fase persistida agrupa varias operaciones: `publishing_cleanup` no distingue obsoletos, assets, Blade, verificación u OPcache; `migrations` no identifica la última migración confirmada.
- No apareció falso éxito, pérdida del manifiesto anterior ni escape de ruta. El fallo del mensaje final sí puede devolver falso fallo con estado `completed`; la recuperación debe confiar en el estado, no solo en el booleano o la UI.

## Requisitos derivados

### B5.2: recuperación de filesystem

Debe conservar un snapshot o journal verificable de cada fichero administrado sustituido/eliminado, assets, manifiesto de assets, caché y manifiesto principal. La restauración ha de ser confinada por `SafePath`, idempotente, compatible con manifiesto ausente y capaz de resolver #5–#10 y la parte FS de #11–#16. No existe hoy este rollback.

### B5.3: recuperación de base de datos

Debe registrar por migración el inicio y la confirmación, definir qué migraciones admiten transacción y exigir backup/restauración para las demás. La decisión debe coordinar versión de DB, árbol y manifiesto; restaurar solo ficheros después de #11–#15 puede dejar código viejo contra esquema nuevo. No existe hoy rollback de DB.

### B5.4: reconciliación y operación administrativa

Debe resolver estados ambiguos comparando estado, hash del manifiesto, árbol y migraciones; ofrecer una acción explícita de completar o restaurar, nunca borrar a ciegas el marcador. También debe hacer que la respuesta de `applyUpdate()` tome `completed` como resultado autoritativo si falla únicamente el mensaje posterior. Conviene desglosar `publishing_cleanup` y registrar la última migración confirmada.

## Decisión de release

1. **Actualización automática habilitable:** no con el diseño actual. Antes de habilitarla son imprescindibles B5.2, una estrategia B5.3 probada con backup/restauración y la reconciliación mínima de B5.4 para #15–#17.
2. **Actualización manual controlada con backup:** sí, en mantenimiento, con backup verificado de filesystem y DB, paquete anterior retenido, inspección de `state.json` y procedimiento ensayado de restauración. Es la única recomendación para la próxima release si B5.2/B5.3 no se completan.
3. **Aplazable hasta 1.0:** cambio atómico de árbol por releases/symlink, rollback automático completo, optimización de retención y UX avanzada. La firma/autenticidad criptográfica sigue siendo necesaria en el modelo final, pero no fue implementada ni evaluada como mecanismo de recuperación en B5.1.

B5.1 caracteriza todas las fases persistentes solicitadas; no implementa rollback, restauración, transacciones, atomicidad de árbol ni firma de artefactos.

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

`FS` resume ficheros administrados, obsoletos, assets y Blade. `DB` indica el testigo de migración. Todos los fallos controlados liberan `flock`. `filesystem_rolled_back` permite un intento completo; `rollback_failed` y las fases desde `migrations` bloquean.

Salvo indicación contraria, “anterior” significa hash y contenido idénticos al fixture previo; “nuevo” significa hash idéntico al release. En #1–#16 no se genera mensaje de éxito: la excepción llega a `UpdateService` como “Actualización cancelada”; #17 devuelve `false` y mensaje de error pese a que `completed` ya es autoritativo. El manifiesto permanece anterior hasta #14, puede ser anterior o nuevo en #15 y es nuevo en #16–#17. Las migraciones no se invocan hasta #10 inclusive.

| # | Fallo solicitado | Estado persistido | Evidencia después del fallo | Siguiente intento | Clasificación | Recuperación actual |
|---:|---|---|---|---|---|---|
| 1 | Descarga | `failed/preparing`, mutaciones `false` | FS, assets, Blade, manifiesto y DB anteriores | permitido | **SAFE_RETRY** | repetir descarga |
| 2 | ZIP o release inválido | `failed/preparing`, `false` | igual que #1; el release real rechaza hash/estructura | permitido | **SAFE_RETRY** | obtener artefacto válido |
| 3 | Plan o preflight | `failed/preparing`, `false` | conflicto conservado, sin copias ni migraciones | permitido tras resolver conflicto | **SAFE_RETRY** | resolver causa y repetir |
| 4 | Antes de primera copia | `filesystem_rolled_back/installing_files`, `true` | árbol anterior; journal íntegro | permitido | **SAFE_RETRY**, **RECOVERED_FILESYSTEM** | no había bytes de aplicación que restaurar |
| 5 | Copia posterior | `filesystem_rolled_back/installing_files`, `true` | copias sustituidas y nuevas restauradas byte a byte | permitido | **RECOVERED_FILESYSTEM** | reconciliación inversa por hash |
| 6 | Eliminación de obsoleto posterior | `filesystem_rolled_back/publishing_cleanup`, `true` | copias y obsoletos anteriores restaurados | permitido | **RECOVERED_FILESYSTEM** | backup verificado y restauración inversa |
| 7 | Publicación de assets | `filesystem_rolled_back/publishing_cleanup`, `true` | assets y manifiesto de assets anteriores | permitido | **RECOVERED_FILESYSTEM** | rollback de operaciones de assets |
| 8 | Limpieza Blade | `filesystem_rolled_back/publishing_cleanup`, `true` | ficheros/assets anteriores; Blade vacío y regenerable | permitido | **RECOVERED_FILESYSTEM** | no restaura compilados derivados |
| 9 | Verificación del árbol | `filesystem_rolled_back/publishing_cleanup`, `true` | árbol administrado anterior | permitido | **RECOVERED_FILESYSTEM** | reconciliación completa |
| 10 | Reset OPcache | `filesystem_rolled_back/publishing_cleanup`, `true` | árbol anterior; nueva invalidación best effort | permitido | **RECOVERED_FILESYSTEM** | no se afirma limpieza de PHP-FPM remoto |
| 11 | Primera migración tras mutar | `failed/migrations`, `true` | FS nuevo, manifiesto anterior, primer testigo DB persistente | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM**, **DATABASE_RECOVERY_REQUIRED** | backup/restauración manual de FS y DB |
| 12 | Migración posterior | `failed/migrations`, `true` | testigos DB de migración previa y actual; manifiesto anterior | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM**, **DATABASE_RECOVERY_REQUIRED** | restauración coordinada de FS y DB |
| 13 | Validación final tras migrar | `failed/migrations`, `true` | DB modificada y árbol divergente; manifiesto anterior | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM**, **DATABASE_RECOVERY_REQUIRED** | restauración coordinada y diagnóstico del cambio |
| 14 | Promoción antes de `rename` | `failed/promoting_manifest`, `true` | DB aplicada; manifiesto anterior byte a byte | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **RECOVERABLE_FILESYSTEM**, **DATABASE_RECOVERY_REQUIRED** | restaurar o completar manualmente tras verificar todo |
| 15 | Promoción después de `rename` / interrupción en promoción | `failed/promoting_manifest` o `interrupted/promoting_manifest`, `true` | la misma fase admite manifiesto anterior o nuevo; FS y DB pudieron terminar | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **AMBIGUOUS_COMMIT**, posible **DATABASE_RECOVERY_REQUIRED** | comparar hash del manifiesto, árbol y registro DB antes de decidir |
| 16 | Persistencia de `completed` antes/después de `rename` | `failed/promoting_manifest`, `true` tras excepción controlada | manifiesto nuevo; nunca se devuelve éxito; el catch vuelve a estado fallido | bloqueado | **BLOCKED_MANUAL_RECOVERY**, **AMBIGUOUS_COMMIT** | reconciliar manifiesto/árbol/DB y escribir decisión administrativa |
| 17 | Generación del mensaje de éxito | `completed/completed`, `true` | manifiesto y árbol nuevos; fallo de mensaje produce error y `false` | permitido por estado | **AMBIGUOUS_COMMIT** para el llamador; el estado lo resuelve | leer `state.json`; no repetir ni restaurar si sigue siendo `completed` |

Las interrupciones abruptas anteriores a migraciones se reconcilian con `journal.json` y el árbol real: una operación `applying` se resuelve por existencia, tipo y hash, se completa el rollback y queda `filesystem_rolled_back`. Si falta o está corrupto el journal/snapshot, queda `rollback_failed` y se bloquea. En `migrations` y `promoting_manifest` se conserva el bloqueo anterior sin rollback automático.

## Seguridad e invariantes

- No se observó escritura exterior ni alteración de rutas protegidas en ningún escenario.
- Symlinks, manifiestos/locks inseguros y nodos inesperados siguen fallando cerrados en las regresiones B2/B3.
- El manifiesto principal no se promueve antes de migraciones y validación final.
- Ningún fallo anterior a la persistencia final devuelve éxito ni deja `completed`.
- Una excepción controlada anterior a migraciones libera el lock después de restaurar o persistir `rollback_failed`; una interrupción con journal íntegro completa la misma recuperación.
- `mutations_started` sigue siendo conservador; el journal íntegro demuestra el resultado del caso 4 y evita una inspección manual innecesaria.
- La fase persiste la frontera general y el journal distingue cada mutación de filesystem; `migrations` aún no identifica la última migración confirmada.
- No apareció falso éxito, pérdida del manifiesto anterior ni escape de ruta. El fallo del mensaje final sí puede devolver falso fallo con estado `completed`; la recuperación debe confiar en el estado, no solo en el booleano o la UI.

## Requisitos derivados

### B5.2: recuperación de filesystem

Implementada para `installing_files` y `publishing_cleanup`: snapshot por intento, JSON estricto versionado, estados duraderos por operación, reconciliación por SHA-256, rollback inverso, protección contra cambios concurrentes y bloqueo `rollback_failed`. Blade y OPcache se invalidan como derivados; el manifiesto principal sigue sin promoverse. La frontera `migrations` queda excluida deliberadamente.

### B5.3: recuperación de base de datos

Implementada con preflight de pendientes sobre el artefacto, contrato portable de proveedor externo
y `database.json` por intento. Registra `pending`, `started`, `applied`, `failed` y `ambiguous`, además
de la última migración confirmada. Sin pendientes no exige snapshot. Con pendientes, el proveedor
predeterminado bloquea hasta que una integración demuestre evidencia externa `validated` o
`restore_tested`. El DDL MySQL/MariaDB actual no se declara transaccional. No existe rollback

### B5.4: reconciliación y operación administrativa

Debe resolver estados ambiguos comparando estado, hash del manifiesto, árbol y migraciones; ofrecer una acción explícita de completar o restaurar, nunca borrar a ciegas el marcador. También debe hacer que la respuesta de `applyUpdate()` tome `completed` como resultado autoritativo si falla únicamente el mensaje posterior. Conviene desglosar `publishing_cleanup` y registrar la última migración confirmada.

## Decisión de release

1. **Actualización automática habilitable:** solo para releases sin migraciones. Con migraciones,
   queda bloqueada por defecto hasta integrar un proveedor B5.3 que verifique recuperación externa;
   B5.4 sigue siendo necesaria para reconciliar los casos ambiguos.
2. **Actualización manual controlada con backup:** sí, en mantenimiento, con backup verificado de filesystem y DB, paquete anterior retenido, inspección de `state.json` y procedimiento ensayado de restauración. Sigue siendo la recomendación hasta completar B5.3/B5.4.
3. **Aplazable hasta 1.0:** cambio atómico de árbol por releases/symlink, rollback automático completo, optimización de retención y UX avanzada. La firma/autenticidad criptográfica sigue siendo necesaria en el modelo final, pero no fue implementada ni evaluada como mecanismo de recuperación en B5.1.

B5.1 caracteriza todas las fases; B5.2 recupera filesystem antes del comienzo DB y B5.3 coordina
evidencia externa y checkpoints. No se implementan rollback DB, reconciliación B5.4, atomicidad de árbol ni firma.

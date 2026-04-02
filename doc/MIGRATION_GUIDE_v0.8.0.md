# Guía de Migración: Alxarafe v0.5.x a v0.8.0 (Arquitectura Hexagonal)

La actualización a Alxarafe v0.8.0 introduce cambios estructurales profundos debido a la adopción de la Arquitectura Hexagonal y la reorganización completa de los namespaces del core.

Esta guía documenta los **Breaking Changes** identificados durante la migración de `Chascarrillo` y proporciona la hoja de ruta para migrar el resto de repositorios.

---

## 🚀 1. Cambios Estructurales de Namespaces

El framework ha reorganizado sus recursos para separar Dominio, Aplicación e Infraestructura. Debes ejecutar un **reemplazo masivo** en tu código base:

| Clase/Namespace Anterior (v0.5) | Nuevo Namespace (v0.8.0) |
| :--- | :--- |
| `Alxarafe\Base\Controller\*` | `Alxarafe\Infrastructure\Http\Controller\*` |
| `Alxarafe\Base\Model\*` | `Alxarafe\Infrastructure\Persistence\Model\*` |
| `Alxarafe\Base\Config` | `Alxarafe\Infrastructure\Persistence\Config` |
| `Alxarafe\Base\Database` | `Alxarafe\Infrastructure\Persistence\Database` |
| `Alxarafe\Component\*` | `Alxarafe\Infrastructure\Component\*` |
| `Alxarafe\Lib\*` | `Alxarafe\Infrastructure\Lib\*` |
| `Alxarafe\Service\*` | `Alxarafe\Infrastructure\Service\*` |

> **⚠️ Advertencia**: Revisa cuidadosamente las clases importadas en los archivos `.blade.php` (caché de vistas) y en los scripts en crudo (`/scripts/*.php`), ya que los IDEs a menudo no actualizan las sentencias `use` o llamadas absolutas dentro de los strings HTML/Blade.

---

## 💥 2. Colisión en Controladores Base (`CoreModules` vs `Modules`)

En versiones anteriores, los módulos nativos del framework residían en el namespace `CoreModules\`. En la v0.8.0, **el framework ha renombrado sus propios módulos a `Modules\`**.

**Problema:**
Si tu aplicación extendía un controlador del core (por ejemplo, `CoreModules\Admin\Controller\ConfigController`) usando el mismo namespace (`Modules\Admin\Controller\ConfigController`), ahora se producirá una **colisión fatal de clases en PHP**, porque tanto la clase nativa como tu sobreescritura tendrán exactamente el mismo FQCN (Fully Qualified Class Name) bajo PSR-4.

**Solución Documentada:**
1. Renombra tu propia clase para evitar la colisión. Ejemplo:
   - *Antes:* `class ConfigController extends BaseConfigController`
   - *Después:* `class MiAppConfigController extends BaseConfigController`
2. Modifica el namespace hacia tu propio módulo (`Modules\MiApp\Controller\...`) y actualiza el metadato del menú (`#[Menu(...)]`) para que el `WebDispatcher` inyecte tu propia configuración en el backoffice sin colisionar con la nativa.

---

## 🛠️ 3. Transición a Puertos y Adaptadores (Caso Práctico)

La migración exige que la lógica de negocio se separe del ORM (Eloquent).

### A. Repositorio vs Modelo Eloquent
No inyectes los modelos de Eloquent directamente en los Controladores para ejecutar CRUDs complejos.
1. Crea tu interfaz de Dominio: `Domain/Port/Driven/EntityRepositoryInterface.php`.
2. Crea el adaptador puro de Persistencia: `Infrastructure/Adapter/Persistence/PdoEntityRepository.php`.
3. Inyéctalos vía contenedor.

### B. Command Buses (Capa de Aplicación)
Sustituye la manipulación directa de requests en los Controladores:
1. Crea un Comando simple (DTO): `Application/Bus/Command/CreateEntityCommand.php`.
2. Crea su Handler aislado: `Application/Bus/Handler/CreateEntityHandler.php`.
3. El Controller solo empaqueta la petición de PHP y dispara el comando.

---

## 🐛 4. Opcache en Entornos Dockerizados

Tras realizar un reemplazo masivo de *namespaces* en los archivos PHP:
- Es crucial **purgar el compilador OPcache de PHP**.
- Si OPcache está activo (sin validación de timestamps), Docker seguirá ejecutando en memoria los archivos con los namespaces antiguos (ej. `Alxarafe\Base\Controller`), provocando misteriosos errores de *Class Not Found* a pesar de que los archivos en disco estén correctos.
- **Acción:** Ejecuta `docker restart [nombre_contenedor_php]` o crea un script temporal que dispare `opcache_reset();`.

---

## 🎨 5. Parsedown y Renderizado Markdown

El servicio `MarkdownService` introdujo cambios en el renderizado de los componentes base.
- Bloques de tipo `::: feature-card` o `:::: feature-grid` seguirán funcionando, pero el manejo nativo del DOM de los elementos puede requerir ajustes menores de formato Markdown puro (por ejemplo, aplicar dobles saltos de línea para forzar bloqueos en enlaces que deban tomar estilos de clase botón como `.feature-content a`).

---

## ✅ Resumen de Siguientes Pasos (Para cualquier Repo hijo)

1. Actualizar `composer.json` → `"alxarafe/alxarafe": "^0.8.0"`.
2. Lanzar script regex de migraciones de `Alxarafe\Base` -> `Alxarafe\Infrastructure`.
3. Renombrar cualquier controlador legacy en `Modules/Admin/Controller` que colisione.
4. Purga completa: `composer dump-autoload` y recarga de OPcache.
5. Iniciar la refactorización iterativa (Controlador -> Bus -> Repositorio).

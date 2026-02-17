# Gestión de Páginas Fijas

Las páginas fijas son secciones estáticas de tu sitio, como "Quiénes somos", "Servicios" o "Manifiesto". En Chascarrillo, estas páginas se gestionan desde el módulo de administración o mediante archivos Markdown.

## Cómo crear una Página Fija

### Desde el Panel de Administración
1. Accede al **Dashboard**.
2. Ve a **Gestión Chascarrillos** > **Posts**.
3. Haz clic en **Nuevo**.
4. Rellena los campos:
   - **Título**: El nombre que verán los usuarios.
   - **Slug**: La dirección URL (ej: `quienes-somos`).
   - **Tipo**: Asegúrate de que el registro tenga el tipo `page`.
   - **Contenido**: Escribe tu contenido usando Markdown.
5. Haz clic en **Guardar**.

### Opciones de Visualización
Cada página tiene opciones que afectan a su diseño:
- **Imagen Destacada**: Una URL a una imagen que aparecerá antes del contenido.
- **Meta Descripción**: Texto para buscadores (SEO) que también se usa en la sección de cabecera (Hero).
- **Publicado**: Solo las páginas marcadas como publicadas serán visibles.

## Estructura Visual
Todas las páginas en Chascarrillo siguen una estructura de diseño unificada:
1. **Sección Hero**: Un bloque superior con fondo limpio, el título de la página y una descripción corta (extraída de la meta-descripción).
2. **Cuerpo del Contenido**: El texto renderizado desde Markdown, centrado y optimizado para la lectura.
3. **Imágenes**: Se adaptan automáticamente al ancho del contenedor y mantienen un estilo profesional.

## Definición en el Menú
Para que una página aparezca automáticamente en el menú principal:
1. Edita la página.
2. Marca la opción **En Menú** (si está disponible en tu versión) o asegúrate de que el campo `in_menu` en la base de datos sea `1`.
3. El campo **Orden Menú** determina la posición (de menor a mayor).

*Nota: Algunas páginas pueden estar fijadas manualmente en el código (`project_menu.blade.php`) para un control total del diseño.*

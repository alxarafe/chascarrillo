# Configuración del Menú de Navegación

El menú de Chascarrillo es híbrido: combina enlaces estratégicos definidos en el código con páginas dinámicas gestionadas desde la base de datos.

## Estructura del Menú
El menú se define en el archivo `templates/partial/project_menu.blade.php`.

### 1. Enlaces Fijos
Existen enlaces que suelen ser constantes y se definen directamente en el archivo para mayor velocidad y precisión:
- **Inicio**: Apunta a `/`.
- **Laboratorio**: Apunta a `/blog`.
- **Secciones Estratégicas**: Como "Framework" o "Manifiesto IA", que suelen tener un peso visual importante.

### 2. Páginas Dinámicas
Cualquier registro de tipo `page` marcado con `in_menu = 1` aparecerá automáticamente en el menú principal. Chascarrillo recorre estos registros y los inserta en el orden especificado.

## Cómo añadir opciones al Menú

### Añadir una Página Dinámica
1. Ve a la gestión de **Posts**.
2. Crea o edita una página.
3. Asegúrate de marcar **En Menú**.
4. Define el **Orden** (ej: 10, 20, 30).
5. Guarda los cambios. La página aparecerá inmediatamente.

### Añadir Enlaces Manuales (Avanzado)
Si necesitas añadir un enlace externo o una sección especial:
1. Abre `templates/partial/project_menu.blade.php`.
2. Busca la lista `navbar-nav`.
3. Añade un bloque HTML como este:
   ```html
   <li class="nav-item">
       <a class="nav-link" href="https://ejemplo.com">Mi Enlace</a>
   </li>
   ```

## Estado Activo
Chascarrillo detecta automáticamente en qué página te encuentras para resaltar el enlace correspondiente en el menú. Esto se logra mediante el parámetro `route_name` o el `slug` de la página actual.

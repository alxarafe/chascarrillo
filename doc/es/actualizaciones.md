# Actualizaciones del Sistema

Chascarrillo incluye un sistema de auto-actualización que permite mantener tu sitio al día con las últimas mejoras de seguridad y características, directamente desde el panel de administración.

## Cómo actualizar

1. **Acceso**: Inicia sesión como administrador y ve a la sección **Actualización** en el menú lateral del Dashboard.
2. **Comprobación**: El sistema consultará automáticamente si existe una versión más reciente en el repositorio oficial.
3. **Ejecución**: Si hay una actualización disponible, aparecerá un botón para descargar e instalar. 
   - Haz clic en **Descargar e Instalar Actualización**.
   - El sistema descargará el paquete, reemplazará los archivos del núcleo y ejecutará las migraciones de base de datos necesarias de forma automática.

## Seguridad y Respaldos

### Archivos Protegidos
El sistema de actualización es inteligente y **nunca sobrescribirá** tus archivos de configuración personal:
- `config.json`
- `.env`
- `.htaccess` (en el directorio raíz)

### Recomendaciones
Aunque el proceso es seguro, siempre recomendamos realizar una copia de seguridad de tus archivos y base de datos antes de realizar una actualización mayor del sistema.

## Resolución de Problemas
Si la actualización falla, asegúrate de que el usuario del servidor web (ej: `www-data`) tiene permisos de escritura en la carpeta raíz del proyecto. Sin estos permisos, el sistema no podrá reemplazar los archivos.

# Guía de Despliegue y Sustitución de WordPress

Esta guía detalla los pasos para instalar **Chascarrillo CMS** en tu servidor (ej: `alxarafe.es`) y sustituir una instalación existente de WordPress.

## Requisitos Previos
- Servidor con **PHP 8.5** (recomendado) o PHP 8.2+.
- Base de datos MySQL / MariaDB.
- Servidor web (Apache con `mod_rewrite` activo o Nginx).
- Composer instalado en el servidor (o subir la carpeta `vendor` ya procesada).

## Pasos para la Instalación

### 1. Preparación de Archivos
1. Sube el contenido del repositorio a la carpeta de tu dominio.
2. Asegúrate de que el documento raíz (DocumentRoot) de tu dominio apunte a la carpeta `/public` de Chascarrillo, no a la raíz del proyecto.

### 2. Configuración del Entorno
1. Copia el archivo `config.json` y ajusta los parámetros de base de datos:
   ```json
   "db": {
       "host": "tu_host",
       "user": "tu_usuario",
       "pass": "tu_password",
       "name": "nombre_bd"
   }
   ```
2. La URL base ahora se detecta automáticamente, pero puedes forzarla en `config.json` dentro de `main.url`.

### 3. Instalación de Dependencias y Assets
Si tienes acceso por terminal (SSH):
```bash
composer install --no-dev --optimize-autoloader
```
Si no tienes acceso, asegúrate de subir la carpeta `vendor` completa. Los assets (CSS, JS) se auto-publicarán en el primer acceso a la web si faltan.

### 4. Inicialización de la Base de Datos
Ejecuta los siguientes scripts desde la raíz (vía SSH):
```bash
php run_migrations.php
php run_seeders.php
```
Esto creará las tablas necesarias y cargará el contenido de prueba inicial.

### 5. Creación del Usuario Administrador
Para poder acceder al panel de control, crea tu primer usuario:
```bash
php create_admin_user.php
```
*Por defecto crea el usuario `user` con contraseña `password`. Recuerda cambiarlo inmediatamente desde el panel.*

## Sustitución de WordPress (Estrategia)

### Paso A: Limpieza
WordPress suele tener sus propios archivos `.htaccess`, `index.php` y carpetas `wp-admin`, `wp-content`. 
1. **Mueve** los archivos de WordPress a una carpeta temporal (ej: `wp_old/`) o elimínalos si tienes copia de seguridad.
2. Asegúrate de que el `.htaccess` de Chascarrillo (ubicado en `public/`) es el que está activo.

### Paso B: Redirecciones SEO (Opcional)
Si tenías enlaces posicionados en WordPress con una estructura distinta, puedes añadir reglas de redirección en el `.htaccess` superior para no perder tráfico. Chascarrillo usa `/blog/{slug}`, mientras que WordPress puede usar otras estructuras.

### Paso C: Importación de Contenido
Si quieres traer tus posts de WordPress:
1. Exporta tus posts de WordPress a archivos Markdown.
2. Colócalos en `Modules/Chascarrillo/posts/`.
3. Desde el panel de Chascarrillo, pulsa en **Sincronizar Markdown**.

## Primeros Pasos en Producción
1. Accede a `tu-dominio.com/index.php?module=Admin&controller=Auth`.
2. Identifícate con el usuario admin creado.
3. Ve a **Perfil** y cambia tu contraseña.
4. Empieza a crear tus nuevos "Chascarrillos" desde la gestión de Posts.

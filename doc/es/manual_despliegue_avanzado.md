# Manual de Despliegue Avanzado: Chascarrillo CMS

Este manual detalla el proceso completo de arquitectura, empaquetado y despliegue del sistema **Chascarrillo**, incluyendo la gestión del **Framework Alxarafe** y la configuración en servidores de producción (como Hostinger).

---

## 1. Arquitectura de Versiones
Chascarrillo no es una aplicación aislada; depende estrechamente del núcleo (Framework Alxarafe). Para un despliegue exitoso, es vital mantener la coherencia entre ambos.

### El Framework Alxarafe
- **Repositorio**: `alxarafe/alxarafe`
- **Gestión de Versiones**: Se realiza mediante Git Tags.
- **Punto Crítico**: El archivo `composer.json` del framework **no debe incluir** el campo `"version"`. Esto permite que Packagist confíe exclusivamente en las etiquetas de Git y evita errores de "version mismatch".

### La Aplicación Chascarrillo
- **Repositorio**: `alxarafe/chascarrillo`
- **Dependencia**: En su `composer.json`, debe apuntar a una versión específica del framework (ej: `"alxarafe/alxarafe": "v0.1.9"`).

---

## 2. Flujo de Trabajo del Desarrollador (Release)

Cuando se completa una funcionalidad, el proceso de "subida de versión" sigue estos pasos:

### Paso 1: Actualizar el Framework (si hay cambios en el Core)
1. Realizar los cambios en la carpeta del framework.
2. Hacer commit y subir a la rama `main`.
3. Crear un nuevo tag incrementando la versión:
   ```bash
   git tag v0.1.X
   git push origin v0.1.X
   ```

### Paso 2: Actualizar la Aplicación Chascarrillo
1. Modificar el `composer.json` de la aplicación para que requiera la nueva versión del framework.
2. Hacer commit y subir a `main`.
3. Crear un nuevo tag de aplicación:
   ```bash
   git tag v0.6.X
   git push origin v0.6.X
   ```

---

## 3. Generación Automática del Paquete (CI/CD)

Chascarrillo utiliza **GitHub Actions** para automatizar la creación del paquete de despliegue.

### Flujo Automático
Cada vez que se sube un tag que empieza por `v*` al repositorio de Chascarrillo:
1. El workflow `.github/workflows/deploy-package.yml` se activa.
2. Limpia las dependencias locales y resuelve el framework desde el repositorio oficial.
3. Instala las dependencias de producción (`--no-dev`).
4. Genera un archivo ZIP llamado `chascarrillo-deploy-vX.X.X.zip`.
5. Publica automáticamente una **Release** en GitHub y adjunta el ZIP como un Asset.

### Generación Manual (Local)
Si necesitas generar el bulto sin pasar por GitHub, usa el script incluido:
```bash
./bin/build_release.sh v0.6.X
```
Esto generará el ZIP en la raíz de tu proyecto local, excluyendo archivos de desarrollo (`.git`, `tests`, `cache`, etc.).

---

## 4. Instalación en el Servidor (Producción)

### Método A: Despliegue mediante ZIP (Recomendado)
Es el método más seguro ya que incluye la carpeta `vendor` verificada.
1. Descarga el último `chascarrillo-deploy-vX.X.X.zip` desde las Releases de GitHub.
2. Súbelo a la carpeta raíz de tu hosting (fuera de `public_html` si es posible, o directamente dentro según configuración).
3. Descomprime el archivo.
4. **Importante (Hostinger/Apache)**: Asegúrate de que la carpeta pública de la web se llame `public_html`. El sistema está configurado para detectar tanto `public` como `public_html`.

### Método B: Preparación para FTP
Si vas a subir los archivos uno a uno por FTP, ejecuta primero:
```bash
./bin/prepare_ftp.sh
```
Este script limpia la carpeta `vendor` de manuales, tests y archivos innecesarios para ahorrar espacio y tiempo de subida.

---

## 5. Configuración y Activación

Una vez los archivos están en el servidor:

### 1. Base de Datos
Crea el archivo `config.json` en la raíz (basado en `config.json.example`) con tus credenciales:
```json
{
    "db": {
        "host": "localhost",
        "user": "u123456789_user",
        "pass": "tu_password_segura",
        "name": "u123456789_chascarrillo"
    }
}
```

### 2. Migraciones
Abre la terminal SSH de tu hosting o usa la herramienta de ejecución de PHP y lanza:
```bash
php run_migrations.php
```
Esto creará las tablas de posts, tags, categorías y menús.

### 3. Sincronización de Contenido
Coloca tus archivos Markdown (`.md`) en:
- `Content/pages/` para páginas fijas (Inicio, Contacto, etc.).
- `Content/posts/` para las entradas del blog.

Entra al panel de administración y pulsa en **Sincronizar** dentro de la sección de Posts para importar el contenido a la base de datos.

---

## 6. Resolución de Problemas Comunes (FAQ)

### Mensaje: "Some tags were ignored because of a version mismatch"
**Causa**: Existe un campo `"version"` dentro del `composer.json` del framework que no coincide con el tag de Git.
**Solución**: Eliminar el campo `"version"` del `alxarafe/composer.json`. Packagist usará el tag de Git automáticamente.

### Error en GitHub Actions (Security Advisory)
**Causa**: Composer bloquea la instalación porque una dependencia (ej: `firebase/php-jwt`) tiene un aviso de seguridad activo.
**Solución**: Actualizar la dependencia a una versión segura en el framework, subir tag y actualizar el requerimiento en la aplicación.

### Las imágenes no se ven
**Causa**: Las imágenes deben estar en `public_html/uploads/`.
**Solución**: Verifica que la ruta en el Markdown sea absoluta respecto a la web (ej: `/uploads/posts/foto.jpg`) y que los permisos de carpeta sean de escritura.

---

*Manual generado el 18 de febrero de 2026 por Antigravity para Alxarafe.*

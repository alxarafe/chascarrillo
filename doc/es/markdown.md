# Sincronización mediante Markdown

Una de las características más potentes de Chascarrillo es la capacidad de gestionar tu contenido como si fuera código. Puedes escribir tus posts y páginas localmente y sincronizarlos con la base de datos de un solo clic.

## Configuración de Archivos
Los archivos para sincronizar deben estar ubicados en:
`Modules/Chascarrillo/posts/`

### Formato del archivo
Los archivos deben usar la extensión `.md` y pueden incluir metadatos en la parte superior (Frontmatter):

```markdown
---
title: Mi gran descubrimiento
slug: mi-gran-descubrimiento
type: post
is_published: true
published_at: 2026-02-17 08:00:00
meta_description: Una reflexión sobre el futuro de la web.
---
# Contenido del post
Aquí va todo el texto en Markdown...
```

## Cómo Sincronizar
1. Accede al Panel de Administración.
2. Ve a **Gestión Chascarrillos** > **Posts**.
3. Haz clic en el botón superior **Sincronizar Markdown**.
4. El sistema analizará la carpeta, creará los registros nuevos y actualizará los existentes basándose en el `slug`.

## Ventajas de este método
- **Control de Versiones**: Mantén tu blog en Git junto con tu código.
- **Edición Local**: Usa tu editor de texto favorito (VSCode, Obsidian, etc.).
- **Backups Naturales**: Tu contenido no solo vive en la base de datos, sino también en archivos físicos.

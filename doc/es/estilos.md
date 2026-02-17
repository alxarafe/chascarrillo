# Formato de Contenido y Estilos

Chascarrillo utiliza una estética minimalista y profesional. Para sacar el máximo provecho al diseño, puedes usar las siguientes herramientas:

## Markdown
Todo el contenido de páginas y posts se escribe en Markdown. Aquí tienes los elementos más comunes:

### Títulos
```markdown
# Título Principal (H1)
## Título de Sección (H2)
### Subtítulo (H3)
```

### Formato de Texto
- **Negrita**: `**texto**`
- *Cursiva*: `*texto*`
- [Enlaces](https://alxarafe.com)

### Bloques de Contenido Especiales
Gracias a nuestra integración, puedes usar clases de utilidad para crear bloques visuales:

#### Notas e Información (Callouts)
Puedes añadir bloques de colores para resaltar información:
```html
<div class="callout callout-info">
    <div class="callout-title"><i class="fas fa-info-circle"></i> Información</div>
    Este es un bloque de información azul para el usuario.
</div>

<div class="callout callout-warn">
    <div class="callout-title"><i class="fas fa-exclamation-triangle"></i> Advertencia</div>
    Ten cuidado con esta configuración.
</div>
```

## Imágenes y Multimedia
- Para insertar una imagen normal: `![Texto alternativo](/ruta/a/imagen.jpg)`
- Las imágenes en Chascarrillo se ajustan automáticamente al 100% del ancho del texto con bordes redondeados y una sombra suave.

## Secciones Hero
El título y la descripción corta que aparecen en la parte superior de cada página se generan automáticamente:
- **Título**: Se toma del campo `title`.
- **Descripción**: Se toma del campo `meta_description`. Si está vacío, no se mostrará subtítulo.

## El botón "Action"
En algunas plantillas, como la de Inicio, puedes destacar botones llamativos usando la clase `.btn-alx`:
```html
<a href="/mi-ruta" class="btn-alx">¡Empezar ahora!</a>
```

# Gestión del Blog (Laboratorio)

El "Laboratorio" es el corazón dinámico de Chascarrillo. Aquí es donde publicas artículos, noticias y reflexiones técnicas.

## Cómo crear un Post
El proceso es idéntico al de las páginas, pero con algunas diferencias clave:
1. Crea un nuevo registro en **Posts**.
2. Selecciona el tipo `post`.
3. Define una **Fecha de Publicación**: Solo se mostrarán los posts cuya fecha sea igual o anterior a la actual.
4. Completa el **Título**, **Slug** y **Contenido**.

## El índice del Blog
La página `/blog` recopila automáticamente todos los posts de tipo `post` que estén publicados:
- Muestra el título, la fecha formateada y un extracto automático.
- Si el post tiene una **Imagen Destacada**, se mostrará una miniatura atractiva.
- Los posts se ordenan cronológicamente (los más recientes primero).

## Detalles Técnicos
- **Extractos**: Si no proporcionas una meta-descripción, Chascarrillo generará un extracto automáticamente de los primeros 140 caracteres del contenido.
- **Markdown**: El contenido soporta Markdown completo, permitiendo bloques de código, citas y listas de forma nativa.
- **Slug Único**: Asegúrate de que el slug no coincida con el de una página fija para evitar conflictos de rutas.

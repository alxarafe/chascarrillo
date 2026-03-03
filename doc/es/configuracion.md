# Configuración del Sitio

Chascarrillo permite personalizar el comportamiento del blog y las redes sociales directamente desde el panel de administración, sin necesidad de tocar archivos de código o base de datos.

## Acceso al Panel de Configuración

1. Inicia sesión como administrador.
2. Ve a la sección **Configuración** en el menú lateral.
3. Encontrarás varias pestañas, incluyendo las específicas de Chascarrillo: **Ajustes del Blog** y **Redes Sociales**.

## Ajustes del Blog (Laboratorio)

En esta pestaña puedes definir:
- **Título del Blog**: El nombre que aparecerá en la cabecera de la sección `/blog`.
- **Entradas por página**: Número máximo de posts que se mostrarán en el índice antes de (futura) paginación.
- **Longitud del extracto**: Número de caracteres que se mostrarán en la vista previa de cada post en el índice si no se ha definido una descripción manual.

## Redes Sociales

Aquí puedes configurar los enlaces a tus perfiles públicos:
- **GitHub** y **LinkedIn**: Aparecen resaltados en la cabecera del sitio.
- **Twitter / X**, **Instagram** y **Facebook**: Se añaden automáticamente a la lista de iconos sociales si se proporciona una URL válida.

> [!TIP]
> Si dejas vacío el campo de una red social, el icono correspondiente no se mostrará en la web, manteniendo el diseño limpio y minimalista.

## Configuración Técnica (config.json)

Todos estos ajustes se guardan de forma persistente en el archivo `config.json` de la raíz del proyecto. Este archivo es el "cerebro" de la configuración y debe estar protegido, pero puedes editarlo manualmente si fuera necesario.

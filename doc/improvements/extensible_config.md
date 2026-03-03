# Solicitud de Mejora: Sistema de Configuración Extensible

## 1. Motivación
Actualmente, el sistema de configuración de Alxarafe está centrado en las opciones base del framework (Base de Datos, Seguridad, Main). Sin embargo, las aplicaciones basadas en el framework (como Chascarrillo) a menudo requieren sus propias secciones de configuración (Redes Sociales, Integraciones, Ajustes del Blog, etc.).

El objetivo es permitir que una aplicación pueda extender la página de configuración de Alxarafe añadiendo sus propias pestañas y campos, sin perder las funcionalidades core (como la gestión de migraciones o cambio de idioma).

## 2. Problemas Identificados
*   **Filtrado Estricto**: `Alxarafe\Base\Config` tiene una constante `CONFIG_STRUCTURE` que filtra los campos al guardar. Si una aplicación añade una sección nueva al `config.json`, el framework la ignora o la borra al usar `setConfig`.
*   **Dificultad de Extensión**: El `ConfigController` del core no ofrece hooks sencillos para que un controlador derivado añada nuevas secciones en `getEditFields()` sin sobrescribir todo el método.
*   **Falta de Soporte para Tabs**: Aunque el framework soporta pestañas en el `ResourceController`, el `ConfigController` actual utiliza Paneles simples. Una configuración compleja requiere transicionar a un layout de pestañas (`TabGroup`).

## 3. Propuesta de Implementación: El Método de Generación de Pestañas

### 3.1. Refactorización de `ConfigController` (Core)
El controlador base de Alxarafe debe delegar la creación de la interfaz a un método específico que pueda ser fácilmente interceptado.

```php
// Alxarafe Core: ConfigController.php
public function getViewDescriptor(): array
{
    // ... lógica de botones ...

    return [
        'mode'     => $this->mode,
        'recordId' => 'current',
        'body'     => new TabGroup('config_tabs', $this->getTabs()),
        'buttons'  => $buttons,
    ];
}

/**
 * Genera la lista de pestañas. Este es el método "elegante" a extender.
 */
protected function getTabs(): array
{
    $fields = $this->getEditFields();
    $tabs = [];

    foreach ($fields as $key => $data) {
        $tabs[] = new Tab($key, Trans::_($data['label']), $data['fields']);
    }

    return $tabs;
}
```

### 3.2. Extensión en la Aplicación (Chascarrillo)
La aplicación simplemente "inyecta" su pestaña en el array resultante.

```php
// Chascarrillo: Modules/Admin/Controller/ConfigController.php
protected function getTabs(): array
{
    $tabs = parent::getTabs(); // Pestañas estándar de Alxarafe
    
    // Inyectar pestaña propia de la App
    $tabs[] = new Tab('chascarrillo', 'Ajustes Blog', [
        new Text('blog.title', 'Título del Blog'),
        new Select('social.network', 'Red Social', [...]),
    ]);
    
    return $tabs;
}
```

## 4. Beneficios del Enfoque "getTabs()"
1.  **Elegancia**: No se manipulan arrays complejos de forma manual; se trabaja con objetos de componente (`Tab`).
2.  **Orden**: Permite decidir fácilmente la posición de la pestaña (al principio, al final, o sustituyendo una existente).
3.  **Encapsulación**: Cada pestaña puede tener su propia lógica de visibilidad o validación.
4.  **Consistencia**: Se aprovecha el componente `TabGroup` de Alxarafe que ya maneja el renderizado y la persistencia de la pestaña activa.

# Chascarrillo

![Versión PHP](https://img.shields.io/badge/PHP-8.5.1-blueviolet?style=flat-square)
![CI](https://github.com/alxarafe/chascarrillo/actions/workflows/ci.yml/badge.svg)
![Tests](https://github.com/alxarafe/chascarrillo/actions/workflows/tests.yml/badge.svg)
[![Informe de calidad](https://img.shields.io/badge/calidad-informe-brightgreen?style=flat-square)](https://alxarafe.github.io/chascarrillo/quality/)
![Análisis Estático](https://img.shields.io/badge/an%C3%A1lisis%20est%C3%A1tico-PHPStan%20%2B%20Psalm-blue?style=flat-square)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/alxarafe/chascarrillo/issues?utf8=✓&q=is%3Aopen%20is%3Aissue)

> **El motor de blog "poco serio" construido con el framework "muy serio" Alxarafe.**

En un sector donde todo es *Cloud*, *Stream* o *Flow*, sacar un motor de blog llamado **Chascarrillo** es un acto de rebeldía elegante. Al igual que con el Framework Alxarafe, reivindicamos el origen y la esencia de la comunicación.

### ¿Qué es un "Chascarrillo"?
"Un chascarrillo es una historia corta con ingenio. Esta aplicación es exactamente eso: código ingenioso, ligero y diseñado para contar historias sin complicaciones técnicas."

## Made in Spain 🇪🇸 (Reivindicación del origen)
Mientras la industria se pierde en anglicismos y términos vacíos, Chascarrillo prefiere la tradición de la buena historia. Es una apuesta por lo auténtico, lo directo y lo nuestro.

## Filosofía Técnica
Construido sobre el **Alxarafe Framework**, Chascarrillo aprovecha su robustez para ofrecer una experiencia de blogging sin fricciones.

- **Minimalista**: Foco absoluto en el contenido.
- **Código Ingenioso**: Implementación eficiente y elegante.
- **Potencia Alxarafe**: Utiliza Alxarafe v0.1.1 para servicios centrales y enrutamiento.

## Requisitos
- PHP >= 8.5
- Alxarafe Framework v0.1.1
- Composer

## Instalación

### Desarrollo local con Docker

Chascarrillo incluye un entorno completo de desarrollo con Docker basado en los patrones de Alxarafe. Para usarlo:

1. **Clonar el repositorio**:
   ```bash
   git clone https://github.com/alxarafe/chascarrillo.git
   cd chascarrillo
   ```

2. **Arrancar los contenedores**:
   ```bash
   ./bin/docker_start.sh
   ```

3. **Instalar dependencias**:
   ```bash
   docker exec -it chascarrillo_php composer install
   ```

4. **Ejecutar migraciones y seeders**:
   ```bash
   ./bin/run_migrations.sh
   ```

5. **Acceder a la aplicación**:
   Abre [http://localhost:8082](http://localhost:8082) en tu navegador.
   También puedes acceder a phpMyAdmin en [http://localhost:9082](http://localhost:9082).

### Instalación manual

Si prefieres ejecutarlo sin Docker:
1. Configura tu base de datos en el archivo `.env`.
2. Ejecuta `composer install`.
3. Ejecuta `php run_migrations.php` y `php run_seeders.php`.
4. Lanza el servidor local: `php -S localhost:8000 -t public`.

## Control de Calidad

Utiliza los scripts proporcionados en `bin/` para asegurar la calidad del código:
*   **Tests**: `./bin/run_tests.sh`
*   **Análisis Estático**: `./bin/static_analysis.sh`
*   **Estándares de Código**: `./bin/check_standards.sh`

## Licencia
GPL-3.0-or-later.

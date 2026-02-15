# Chascarrillo

![PHP Version](https://img.shields.io/badge/PHP-8.5.1-blueviolet?style=flat-square)
![CI](https://github.com/alxarafe/chascarrillo/actions/workflows/ci.yml/badge.svg)
![Tests](https://github.com/alxarafe/chascarrillo/actions/workflows/tests.yml/badge.svg)
[![Quality Report](https://img.shields.io/badge/quality-report-brightgreen?style=flat-square)](https://alxarafe.github.io/chascarrillo/quality/)
![Static Analysis](https://img.shields.io/badge/static%20analysis-PHPStan%20%2B%20Psalm-blue?style=flat-square)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/alxarafe/chascarrillo/issues?utf8=✓&q=is%3Aopen%20is%3Aissue)

> **The "not-so-serious" blog engine built with the "very serious" Alxarafe Framework.**

In a world dominated by "Cloud-native", "Stream-based", and "Flow-oriented" architectures, releasing a blog engine called **Chascarrillo** is an act of elegant rebellion. It's a statement of origin and a return to the essence of storytelling.

### What is a "Chascarrillo"?
A *chascarrillo* is a short, witty story. This application is exactly that: clever code, lightweight design, and built for telling stories without technical complications.

## Made in Spain 🇪🇸
Chascarrillo claims its roots. While others look for the next buzzword in Silicon Valley, we looked into our own heritage to find a name that reflects exactly what we want to offer: something authentic, direct, and slightly mischievous.

## Technical Philosophy
Built upon the **Alxarafe Framework**, Chascarrillo leverages its robustness to provide a seamless blogging experience.

- **Minimalist**: Focuses on content, not configuration.
- **Witty Code**: Efficient and elegant implementation.
- **Framework Powered**: Uses Alxarafe v0.1.1 for core services and routing.

## Requirements
- PHP >= 8.5
- Alxarafe Framework v0.1.1
- Composer

## Installation

### Local development with Docker

Chascarrillo includes a complete Docker development environment based on Alxarafe patterns. To use it:

1. **Clone the repository**:
   ```bash
   git clone https://github.com/alxarafe/chascarrillo.git
   cd chascarrillo
   ```

2. **Start the containers**:
   ```bash
   ./bin/docker_start.sh
   ```

3. **Install dependencies**:
   ```bash
   docker exec -it chascarrillo_php composer install
   ```

4. **Run migrations and seeders**:
   ```bash
   ./bin/run_migrations.sh
   ```

5. **Access the application**:
   Open [http://localhost:8082](http://localhost:8082) in your browser.
   You can also access phpMyAdmin at [http://localhost:9082](http://localhost:9082).

### Manual installation

If you prefer to run it without Docker:
1. Copy `.env.example` to `.env` (if available) and configure your database.
2. Run `composer install`.
3. Run `php run_migrations.php` and `php run_seeders.php`.
4. Start a local server: `php -S localhost:8000 -t public`.

## Quality Control

Use the provided scripts in `bin/` to ensure code quality:
*   **Tests**: `./bin/run_tests.sh`
*   **Static Analysis**: `./bin/static_analysis.sh`
*   **Coding Standards**: `./bin/check_standards.sh`

## License
GPL-3.0-or-later.

# Chascarrillo Scripts Documentation

This directory contains shell scripts to automate testing, code styling, and static analysis tasks. These scripts are designed to run commands inside the Docker container `chascarrillo_php`, ensuring consistency with the development and CI environments.

## Docker Scripts

### `docker_start.sh`
*   **Purpose:** Starts the Chascarrillo project containers in detached mode.

### `docker_stop.sh`
*   **Purpose:** Stops the running containers for Chascarrillo.

### `run_migrations.sh`
*   **Purpose:** Runs database migrations and seeders inside the `chascarrillo_php` container.

---

## Quality Assurance Scripts

### `check_standards.sh`
*   **Tool:** `phpcs` (PHP Code Sniffer)
*   **Purpose:** Reports coding standard violations (PSR-12) in `src` and `Tests`.

### `static_analysis.sh`
*   **Tools:** `phpstan` and `psalm`
*   **Purpose:** Detects bugs and type inconsistencies early by analyzing code structure.

### `run_tests.sh`
*   **Tool:** `phpunit`
*   **Purpose:** Executes the application's Unit and Feature tests.

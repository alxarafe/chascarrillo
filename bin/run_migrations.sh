#!/bin/bash
# Description: Runs database migrations and seeders for the Chascarrillo project.

echo "Running migrations inside chascarrillo_php container..."
docker exec -it chascarrillo_php php run_migrations.php

echo "Running seeders inside chascarrillo_php container..."
docker exec -it chascarrillo_php php run_seeders.php

echo "Process finished."

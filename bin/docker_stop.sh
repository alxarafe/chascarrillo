#!/bin/bash
# Description: Stops the Chascarrillo project containers.

clear

echo "Stopping Chascarrillo containers..."
docker stop chascarrillo_nginx chascarrillo_php chascarrillo_db chascarrillo_phpmyadmin

echo "List of containers"
docker ps -a

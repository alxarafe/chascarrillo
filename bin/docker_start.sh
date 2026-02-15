#!/bin/bash
# Description: Starts the Chascarrillo project containers.

clear

echo "Starting Chascarrillo containers with docker compose..."
docker compose up -d

echo "List of containers"
docker ps

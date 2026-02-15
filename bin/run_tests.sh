#!/bin/bash
# Description: Runs the PHPUnit test suite inside the container.

echo "Running PHPUnit Tests..."
docker exec chascarrillo_php ./vendor/bin/phpunit

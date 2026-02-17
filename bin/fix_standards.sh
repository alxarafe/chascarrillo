#!/bin/bash
# Description: Automatically fixes coding standards using PHPCBF inside the container.

echo "Running PHPCBF..."
docker exec chascarrillo_php ./vendor/bin/phpcbf --tab-width=4 --encoding=utf-8 --standard=phpcs.xml Modules
docker exec chascarrillo_php ./vendor/bin/phpcbf --tab-width=4 --encoding=utf-8 --standard=phpcs.xml Tests

#!/bin/bash
# Description: Syncs Markdown content from Content/ to the database.

echo "Sincronizando contenido Markdown..."
docker exec chascarrillo_php php bin/sync_content.php

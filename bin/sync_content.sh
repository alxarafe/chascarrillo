#!/bin/bash
# Description: Syncs Markdown content from Content/import to the database.

echo "Sincronizando contenido Markdown..."
docker exec chascarrillo_php php bin/sync_content.php

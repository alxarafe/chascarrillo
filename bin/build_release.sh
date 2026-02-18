#!/bin/bash

# Script para generar un paquete de despliegue de Chascarrillo con todas las dependencias
# Uso: ./bin/build_release.sh v0.6.0

VERSION=${1:-"v0.6.0"}
OUTPUT_FILE="chascarrillo-deploy-${VERSION}.zip"

echo "🚀 Preparando paquete de despliegue $VERSION..."

# 1. Asegurar que las dependencias están optimizadas
echo "📦 Optimizando dependencias con Composer..."
composer install --no-dev --optimize-autoloader

# 2. Crear el archivo ZIP (incluyendo vendor)
echo "🗜️ Creando archivo ZIP: $OUTPUT_FILE..."
# Excluimos archivos de desarrollo y configuración local
zip -r "$OUTPUT_FILE" . \
    -x "*.git*" \
    -x "var/*" \
    -x "config.json" \
    -x ".env" \
    -x "tests/*" \
    -x "phpunit.xml" \
    -x "phpstan.neon" \
    -x "bin/build_release.sh" \
    -x ".agent/*" \
    -x "*.zip" \
    -x "setup.unlock"

echo "✅ Paquete generado con éxito!"
echo "👉 Ahora sube el archivo $OUTPUT_FILE como un 'Asset' a la Release $VERSION en GitHub."

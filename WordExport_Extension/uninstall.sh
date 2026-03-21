#!/bin/bash
# Uninstall script for WordExport Module
# Usage: ./uninstall.sh <container_name>

CONTAINER=${1}

if [ -z "$CONTAINER" ]; then
    echo "Error: Container name required."
    echo "Usage: ./uninstall.sh <container_name>"
    exit 1
fi

SCRIPT_DIR="$(dirname "$0")"

echo "Copying uninstall script to container..."
docker cp "$SCRIPT_DIR/uninstall_wordexport.php" "$CONTAINER":/var/www/html/uninstall_wordexport.php

echo "Running vtlib uninstall..."
docker exec -it "$CONTAINER" bash -c "cd /var/www/html && php uninstall_wordexport.php"

echo "Removing module files..."
docker exec "$CONTAINER" bash -c "
  rm -f  /var/www/html/uninstall_wordexport.php
  rm -rf /var/www/html/modules/WordExport
  rm -rf /var/www/html/layouts/v7/modules/WordExport
  rm -rf /var/www/html/test/wordexport
  rm -rf /var/www/html/test/templates_c/v7/*
  rm -rf /var/www/html/test/templates_c/*
"

echo "WordExport uninstalled successfully."

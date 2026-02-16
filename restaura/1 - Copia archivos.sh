#!/bin/bash

CONTAINER="$1"
BACKUP="/home/administrator/vtiger_backup/vtiger"

if [ -z "$CONTAINER" ]; then
  echo "Uso: $0 <nombre_contenedor>"
  exit 1
fi

echo "Restaurando en contenedor: $CONTAINER"

# 1. Copia total
echo "Copiando TODO el sitio desde $BACKUP..."
sudo docker cp "$BACKUP/." "$CONTAINER:/var/www/html/"

# 2. Reinicio para permisos
echo "Reiniciando contenedor..."
sudo docker restart "$CONTAINER"

echo "Restore completo ✅"
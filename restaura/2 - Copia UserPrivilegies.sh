#!/bin/bash

CONTAINER="$1"
BACKUP="/home/administrator/vtiger_backup/vtiger"

if [ -z "$CONTAINER" ]; then
  echo "Uso: $0 <nombre_contenedor>"
  exit 1
fi


# 1. COPIA TOTAL (El Cuerpo y el Cerebro)
# Como el contenedor está vacío, necesitamos TODO el código de Vtiger (index.php, librerías, etc.)
echo "Copiando /user_privileges $BACKUP..."
# Usamos /. para copiar el contenido del directorio, no el directorio en sí
sudo docker cp "$BACKUP/user_privileges/." "$CONTAINER:/var/www/html/user_privileges"

# 2. TRIGGER PERMISOS (Reinicia para ejecutar entrypoint hooks)
echo "Reiniciando contenedor para aplicar permisos (entrypoint hook)..."
sudo docker restart "$CONTAINER"

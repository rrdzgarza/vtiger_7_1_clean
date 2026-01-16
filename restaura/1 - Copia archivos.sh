#!/bin/bash
CONTAINER="vtiger_crm"
BACKUP="/home/administrator/vtiger_backup/vtiger"


# 1. COPIA TOTAL (El Cuerpo y el Cerebro)
# Como el contenedor está vacío, necesitamos TODO el código de Vtiger (index.php, librerías, etc.)
echo "Copiando TODO el sitio desde $BACKUP..."
# Usamos /. para copiar el contenido del directorio, no el directorio en sí
sudo docker cp "$BACKUP/." "$CONTAINER:/var/www/html/"

# 2. TRIGGER PERMISOS (Reinicia para ejecutar entrypoint hooks)
echo "Reiniciando contenedor para aplicar permisos (entrypoint hook)..."
sudo docker restart "$CONTAINER"

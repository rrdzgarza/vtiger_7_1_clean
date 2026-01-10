#!/bin/bash
CONTAINER="vtiger_crm"
BACKUP="/home/administrator/vtiger_backup/vtiger"

OLD_SITE_URL="https://vtiger-test1.caperti.com/"

# 1. COPIA TOTAL (El Cuerpo y el Cerebro)
# Como el contenedor está vacío, necesitamos TODO el código de Vtiger (index.php, librerías, etc.)
echo "Copiando TODO el sitio desde $BACKUP..."
# Usamos /. para copiar el contenido del directorio, no el directorio en sí
sudo docker cp "$BACKUP/." "$CONTAINER:/var/www/html/"

# 2. ARREGLAR DUEÑO (CRÍTICO)
echo "Arreglando permisos..."
# Aseguramos que TODO sea de www-data
sudo docker exec -u 0 "$CONTAINER" chown -R www-data:www-data /var/www/html

# 5. ACTUALIZAR SITE URL (Variable nueva solicitada)
# Reemplazamos la URL vieja por la del nuevo entorno ($SITE_URL)
# Usamos | como delimitador por las barras de la URL
sudo docker exec "$CONTAINER" /bin/bash -c "sed -i \"s|$OLD_SITE_URL|\$SITE_URL|g\" /var/www/html/config.inc.php"

# 6. Reiniciar para aseguranos

sudo docker restart "$CONTAINER"
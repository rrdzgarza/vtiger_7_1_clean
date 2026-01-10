#!/bin/bash
CONTAINER="vtiger_crm"
BACKUP="/home/administrator/vtiger_backup/vtiger"

OLD_SITE_URL="https://vtiger-test1.caperti.com/"

# 1. COPIA TOTAL (El Cuerpo y el Cerebro)
# Como el contenedor está vacío, necesitamos TODO el código de Vtiger (index.php, librerías, etc.)
echo "Copiando TODO el sitio desde $BACKUP..."
# Usamos /. para copiar el contenido del directorio, no el directorio en sí
sudo docker cp "$BACKUP/." "$CONTAINER:/var/www/html/"

# 2. ARREGLAR DUEÑO Y PERMISOS (CRÍTICO)
# Importante porque el ZIP piede haber perdido los detalles de permisos (755/644)
echo "Arreglando permisos (esto puede tardar un poco)..."

# 2.1 Dueño absoluto
sudo docker exec -u 0 "$CONTAINER" chown -R www-data:www-data /var/www/html

# 2.2 Resetear a permisos seguros base (Directorios 755, Archivos 644)
# Evita que archivos sean ejecutables o escribibles por todos
sudo docker exec -u 0 "$CONTAINER" find /var/www/html -type d -exec chmod 755 {} \;
sudo docker exec -u 0 "$CONTAINER" find /var/www/html -type f -exec chmod 644 {} \;

# 2.3 Dar permisos de escritura a carpetas críticas de Vtiger
echo "Abriendo permisos en carpetas de escritura..."
sudo docker exec -u 0 "$CONTAINER" chmod -R 775 /var/www/html/cache
sudo docker exec -u 0 "$CONTAINER" chmod -R 775 /var/www/html/storage
sudo docker exec -u 0 "$CONTAINER" chmod -R 775 /var/www/html/logs
sudo docker exec -u 0 "$CONTAINER" chmod -R 775 /var/www/html/test/templates_c
sudo docker exec -u 0 "$CONTAINER" chmod -R 775 /var/www/html/user_privileges
sudo docker exec -u 0 "$CONTAINER" chmod -R 775 /var/www/html/modules

# 2.4 Permitir ejecución de scripts de cron
sudo docker exec -u 0 "$CONTAINER" chmod +x /var/www/html/cron/*.sh

# 5. ACTUALIZAR SITE URL (Variable nueva solicitada)
# Reemplazamos la URL vieja por la del nuevo entorno ($SITE_URL)
# Usamos | como delimitador por las barras de la URL
sudo docker exec "$CONTAINER" /bin/bash -c "sed -i \"s|$OLD_SITE_URL|\$SITE_URL|g\" /var/www/html/config.inc.php"

# 6. Reiniciar para aseguranos

sudo docker restart "$CONTAINER"
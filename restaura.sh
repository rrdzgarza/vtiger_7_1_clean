#!/bin/bash
CONTAINER="d19f03a6f9e6"
BACKUP="/home/administrator/vtiger_backup/vtiger"

OLD_SITE_URL="https://vtiger-test1.caperti.com/"

# 1. Configuración (El Cerebro)
sudo docker cp "$BACKUP/config.inc.php" "$CONTAINER:/var/www/html/config.inc.php"

# 2. Privilegios y Menús (Evita WSOD)
sudo docker cp "$BACKUP/user_privileges" "$CONTAINER:/var/www/html/"
# Por si tabdata vive fuera en tu versión
[ -f "$BACKUP/user_privileges/tabdata.php" ] && sudo docker cp "$BACKUP/user_privileges/tabdata.php" "$CONTAINER:/var/www/html/user_privileges/"

# 3. Módulos (Consistencia DB)
sudo docker cp "$BACKUP/modules" "$CONTAINER:/var/www/html/"

# 4. ARREGLAR DUEÑO (CRÍTICO)
sudo docker exec -u 0 "$CONTAINER" chown -R www-data:www-data /var/www/html/config.inc.php /var/www/html/user_privileges /var/www/html/modules

# 5. ACTUALIZAR SITE URL (Variable nueva solicitada)
# Reemplazamos la URL vieja por la del nuevo entorno ($SITE_URL)
# Usamos | como delimitador por las barras de la URL
sudo docker exec "$CONTAINER" /bin/bash -c "sed -i \"s|$OLD_SITE_URL|\$SITE_URL|g\" /var/www/html/config.inc.php"

# 6. Reiniciar para aseguranos

sudo docker restart "$CONTAINER"
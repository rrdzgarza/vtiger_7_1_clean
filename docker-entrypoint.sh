#!/bin/bash
set -e

# 1. Update Config from Environment Variables (If config exists)
# This allows the user to copy their backup config, restart, and have the DB connection fixed automatically.
if [ -f /var/www/html/config.inc.php ]; then
    echo "Found config.inc.php. Updating Database Configuration from Environment..."
    
    # Update Legacy Variables
    if [ ! -z "$DB_HOSTNAME" ]; then sed -i "s/\$db_hostname = .*/\$db_hostname = '${DB_HOSTNAME}';/" /var/www/html/config.inc.php; fi
    if [ ! -z "$DB_USERNAME" ]; then sed -i "s/\$db_username = .*/\$db_username = '${DB_USERNAME}';/" /var/www/html/config.inc.php; fi
    if [ ! -z "$DB_PASSWORD" ]; then sed -i "s/\$db_password = .*/\$db_password = '${DB_PASSWORD}';/" /var/www/html/config.inc.php; fi
    if [ ! -z "$DB_NAME" ]; then sed -i "s/\$db_name = .*/\$db_name = '${DB_NAME}';/" /var/www/html/config.inc.php; fi
    
    # Update V7 Array Variables ($dbconfig['key'])
    if [ ! -z "$DB_HOSTNAME" ]; then 
        sed -i "s|\$dbconfig\['db_server'\] = .*;|\$dbconfig['db_server'] = '${DB_HOSTNAME}';|g" /var/www/html/config.inc.php
    fi
    if [ ! -z "$DB_USERNAME" ]; then 
        sed -i "s|\$dbconfig\['db_username'\] = .*;|\$dbconfig['db_username'] = '${DB_USERNAME}';|g" /var/www/html/config.inc.php
    fi
    if [ ! -z "$DB_PASSWORD" ]; then 
        sed -i "s|\$dbconfig\['db_password'\] = .*;|\$dbconfig['db_password'] = '${DB_PASSWORD}';|g" /var/www/html/config.inc.php
    fi
    if [ ! -z "$DB_NAME" ]; then 
        sed -i "s|\$dbconfig\['db_name'\] = .*;|\$dbconfig['db_name'] = '${DB_NAME}';|g" /var/www/html/config.inc.php
    fi
    
    # Update Site URL if provided
    if [ ! -z "$SITE_URL" ]; then
         # We do a loose match on $site_URL = '...'; to replace it with the new one
         # Regex: $site_URL = '...';
         sed -i "s|\$site_URL = .*;|\$site_URL = '${SITE_URL}';|g" /var/www/html/config.inc.php
    fi
    
    # Disable HTTPS redirect check in WebUI.php to prevent loops behind Traefik
    if [ -f /var/www/html/includes/main/WebUI.php ]; then
        echo "Patching WebUI.php to disable strict URL check..."
        sed -i 's/if.*$site_URL.*&&.*stripos.*$request_URL.*$site_URL.*/\/\/ &/' /var/www/html/includes/main/WebUI.php
        sed -i 's/header("Location: $site_URL",TRUE,301);/\/\/ &/' /var/www/html/includes/main/WebUI.php
        sed -i '/header("Location: \$site_URL",TRUE,301);/{n;s/exit;/\/\/ exit;/}' /var/www/html/includes/main/WebUI.php
    fi
    
    echo "Configuration updated."
else
    echo "WARNING: config.inc.php NOT found. Waiting for manual copy..."
fi

# 2. Fix Permissions
# Ensure www-data accepts the files
echo "Fixing permissions..."
chown -R www-data:www-data /var/www/html

# 3. Start Apache
echo "Starting Apache..."
exec apache2-foreground

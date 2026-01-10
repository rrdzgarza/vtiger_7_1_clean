#!/bin/bash
set -e

# Copy config if it doesn't exist but sample does
if [ ! -f /var/www/html/config.inc.php ]; then
    if [ -f /var/www/html/config.sample.inc.php ]; then
        echo "Initializing config.inc.php from config.sample.inc.php..."
        cp /var/www/html/config.sample.inc.php /var/www/html/config.inc.php
    fi
fi

# Apply Envs if config exists and vars are set
if [ -f /var/www/html/config.inc.php ]; then
    echo "Checking for environment variables to configure Vtiger..."
    
    if [ ! -z "$DB_HOSTNAME" ]; then 
        sed -i "s/\$db_hostname = .*/\$db_hostname = '${DB_HOSTNAME}';/" /var/www/html/config.inc.php
    fi
    
    if [ ! -z "$DB_USERNAME" ]; then 
        sed -i "s/\$db_username = .*/\$db_username = '${DB_USERNAME}';/" /var/www/html/config.inc.php
    fi
    
    if [ ! -z "$DB_PASSWORD" ]; then 
        sed -i "s/\$db_password = .*/\$db_password = '${DB_PASSWORD}';/" /var/www/html/config.inc.php
    fi
    
    if [ ! -z "$DB_NAME" ]; then 
        sed -i "s/\$db_name = .*/\$db_name = '${DB_NAME}';/" /var/www/html/config.inc.php
    fi
    
    # Use | delimiter for URL to handle slashes
    if [ ! -z "$SITE_URL" ]; then 
        sed -i "s|\$site_URL = .*|\$site_URL = '${SITE_URL}';|" /var/www/html/config.inc.php
    fi
    
    # Ensure correct permissions
    chown www-data:www-data /var/www/html/config.inc.php
fi

exec apache2-foreground

#!/bin/bash
set -e

# Copy config if it doesn't exist but sample does
# Check if config is missing OR empty (0 bytes)
if [ ! -s /var/www/html/config.inc.php ]; then
    # We use config.template.php because config.sample.inc.php is missing in this version
    if [ -f /var/www/html/config.template.php ]; then
        echo "Initializing config.inc.php from config.template.php..."
        cp /var/www/html/config.template.php /var/www/html/config.inc.php
    elif [ -f /var/www/html/config.sample.inc.php ]; then
         echo "Initializing config.inc.php from config.sample.inc.php..."
         cp /var/www/html/config.sample.inc.php /var/www/html/config.inc.php
    fi
fi

fi

# Replace Template Placeholders if they exist (handling config from template)
if [ -f /var/www/html/config.inc.php ]; then
    # Replace Database Placeholders
    # usage of | delimiter to handle special chars in passwords
    if [ ! -z "$DB_HOSTNAME" ]; then sed -i "s|_DBC_SERVER_|${DB_HOSTNAME}|g" /var/www/html/config.inc.php; fi
    if [ ! -z "$DB_USERNAME" ]; then sed -i "s|_DBC_USER_|${DB_USERNAME}|g" /var/www/html/config.inc.php; fi
    if [ ! -z "$DB_PASSWORD" ]; then sed -i "s|_DBC_PASS_|${DB_PASSWORD}|g" /var/www/html/config.inc.php; fi
    if [ ! -z "$DB_NAME" ]; then sed -i "s|_DBC_NAME_|${DB_NAME}|g" /var/www/html/config.inc.php; fi
    
    # Set DB Type to mysqli
    sed -i "s|_DBC_TYPE_|mysqli|g" /var/www/html/config.inc.php
    # Set Port (remove colon if present in template)
    sed -i "s|:_DBC_PORT_|3306|g" /var/www/html/config.inc.php
    sed -i "s|_DBC_PORT_|3306|g" /var/www/html/config.inc.php
fi

# Apply Envs if config exists and vars are set (Legacy variable names)
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

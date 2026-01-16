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
    if [ ! -z "$DB_PORT" ]; then 
        sed -i "s|\$dbconfig\['db_port'\] = .*;|\$dbconfig['db_port'] = '${DB_PORT}';|g" /var/www/html/config.inc.php
        # Also ensure db_hostname includes the port if needed (common Vtiger pattern)
        # We append matches that look like 'host' without port, replacing them with 'host' . 'port'
        # BUT safely, it is better to just rely on the array config if Vtiger 7 supports it.
        # However, for legacy compatibility, we might want to force db_hostname
        if [ ! -z "$DB_HOSTNAME" ]; then
             sed -i "s|\$db_hostname = .*;|\$db_hostname = '${DB_HOSTNAME}${DB_PORT}';|g" /var/www/html/config.inc.php
        fi
    fi
    
    # Update Site URL if provided
    if [ ! -z "$SITE_URL" ]; then
         # We do a loose match on $site_URL = '...'; to replace it with the new one
         # Regex: $site_URL = '...';
         sed -i "s|\$site_URL = .*;|\$site_URL = '${SITE_URL}';|g" /var/www/html/config.inc.php
    fi
    
    # Ensure log file exists and is writable
    touch /var/www/html/logs/vtigercrm.log
    chmod 777 /var/www/html/logs/vtigercrm.log
    
    # ---------------------------------------------------------------------------
    # REVERSE PROXY FIX (CRITICAL FOR SSL/TRAEFIK)
    # ---------------------------------------------------------------------------
    # We inject this snippet at the end of config.inc.php to force Vtiger to respect the SITE_URL scheme.
    # This prevents WSOD and Mixed Content errors when behind a proxy.
    
    # Check if we already injected it to avoid duplicates
    if ! grep -q "DOCKER_PROXY_FIX" /var/www/html/config.inc.php; then
        echo "Injecting Reverse Proxy Fix into config.inc.php..."
        
        # CRITICAL FIX: Remove closing PHP tag '?>' (and any trailing whitespace) 
        # so we don't accidentally append text outside PHP mode.
        sed -i 's/?>\s*$//' /var/www/html/config.inc.php
        
        cat <<'EOPHP' >> /var/www/html/config.inc.php

// --- DOCKER_PROXY_FIX START ---
// Force $_SERVER variables to match the configured $site_URL.
// This allows Vtiger to work correctly behind a Reverse Proxy (Traefik/Nginx) handling SSL.
if (isset($site_URL)) {
    $parse = parse_url($site_URL);
    if (isset($parse['scheme']) && $parse['scheme'] === 'https') {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = 443;
        if (isset($parse['host'])) {
            $_SERVER['HTTP_HOST'] = $parse['host'];
        }
    }
}
// --- DOCKER_PROXY_FIX END ---
?>
EOPHP
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

# 2. Deploy Maintenance Tools (recalculate.php, test_debug.php)
# We copy them from /usr/src/vtiger-tools/ (baked in image) to /var/www/html/
# verifying they exist first
if [ -d /usr/src/vtiger-tools ]; then
    echo "Deploying maintenance tools (recalculate.php, test_debug.php, debug_module.php)..."
    cp /usr/src/vtiger-tools/recalculate.php /var/www/html/
    cp /usr/src/vtiger-tools/test_debug.php /var/www/html/
    cp /usr/src/vtiger-tools/debug_module.php /var/www/html/
fi

# 3. Fix Permissions
# Ensure www-data accepts the files
echo "Fixing permissions..."
chown -R www-data:www-data /var/www/html

# 4. CUSTOM INIT SCRIPTS (Hooks)
# ---------------------------------------------------------------------------
# exec /docker-entrypoint-init.d/*.sh if they exist
if [ -d /docker-entrypoint-init.d ]; then
    echo "Looking for custom init scripts in /docker-entrypoint-init.d/..."
    for f in /docker-entrypoint-init.d/*.sh; do
        if [ -f "$f" ]; then
            echo "Running init script: $f"
            # Ensure it is executable
            chmod +x "$f"
            # Run it
            /bin/bash "$f" || echo "WARNING: Script $f returned error via exit code."
        fi
    done
fi

# 3. Start Apache
echo "Starting Apache..."
exec apache2-foreground

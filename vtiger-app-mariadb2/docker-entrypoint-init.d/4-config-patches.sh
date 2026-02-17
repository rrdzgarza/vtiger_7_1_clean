#!/bin/bash
# 4-config-patches.sh
# Handles system patches, runtime tools, and debug mode.

echo "---------------------------------------------------"
echo " [HOOK] 4-config-patches.sh: System Patches"
echo "---------------------------------------------------"
echo " 1. WebUI.php Patches (Host Validation / Redirects)"
echo " 2. User Privileges (Recalculate)"
echo " 3. Debug Mode (ENABLE_DEBUG)"
echo "---------------------------------------------------"

# 1. WEBUI.PHP PATCHES
# 1. WEBUI.PHP PATCHES / REPLACEMENT
TARGET_WEBUI="/var/www/html/includes/main/WebUI.php"
SOURCE_WEBUI="/docker-entrypoint-init.d/WebUI.php"

if [ -f "$SOURCE_WEBUI" ]; then
    echo " -> REPLACING WebUI.php with custom version from init.d..."
    cp "$SOURCE_WEBUI" "$TARGET_WEBUI"
    chown www-data:www-data "$TARGET_WEBUI"
else
    echo " -> Custom WebUI.php not found in init.d. Applying fallback patches..."
    if [ -f "$TARGET_WEBUI" ]; then
        # fallback: patch existing file if no custom replacement provided
        sed -i 's/if.*CSRF.*validate.*/if (false) { \/\/ BYPASS CSRF CHECK FOR DOCKER/g' "$TARGET_WEBUI"
        
        sed -i 's/if.*$site_URL.*&&.*stripos.*$request_URL.*$site_URL.*/\/\/ &/' "$TARGET_WEBUI"
        sed -i 's/header("Location: $site_URL",TRUE,301);/\/\/ &/' "$TARGET_WEBUI"
        sed -i '/header("Location: \$site_URL",TRUE,301);/{n;s/exit;/\/\/ exit;/}' "$TARGET_WEBUI"
    fi
fi

# 2. RECALCULATE PRIVILEGES
if [ "$FORCE_RECALCULATE" = "true" ]; then
    echo " -> FORCE_RECALCULATE=true. Regenerating User Privileges..."
    if [ -f /var/www/html/recalculate.php ]; then
        php -f /var/www/html/recalculate.php > /tmp/recalculate.log 2>&1
        echo "    (Done. Log in /tmp/recalculate.log)"
    else
        echo "⚠️  WARNING: recalculate.php not found. Skipping."
    fi
else
    echo " -> FORCE_RECALCULATE not set. Skipping privilege regeneration (Preserving existing files)."
fi

# 3. DEBUG MODE
if [ "$ENABLE_DEBUG" = "true" ]; then
    echo " -> ENABLE_DEBUG is set to true. Injecting error reporting..."
    
    # Inject into index.php
    if [ -f /var/www/html/index.php ]; then
        sed -i "2i error_reporting(E_ALL); ini_set('display_errors', 1);" /var/www/html/index.php
        echo "    (Injected into index.php)"
    fi
    
    # Inject into config.inc.php (Ensure display_errors is on)
    if [ -f /var/www/html/config.inc.php ]; then
         sed -i "s|ini_set('display_errors'.*|ini_set('display_errors','on');|" /var/www/html/config.inc.php
         echo "    (Updated config.inc.php)"
    fi
else
    echo " -> Debug mode disabled."
fi

echo "✅ [HOOK] Patches applied."

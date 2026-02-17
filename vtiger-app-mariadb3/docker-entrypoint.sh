#!/bin/bash
set -e

echo "========================================================="
echo " VTIGER CRM 7.1 DOCKER ENTRYPOINT"
echo "========================================================="

# 1. DEPLOY MAINTENANCE TOOLS
# We copy them from /usr/src/vtiger-tools/ (baked in image) to /var/www/html/
if [ -d /usr/src/vtiger-tools ]; then
    echo " -> Deploying maintenance tools..."
    cp /usr/src/vtiger-tools/recalculate.php /var/www/html/ 2>/dev/null || true
    cp /usr/src/vtiger-tools/test_debug.php /var/www/html/ 2>/dev/null || true
    cp /usr/src/vtiger-tools/debug_module.php /var/www/html/ 2>/dev/null || true
fi

# 2. RUN CUSTOM INIT SCRIPTS (Hooks)
# This includes:
# - 1-permisos.sh (Permissions)
# - 3-config-main.sh (Config.inc.php)
# - 4-config-patches.sh (Patches & Debug)
if [ -d /docker-entrypoint-init.d ]; then
    echo " -> Looking for init scripts in /docker-entrypoint-init.d/..."
    # Sort files naturally
    for f in $(ls /docker-entrypoint-init.d/*.sh | sort); do
        if [ -f "$f" ]; then
            echo ":: Executing $f ..."
            chmod +x "$f"
            /bin/bash "$f" || echo "⚠️  WARNING: Script $f returned error via exit code."
        fi
    done
fi

# 3. START APACHE
echo " -> Starting Apache..."
exec apache2-foreground

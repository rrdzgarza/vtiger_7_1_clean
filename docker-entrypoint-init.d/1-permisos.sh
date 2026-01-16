#!/bin/bash
# 1-permisos.sh
# Assigns permissions inside the container on startup.

echo "---------------------------------------------------"
echo " [HOOK] 1-permisos.sh: Asignando Permisos Vtiger"
echo "---------------------------------------------------"

# 1. ACTUALIZAR DUEÑO ABSOLUTO
echo " -> Validando dueño www-data..."
chown -R www-data:www-data /var/www/html

# 2. PERMISOS BASE SEGUROS
echo " -> Estableciendo permisos base (755 directorios, 644 archivos)..."
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;

# 3. CARPETAS DE ESCRITURA (775)
echo " -> Abriendo carpetas de escritura (775)..."

WRITE_DIRS=(
    "/var/www/html/cache"
    "/var/www/html/storage"
    "/var/www/html/logs"
    "/var/www/html/test/templates_c"
    "/var/www/html/user_privileges"
    "/var/www/html/modules"
)

for DIR in "${WRITE_DIRS[@]}"; do
    if [ -d "$DIR" ]; then
        chmod -R 775 "$DIR"
    fi
done

# 4. LIMPIEZA DE SMARTY CACHE
if [ -d "/var/www/html/test/templates_c" ]; then
    echo " -> Limpiando Smarty Cache (test/templates_c)..."
    rm -rf /var/www/html/test/templates_c/*
    chmod -R 775 /var/www/html/test/templates_c
    chown -R www-data:www-data /var/www/html/test/templates_c
fi

# 5. EJECUTABLES
echo " -> Marcando ejecutables..."
if ls /var/www/html/cron/*.sh 1> /dev/null 2>&1; then
    chmod +x /var/www/html/cron/*.sh
fi

if [ -f /var/www/html/vtiger ]; then
    chmod +x /var/www/html/vtiger
fi

echo "✅ [HOOK] Permisos aplicados."

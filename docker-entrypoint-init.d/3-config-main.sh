#!/bin/bash
# 3-config-main.sh
# Handles core configuration in config.inc.php (DB, SiteURL, Proxy)

echo "---------------------------------------------------"
echo " [HOOK] 3-config-main.sh: Core Configuration"
echo "---------------------------------------------------"
echo " 1. DB Configuration (Env Vars)"
echo " 2. Site URL & Root Directory"
echo " 3. Robust Proxy Fix (SSL/Traefik)"
echo "---------------------------------------------------"

CONFIG_FILE="/var/www/html/config.inc.php"

if [ ! -f "$CONFIG_FILE" ]; then
    echo "❌ ERROR: $CONFIG_FILE not found. Skipping configuration."
    exit 0
fi

# 1. DB CONFIGURATION
echo " -> Updating Database Configuration..."
if [ ! -z "$DB_HOSTNAME" ]; then sed -i "s/\$db_hostname = .*/\$db_hostname = '${DB_HOSTNAME}';/" "$CONFIG_FILE"; fi
if [ ! -z "$DB_USERNAME" ]; then sed -i "s/\$db_username = .*/\$db_username = '${DB_USERNAME}';/" "$CONFIG_FILE"; fi
if [ ! -z "$DB_PASSWORD" ]; then sed -i "s/\$db_password = .*/\$db_password = '${DB_PASSWORD}';/" "$CONFIG_FILE"; fi
if [ ! -z "$DB_NAME" ]; then sed -i "s/\$db_name = .*/\$db_name = '${DB_NAME}';/" "$CONFIG_FILE"; fi

# Update V7 Array Variables ($dbconfig['key'])
if [ ! -z "$DB_HOSTNAME" ]; then sed -i "s|\$dbconfig\['db_server'\] = .*;|\$dbconfig['db_server'] = '${DB_HOSTNAME}';|g" "$CONFIG_FILE"; fi
if [ ! -z "$DB_USERNAME" ]; then sed -i "s|\$dbconfig\['db_username'\] = .*;|\$dbconfig['db_username'] = '${DB_USERNAME}';|g" "$CONFIG_FILE"; fi
if [ ! -z "$DB_PASSWORD" ]; then sed -i "s|\$dbconfig\['db_password'\] = .*;|\$dbconfig['db_password'] = '${DB_PASSWORD}';|g" "$CONFIG_FILE"; fi
if [ ! -z "$DB_NAME" ];     then sed -i "s|\$dbconfig\['db_name'\] = .*;|\$dbconfig['db_name'] = '${DB_NAME}';|g" "$CONFIG_FILE"; fi
if [ ! -z "$DB_PORT" ];     then sed -i "s|\$dbconfig\['db_port'\] = .*;|\$dbconfig['db_port'] = '${DB_PORT}';|g" "$CONFIG_FILE"; fi

# 2. SITE URL & ROOT DIRECTORY
echo " -> Updating Site URL & Root Directory..."
if [ ! -z "$SITE_URL" ]; then
    sed -i "s|\$site_URL = .*;|\$site_URL = '${SITE_URL}';|g" "$CONFIG_FILE"
fi

# CRITICAL: Force root_directory to /var/www/html/
# Many migrations fail because this path is hardcoded to the old server's path
sed -i "s|\$root_directory *=.*|\$root_directory = '/var/www/html/';|" "$CONFIG_FILE"

# 3. ROBUST PROXY FIX
echo " -> Injecting Robust Proxy Fix..."
# Remove previous fix if exists to avoid duplication
sed -i '/DOCKER_PROXY_FIX START/,/DOCKER_PROXY_FIX END/d' "$CONFIG_FILE"

# Remove closing PHP tag so we can append safely
sed -i 's/?>\s*$//' "$CONFIG_FILE"

cat <<'EOPHP' >> "$CONFIG_FILE"

// --- DOCKER_PROXY_FIX START (v7.2.0 Style) ---
if (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https") {
    $_SERVER["HTTPS"] = "on";
    $_SERVER["SERVER_PORT"] = 443;
} elseif (isset($_SERVER["HTTP_X_FORWARDED_SSL"]) && $_SERVER["HTTP_X_FORWARDED_SSL"] === "on") {
    $_SERVER["HTTPS"] = "on";
    $_SERVER["SERVER_PORT"] = 443;
}
if (isset($_SERVER["HTTP_X_FORWARDED_HOST"])) {
    $_SERVER["HTTP_HOST"] = $_SERVER["HTTP_X_FORWARDED_HOST"];
}
// Ensure site_URL matches the public URL exactly
if (isset($site_URL)) {
    // Optional: Dynamic override if needed, but usually trusting config is safer
}
// --- DOCKER_PROXY_FIX END ---
?>
EOPHP

echo "✅ [HOOK] Core Config Applied."

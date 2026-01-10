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
    
    # Fix root_directory to satisfy Vtiger security check
    sed -i "s|^\s*\$root_directory = .*|\$root_directory = '/var/www/html';|g" /var/www/html/config.inc.php

    # Replace DB_STAT
    sed -i "s|_DB_STAT_|true|g" /var/www/html/config.inc.php
    
    # Enable display_errors in config.inc.php for debugging the 500 error
    # (Optional: remove this after debugging)
    if grep -q "ini_set('display_errors'" /var/www/html/config.inc.php; then
        sed -i "s|ini_set('display_errors',.*|ini_set('display_errors', 'On');|g" /var/www/html/config.inc.php
    else
        # Append it if not present
        echo "ini_set('display_errors', 'On');" >> /var/www/html/config.inc.php
        echo "version_compare(PHP_VERSION, '5.5.0') || error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);" >> /var/www/html/config.inc.php
    fi
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
    else 
        # Fallback if SITE_URL is not provided: try to deduce or set a default to avoid WSOD
        # Vtiger requires a valid URL here.
        sed -i "s|\$site_URL = .*|\$site_URL = 'https://vtiger-test1.caperti.com/';|" /var/www/html/config.inc.php
    fi
    
    # Ensure correct permissions
    chown -R www-data:www-data /var/www/html
    
    # Force permissions for critical writable folders
    chmod -R 775 /var/www/html/cache
    chmod -R 775 /var/www/html/logs
    chmod -R 775 /var/www/html/test
    chmod -R 775 /var/www/html/storage
    chmod -R 775 /var/www/html/user_privileges
    chmod -R 775 /var/www/html/modules
    chmod -R 775 /var/www/html/test/templates_c
    
    # REGENERATE PRIVILEGES IF MISSING (For existing DBs)
    if [ ! -f /var/www/html/user_privileges/user_privileges_1.php ]; then
        echo "Detected missing user_privileges for existing install. Regenerating..."
        cat <<EOF > /var/www/html/recalculate.php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Limit memory ensuring it runs
ini_set('memory_limit', '512M');

echo "Bootstraping Vtiger... ";
chdir('/var/www/html');
if (!file_exists('config.inc.php')) { die("config.inc.php missing"); }

require_once 'config.inc.php';
require_once 'include/utils/utils.php';

echo "Utils loaded. Loading Users... ";
require_once 'modules/Users/Users.php';
echo "Users loaded. Loading Creator... ";
require_once 'modules/Users/CreateUserPrivilegeFile.php';
echo "Creator loaded.\n";

global \$adb;
echo "Starting Privilege Regeneration...\n";

// Use RAW connection because \$adb seems broken in this context
include 'config.inc.php';
\$raw_conn = new mysqli(\$dbconfig['db_server'], \$dbconfig['db_username'], \$dbconfig['db_password'], \$dbconfig['db_name'], \$dbconfig['db_port']);

if (\$raw_conn->connect_error) {
    die("Raw Connection failed: " . \$raw_conn->connect_error);
}

\$query = "SELECT id, user_name FROM vtiger_users WHERE deleted=0";
echo "Executing raw query: \$query\n";
\$result = \$raw_conn->query(\$query);

if(\$result) {
    \$count = \$result->num_rows;
    echo "Query successful. Users found: \$count\n";
    while(\$row = \$result->fetch_assoc()) {
        \$userId = \$row['id'];
        \$userName = \$row['user_name'];
        echo "Generating privileges for User ID: \$userId (\$userName) ... ";
        // We use the Vtiger function for generation, ensuring \$adb is somewhat available if needed by the function
        // Re-ensure adb is global
        global \$adb;
        createUserPrivilegesfile(\$userId);
        echo "Done.\n";
    }
} else {
    echo "Query FAILED: " . \$raw_conn->error . "\n";
}
echo "Privilege regeneration complete.\n";
?>
EOF
        # Run it with set +e so we don't kill the container if it fails
        set +e
        echo "Running privilege regeneration..."
        php /var/www/html/recalculate.php > /var/www/html/recalc_log.txt 2>&1
        RECALC_STATUS=$?
        echo "Regeneration finished with status $RECALC_STATUS. Log saved to recalc_log.txt"
        set -e
        
        # Don't remove it yet so we can debug if needed
        # rm /var/www/html/recalculate.php
    fi
    
    # DEBUG: Print config to logs to verify generation (hide sensitive pass first?)
    # Ideally checking syntax
    php -l /var/www/html/config.inc.php
    echo "--- CONFIGURATION GENERATED ---"
    # We redact the password for safety in logs if we print it, but for now just syntax check is good validation.
    # If syntax is ok, we print the first 20 lines to check headers
    head -n 50 /var/www/html/config.inc.php
    echo "-------------------------------"
    
    # DEBUG: Create specific test file to check extensions and DB independently
    cat <<EOF > /var/www/html/test_debug.php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Vtiger Environment Check</h1>";
echo "<h2>PHP Extensions</h2>";
\$extensions = get_loaded_extensions();
echo "cURL: " . (in_array('curl', \$extensions) ? 'OK' : 'MISSING') . "<br>";
echo "MBString: " . (in_array('mbstring', \$extensions) ? 'OK' : 'MISSING') . "<br>";
echo "IMAP: " . (in_array('imap', \$extensions) ? 'OK' : 'MISSING') . "<br>";
echo "GD: " . (in_array('gd', \$extensions) ? 'OK' : 'MISSING') . "<br>";
echo "MySQLi: " . (in_array('mysqli', \$extensions) ? 'OK' : 'MISSING') . "<br>";

echo "<h2>Database Connection</h2>";
include 'config.inc.php';
echo "Server: " . \$dbconfig['db_server'] . "<br>";
echo "User: " . \$dbconfig['db_username'] . "<br>";
echo "DB: " . \$dbconfig['db_name'] . "<br>";

\$conn = new mysqli(\$dbconfig['db_server'], \$dbconfig['db_username'], \$dbconfig['db_password'], \$dbconfig['db_name'], \$dbconfig['db_port']);

if (\$conn->connect_error) {
  die("Connection failed: " . \$conn->connect_error);
}
echo "Connected successfully to Database!";

echo "<h2>Installation Check</h2>";
echo "Looking for install.php...<br>";
\$install_files = glob('/var/www/html/**/install.php');
print_r(\$install_files);

echo "<br>Checking modules/Install directory:<br>";
if (is_dir('/var/www/html/modules/Install')) {
    echo "modules/Install exists.<br>";
    \$scandir = scandir('/var/www/html/modules/Install');
    print_r(\$scandir);
} else {
    echo "modules/Install MISSING.<br>";
}

echo "<h2>Database Version Check</h2>";
\$result = \$conn->query("SELECT * FROM vtiger_version");
if (\$result) {
    while(\$row = \$result->fetch_assoc()) {
        echo "DB Version: " . \$row['current_version'] . " (Date: " . \$row['old_version'] . ")<br>";
    }
} else {
    echo "Could not query vtiger_version table. Is the DB initialized?<br>";
}

echo "<h2>User Privileges Check</h2>";
\$admin_priv_file = '/var/www/html/user_privileges/user_privileges_1.php';
if (file_exists(\$admin_priv_file)) {
    echo "Admin privileges file exists.<br>";
} else {
    echo "<strong>CRITICAL: Admin privileges file MISSING.</strong> This causes WSOD on existing DBs.<br>";
    echo "Attempting to regenerate... <br>";
}

echo "<h3>File System Check</h3>";
echo "Checking modules/Users/CreateUserPrivilegesFile.php... ";
if (file_exists('/var/www/html/modules/Users/CreateUserPrivilegesFile.php')) {
    echo "FOUND.<br>";
} else {
    echo "<strong>MISSING!</strong><br>";
    echo "Listing modules/Users/:<br>";
    print_r(scandir('/var/www/html/modules/Users'));
}

echo "<h3>User Count Check</h3>";
\$user_res = \$conn->query("SELECT COUNT(*) as c FROM vtiger_users");
if (\$user_res) {
    \$row = \$user_res->fetch_assoc();
    echo "Total Users in DB: " . \$row['c'] . "<br>";
} else {
    echo "Failed to query vtiger_users: " . \$conn->error . "<br>";
}
?>
EOF
    chown www-data:www-data /var/www/html/test_debug.php

fi

exec apache2-foreground

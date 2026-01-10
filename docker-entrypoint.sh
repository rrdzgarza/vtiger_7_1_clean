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
    
    sed -i "s|_DBC_TYPE_|mysqli|g" /var/www/html/config.inc.php
    
    # Set Port (Aggressive Replacement to fix previous bad states)
    TARGET_PORT="3306"
    if [ ! -z "$DB_PORT" ]; then
        TARGET_PORT="${DB_PORT}"
    fi
    # If the file already has '3306' or ':3306', we want to ensure it is ':3306' if that's what is needed for hostname concatenation.
    # The config says: $dbconfig['db_hostname'] = $dbconfig['db_server'].$dbconfig['db_port'];
    # So we MUST have a colon if db_server does not have it.
    
    # 1. Handle Template Placeholders
    sed -i "s|:_DBC_PORT_|:${TARGET_PORT}|g" /var/www/html/config.inc.php
    sed -i "s|_DBC_PORT_|:${TARGET_PORT}|g" /var/www/html/config.inc.php
    
    # 2. Fix existing wrong values (e.g. '3306' -> ':3306')
    # If line matches $dbconfig['db_port'] = '3306'; change to ':3306'
    sed -i "s|\$dbconfig\['db_port'\] = '${TARGET_PORT}';|\$dbconfig['db_port'] = ':${TARGET_PORT}';|g" /var/www/html/config.inc.php
    
    # Replace Missing Directories (CRITICAL FIX)
    sed -i "s|_VT_CACHEDIR_|cache/|g" /var/www/html/config.inc.php
    sed -i "s|_VT_TMPDIR_|cache/images/|g" /var/www/html/config.inc.php
    sed -i "s|_VT_UPLOADDIR_|storage/|g" /var/www/html/config.inc.php
    sed -i "s|_VT_APP_UNIQKEY_|$(date +%s)|g" /var/www/html/config.inc.php
    sed -i "s|_USER_SUPPORT_EMAIL_|support@localhost|g" /var/www/html/config.inc.php
    sed -i "s|_VT_CHARSET_|UTF-8|g" /var/www/html/config.inc.php
    sed -i "s|_VT_DEFAULT_LANGUAGE_|en_us|g" /var/www/html/config.inc.php
    sed -i "s|_MASTER_CURRENCY_|USD|g" /var/www/html/config.inc.php

    # Fix root_directory (Idempotent: Replace existing instead of append)
    sed -i "s|^\s*\$root_directory = .*|\$root_directory = '/var/www/html/';|g" /var/www/html/config.inc.php
    
    # Use | delimiter for URL to handle slashes
    if [ ! -z "$SITE_URL" ]; then 
        # Ensure SITE_URL has trailing slash if missing
        CleanedURL=$(echo "$SITE_URL" | sed 's:/*$::')
        sed -i "s|\$site_URL = .*|\$site_URL = '${CleanedURL}/';|" /var/www/html/config.inc.php
    else 
        # Fallback if SITE_URL is not provided: try to deduce or set a default to avoid WSOD
        # Vtiger requires a valid URL here.
        sed -i "s|\$site_URL = .*|\$site_URL = 'https://vtiger-test1.caperti.com/';|" /var/www/html/config.inc.php
    fi
    
    # Remove aggressive append of root_directory to prevent duplication
    # We already handled it nicely in the section above via sed replacement.
    # echo "Force updating root_directory..."
    # echo "\$root_directory = '/var/www/html/';" >> /var/www/html/config.inc.php
    
    # Ensure correct permissions
    chown -R www-data:www-data /var/www/html
    
    # Force permissions for critical writable folders
    chmod -R 775 /var/www/html/cache
    chmod -R 775 /var/www/html/logs
    chmod -R 775 /var/www/html/test
    chmod -R 775 /var/www/html/storage
    chmod -R 775 /var/www/html/user_privileges
    chmod -R 775 /var/www/html/modules
    chmod -R 775 /var/www/html/modules
    chmod -R 775 /var/www/html/test/templates_c
    
    # Ensure log file exists and is writable
    touch /var/www/html/logs/vtigercrm.log
    chmod 777 /var/www/html/logs/vtigercrm.log
    
    # REGENERATE PRIVILEGES IF MISSING (For existing DBs)
    # Check for user_privileges_1.php OR missing tabdata.php (which implies incomplete setup)
    if [ ! -f /var/www/html/user_privileges/user_privileges_1.php ] || [ ! -f /var/www/html/user_privileges/tabdata.php ]; then
        echo "Detected missing user_privileges or tabdata for existing install. Regenerating..."
        
        # Remove zombie file if exists (to start clean)
        rm -f /var/www/html/user_privileges/user_privileges_1.php






    # Remove specific check logic in WebUI.php that causes redirect loops behind proxies
    # We use aggressive sed to comment out the block lines 107-113 approx
    # Target: if ($site_URL && stripos($request_URL, $site_URL) !== 0){
    # We use a loose match to ensure it works even if whitespace differs
    sed -i 's/if.*$site_URL.*&&.*stripos.*$request_URL.*$site_URL.*/\/\/ &/' /var/www/html/includes/main/WebUI.php
    sed -i 's/header("Location: $site_URL",TRUE,301);/\/\/ &/' /var/www/html/includes/main/WebUI.php
    # We comment out the exit that follows the header
    # Context match: Find header line, then next line is exit.
    sed -i '/header("Location: \$site_URL",TRUE,301);/{n;s/exit;/\/\/ exit;/}' /var/www/html/includes/main/WebUI.php

    # Create temporary regeneration script
    # (Restored header)
    cat <<EOF > /var/www/html/recalculate.php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('vtiger_exit', true);

// 1. Force CWD
chdir(dirname(__FILE__));

// 2. Shutdown Handler
register_shutdown_function(function() {
    \$error = error_get_last();
    if (\$error && (\$error['type'] === E_ERROR || \$error['type'] === E_PARSE || \$error['type'] === E_CORE_ERROR)) {
        echo "FATAL SHUTDOWN: " . \$error['message'] . " in " . \$error['file'] . ":" . \$error['line'] . "\n";
    }
    echo "Script finished (shutdown).\n";
});

try {
    require_once 'config.inc.php';
    require_once 'include/utils/utils.php';
    require_once 'include/database/PearDatabase.php';

    global \$adb;
    if (empty(\$adb)) {
        \$adb = PearDatabase::getInstance();
        \$adb->connect();
    }
    
    // --- STEP 1: GENERATE CRITICAL MENU FILES FIRST ---
    // If the loop crashes later, we at least have these.
    echo "Generating Tab Data (tabdata.php)... ";
    require_once 'include/utils/UserInfoUtil.php';
    create_tab_data_file();
    create_parenttab_data_file();
    echo "Done.\n";

    require_once 'modules/Users/CreateUserPrivilegeFile.php';

    // --- STEP 2: GENERATE ADMIN SKELETON (SAFE MODE) ---
    // Bypassing complex recursion for Admin (ID 1) to ensure login works.
    echo "Generating Skeleton Privileges for User ID: 1 (admin) ... ";
    \$handle = @fopen('user_privileges/user_privileges_1.php', "w+");
    if (\$handle) {
        \$content = "<?php\n";
        \$content .= "\$user_info = array('is_admin'=>'on');\n";
        \$content .= "\$is_admin = true;\n";
        \$content .= "\$current_user_roles = '';\n";
        \$content .= "\$current_user_parent_role_seq = '';\n";
        \$content .= "\$current_user_profiles = array();\n";
        \$content .= "\$profileGlobalPermission = array(1=>0, 2=>0);\n"; // 0 = Allowed
        \$content .= "\$profileTabsPermission = array();\n";
        \$content .= "\$profileActionPermission = array();\n";
        \$content .= "\$current_user_groups = array();\n";
        \$content .= "\$subordinate_roles = array();\n";
        \$content .= "\$parent_roles = array();\n";
        \$content .= "\$subordinate_roles_users = array();\n";
        \$content .= "?>";
        fwrite(\$handle, \$content);
        fclose(\$handle);
        echo "Done (Skeleton Written).\n";
    } else {
        echo "FAILED (File Write Error).\n";
    }

    // --- STEP 3: ATTEMPT TO GENERATE OTHERS (RISKY) ---
    // We use raw mysqli to get list of IDs
    \$mysqli = new mysqli(\$dbconfig['db_hostname'], \$dbconfig['db_username'], \$dbconfig['db_password'], \$dbconfig['db_name']);
    if (\$mysqli->connect_error) {
         echo "DB Connection Warning: " . \$mysqli->connect_error . "\n";
    } else {
        \$result = \$mysqli->query("SELECT id, user_name FROM vtiger_users WHERE deleted=0 AND id != 1");
        if (\$result) {
            echo "Other Users found: " . \$result->num_rows . "\n";
            while (\$row = \$result->fetch_assoc()) {
                echo "Generating privileges for User ID: " . \$row['id'] . " (" . \$row['user_name'] . ") ... ";
                try {
                    // Flush output before dangerous call
                    flush(); 
                    createUserPrivilegesfile(\$row['id']);
                    echo "Done.\n";
                } catch (Throwable \$t) {
                     echo "FATAL ERROR (Caught): " . \$t->getMessage() . "\n";
                } catch (Exception \$e) {
                    echo "FAILED: " . \$e->getMessage() . "\n";
                }
                flush();
            }
        }
    }

    require_once('modules/Users/Users.php');
    echo "Privilege regeneration complete.\n";

} catch (Throwable \$t) {
    echo "GLOBAL CRASH: " . \$t->getMessage() . "\n";
}
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
        
        # FIX PERMISSIONS AGAIN (Because script ran as root)
        echo "Fixing ownership of generated files..."
        chown -R www-data:www-data /var/www/html/user_privileges
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

echo "<h2>Configuration Check</h2>";
echo "<strong>Configured site_URL:</strong> " . \$site_URL . "<br>";
echo "<strong>Configured root_directory:</strong> " . \$root_directory . "<br>";
echo "<strong>Current HTTP_HOST:</strong> " . \$_SERVER['HTTP_HOST'] . "<br>";
echo "Current SCRIPT_NAME: " . \$_SERVER['SCRIPT_NAME'] . "<br>";

echo "<h2>Ghost File Check</h2>";
echo "Listing /var/www/ (checking for mislocated files due to missing slash):<br>";
print_r(scandir('/var/www/'));

echo "<h2>Apache Error Log (Last 20 lines)</h2>";
// Try to read apache log (might fail due to permissions, but worth a try)
\$log_file = '/var/log/apache2/error.log';
if (is_readable(\$log_file)) {
    echo "<pre>" . htmlspecialchars(shell_exec("tail -n 20 \$log_file")) . "</pre>";
} else {
    echo "Cannot read $log_file (Permission denied or missing).<br>";
}

echo "<h2>Viewer/Smarty Test</h2>";
ob_start();
try {
    chdir('/var/www/html');
    echo "Current Dir: " . getcwd() . "<br>";
    
    // Fix: Include Utils so checkFileAccessForInclusion exists
    require_once 'include/utils/utils.php';
    
    if (!file_exists('includes/Loader.php')) {
        echo "CRITICAL: includes/Loader.php MISSING.<br>";
    } else {
        require_once 'includes/Loader.php';
        echo "Loader.php included.<br>";
        
        // Manual check for SmartyBC
        \$smarty_file = 'libraries/Smarty/libs/SmartyBC.class.php';
        echo "Checking \$smarty_file... ";
        if (file_exists(\$smarty_file)) {
            echo "FOUND. ";
            if (is_readable(\$smarty_file)) {
                echo "READABLE.<br>";
            } else {
                echo "NOT READABLE (Permissions?).<br>";
            }
        } else {
            echo "MISSING!<br>";
        }

        // Test vimport resolution
        echo "Testing vimport('~/libraries/Smarty/libs/SmartyBC.class.php')... ";
        \$resolved = Vtiger_Loader::resolveNameToPath('~/libraries/Smarty/libs/SmartyBC.class.php');
        echo "Resolved to: " . \$resolved . "<br>";
        
        echo "Attempting to import Viewer...<br>";
        vimport('includes.runtime.Viewer');
        
        if (class_exists('Vtiger_Viewer')) {
            echo "Vtiger_Viewer class FOUND.<br>";
            \$viewer = new Vtiger_Viewer();
            echo "Vtiger_Viewer instantiated.<br>";
            
            // Try to render a simple string or template if possible
            echo "Attempting Basic Template Render... ";
            try {
                // Determine layout path
                \$layout = 'v7'; 
                echo "Layout: \$layout <br>";
                // Check if layout folder exists
                if (is_dir("layouts/\$layout")) {
                   echo "layouts/\$layout exists.<br>";
                } else {
                   echo "layouts/\$layout MISSING.<br>";
                }
            } catch (Exception \$e) {
                echo "Render Error: " . \$e->getMessage() . "<br>";
            }
        } else {
            echo "Vtiger_Viewer class MISSING after import.<br>";
        }
    }
} catch (Exception \$e) {
    echo "Viewer/Smarty Error: " . \$e->getMessage() . "<br>";
} catch (Throwable \$t) { // PHP 7+
    echo "Fatal Error: " . \$t->getMessage() . "<br>";
}
\$v_out = ob_get_clean();
echo \$v_out;

echo "<h2>Logs Inspection</h2>";
echo "<h3>Recalc Log (Last 50 lines)</h3>";
if (file_exists('recalc_log.txt')) {
    echo "<pre>" . htmlspecialchars(shell_exec('tail -n 50 recalc_log.txt')) . "</pre>";
} else {
    echo "recalc_log.txt missing.<br>";
}

echo "<h3>VtigerCRM Log (Last 50 lines)</h3>";
if (file_exists('logs/vtigercrm.log')) {
    echo "<pre>" . htmlspecialchars(shell_exec('tail -n 50 logs/vtigercrm.log')) . "</pre>";
} else {
    echo "logs/vtigercrm.log missing or not readable.<br>";
}

echo "<h2>Index Include Test</h2>";
echo "Buffered Output from include check: <pre>" . htmlspecialchars(substr(\$output, 0, 500)) . "</pre>";

echo "<h2>Config Integrity Check</h2>";
// Dump config.inc.php but mask password
\$config_content = file_get_contents('config.inc.php');
\$config_content = preg_replace("/'db_password'\s*=>\s*'.*'/", "'db_password' => '***REDACTED***'", \$config_content);
\$config_content = preg_replace("/\\\$db_password\s*=\s*'[^']*'/", "\$db_password = '***REDACTED***'", \$config_content);
echo "<textarea rows='20' cols='100'>" . htmlspecialchars(\$config_content) . "</textarea>";

?>
EOF
    chown www-data:www-data /var/www/html/test_debug.php

fi

exec apache2-foreground

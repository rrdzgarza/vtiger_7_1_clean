<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Vtiger Environment Check</h1>";
echo "<h2>PHP Extensions</h2>";
$extensions = get_loaded_extensions();
echo "cURL: " . (in_array('curl', $extensions) ? 'OK' : 'MISSING') . "<br>";
echo "MBString: " . (in_array('mbstring', $extensions) ? 'OK' : 'MISSING') . "<br>";
echo "IMAP: " . (in_array('imap', $extensions) ? 'OK' : 'MISSING') . "<br>";
echo "GD: " . (in_array('gd', $extensions) ? 'OK' : 'MISSING') . "<br>";
echo "MySQLi: " . (in_array('mysqli', $extensions) ? 'OK' : 'MISSING') . "<br>";

echo "<h2>Database Connection</h2>";
include 'config.inc.php';
echo "Server: " . $dbconfig['db_server'] . "<br>";
echo "User: " . $dbconfig['db_username'] . "<br>";
echo "DB: " . $dbconfig['db_name'] . "<br>";

$conn = new mysqli($dbconfig['db_server'], $dbconfig['db_username'], $dbconfig['db_password'], $dbconfig['db_name'], $dbconfig['db_port']);

// Explicit Connection Check
if ($conn->connect_errno) {
    die("Connection failed (Errno: " . $conn->connect_errno . "): " . $conn->connect_error);
}
echo "Connected successfully to Server!<br>";
echo "Host Info: " . $conn->host_info . "<br>";

// Explicit DB Selection Check
$target_db = $dbconfig['db_name'];
echo "Attempting to select database: '$target_db'... ";
if ($conn->select_db($target_db)) {
    echo "SUCCESS.<br>";
} else {
    echo "<strong style='color:red'>FAILED!</strong><br>";
    echo "Error: " . $conn->error . "<br>";
    echo "Errno: " . $conn->errno . "<br>";
}

// DEEP DEBUGGING
$thread_id = $conn->thread_id;
$current_db = "UNKNOWN";
if ($res = $conn->query("SELECT DATABASE()")) {
    $row = $res->fetch_row();
    $current_db = $row[0];
}
echo "<strong>Connection Debug Info:</strong><br>";
echo "MySQL Thread ID: $thread_id<br>";
echo "Current Database: <strong>$current_db</strong><br>";
echo "Target Database (Config): " . $dbconfig['db_name'] . "<br>";

if ($current_db != $dbconfig['db_name']) {
    echo "<strong style='color:red'>WARNING: CONNECTED TO WRONG DATABASE!</strong><br>";
}

// Check for table existence in Schema
echo "Checking Information Schema for 'vtiger_version'...<br>";
$check_table = $conn->query("SELECT TABLE_NAME, TABLE_SCHEMA FROM information_schema.TABLES WHERE TABLE_NAME = 'vtiger_version'");
if ($check_table && $check_table->num_rows > 0) {
    while ($t = $check_table->fetch_assoc()) {
        echo "Found table in schema: " . $t['TABLE_SCHEMA'] . "<br>";
    }
} else {
    echo "Table 'vtiger_version' NOT FOUND in any visible schema.<br>";
}

echo "<h2>Installation Check</h2>";
echo "Looking for install.php...<br>";
$install_files = glob('/var/www/html/**/install.php');
print_r($install_files);

echo "<br>Checking modules/Install directory:<br>";
if (is_dir('/var/www/html/modules/Install')) {
    echo "modules/Install exists.<br>";
    $scandir = scandir('/var/www/html/modules/Install');
    print_r($scandir);
} else {
    echo "modules/Install MISSING.<br>";
}

// DB Version Check with Error Reporting
echo "<h2>Database Version Check</h2>";
$result = $conn->query("SELECT * FROM vtiger_version");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "DB Version: " . $row['current_version'] . " (Date: " . $row['old_version'] . ")<br>";
    }
} else {
    echo "Could not query vtiger_version table.<br>";
    echo "<strong>Error:</strong> " . $conn->error . "<br>";
    echo "Is the DB name correct and tables imported?<br>";
}

echo "<h2>User Privileges Check</h2>";
$admin_priv_file = '/var/www/html/user_privileges/user_privileges_1.php';
if (file_exists($admin_priv_file)) {
    echo "Admin privileges file exists.<br>";
} else {
    echo "<strong>CRITICAL: Admin privileges file MISSING.</strong> This causes WSOD on existing DBs.<br>";
    echo "Attempting to regenerate... <br>";
}

echo "<h3>File System Check</h3>";
// Fixed Typo: Privileges -> Privilege
echo "Checking modules/Users/CreateUserPrivilegeFile.php... ";
if (file_exists('/var/www/html/modules/Users/CreateUserPrivilegeFile.php')) {
    echo "FOUND.<br>";
} else {
    echo "<strong>MISSING!</strong><br>";
    echo "Listing modules/Users/:<br>";
    print_r(scandir('/var/www/html/modules/Users'));
}

echo "<h3>User Count Check</h3>";
$user_res = $conn->query("SELECT COUNT(*) as c FROM vtiger_users");
if ($user_res) {
    $row = $user_res->fetch_assoc();
    echo "Total Users in DB: " . $row['c'] . "<br>";
} else {
    echo "Failed to query vtiger_users: " . $conn->error . "<br>";
}

echo "<h2>Configuration Check</h2>";
echo "<strong>Configured site_URL:</strong> " . $site_URL . "<br>";
echo "<strong>Configured root_directory:</strong> " . $root_directory . "<br>";
echo "<strong>Current HTTP_HOST:</strong> " . $_SERVER['HTTP_HOST'] . "<br>";
echo "Current SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "<br>";

echo "<h2>Ghost File Check</h2>";
echo "Listing /var/www/ (checking for mislocated files due to missing slash):<br>";
print_r(scandir('/var/www/'));

echo "<h2>Apache Error Log (Last 20 lines)</h2>";
// Try to read apache log (might fail due to permissions, but worth a try)
$log_file = '/var/log/apache2/error.log';
if (is_readable($log_file)) {
    echo "<pre>" . htmlspecialchars(shell_exec("tail -n 20 $log_file")) . "</pre>";
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
        $smarty_file = 'libraries/Smarty/libs/SmartyBC.class.php';
        echo "Checking $smarty_file... ";
        if (file_exists($smarty_file)) {
            echo "FOUND. ";
            if (is_readable($smarty_file)) {
                echo "READABLE.<br>";
            } else {
                echo "NOT READABLE (Permissions?).<br>";
            }
        } else {
            echo "MISSING!<br>";
        }

        // Test vimport resolution
        echo "Testing vimport('~/libraries/Smarty/libs/SmartyBC.class.php')... ";
        $resolved = Vtiger_Loader::resolveNameToPath('~/libraries/Smarty/libs/SmartyBC.class.php');
        echo "Resolved to: " . $resolved . "<br>";

        echo "Attempting to import Viewer...<br>";
        vimport('includes.runtime.Viewer');

        if (class_exists('Vtiger_Viewer')) {
            echo "Vtiger_Viewer class FOUND.<br>";
            $viewer = new Vtiger_Viewer();
            echo "Vtiger_Viewer instantiated.<br>";

            // Try to render a simple string or template if possible
            echo "Attempting Basic Template Render... ";
            try {
                // Determine layout path
                $layout = 'v7';
                echo "Layout: $layout <br>";
                // Check if layout folder exists
                if (is_dir("layouts/$layout")) {
                    echo "layouts/$layout exists.<br>";
                } else {
                    echo "layouts/$layout MISSING.<br>";
                }
            } catch (Exception $e) {
                echo "Render Error: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "Vtiger_Viewer class MISSING after import.<br>";
        }
    }
} catch (Exception $e) {
    echo "Viewer/Smarty Error: " . $e->getMessage() . "<br>";
} catch (Throwable $t) { // PHP 7+
    echo "Fatal Error: " . $t->getMessage() . "<br>";
}
$v_out = ob_get_clean();
echo $v_out;

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
echo "Buffered Output from include check: <pre>" . htmlspecialchars(substr($output, 0, 500)) . "</pre>";

echo "<h2>Config Integrity Check</h2>";
// Dump config.inc.php but mask password
$config_content = file_get_contents('config.inc.php');
$config_content = preg_replace("/'db_password'\s*=>\s*'.*'/", "'db_password' => '***REDACTED***'", $config_content);
$config_content = preg_replace("/\\\$db_password\s*=\s*'[^']*'/", "\$db_password = '***REDACTED***'", $config_content);
echo "<textarea rows='20' cols='100'>" . htmlspecialchars($config_content) . "</textarea>";
?>
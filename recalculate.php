<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('vtiger_exit', true);

// 1. Force CWD
chdir(dirname(__FILE__));

// 2. Shutdown Handler
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR)) {
        echo "FATAL SHUTDOWN: " . $error['message'] . " in " . $error['file'] . ":" . $error['line'] . "\n";
    }
    echo "Script finished (shutdown).\n";
});

try {
    require_once 'config.inc.php';
    require_once 'include/utils/utils.php';
    require_once 'include/database/PearDatabase.php';

    global $adb;
    if (empty($adb)) {
        $adb = PearDatabase::getInstance();
        $adb->connect();
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
    $handle = @fopen('user_privileges/user_privileges_1.php', "w+");
    if ($handle) {
        $content = "<?php\n";
        $content .= "\$user_info = array('is_admin'=>'on');\n";
        $content .= "\$is_admin = true;\n";
        $content .= "\$current_user_roles = '';\n";
        $content .= "\$current_user_parent_role_seq = '';\n";
        $content .= "\$current_user_profiles = array();\n";
        $content .= "\$profileGlobalPermission = array(1=>0, 2=>0);\n"; // 0 = Allowed
        $content .= "\$profileTabsPermission = array();\n";
        $content .= "\$profileActionPermission = array();\n";
        $content .= "\$current_user_groups = array();\n";
        $content .= "\$subordinate_roles = array();\n";
        $content .= "\$parent_roles = array();\n";
        $content .= "\$subordinate_roles_users = array();\n";
        $content .= "?>";
        fwrite($handle, $content);
        fclose($handle);
        echo "Done (Skeleton Written).\n";
    } else {
        echo "FAILED (File Write Error).\n";
    }

    // --- STEP 3: ATTEMPT TO GENERATE OTHERS (RISKY) ---
    // We use raw mysqli to get list of IDs
    // Fix for Docker/mysqli: separate host and port
    $db_port = 3306;
    if (isset($dbconfig['db_port']) && !empty($dbconfig['db_port'])) {
        $db_port = (int) str_replace(':', '', $dbconfig['db_port']);
    }
    $mysqli = new mysqli($dbconfig['db_server'], $dbconfig['db_username'], $dbconfig['db_password'], $dbconfig['db_name'], $db_port);
    if ($mysqli->connect_error) {
        echo "DB Connection Warning: " . $mysqli->connect_error . "\n";
    } else {
        $result = $mysqli->query("SELECT id, user_name FROM vtiger_users WHERE deleted=0 AND id != 1");
        if ($result) {
            echo "Other Users found: " . $result->num_rows . "\n";
            while ($row = $result->fetch_assoc()) {
                echo "Generating privileges for User ID: " . $row['id'] . " (" . $row['user_name'] . ") ... ";
                try {
                    // Flush output before dangerous call
                    flush();
                    createUserPrivilegesfile($row['id']);
                    echo "Done.\n";
                } catch (Throwable $t) {
                    echo "FATAL ERROR (Caught): " . $t->getMessage() . "\n";
                } catch (Exception $e) {
                    echo "FAILED: " . $e->getMessage() . "\n";
                }
                flush();
            }
        }
    }

    require_once('modules/Users/Users.php');
    echo "Privilege regeneration complete.\n";

} catch (Throwable $t) {
    echo "GLOBAL CRASH: " . $t->getMessage() . "\n";
}
?>
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

    // --- STEP 1: LOAD VTIGER LIBRARIES ---
    require_once 'include/utils/UserInfoUtil.php';
    require_once 'include/utils/VtlibUtils.php';
    require_once 'modules/Users/Users.php';
    require_once 'modules/Users/CreateUserPrivilegeFile.php';

    // --- STEP 2: USE NATIVE REGENERATION ---
    echo "Regenerating User Privileges using NATIVE Vtlib function...\n";

    // This function handles everything: looping users, calling createUserPrivilegesfile, etc.
    // It is the exact same function used by the Module Manager.
    vtlib_RecreateUserPrivilegeFiles();

    echo "Privilege regeneration complete (Native Pattern).\n";

} catch (Throwable $t) {
    echo "GLOBAL CRASH: " . $t->getMessage() . "\n";
}
?>
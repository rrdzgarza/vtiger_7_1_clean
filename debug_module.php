<?php
// debug_module.php
// Place this in /var/www/html/ and run via browser or CLI
// Usage: debug_module.php?module=Products

ini_set('display_errors', 1);
error_reporting(E_ALL);

chdir('/var/www/html');

echo "<h1>Vtiger Module Debugger</h1>";
$target_module = isset($_GET['module']) ? $_GET['module'] : 'Products';
echo "Target Module: <strong>$target_module</strong><br>";

echo "<h2>1. Basic Includes</h2>";
try {
    require_once 'config.inc.php';
    echo "config.inc.php loaded.<br>";
    require_once 'include/utils/utils.php';
    echo "utils.php loaded.<br>";
} catch (Throwable $t) {
    die("CRITICAL: Failed to load basics: " . $t->getMessage());
}

echo "<h2>2. DB Connection</h2>";
global $adb;
if (empty($adb)) {
    echo "Instantiating PearDatabase... ";
    require_once 'include/database/PearDatabase.php';
    $adb = PearDatabase::getInstance();
    $adb->connect();
    echo "Done.<br>";
}

echo "<h2>3. Module File Check</h2>";
$module_file = "modules/$target_module/$target_module.php";
if (file_exists($module_file)) {
    echo "File $module_file EXISTS.<br>";
    // Check for syntax errors
    echo "Syntax Check: ";
    $output = shell_exec("php -l $module_file");
    echo "$output<br>";
} else {
    die("CRITICAL: Module file $module_file NOT FOUND.");
}

echo "<h2>4. CRMEntity Instantiation</h2>";
try {
    require_once 'data/CRMEntity.php';
    echo "CRMEntity.php loaded.<br>";

    echo "Instantiating $target_module... ";
    require_once $module_file;
    $focus = new $target_module();
    echo "Done. Object class: " . get_class($focus) . "<br>";

    if (isset($focus->table_name)) {
        echo "Table Name: " . $focus->table_name . "<br>";
    } else {
        echo "WARNING: table_name property missing.<br>";
    }

} catch (Throwable $t) {
    echo "<br><strong style='color:red'>FATAL ERROR during instantiation:</strong> " . $t->getMessage();
    echo "<pre>" . $t->getTraceAsString() . "</pre>";
    die();
}

echo "<h2>5. User Privileges Check</h2>";
// Try to get current user
$user_id = 1; // Admin
try {
    require_once 'modules/Users/Users.php';
    $current_user = new Users();
    $current_user->retrieveCurrentUserInfoFromFile($user_id);
    echo "Loaded User: " . $current_user->user_name . " (ID: $user_id)<br>";
} catch (Throwable $t) {
    echo "Failed to load User: " . $t->getMessage() . "<br>";
}

echo "<h2>6. List Query Generation Test</h2>";
echo "<h2>6. Class Inspection</h2>";
try {
    echo "Checking inheritance...<br>";
    echo "Parent Class: " . get_parent_class($focus) . "<br>";

    echo "Checking exists method 'getListQuery': ";
    if (method_exists($focus, 'getListQuery')) {
        echo "YES.<br>";
        echo "Generating List Query for $target_module... ";
        $query = $focus->getListQuery($target_module);
        echo "Done.<br>";
    } else {
        echo "<strong style='color:red'>NO (Method Missing)</strong><br>";
        echo "Dumping Module Methods (first 10):<br>";
        $methods = get_class_methods($focus);
        echo "<pre>" . print_r(array_slice($methods, 0, 10), true) . "</pre>";

        echo "Checking CRMEntity location...<br>";
        $ref = new ReflectionClass('CRMEntity');
        echo "Defined in: " . $ref->getFileName() . "<br>";
    }
} catch (Throwable $t) {
    echo "<br><strong style='color:red'>FAILED during inspection:</strong> " . $t->getMessage();
}

echo "<h2>7. Memory Usage</h2>";
echo "Peak Usage: " . (memory_get_peak_usage(true) / 1024 / 1024) . " MB<br>";

echo "<h3>SUCCESS: Module core seems intact.</h3>";
echo "If this script works but the UI doesn't, the issue is likely in the **Smarty Template** or **View Class** (modules/$target_module/views/List.php).";
?>
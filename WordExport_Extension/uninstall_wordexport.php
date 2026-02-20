<?php
chdir(__DIR__);
include_once 'vtlib/Vtiger/Module.php';
include_once 'includes/main/WebUI.php';

$moduleName = 'WordExport';
$module = Vtiger_Module::getInstance($moduleName);

if ($module) {
    echo "Found module: $moduleName. Uninstalling...\n";
    $module->delete();
    echo "Module $moduleName has been completely removed.\n";
    echo "You can now install the new version.\n";
} else {
    echo "Module $moduleName not found. It might have been already removed.\n";
}
?>
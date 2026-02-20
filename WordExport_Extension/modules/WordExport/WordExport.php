<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class WordExport
{

    /**
     * Invoked when special actions are performed on the module.
     * @param String Module name
     * @param String Event Type
     */
    function vtlib_handler($moduleName, $eventType)
    {
        $moduleInstance = Vtiger_Module::getInstance($moduleName);

        if ($eventType == 'module.postinstall') {
            // 1. Create Templates Table
            $db = PearDatabase::getInstance();
            $db->pquery("CREATE TABLE IF NOT EXISTS vtiger_wordexport_templates (
                template_id INT(11) NOT NULL AUTO_INCREMENT,
                module_name VARCHAR(50) NOT NULL,
                filename VARCHAR(255) NOT NULL,
                description TEXT,
                createdtime DATETIME,
                PRIMARY KEY (template_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

            // 1.1 Register Default Templates
            $defaultTemplates = [
                ['Quotes', 'Quote_Template.html', 'Default Quote Template (HTML)'],
                ['SalesOrder', 'SalesOrder_Template.html', 'Default Sales Order Template (HTML)']
            ];

            // Ensure storage directory exists
            $storageDir = 'test/wordexport/';
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0777, true);
            }

            foreach ($defaultTemplates as $tpl) {
                $filename = $tpl[1];
                $description = $tpl[2];
                $module = $tpl[0];

                // 1. Insert into DB if not exists
                $check = $db->pquery("SELECT template_id FROM vtiger_wordexport_templates WHERE filename=?", array($filename));
                if ($db->num_rows($check) == 0) {
                    $db->pquery(
                        "INSERT INTO vtiger_wordexport_templates (module_name, filename, description, createdtime) VALUES (?, ?, ?, NOW())",
                        array($module, $filename, $description)
                    );
                }

                // 2. Copy file to storage
                $sourcePath = 'modules/WordExport/templates/' . $filename;
                $destPath = $storageDir . $filename;

                if (file_exists($sourcePath) && !file_exists($destPath)) {
                    copy($sourcePath, $destPath);
                }
            }

            // 2. Register Export Actions in Quotes, SalesOrder, Invoice
            $this->addLinks();

            // 3. Set Default Profile Access
            $moduleInstance->initWebservice();

            // Explicitly enable for Admin Profile (Profile ID 1) to avoid Tools permission denied
            $db = PearDatabase::getInstance();
            $tab_id = $moduleInstance->id;

            // Allow access to module
            $check = $db->pquery("SELECT * FROM vtiger_profile2tab WHERE profileid=1 AND tabid=?", array($tab_id));
            if ($db->num_rows($check) == 0) {
                $db->pquery("INSERT INTO vtiger_profile2tab (profileid, tabid, permissions) VALUES(1, ?, 0)", array($tab_id));
            } else {
                $db->pquery("UPDATE vtiger_profile2tab SET permissions=0 WHERE profileid=1 AND tabid=?", array($tab_id));
            }

            // This is a safer way to initialize basic permissions in Vtiger 7
            require_once('modules/Users/Users.php');
            $user = new Users();
            $user->getActiveAdminUser();
            // In standard Vtiger, custom modules are accessible to Admin by default if installed correctly.
            // If it says permission denied, it's often because the module isn't marked as 'active' (presence=0) or lacks a default view.

        } else if ($eventType == 'module.disabled') {
            // ...
        } else if ($eventType == 'module.enabled') {
            // ...
        } else if ($eventType == 'module.preuninstall') {
            // ...
        } else if ($eventType == 'module.preupdate') {
            // ...
        } else if ($eventType == 'module.postupdate') {
            // ...
        }
    }

    /**
     * Add Link to Quotes Detail View
     */
    function addLinks()
    {
        $targetModules = ['Quotes', 'SalesOrder', 'Invoice', 'PurchaseOrder'];

        foreach ($targetModules as $moduleName) {
            $moduleInstance = Vtiger_Module::getInstance($moduleName);
            if ($moduleInstance) {
                $moduleInstance->deleteLink('HEADERSCRIPT', 'WordExportJS'); // Prevent Duplicates
                $moduleInstance->addLink('HEADERSCRIPT', 'WordExportJS', 'layouts/v7/modules/WordExport/resources/WordExportInjection.js');
            }
        }
    }
}

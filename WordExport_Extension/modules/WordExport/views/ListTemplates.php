<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class WordExport_ListTemplates_View extends Vtiger_Index_View
{

    public function process(Vtiger_Request $request)
    {
        $viewer = $this->getViewer($request);
        $moduleName = $request->getModule();
        $db = PearDatabase::getInstance();

        // Query Database
        $result = $db->pquery("SELECT * FROM vtiger_wordexport_templates ORDER BY module_name, filename", []);
        $num_rows = $db->num_rows($result);

        $templates = [];
        $rootDir = vglobal('root_directory');
        $templatesDir = $rootDir . 'modules/WordExport/templates/';

        for ($i = 0; $i < $num_rows; $i++) {
            $row = $db->query_result_rowdata($result, $i);
            $filePath = $templatesDir . $row['filename'];

            $size = 'N/A';
            if (file_exists($filePath)) {
                $size = round(filesize($filePath) / 1024, 2) . ' KB';
            }

            $templates[] = [
                'id' => $row['template_id'],
                'module_name' => $row['module_name'],
                'filename' => $row['filename'],
                'description' => $row['description'],
                'size' => $size,
                'createdtime' => $row['createdtime']
            ];
        }

        $viewer->assign('MODULE', $moduleName);
        $viewer->assign('TEMPLATES', $templates);
        // List of supported modules for Upload Dropdown
        $viewer->assign('SUPPORTED_MODULES', ['Quotes', 'SalesOrder', 'Invoice', 'PurchaseOrder']);

        $viewer->view('ListTemplates.tpl', $moduleName);
    }
}

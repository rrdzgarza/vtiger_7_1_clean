<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class WordExport_Popup_View extends Vtiger_Popup_View
{

    public function process(Vtiger_Request $request)
    {
        $viewer = $this->getViewer($request);
        $moduleName = $request->getModule();
        $sourceModule = $request->get('source_module'); // Quotes, SalesOrder, etc.

        $recordId = $request->get('record');
        $rootDir = vglobal('root_directory');
        $templatesDir = $rootDir . 'modules/WordExport/templates/';

        // Query Database for templates matching this module
        $db = PearDatabase::getInstance();
        $query = "SELECT filename, template_id FROM vtiger_wordexport_templates WHERE module_name = ?";
        $result = $db->pquery($query, array($sourceModule));
        $num_rows = $db->num_rows($result);

        $templates = [];
        for ($i = 0; $i < $num_rows; $i++) {
            $filename = $db->query_result($result, $i, 'filename');
            $id = $db->query_result($result, $i, 'template_id'); // We might use ID later, staying with filename for now

            if (file_exists($templatesDir . $filename)) {
                $templates[] = $filename;
            }
        }

        $viewer->assign('MODULE', $moduleName);
        $viewer->assign('RECORD', $recordId);
        $viewer->assign('SOURCE_MODULE', $sourceModule);
        $viewer->assign('TEMPLATES', $templates);

        echo $viewer->view('Popup.tpl', $moduleName, true);
    }
}

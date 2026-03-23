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

        // Generate default filename
        $defaultFileName = '';
        if ($recordId && $sourceModule) {
            try {
                $recordModel = Vtiger_Record_Model::getInstanceById($recordId, $sourceModule);
                $acctName = '';
                $acctId = $recordModel->get('account_id');
                if ($acctId) {
                    try {
                        $acctModel = Vtiger_Record_Model::getInstanceById($acctId, 'Accounts');
                        $acctName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $acctModel->get('accountname') ?? '');
                    } catch (Exception $e) {}
                }

                if ($sourceModule === 'Quotes') {
                    $rev = $recordModel->get('cf_996');
                    $revPart = (!empty($rev)) ? '_' . $rev : '';
                    $defaultFileName = $recordModel->get('quote_no') . $revPart . '_' . $acctName;
                } elseif ($sourceModule === 'SalesOrder') {
                    $soStatus = preg_replace('/[^A-Za-z0-9_\-]/', '_', $recordModel->getDisplayValue('sostatus') ?? '');
                    $marca = '';
                    $potId = $recordModel->get('potential_id');
                    if ($potId) {
                        try {
                            $potModel = Vtiger_Record_Model::getInstanceById($potId, 'Potentials');
                            $marca = preg_replace('/[^A-Za-z0-9_\-]/', '_', $potModel->get('cf_984') ?? '');
                        } catch (Exception $e) {}
                    }
                    $marcaPart = (!empty($marca)) ? '_' . $marca : '';
                    $defaultFileName = '01_' . $recordModel->get('salesorder_no') . '_' . $acctName . $marcaPart . '_' . $soStatus;
                } elseif ($sourceModule === 'Invoice') {
                    $defaultFileName = $recordModel->get('invoice_no') . '_' . $acctName;
                } elseif ($sourceModule === 'PurchaseOrder') {
                    $defaultFileName = $recordModel->get('purchaseorder_no') . '_' . $acctName;
                }
            } catch (Exception $e) {}
        }

        $viewer->assign('MODULE', $moduleName);
        $viewer->assign('RECORD', $recordId);
        $viewer->assign('SOURCE_MODULE', $sourceModule);
        $viewer->assign('TEMPLATES', $templates);
        $viewer->assign('DEFAULT_FILENAME', $defaultFileName);

        echo $viewer->view('Popup.tpl', $moduleName, true);
    }
}

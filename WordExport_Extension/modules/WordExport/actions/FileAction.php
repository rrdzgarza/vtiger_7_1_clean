<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class WordExport_FileAction_Action extends Vtiger_Action_Controller
{

    public function checkPermission(Vtiger_Request $request)
    {
        return true;
    }

    public function process(Vtiger_Request $request)
    {
        $mode = $request->get('mode');

        if ($mode === 'upload') {
            $this->uploadFile($request);
        } else if ($mode === 'delete') {
            $this->deleteFile($request);
        } else if ($mode === 'download') {
            $this->downloadFile($request);
            return; // download sends headers, no redirect
        }

        // Redirect back to List
        $moduleName = $request->getModule();
        header("Location: index.php?module=$moduleName&view=ListTemplates");
    }

    private function uploadFile(Vtiger_Request $request)
    {
        if (isset($_FILES['template_file'])) {
            $file = $_FILES['template_file'];
            $targetModule = $request->get('target_module'); // Quotes, SalesOrder, etc.

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if ($ext !== 'docx' && $ext !== 'html') {
                return;
            }

            // Sanitized Filename
            $cleanName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($file['name']));

            $targetDir = vglobal('root_directory') . 'modules/WordExport/templates/';
            $targetFile = $targetDir . $cleanName;

            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                // Insert into DB
                $db = PearDatabase::getInstance();
                $db->pquery(
                    "INSERT INTO vtiger_wordexport_templates (module_name, filename, createdtime) VALUES (?, ?, NOW())",
                    array($targetModule, $cleanName)
                );
            }
        }
    }

    private function downloadFile(Vtiger_Request $request)
    {
        $id = $request->get('id');
        if ($id) {
            $db = PearDatabase::getInstance();
            $result = $db->pquery("SELECT filename FROM vtiger_wordexport_templates WHERE template_id = ?", array($id));
            if ($db->num_rows($result)) {
                $filename = $db->query_result($result, 0, 'filename');
                $filePath = vglobal('root_directory') . 'modules/WordExport/templates/' . $filename;

                if (file_exists($filePath)) {
                    if (ob_get_length()) ob_clean();
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Content-Length: ' . filesize($filePath));
                    readfile($filePath);
                    exit;
                }
            }
        }
    }

    private function deleteFile(Vtiger_Request $request)
    {
        $id = $request->get('id');
        if ($id) {
            $db = PearDatabase::getInstance();

            // Get Filename first
            $result = $db->pquery("SELECT filename FROM vtiger_wordexport_templates WHERE template_id = ?", array($id));
            if ($db->num_rows($result)) {
                $filename = $db->query_result($result, 0, 'filename');
                $targetFile = vglobal('root_directory') . 'modules/WordExport/templates/' . $filename;

                // Delete from DB
                $db->pquery("DELETE FROM vtiger_wordexport_templates WHERE template_id = ?", array($id));

                // Delete File
                if (file_exists($targetFile)) {
                    unlink($targetFile);
                }
            }
        }
    }
}

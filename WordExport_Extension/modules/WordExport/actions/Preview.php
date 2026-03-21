<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class WordExport_Preview_Action extends Vtiger_Action_Controller
{
    public function checkPermission(Vtiger_Request $request)
    {
        return true;
    }

    public function process(Vtiger_Request $request)
    {
        // Forward to Export action with preview=1 (inline PDF, no download)
        $params = http_build_query([
            'module'        => 'WordExport',
            'action'        => 'Export',
            'record'        => $request->get('record'),
            'source_module' => $request->get('source_module'),
            'template'      => $request->get('template'),
            'format'        => 'pdf',
            'preview'       => '1',
        ]);
        header("Location: index.php?" . $params);
        exit;
    }
}

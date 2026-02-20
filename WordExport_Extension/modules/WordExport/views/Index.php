<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

// In Vtiger, if no view is specified, it defaults to 'Index'.
// We want the main screen of this module to be the ListTemplates view.
require_once 'modules/WordExport/views/ListTemplates.php';

class WordExport_Index_View extends WordExport_ListTemplates_View
{
    // Inherit the process() method so that going to the module's root
    // automatically loads the template manager.
}

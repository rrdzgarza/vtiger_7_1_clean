<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
$moduleVendor = realpath(__DIR__ . '/../vendor/autoload.php');
class WordExport_Export_Action extends Vtiger_Action_Controller
{

    public function checkPermission(Vtiger_Request $request)
    {
        return true;
    }

    public function process(Vtiger_Request $request)
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);

        try {
            $recordId = $request->get('record');
            $module = $request->get('source_module'); // Quotes, SalesOrder, Invoice, PurchaseOrder
            $templateName = $request->get('template');
            $format = $request->get('format');
            $saveToDocs = $request->get('save_to_docs');

            if (empty($recordId) || empty($templateName)) {
                echo "Missing record or template";
                return;
            }

            // 1. Load Dependencies
            $moduleVendor = realpath(__DIR__ . '/../vendor/autoload.php');
            $rootVendor = realpath(__DIR__ . '/../../../vendor/autoload.php');

            // It is crucial to load both if they exist, so we have access to local PhpWord AND global mPDF
            if ($rootVendor && file_exists($rootVendor)) {
                require_once $rootVendor;
            }
            if ($moduleVendor && file_exists($moduleVendor)) {
                require_once $moduleVendor;
            }

            if (!$rootVendor && !$moduleVendor) {
                die("Composer dependencies not found. Please ensure vendors are installed.");
            }

            // 2. Load Record Data
            $recordModel = Vtiger_Record_Model::getInstanceById($recordId, $module);
            $data = $recordModel->getData();

            // Release session lock so other requests are not blocked during PDF generation
            session_write_close();

            // 3. Prepare Template Path
            $templatePath = __DIR__ . '/../templates/' . basename($templateName);
            if (!file_exists($templatePath)) {
                die("Template file not found: " . $templatePath);
            }

            $ext = pathinfo($templateName, PATHINFO_EXTENSION);
            $tempFileName = tempnam(sys_get_temp_dir(), 'WordExport');

            // ROUTING: Word vs HTML
            if ($ext === 'docx') {
                $this->processWordTemplate($templatePath, $data, $recordModel, $tempFileName);

                if ($format === 'pdf') {
                    try {
                        $tempFileName = $this->convertWordToPdf($tempFileName);
                        $ext = 'pdf';
                        $contentType = 'application/pdf';
                    } catch (Exception $e) {
                        echo "Error generating PDF: " . $e->getMessage();
                        return;
                    }
                } else {
                    $ext = 'docx';
                    $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                }

            } elseif ($ext === 'html') {
                // HTML Template (PDF Maker Style)
                $htmlContent = file_get_contents($templatePath);
                $processedHtml = $this->processHtmlTemplate($htmlContent, $recordModel, $data, $module);

                try {
                    if (!class_exists('\Mpdf\Mpdf')) {
                        throw new Exception("Global mPDF library not found. Please run 'composer require mpdf/mpdf' in Vtiger root.");
                    }

                    $mpdfTempCache = sys_get_temp_dir() . '/mpdf_exportCache';
                    if (!is_dir($mpdfTempCache)) {
                        mkdir($mpdfTempCache, 0777, true);
                    }

                    $mpdf = new \Mpdf\Mpdf([
                        'mode' => 'utf-8',
                        'format' => 'Letter',
                        'tempDir' => $mpdfTempCache,
                    ]);
                    $mpdf->WriteHTML($processedHtml);
                    $mpdf->Output($tempFileName, \Mpdf\Output\Destination::FILE);

                    $ext = 'pdf';
                    $contentType = 'application/pdf';

                } catch (\Throwable $e) {
                    die("Error generating PDF with mPDF: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
                }
            }

            // Use custom filename from popup if provided, otherwise generate default
            $customFilename = $request->get('custom_filename');
            if (!empty($customFilename)) {
                $finalFileName = preg_replace('/[^A-Za-z0-9_\-\. ]/', '_', $customFilename) . '.' . $ext;
            } else {
                $acctName = '';
                $acctId = $recordModel->get('account_id');
                if ($acctId) {
                    try {
                        $acctModel = Vtiger_Record_Model::getInstanceById($acctId, 'Accounts');
                        $acctName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $acctModel->get('accountname') ?? '');
                    } catch (Exception $e) {}
                }

                $finalFileName = $module . '_' . $acctName . '.' . $ext;

                if ($recordModel->get('quote_no')) {
                    $rev = $recordModel->get('cf_996');
                    $revPart = (!empty($rev)) ? '_' . $rev : '';
                    $finalFileName = $recordModel->get('quote_no') . $revPart . '_' . $acctName . '.' . $ext;
                }
                if ($recordModel->get('salesorder_no')) {
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
                    $finalFileName = '01_' . $recordModel->get('salesorder_no') . '_' . $acctName . $marcaPart . '_' . $soStatus . '.' . $ext;
                }
                if ($recordModel->get('invoice_no'))
                    $finalFileName = $recordModel->get('invoice_no') . '_' . $acctName . '.' . $ext;
                if ($recordModel->get('purchaseorder_no'))
                    $finalFileName = $recordModel->get('purchaseorder_no') . '_' . $acctName . '.' . $ext;
            }

            // Save to Documents if requested
            if ($saveToDocs) {
                $this->saveToDocuments($recordModel, $tempFileName, $finalFileName);
            }

            // Output file (inline for preview, attachment for download)
            $isPreview = ($request->get('preview') === '1');
            $disposition = $isPreview ? 'inline' : 'attachment';

            if (ob_get_length()) {
                ob_clean();
            }
            header("Content-Type: " . $contentType);
            header("Content-Disposition: " . $disposition . "; filename=\"" . $finalFileName . "\"");
            header("Content-Transfer-Encoding: binary");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");
            header("Content-Length: " . filesize($tempFileName));
            readfile($tempFileName);
            unlink($tempFileName);
            exit;

        } catch (\Throwable $e) {
            die("<h2 style='color:red;'>WordExport Fatal Error:</h2><pre>" . htmlspecialchars((string) $e) . "</pre>");
        }
    }

    // --- WORD Helper ---
    private function processWordTemplate($templatePath, $data, $recordModel, $outputPath)
    {
        $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $templateProcessor->setValue($key, $value);
            }
        }
        $this->processInventoryLinesWord($recordModel, $templateProcessor);
        $templateProcessor->saveAs($outputPath);
    }

    private function processInventoryLinesWord($recordModel, $processor)
    {
        $lineItems = $this->getInventoryItems($recordModel);
        if (count($lineItems) > 0) {
            $processor->cloneRowAndSetValues('product_name', $lineItems);
        }
    }

    // --- HTML Helper ---
    private function processHtmlTemplate($html, $recordModel, $data, $moduleName)
    {
        // 0. Pre-processing: Merge Header/Footer if separate? (User provided them as blocks, assuming file is already merged or we handle structure here)
        // For now, we assume the input $html is the full content.

        // 0. Static Images — $IMG_filename$ → base64 embedded img tag
        // Images stored in modules/WordExport/images/
        // Tag example: $IMG_Logo_AAS$ → loads Logo_AAS.png (or .jpg, .gif, .svg)
        $imagesDir = __DIR__ . '/../images/';
        // Tag format: $IMG_filename$  or  $IMG_filename|width$  or  $IMG_filename|width|height$
        // Examples:  $IMG_Logo_AAS$   /  $IMG_Logo_AAS|40mm$   /  $IMG_Logo_AAS|40mm|25mm$
        $html = preg_replace_callback('/\$IMG_([A-Za-z0-9_\-]+)(?:\|([^|$]*))?(?:\|([^$]*))?\$/', function($matches) use ($imagesDir) {
            $name   = $matches[1];
            $width  = !empty($matches[2]) ? $matches[2] : null;
            $height = !empty($matches[3]) ? $matches[3] : null;

            foreach (['png', 'jpg', 'jpeg', 'gif', 'svg'] as $ext) {
                $found = glob($imagesDir . '*.' . $ext);
                foreach ($found as $file) {
                    if (strcasecmp(pathinfo($file, PATHINFO_FILENAME), $name) === 0) {
                        $mime = mime_content_type($file);
                        $data = base64_encode(file_get_contents($file));
                        $attrs = '';
                        if ($width)  $attrs .= ' width="'  . htmlspecialchars($width)  . '"';
                        if ($height) $attrs .= ' height="' . htmlspecialchars($height) . '"';
                        return '<img src="data:' . $mime . ';base64,' . $data . '"' . $attrs . '>';
                    }
                }
            }
            return '';
        }, $html);

        // 1. Company Logo & Organization Details
        $orgDetails = $this->getOrganizationDetails();
        $logoImg = $this->getCompanyLogo();

        $html = str_replace('$COMPANY_LOGO$', $logoImg, $html);

        $html = str_replace('$COMPANY_NAME$',    $orgDetails['organizationname'] ?? '', $html);
        $html = str_replace('$COMPANY_ADDRESS$', $orgDetails['address'] ?? '', $html);
        $html = str_replace('$COMPANY_CITY$',    $orgDetails['city'] ?? '', $html);
        $html = str_replace('$COMPANY_STATE$',   $orgDetails['state']  ?? '', $html);
        $html = str_replace('$COMPANY_ZIP$',     $orgDetails['code']   ?? '', $html);
        $html = str_replace('$COMPANY_CODE$',    $orgDetails['code']   ?? '', $html);
        $html = str_replace('$COMPANY_VATID$',   $orgDetails['vatid']  ?? '', $html);
        $html = str_replace('$COMPANY_PHONE$',   $orgDetails['phone']  ?? '', $html);
        $html = str_replace('$COMPANY_WEBSITE$', $orgDetails['website'] ?? '', $html);
        $html = str_replace('$COMPANY_COUNTRY$', $orgDetails['country'] ?? '', $html);

        // 2. Current User Information
        $userModel = Vtiger_Record_Model::getInstanceById($recordModel->get('assigned_user_id'), 'Users');
        $uFirstName = $userModel->get('first_name') ?? '';
        $uLastName  = $userModel->get('last_name') ?? '';
        $uEmail     = $userModel->get('email1') ?? '';
        $html = str_replace('$USERS_FIRST_NAME$', $uFirstName, $html);
        $html = str_replace('$USERS_LAST_NAME$',  $uLastName, $html);
        $html = str_replace('$USERS_EMAIL1$',      $uEmail, $html);
        // $R_USERS_*$ = current logged-in user (who generates the PDF)
        $currentUser = Users_Record_Model::getCurrentUserModel();
        $html = str_replace('$R_USERS_FIRST_NAME$', $currentUser->get('first_name') ?? '', $html);
        $html = str_replace('$R_USERS_LAST_NAME$',  $currentUser->get('last_name') ?? '', $html);
        $html = str_replace('$R_USERS_EMAIL1$',     $currentUser->get('email1') ?? '', $html);

        // 3. Translation Labels %KEY%
        // Replace known labels FIRST with direct str_replace (most reliable)
        $knownLabels = array(
            '%G_Description%'     => 'Descripción',
            '%G_List Price%'      => 'Precio Unitario',
            '%G_Total%'           => 'Total',
            '%G_Subtotal%'        => 'Subtotal',
            '%G_LBL_DISCOUNT%'    => 'Descuentos',
            '%G_Tax%'             => 'Impuesto',
            '%G_LBL_GRAND_TOTAL%' => 'TOTAL',
            '%M_Quote No%'        => 'Cotización',
        );
        $html = str_replace(array_keys($knownLabels), array_values($knownLabels), $html);

        // Then use preg_replace_callback for any remaining unknown %LABEL% tags
        $labelTranslations = array(
            'Description' => 'Descripción',
            'List Price' => 'Precio Unitario',
            'Total' => 'Total',
            'Subtotal' => 'Subtotal',
            'LBL_DISCOUNT' => 'Descuentos',
            'Tax' => 'Impuesto',
            'LBL_GRAND_TOTAL' => 'Total a Pagar'
        );

        $html = preg_replace_callback('/%([A-Za-z0-9_ ]+)%/', function ($matches) use ($moduleName, $labelTranslations) {
            $key = trim($matches[1]);
            $transModule = $moduleName;
            $isGlobalLabel = false;

            // Handle PDF Maker specific prefixes
            $prefix = substr($key, 0, 2);
            if ($prefix === 'M_') {
                $key = substr($key, 2);           // Module specific
            } elseif ($prefix === 'G_') {
                $key = substr($key, 2);           // Global dictionary
                $transModule = 'Vtiger';
                $isGlobalLabel = true;
            } elseif ($prefix === 'R_') {
                $transModule = 'Vtiger';
            }

            // For global labels, use fallback dictionary first
            if ($isGlobalLabel && isset($labelTranslations[$key])) {
                return $labelTranslations[$key];
            }

            // Handle %MODULENAME_FIELDNAME% → resolve to field label via DB query
            // e.g., %SALESORDER_CF_1175% → label of cf_1175 in SalesOrder module
            $moduleMap = array(
                'SALESORDER'    => 'SalesOrder',
                'QUOTES'        => 'Quotes',
                'INVOICE'       => 'Invoice',
                'PURCHASEORDER' => 'PurchaseOrder',
            );
            foreach ($moduleMap as $modPrefix => $modName) {
                if (strpos($key, $modPrefix . '_') === 0) {
                    $fieldName = strtolower(substr($key, strlen($modPrefix) + 1));
                    try {
                        $db = PearDatabase::getInstance();
                        $res = $db->pquery(
                            "SELECT fieldlabel FROM vtiger_field WHERE columnname = ? AND tabid = (SELECT tabid FROM vtiger_tab WHERE name = ?)",
                            [$fieldName, $modName]
                        );
                        if ($db->num_rows($res) > 0) {
                            return $db->query_result($res, 0, 'fieldlabel');
                        }
                    } catch (\Exception $e) {}
                    return $fieldName;
                }
            }

            // Also handle %R_MODULEID_FIELDNAME% for related field labels
            if (strpos($key, 'R_') === 0) {
                $relParts = explode('_', substr($key, 2), 2);
                if (count($relParts) === 2) {
                    $relIdField = strtolower($relParts[0]);
                    $relFieldName = strtolower($relParts[1]);
                    $relModuleMap = array(
                        'accountid'   => 'Accounts',
                        'contactid'   => 'Contacts',
                        'potentialid' => 'Potentials',
                        'quoteid'     => 'Quotes',
                    );
                    $relModule = $relModuleMap[$relIdField] ?? null;
                    if ($relModule) {
                        try {
                            $db = PearDatabase::getInstance();
                            $res = $db->pquery(
                                "SELECT fieldlabel FROM vtiger_field WHERE columnname = ? AND tabid = (SELECT tabid FROM vtiger_tab WHERE name = ?)",
                                [$relFieldName, $relModule]
                            );
                            if ($db->num_rows($res) > 0) {
                                return $db->query_result($res, 0, 'fieldlabel');
                            }
                        } catch (\Exception $e) {}
                    }
                }
            }

            // Otherwise try to translate
            return vtranslate($key, $transModule);
        }, $html);

        // 4. Record Fields (Direct & Generic) — includes custom fields (cf_*)

        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) continue;

            // Use getDisplayValue() for proper formatting (checkboxes→Sí/No, picklists→label, etc.)
            $displayValue = '';
            try {
                $dv = $recordModel->getDisplayValue($key);
                if ($dv !== false && $dv !== null) {
                    $displayValue = (string)$dv;
                }
            } catch (\Throwable $e) {}

            // Fallback to raw value if getDisplayValue returned empty but raw has data
            if ($displayValue === '' && $value !== null && $value !== '') {
                $displayValue = (string)$value;
            }

            // For long text fields, convert newlines to <br /> for mPDF
            if (in_array(strtolower($key), ['terms_conditions', 'notes', 'description', 'comment'])) {
                $displayValue = nl2br($displayValue);
            }

            // Direct: $CF_996$
            $html = str_replace('$' . strtoupper($key) . '$', $displayValue, $html);
            // Module-prefixed: $QUOTES_CF_996$
            $html = str_replace('$' . strtoupper($moduleName) . '_' . strtoupper($key) . '$', $displayValue, $html);
        }

        // 5. Related Fields — resolve ALL fields dynamically from related records
        // Contact
        $contactId = $recordModel->get('contact_id');
        if ($contactId) {
            try {
                $contactModel = Vtiger_Record_Model::getInstanceById($contactId, 'Contacts');
                $contactData = $contactModel->getData();
                foreach ($contactData as $cKey => $cVal) {
                    if (is_array($cVal) || is_object($cVal)) continue;
                    $html = str_replace('$R_CONTACTID_' . strtoupper($cKey) . '$', ($cVal === null) ? '' : (string)$cVal, $html);
                }
            } catch (Exception $e) {}
        }

        // Account
        $accountId = $recordModel->get('account_id');
        if ($accountId) {
            try {
                $accountModel = Vtiger_Record_Model::getInstanceById($accountId, 'Accounts');
                $accountData = $accountModel->getData();
                $accountName = $accountModel->get('accountname') ?? '';
                foreach ($accountData as $aKey => $aVal) {
                    if (is_array($aVal) || is_object($aVal)) continue;
                    $html = str_replace('$R_ACCOUNTID_' . strtoupper($aKey) . '$', ($aVal === null) ? '' : (string)$aVal, $html);
                }
                // Aliases for account name
                $html = str_replace('$QUOTES_ACCOUNT_NAME$', $accountName, $html);
                $html = str_replace('$SALESORDER_ACCOUNT_NAME$', $accountName, $html);
                $html = str_replace('$ACCOUNT_NAME$', $accountName, $html);
            } catch (Exception $e) {}
        }

        // Potential
        $potentialId = $recordModel->get('potential_id');
        if ($potentialId) {
            try {
                $potentialModel = Vtiger_Record_Model::getInstanceById($potentialId, 'Potentials');
                $potentialData = $potentialModel->getData();
                foreach ($potentialData as $pKey => $pVal) {
                    if (is_array($pVal) || is_object($pVal)) continue;
                    $html = str_replace('$R_POTENTIALID_' . strtoupper($pKey) . '$', ($pVal === null) ? '' : (string)$pVal, $html);
                }
            } catch (Exception $e) {}
        }

        // Quote (for SalesOrder referencing its parent quote)
        $quoteId = $recordModel->get('quote_id');
        if ($quoteId) {
            try {
                $quoteModel = Vtiger_Record_Model::getInstanceById($quoteId, 'Quotes');
                $quoteData = $quoteModel->getData();
                foreach ($quoteData as $qKey => $qVal) {
                    if (is_array($qVal) || is_object($qVal)) continue;
                    $html = str_replace('$R_QUOTEID_' . strtoupper($qKey) . '$', ($qVal === null) ? '' : (string)$qVal, $html);
                }
            } catch (Exception $e) {}
        }

        // Clear any remaining unresolved $R_* placeholders
        $html = preg_replace('/\$R_[A-Z0-9_]+\$/', '', $html);

        // 6. Inventory Block
        $pattern = '/#PRODUCTBLOC_START#(.*?)#PRODUCTBLOC_END#/s';
        if (preg_match($pattern, $html, $matches)) {
            $rowTemplate = $matches[1];
            $lineItems = $this->getInventoryItems($recordModel);
            $rowsHtml = '';

            $counter = 1;
            foreach ($lineItems as $item) {
                $row = $rowTemplate;
                $row = str_replace('$PRODUCTPOSITION$', $counter++, $row);
                $row = str_replace('$PRODUCTS_PRODUCTCODE$', $item['product_code'], $row);
                $row = str_replace('$PRODUCTTITLE$', $item['product_name'], $row);
                $row = str_replace('$PRODUCTQUANTITY$', $item['product_qty'], $row);
                $row = str_replace('$PRODUCTLISTPRICE$', $item['product_price'], $row);
                $row = str_replace('$PRODUCTSTOTALAFTERDISCOUNT$', $item['product_total'], $row);
                $row = str_replace('$PRODUCTEDITDESCRIPTION$', $item['comment'] ?? '', $row);
                $rowsHtml .= $row;
            }
            $html = preg_replace($pattern, $rowsHtml, $html);
        }

        // 7. Totals & Currencies
        $subTotal = floatval($data['hdnSubTotal'] ?? 0);
        $discount = floatval($data['hdnDiscountAmount'] ?? 0);
        $grandTotal = floatval($data['hdnGrandTotal'] ?? 0);
        $adjustment = floatval($data['txtAdjustment'] ?? 0);
        $shAmount = floatval($data['hdnS_H_Amount'] ?? 0);

        // Tax = grandTotal minus all other components
        $preTaxTotal = $subTotal - $discount;
        $taxAmount = $grandTotal - $preTaxTotal - $adjustment - $shAmount;
        if ($taxAmount < 0) $taxAmount = 0;

        // Calculate tax percentage based on pre-tax total
        $taxPercent = ($preTaxTotal > 0) ? number_format(($taxAmount / $preTaxTotal) * 100, 2) : '0.00';

        $html = str_replace('$TOTALWITHOUTVAT$', number_format($subTotal, 2), $html);
        $html = str_replace('$TOTAL$', number_format($grandTotal, 2), $html);
        $html = str_replace('$VAT$', number_format($taxAmount, 2), $html);
        $html = str_replace('$VATPERCENT$', $taxPercent, $html);
        $html = str_replace('$TOTALDISCOUNT$', number_format($discount, 2), $html);
        // Fetch currency from record's currency_id
        $currencySymbol = '$';
        $currencyName   = 'USD';
        $currencyId = $data['currency_id'] ?? null;
        if ($currencyId) {
            try {
                $db = PearDatabase::getInstance();
                $cRes = $db->pquery("SELECT currency_name, currency_symbol FROM vtiger_currency_info WHERE id = ?", array($currencyId));
                if ($db->num_rows($cRes) > 0) {
                    $currencyName   = $db->query_result($cRes, 0, 'currency_name')   ?: $currencyName;
                    $currencySymbol = $db->query_result($cRes, 0, 'currency_symbol') ?: $currencySymbol;
                }
            } catch (Exception $e) {}
        }
        $html = str_replace('$CURRENCYSYMBOL$', $currencySymbol, $html);
        $html = str_replace('$CURRENCYNAME$',   $currencyName,   $html);

        // 8. Final fallback: Replace any remaining %G_% labels that weren't translated
        $html = str_replace('%G_Description%', 'Descripción', $html);
        $html = str_replace('%G_List Price%', 'Precio Unitario', $html);
        $html = str_replace('%G_Total%', 'Total', $html);
        $html = str_replace('%G_Subtotal%', 'Subtotal', $html);
        $html = str_replace('%G_LBL_DISCOUNT%', 'Descuentos', $html);
        $html = str_replace('%G_Tax%', 'Impuesto', $html);
        $html = str_replace('%G_LBL_GRAND_TOTAL%', 'TOTAL', $html);

        return $html;
    }

    // --- SHARED Helper ---
    private function getInventoryItems($recordModel)
    {
        $db = PearDatabase::getInstance();
        $query = "SELECT * FROM vtiger_inventoryproductrel WHERE id = ?";
        $result = $db->pquery($query, array($recordModel->getId()));
        $num_rows = $db->num_rows($result);

        $lineItems = [];
        for ($i = 0; $i < $num_rows; $i++) {
            $row = $db->query_result_rowdata($result, $i);
            $row['product_name'] = getProductName($row['productid']);
            $row['product_qty']   = number_format(floatval($row['quantity']), 2);
            $row['product_price'] = number_format(floatval($row['listprice']), 2);
            $row['product_total'] = number_format(floatval($row['listprice']) * floatval($row['quantity']), 2);
            $row['comment'] = $row['comment'];

            // Fetch Product Code (PartNumber)
            $prodResult = $db->pquery("SELECT productcode FROM vtiger_products WHERE productid=?", array($row['productid']));
            if ($db->num_rows($prodResult)) {
                $row['product_code'] = $db->query_result($prodResult, 0, 'productcode');
            } else {
                // Try Service
                $servResult = $db->pquery("SELECT service_usageunit FROM vtiger_service WHERE serviceid=?", array($row['productid']));
                // Services might not have 'productcode' column directly same way, defaulting empty or usageunit
                $row['product_code'] = '-';
            }

            $lineItems[] = $row;
        }
        return $lineItems;
    }

    private function convertWordToPdf($wordFile)
    {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($wordFile);

        // Configure PDF Renderer
        $localVendor = realpath(__DIR__ . '/../vendor');
        $rootVendor = realpath(vglobal('root_directory') . '/vendor');

        $rendererLibraryPath = '';
        if ($localVendor && file_exists($localVendor . '/dompdf/dompdf')) {
            $rendererLibraryPath = $localVendor . '/dompdf/dompdf';
        } elseif ($rootVendor && file_exists($rootVendor . '/dompdf/dompdf')) {
            $rendererLibraryPath = $rootVendor . '/dompdf/dompdf';
        }

        if ($rendererLibraryPath) {
            \PhpOffice\PhpWord\Settings::setPdfRendererName(\PhpOffice\PhpWord\Settings::PDF_RENDERER_DOMPDF);
            \PhpOffice\PhpWord\Settings::setPdfRendererPath($rendererLibraryPath);
        } else {
            // Fallback to TCPDF
            if ($localVendor && file_exists($localVendor . '/tecnickcom/tcpdf')) {
                $rendererLibraryPath = $localVendor . '/tecnickcom/tcpdf';
            } elseif ($rootVendor && file_exists($rootVendor . '/tecnickcom/tcpdf')) {
                $rendererLibraryPath = $rootVendor . '/tecnickcom/tcpdf';
            }

            if ($rendererLibraryPath) {
                \PhpOffice\PhpWord\Settings::setPdfRendererName(\PhpOffice\PhpWord\Settings::PDF_RENDERER_TCPDF);
                \PhpOffice\PhpWord\Settings::setPdfRendererPath($rendererLibraryPath);
            } else {
                throw new Exception("PDF Renderer (DomPDF or TCPDF) not found in vendor.");
            }
        }

        $xmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'PDF');
        $pdfFile = sys_get_temp_dir() . '/' . uniqid() . '.pdf';
        $xmlWriter->save($pdfFile);
        return $pdfFile;
    }

    private function saveToDocuments($recordModel, $filePath, $fileName)
    {
        $db = PearDatabase::getInstance();
        $currentUser = Users_Record_Model::getCurrentUserModel();
        $rootDir = rtrim(vglobal('root_directory'), '/');

        // 1. Create Document record via Vtiger API
        $docModel = Vtiger_Record_Model::getCleanInstance('Documents');
        $docModel->set('notes_title', pathinfo($fileName, PATHINFO_FILENAME));
        $docModel->set('filename', $fileName);
        $docModel->set('filetype', mime_content_type($filePath));
        $docModel->set('filesize', filesize($filePath));
        $docModel->set('filelocationtype', 'I');
        $docModel->set('filestatus', 1);
        $docModel->set('filedownloadcount', 0);
        $docModel->set('folderid', 1);
        $docModel->set('assigned_user_id', $currentUser->getId());
        $docModel->save();

        $docId = $docModel->getId();
        if (!$docId) return;

        // 2. Create attachment crmentity
        $attachId = $db->getUniqueID('vtiger_crmentity');
        $userId = $currentUser->getId();
        $db->pquery(
            "INSERT INTO vtiger_crmentity (crmid, smcreatorid, smownerid, modifiedby, setype, description, createdtime, modifiedtime, presence, deleted) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 1, 0)",
            [$attachId, $userId, $userId, $userId, 'Documents Attachment', '']
        );

        // 3. Insert attachment record
        $db->pquery(
            "INSERT INTO vtiger_attachments (attachmentsid, name, description, type, path) VALUES (?, ?, ?, ?, ?)",
            [$attachId, $fileName, '', mime_content_type($filePath), 'storage/']
        );

        // 4. Copy file to Vtiger storage
        copy($filePath, $rootDir . '/storage/' . $attachId . '_' . $fileName);

        // 5. Link attachment to document
        $db->pquery("INSERT INTO vtiger_seattachmentsrel (crmid, attachmentsid) VALUES (?, ?)", [$docId, $attachId]);

        // 6. Link document to source record (Quote, SalesOrder, etc.)
        $db->pquery("INSERT INTO vtiger_senotesrel (crmid, notesid) VALUES (?, ?)", [$recordModel->getId(), $docId]);
    }

    private function getOrganizationDetails()
    {
        $db = PearDatabase::getInstance();

        // Try vtiger_organizationdetails (standard Vtiger table)
        try {
            $result = $db->pquery("SELECT * FROM vtiger_organizationdetails LIMIT 1", array());
            if ($db->num_rows($result) > 0) {
                $row = $db->query_result_rowdata($result, 0);
                    return $row;
            }
        } catch (Exception $e) {
            error_log('[WordExport] vtiger_organizationdetails error: ' . $e->getMessage());
        }

        // Fallback: try vtiger_companycheckout (some Vtiger versions)
        try {
            $result = $db->pquery("SELECT * FROM vtiger_companycheckout LIMIT 1", array());
            if ($db->num_rows($result) > 0) {
                $row = $db->query_result_rowdata($result, 0);
                error_log('[WordExport] CompanyCheckout: ' . json_encode($row));
                return $row;
            }
        } catch (Exception $e) {}

        return array();
    }

    private function getCompanyLogo()
    {
        $rootDir = rtrim(vglobal('root_directory'), '/');
        $db = PearDatabase::getInstance();
        $logoFile = null;

        // 1. Query DB for logo filename
        try {
            $result = $db->pquery("SELECT logoname FROM vtiger_organizationdetails LIMIT 1", array());
            if ($db->num_rows($result) > 0) {
                $logoname = $db->query_result($result, 0, 'logoname');
                if ($logoname && $logoname !== '') {
                    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
                    $possiblePaths = array(
                        $rootDir  . '/test/logo/'          . $logoname,
                        $rootDir  . '/test/upload/images/' . $logoname,
                        $rootDir  . '/test/upload/'        . $logoname,
                        $rootDir  . '/storage/logo/'       . $logoname,
                        $rootDir  . '/storage/'            . $logoname,
                        $rootDir  . '/uploads/logos/'      . $logoname,
                        $docRoot  . '/test/logo/'          . $logoname,
                        $docRoot  . '/test/upload/images/' . $logoname,
                        '/var/www/html/test/logo/'         . $logoname,
                        '/var/www/html/test/upload/images/'. $logoname,
                    );
                    foreach ($possiblePaths as $path) {
                        if (file_exists($path)) {
                            $logoFile = $path;
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('[WordExport] Logo DB exception: ' . $e->getMessage());
        }

        // 2. If not found by name, try generic filenames
        if (!$logoFile) {
            $genericPaths = array(
                $rootDir . '/test/upload/images/companylogo.png',
                $rootDir . '/test/upload/images/companylogo.jpg',
                $rootDir . '/test/upload/images/companylogo.gif',
                $rootDir . '/test/logo/companylogo.png',
                $rootDir . '/test/logo/companylogo.jpg',
                $rootDir . '/test/logo/companylogo.gif',
            );
            foreach ($genericPaths as $path) {
                if (file_exists($path)) {
                    $logoFile = $path;
                    break;
                }
            }
        }

        // 3. Return base64-embedded img tag (mPDF compatible) or empty string
        if ($logoFile) {
            $mime = mime_content_type($logoFile);
            $data = base64_encode(file_get_contents($logoFile));
            return '<img src="data:' . $mime . ';base64,' . $data . '">';
        }

        return '';
    }
}

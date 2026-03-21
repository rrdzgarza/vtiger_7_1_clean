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

            $finalFileName = $module . "_" . $recordModel->get('no') . "." . $ext;
            if ($recordModel->get('quote_no'))
                $finalFileName = "Quote_" . $recordModel->get('quote_no') . "." . $ext;
            if ($recordModel->get('salesorder_no'))
                $finalFileName = "SalesOrder_" . $recordModel->get('salesorder_no') . "." . $ext;
            if ($recordModel->get('invoice_no'))
                $finalFileName = "Invoice_" . $recordModel->get('invoice_no') . "." . $ext;
            if ($recordModel->get('purchaseorder_no'))
                $finalFileName = "PO_" . $recordModel->get('purchaseorder_no') . "." . $ext;

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
        $html = str_replace('$USERS_FIRST_NAME$', $userModel->get('first_name'), $html);
        $html = str_replace('$USERS_LAST_NAME$', $userModel->get('last_name'), $html);
        $html = str_replace('$USERS_EMAIL1$', $userModel->get('email1'), $html);

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

        $html = preg_replace_callback('/%([^%]+)%/', function ($matches) use ($moduleName, $labelTranslations) {
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
                // Related field tag (e.g., R_ACCOUNTID_CF_856).
                // Since locating the precise related module dynamically is complex here,
                // falling back to Vtiger global dictionary is the safest approach for labels.
                $transModule = 'Vtiger';
            }

            // For global labels, use fallback dictionary first
            if ($isGlobalLabel && isset($labelTranslations[$key])) {
                return $labelTranslations[$key];
            }

            // Otherwise try to translate
            return vtranslate($key, $transModule);
        }, $html);

        // 4. Record Fields (Direct & Generic) — includes custom fields (cf_*)

        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) continue;

            $displayValue = ($value === null) ? '' : (string)$value;

            // For long text fields, convert newlines to <br /> for mPDF
            if (in_array(strtolower($key), ['terms_conditions', 'notes', 'description', 'comment'])) {
                $displayValue = nl2br($displayValue);
            }

            // Direct: $CF_996$
            $html = str_replace('$' . strtoupper($key) . '$', $displayValue, $html);
            // Module-prefixed: $QUOTES_CF_996$
            $html = str_replace('$' . strtoupper($moduleName) . '_' . strtoupper($key) . '$', $displayValue, $html);
        }

        // 5. Related Fields — resolve known fields FIRST, then clear any remaining $R_*
        $contactId = $recordModel->get('contact_id');
        if ($contactId) {
            try {
                $contactModel = Vtiger_Record_Model::getInstanceById($contactId, 'Contacts');
                $html = str_replace('$R_CONTACTID_FIRSTNAME$',    $contactModel->get('firstname') ?? '', $html);
                $html = str_replace('$R_CONTACTID_LASTNAME$',     $contactModel->get('lastname') ?? '', $html);
                $html = str_replace('$R_CONTACTID_SALUTATIONTYPE$', $contactModel->get('salutationtype') ?? '', $html);
                $html = str_replace('$R_CONTACTID_CF_982$',       $contactModel->get('cf_982') ?? '', $html);
            } catch (Exception $e) {}
        }

        $accountId = $recordModel->get('account_id');
        if ($accountId) {
            try {
                $accountModel = Vtiger_Record_Model::getInstanceById($accountId, 'Accounts');
                $accountName  = $accountModel->get('accountname') ?? '';
                $html = str_replace('$QUOTES_ACCOUNT_NAME$',   $accountName, $html);
                $html = str_replace('$ACCOUNT_NAME$',          $accountName, $html);
                $html = str_replace('$R_ACCOUNTID_ACCOUNTNAME$', $accountName, $html);
                $html = str_replace('$R_ACCOUNTID_CF_852$',    $accountModel->get('cf_852') ?? '', $html);
                $html = str_replace('$R_ACCOUNTID_INDUSTRY$',  $accountModel->get('industry') ?? '', $html);
            } catch (Exception $e) {}
        }

        $potentialId = $recordModel->get('potential_id');
        if ($potentialId) {
            try {
                $potentialModel = Vtiger_Record_Model::getInstanceById($potentialId, 'Potentials');
                $html = str_replace('$R_POTENTIALID_CF_984$', $potentialModel->get('cf_984') ?? '', $html);
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
        $taxAmount = floatval($data['hdnTaxType'] ?? 0);
        $discount = floatval($data['hdnDiscountAmount'] ?? 0);
        $grandTotal = floatval($data['hdnGrandTotal'] ?? 0);

        // Calculate tax percentage if subtotal exists
        $taxPercent = ($subTotal > 0) ? number_format(($taxAmount / $subTotal) * 100, 2) : '0.00';

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
        // Placeholder
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

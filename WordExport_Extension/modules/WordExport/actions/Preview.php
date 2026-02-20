<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
require_once 'vars.php';

class WordExport_Preview_Action extends Vtiger_Action_Controller
{

    public function checkPermission(Vtiger_Request $request)
    {
        return true;
    }

    public function process(Vtiger_Request $request)
    {
        $recordId = $request->get('record');
        $module = $request->get('source_module'); // Quotes, SalesOrder, Invoice, PurchaseOrder
        $templateName = $request->get('template');

        if (empty($recordId) || empty($templateName)) {
            echo "Missing record or template";
            return;
        }

        // 1. Load Dependencies
        $moduleVendor = __DIR__ . '/../vendor/autoload.php';
        $rootVendor = vglobal('root_directory') . '/vendor/autoload.php';

        if (file_exists($moduleVendor)) {
            require_once $moduleVendor;
        } else if (file_exists($rootVendor)) {
            require_once $rootVendor;
        } else {
            die("Composer dependencies not found. Please run 'composer install' inside the module directory.");
        }

        // 2. Load Record Data
        $recordModel = Vtiger_Record_Model::getInstanceById($recordId, $module);
        $data = $recordModel->getData();

        // 3. Prepare Template Path
        $templatePath = __DIR__ . '/../templates/' . basename($templateName);
        if (!file_exists($templatePath)) {
            die("Template file not found: " . $templatePath);
        }

        $ext = pathinfo($templateName, PATHINFO_EXTENSION);

        // ROUTING: Word vs HTML
        if ($ext === 'docx') {
            echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
            echo "<h2>Preview is not supported for Word (.docx) templates online.</h2>";
            echo "<p>Please use the <strong>Export</strong> button to download the file and view it in Microsoft Word.</p>";
            echo "</div>";
            return;

        } elseif ($ext === 'html') {
            // HTML Template (PDF Maker Style)
            $htmlContent = file_get_contents($templatePath);
            $processedHtml = $this->processHtmlTemplate($htmlContent, $recordModel, $data, $module);

            // Print the HTML string for preview
            echo $processedHtml;
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

        // 1. Company Information (Mocked or Fetched)
        // In a real Vtiger env, we would query vtiger_organizationdetails
        $html = str_replace('$COMPANY_LOGO$', '<img src="test/logo/vtiger-crm-logo.gif" height="60">', $html); // Placeholder
        $html = str_replace('$COMPANY_NAME$', 'My Company Name', $html);
        $html = str_replace('$COMPANY_ADDRESS$', '123 Business Rd', $html);
        $html = str_replace('$COMPANY_CITY$', 'City', $html);
        $html = str_replace('$COMPANY_STATE$', 'State', $html);
        $html = str_replace('$COMPANY_ZIP$', '00000', $html);

        // 2. Current User Information
        $userModel = Vtiger_Record_Model::getInstanceById($recordModel->get('assigned_user_id'), 'Users');
        $html = str_replace('$USERS_FIRST_NAME$', $userModel->get('first_name'), $html);
        $html = str_replace('$USERS_LAST_NAME$', $userModel->get('last_name'), $html);
        $html = str_replace('$USERS_EMAIL1$', $userModel->get('email1'), $html);

        // 3. Translation Labels %KEY%
        $html = preg_replace_callback('/%(\w+)%/', function ($matches) use ($moduleName) {
            return vtranslate($matches[1], $moduleName);
        }, $html);

        // 4. Record Fields (Direct & Generic)
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Direct: $SUBJECT$
                $html = str_replace('$' . strtoupper($key) . '$', $value, $html);
                // Module: $QUOTES_SUBJECT$
                $html = str_replace('$' . strtoupper($moduleName) . '_' . strtoupper($key) . '$', $value, $html);
            }
        }

        // 5. Related Fields $R_FIELD_SUBFIELD$
        // Regex to find tokens starting with $R_
        $html = preg_replace_callback('/\$R_(\w+)_(\w+)\$/', function ($matches) use ($recordModel) {
            $relFieldName = strtolower($matches[1]); // e.g., contactid
            $targetField = strtolower($matches[2]);  // e.g., firstname

            // Special handling for recursive lookup could go here.
            // Simplified: Use Vtiger display value if it matches the standard "reference" logic
            if ($recordModel->get($relFieldName)) {
                // This is expensive: Loading related record
                // Optimally we'd use getDisplayValue or a lighter lookup
                try {
                    $relId = $recordModel->get($relFieldName);
                    // Determine module? Vtiger API doesn't always tell us easily without metadata.
                    // Doing a "best guess" or using Vtiger_Record_Model::getInstanceById if we knew the module.
                    // For now, simpler approach: if it is a standard field, try to fetch it.
                    // A better approach in Vtiger 7 is uitype 10 handling.

                    // Fallback: If it's just the name validation (e.g. R_CONTACTID_FIRSTNAME)
                    // We might not be able to fetch deep fields without more context.
                    // RETURNING EMPTY for now to prevent breaking, unless we add deep logic.
                    return "";
                } catch (Exception $e) {
                    return "";
                }
            }
            return "";
        }, $html);

        // FIX: Manual mapping for specific requested User fields to ensure they work immediately
        $contactId = $recordModel->get('contact_id');
        if ($contactId) {
            $contactModel = Vtiger_Record_Model::getInstanceById($contactId, 'Contacts');
            $html = str_replace('$R_CONTACTID_FIRSTNAME$', $contactModel->get('firstname'), $html);
            $html = str_replace('$R_CONTACTID_LASTNAME$', $contactModel->get('lastname'), $html);
            $html = str_replace('$R_CONTACTID_SALUTATIONTYPE$', $contactModel->get('salutationtype'), $html);
        }

        $accountId = $recordModel->get('account_id');
        if ($accountId) {
            $accountModel = Vtiger_Record_Model::getInstanceById($accountId, 'Accounts');
            $html = str_replace('$R_ACCOUNTID_CF_852$', $accountModel->get('cf_852'), $html); // Custom field example
            $html = str_replace('$R_ACCOUNTID_INDUSTRY$', $accountModel->get('industry'), $html);
            // ... Add other specific fields from requirements if needed generic parser fails
        }

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
        $html = str_replace('$TOTALWITHOUTVAT$', $data['hdnSubTotal'] ?? '0.00', $html);
        $html = str_replace('$TOTAL$', $data['hdnGrandTotal'] ?? '0.00', $html);
        $html = str_replace('$VAT$', $data['hdnTaxType'] ?? '0.00', $html);
        $html = str_replace('$TOTALDISCOUNT$', $data['hdnDiscountAmount'] ?? '0.00', $html);
        $html = str_replace('$CURRENCYSYMBOL$', '$', $html); // Should fetch currency symbol
        $html = str_replace('$CURRENCYNAME$', 'USD', $html);

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
            $row['product_qty'] = $row['quantity'];
            $row['product_price'] = $row['listprice'];
            $row['product_total'] = $row['listprice'] * $row['quantity'];
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
}

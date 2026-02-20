# How to Install WordExport Module

## Concept: "Self-Contained" Module
This module is designed to be **self-contained**. The dependencies (`phpword`, `dompdf`) are bundled **inside** the ZIP file.
-   **Server Requirements**: ZERO. Your Vtiger server does **not** need `composer` installed.
-   **Dockerfile/YAML**: You do **not** need to modify your server configuration.

## 1. Build the ZIP Package (On Your Computer)
You need to generate the `WordExport.zip` file. Since you are on a different machine than the server, you have two options to generate it:

### Option A: If you have Docker (Recommended)
You can use a temporary container to download the dependencies and create the ZIP, without installing PHP/Composer on your Mac.

Run these commands in your terminal (from `vtiger_7_1_clean` folder):

```bash
# 1. Download dependencies using a temporary Composer container
# Note: Ensure you are in the vtiger_7_1_clean directory
docker run --rm -v "$(pwd)/WordExport_Extension/modules/WordExport":/app composer install --ignore-platform-reqs --no-dev

# 2. Create the ZIP file
cd WordExport_Extension
zip -r WordExport.zip manifest.xml modules layouts
# The file WordExport.zip is now ready to upload.
cd ..
```

### Option B: If you have PHP & Composer installed locally
```bash
./WordExport_Extension/build.sh
```

## 2. Upload to Server
1.  Take the `WordExport.zip` file you just created.
2.  Log in to your remote Vtiger CRM as Administrator.
3.  Go to **CRM Settings > Module Manager**.
4.  Click **Import Module from Zip**.
5.  Select `WordExport.zip`.

## 3. Post-Install
-   Place your `.docx` templates in `modules/WordExport/templates/`.
    *(You can use the File Manager of your server or upload them via FTP to that specific folder).*
-   Go to **Quotes** module and verify the "Word Export" button appears.

---

# Template Variable Guide

You can use the following variables in your Word document (`.docx`). The system will replace them with data from the Quote.

## General Quote Fields
Any field from the Quote record can be used by wrapping its **internal name** in `${}`.

Examples:
-   **Subject**: `${subject}`
-   **Quote Number**: `${quote_no}`
-   **Status**: `${quotestage}`
-   **Valid Until**: `${validtill}`
-   **Carrier**: `${carrier}`
-   **Shipping**: `${shipping}`
-   **Account Name**: `${account_id}` (Note: Might show ID unless customized)
-   **Billing Address**: `${bill_street}`, `${bill_city}`, `${bill_state}`, `${bill_code}`, `${bill_country}`
-   **Shipping Address**: `${ship_street}`, `${ship_city}`, `${ship_state}`, `${ship_code}`, `${ship_country}`
-   **Terms & Conditions**: `${terms_conditions}`
-   **Description**: `${description}`

> **Tip**: To find the internal name of a custom field, go to **CRM Settings > Module Layouts & Fields > Quotes**.

## Product Table (Inventory)
To list the products/services, create a table in your Word document with **two rows**:
1.  **Header Row**: (Item, Quantity, Price, Total)
2.  **Data Row**: (Contains the variables below)

Use these variables in the **Data Row**. The system will automatically duplicate this row for every product in the Quote.

| Variable | Description |
| :--- | :--- |
| `${product_name}` | Name of the Product or Service |
| `${product_qty}` | Quantity |
| `${product_price}` | List Price (Unit Price) |
| `${product_total}` | Row Total (Price * Qty) |
| `${comment}` | Line Item Comment (if available) |

## Totals
-   **Subtotal**: `${hdnSubTotal}`
-   **Grand Total**: `${hdnGrandTotal}`
-   **Tax**: `${hdnTaxType}` (or specific tax variables if customized)

## Example Template Structure
```
Quote: ${quote_no}
Subject: ${subject}
Client: ${account_id}

-------------------------------------------------------
| Item             | Qty           | Price           | Total          |
|------------------|---------------|-----------------|----------------|
| ${product_name}  | ${product_qty}| ${product_price}| ${product_total}|
-------------------------------------------------------

Grand Total: ${hdnGrandTotal}
```

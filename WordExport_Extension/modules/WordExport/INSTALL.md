# How to Install WordExport Module

## Concept: "Self-Contained" Module
This module is designed to be **self-contained**. The dependencies (`phpword`, `dompdf`) are bundled **inside** the ZIP file.

## 1. Build the ZIP Package (On Your Computer)
You need to generate the `WordExport.zip` file. Since you are on a different machine than the server, you have two options to generate it:

### Option A: If you have Docker (Recommended)
You can use a temporary container to download the dependencies and create the ZIP, without installing PHP/Composer on your Mac.

Run these commands in your terminal (from `vtiger_7_1_clean` folder):

```bash
# 1. Download dependencies using a temporary Composer container
docker run --rm -v "$(pwd)/WordExport_Extension/modules/WordExport":/app composer install --ignore-platform-reqs --no-dev

# 2. Create the ZIP file
cd WordExport_Extension
zip -r WordExport.zip manifest.xml modules layouts
cd ..
```

### Option B: If you have PHP & Composer installed locally
```bash
./WordExport_Extension/build.sh
```

## 2. Upload to Server
1.  Log in to your Vtiger CRM as Administrator.
2.  Go to **CRM Settings > Module Manager**.
3.  Click **Import Module from Zip**.
4.  Select `WordExport.zip`.

> **Important**: If you are upgrading from a previous version, this update will automatically create the required database table `vtiger_wordexport_templates`.

## 3. Post-Install
-   Go to **Extension Store** or **CRM Settings**, or directly access the module index to Manage Templates:
    `index.php?module=WordExport&view=ListTemplates`
-   You can also click "Manage Templates" from the Export Popup in any Quote/Order.

---

# Template Variable Guide

## Supported Modules
This extension now supports:
*   **Quotes**
*   **SalesOrder**
*   **Invoice**
*   **PurchaseOrder**

## General Fields
Any field from the record can be used by wrapping its **internal name** in `${}`.

Examples:
-   **Subject**: `${subject}`
-   **Record Number**: `${quote_no}`, `${salesorder_no}`, etc.
-   **Account**: `${account_id}`
-   **Dates**: `${validtill}`, `${duedate}`

## Product Table (Inventory)
Works the same for all modules. Create a table with **two rows**:

| Variable | Description |
| :--- | :--- |
| `${product_name}` | Name of the Product or Service |
| `${product_qty}` | Quantity |
| `${product_price}` | List Price (Unit Price) |
| `${product_total}` | Row Total (Price * Qty) |
| `${product_code}` | Product Part Number |
| `${comment}` | Line Item Comment |

## Totals
-   **Subtotal**: `${hdnSubTotal}`
-   **Grand Total**: `${hdnGrandTotal}`
-   **Tax**: `${hdnTaxType}`

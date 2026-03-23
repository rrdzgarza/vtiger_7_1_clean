# WordExport Extension - Guía del Desarrollador

## 1. Estructura del Código

### 1.1 Entrada Principal
**Archivo:** `modules/WordExport/actions/Export.php`

```php
class WordExport_Export_Action extends Vtiger_Action_Controller {
    public function process(Vtiger_Request $request) {
        // 1. Carga dependencias (Composer)
        // 2. Carga datos del registro
        // 3. Procesa template HTML → processHtmlTemplate()
        // 4. Genera PDF con mPDF o Word con PHPWord
        // 5. Descarga al navegador
    }
}
```

### 1.2 Métodos Principales

#### `processHtmlTemplate($html, $recordModel, $data, $moduleName)`
Orden de procesamiento (crítico — no alterar):

```
0. $IMG_*$         → base64 img tags
1. $COMPANY_*$     → getOrganizationDetails() + getCompanyLogo()
2. $USERS_*$       → usuario asignado
3. %G_% / %M_%     → str_replace directo (etiquetas conocidas)
4. %*%             → preg_replace_callback + vtranslate (resto)
5. $CAMPO$         → loop $data (todos los campos incl. cf_XXX)
6. $R_*$           → relacionados manuales → luego limpiar sobrantes
7. #PRODUCTBLOC#   → expansión de productos
8. Financieros     → totales, moneda
9. Fallback %G_%   → str_replace final
```

#### `getOrganizationDetails()`
Consulta `vtiger_organizationdetails` con `SELECT * LIMIT 1`.
> ⚠️ La columna es `organization_id` (con guión bajo), no `organizationid`.
> Usar `LIMIT 1` sin `WHERE` para evitar el bug del nombre de columna.

**Retorna:** array con claves: `organizationname`, `address`, `city`, `state`, `code`, `country`, `phone`, `website`, `logoname`, `vatid`

#### `getCompanyLogo()`
Consulta `logoname` de `vtiger_organizationdetails LIMIT 1`, busca el archivo en el filesystem y retorna `<img src="data:mime;base64,...">` sin estilos inline.

> ✅ Sin estilos inline — el template controla el tamaño con `width`/`height` en el `<td>` o en el tag `$IMG_*$`.

#### `getInventoryItems($recordModel)`
Fuente: `vtiger_inventoryproductrel`

**Retorna:** array con campos ya formateados:
```php
[
    'product_code'  => string,
    'product_name'  => string,
    'product_qty'   => string (2 decimales),
    'product_price' => string (2 decimales),
    'product_total' => string (2 decimales),
    'comment'       => string
]
```

---

## 2. Sistema de Variables

### 2.1 Imágenes Estáticas `$IMG_*$`
```php
// Regex: /\$IMG_([A-Za-z0-9_\-]+)(?:\|([^|$]*))?(?:\|([^$]*))?\$/
// $IMG_nombre$             → sin dimensiones
// $IMG_nombre|40mm$        → width="40mm"
// $IMG_nombre|40mm|25mm$   → width="40mm" height="25mm"
```
- Directorio: `modules/WordExport/images/`
- Búsqueda case-insensitive con `glob()` + `strcasecmp()`
- Soporta: `.png`, `.jpg`, `.jpeg`, `.gif`, `.svg`

### 2.2 Campos del Registro `$CAMPO$`
```php
foreach ($data as $key => $value) {
    if (is_array($value) || is_object($value)) continue;
    $displayValue = ($value === null) ? '' : (string)$value;
    $html = str_replace('$' . strtoupper($key) . '$', $displayValue, $html);
    $html = str_replace('$' . strtoupper($moduleName) . '_' . strtoupper($key) . '$', $displayValue, $html);
}
```
> ✅ Usa `getDisplayValue()` para formato correcto (checkboxes→Sí/No, picklists→label).
> Los `cf_XXX` se resuelven automáticamente como `$QUOTES_CF_XXX$` o `$SALESORDER_CF_XXX$`.

### 2.3 Campos Relacionados `$R_*$`
Se resuelven **dinámicamente** con loop sobre `getData()` de cada registro relacionado:
```php
// Contact — resuelve TODOS los campos automáticamente
$contactData = $contactModel->getData();
foreach ($contactData as $cKey => $cVal) {
    $html = str_replace('$R_CONTACTID_' . strtoupper($cKey) . '$', ...);
}
// Igual para Account ($R_ACCOUNTID_*$), Potential ($R_POTENTIALID_*$), Quote ($R_QUOTEID_*$)

// Limpiar no resueltos AL FINAL
$html = preg_replace('/\$R_[A-Z0-9_]+\$/', '', $html);
```

### 2.3.1 Usuarios
```php
// $USERS_*$ = usuario asignado al registro
// $R_USERS_*$ = usuario logueado (quien genera el PDF)
$currentUser = Users_Record_Model::getCurrentUserModel();
$html = str_replace('$R_USERS_FIRST_NAME$', $currentUser->get('first_name'), $html);
```

### 2.4 Etiquetas `%G_%`
```php
// Paso 1: str_replace directo para etiquetas conocidas (más confiable)
$knownLabels = [
    '%G_LBL_GRAND_TOTAL%' => 'TOTAL',
    '%M_Quote No%'        => 'Cotización',
    // ...
];
$html = str_replace(array_keys($knownLabels), array_values($knownLabels), $html);

// Paso 2: preg_replace_callback para el resto (NOTA: regex solo alfanuméricos)
$html = preg_replace_callback('/%([A-Za-z0-9_ ]+)%/', function($matches) { ... }, $html);
// ⚠️ NUNCA usar /%([^%]+)%/ — captura HTML entre % literales (ej: $VATPERCENT$%...%CF_1175%)
```
> ⚠️ El str_replace DEBE ir antes del preg_replace_callback, si no vtranslate puede
> reemplazar con string vacío y el fallback final ya no encuentra el tag.

### 2.5 Moneda
```php
// Desde vtiger_currency_info usando currency_id del registro
$cRes = $db->pquery("SELECT currency_name, currency_symbol FROM vtiger_currency_info WHERE id = ?", [$currencyId]);
```

---

## 3. Agregar Nueva Variable

### 3.1 Campo del Registro (automático)
Para cualquier campo del módulo Quotes, ya funciona automáticamente:
```html
$QUOTES_MI_CAMPO$     <!-- automático desde getData() -->
$QUOTES_CF_XXX$       <!-- custom fields también automáticos -->
```

### 3.2 Campo Relacionado
En `processHtmlTemplate()`, sección "Related Fields":
```php
$html = str_replace('$R_CONTACTID_MI_CAMPO$', $contactModel->get('mi_campo') ?? '', $html);
```

### 3.3 Variable de Empresa
En `processHtmlTemplate()`, sección 1:
```php
$html = str_replace('$COMPANY_MICAMPO$', $orgDetails['columna_db'] ?? '', $html);
```

### 3.4 Etiqueta de Traducción
En el array `$knownLabels`:
```php
'%G_MI_ETIQUETA%' => 'Mi Traducción',
```

### 3.5 Variable Calculada
En sección "Totals & Currencies":
```php
$valor = /* calcular */;
$html = str_replace('$MI_VARIABLE$', $valor, $html);
```

---

## 4. Agregar Imagen Estática

1. Copiar archivo a `modules/WordExport/images/NombreArchivo.png`
2. `bash build.sh`
3. Reinstalar en Vtiger
4. Usar en template: `$IMG_NombreArchivo|40mm$`

> La búsqueda es case-insensitive, `$IMG_NOMBREARCHIVO$` encontrará `NombreArchivo.png`.

---

## 5. Crear Nuevo Template

### 5.1 Estructura Base (Letter, mPDF compatible)

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style type="text/css">
        @page {
            size: letter;
            margin-top: 35mm;
            margin-bottom: 25mm;
            margin-left: 15mm;
            margin-right: 15mm;
            header: html_myHeader;
            footer: html_myFooter;
        }
    </style>
</head>
<body>
    <htmlpageheader name="myHeader" style="display:none">
        <table width="186mm" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td>$COMPANY_LOGO$</td>
                <td align="right">$QUOTES_QUOTE_NO$</td>
            </tr>
        </table>
    </htmlpageheader>

    <htmlpagefooter name="myFooter" style="display:none">
        <table width="186mm" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td width="62mm">Col 1</td>
                <td width="62mm" align="center">Col 2</td>
                <td width="62mm" align="right">Col 3</td>
            </tr>
        </table>
    </htmlpagefooter>

    <!-- Contenido -->
    <table width="186mm">
        <tr>
            <td>$R_CONTACTID_FIRSTNAME$ $R_CONTACTID_LASTNAME$</td>
        </tr>
    </table>

    <!-- Productos -->
    <table width="186mm">
        <tbody>
            #PRODUCTBLOC_START#
            <tr>
                <td>$PRODUCTPOSITION$</td>
                <td>$PRODUCTTITLE$</td>
                <td>$CURRENCYSYMBOL$ $PRODUCTSTOTALAFTERDISCOUNT$</td>
            </tr>
            #PRODUCTBLOC_END#
        </tbody>
    </table>

    <!-- Totales -->
    <table width="186mm">
        <tr><td>Subtotal</td><td>$CURRENCYSYMBOL$ $TOTALWITHOUTVAT$</td></tr>
        <tr><td>Impuesto</td><td>$CURRENCYSYMBOL$ $VAT$</td></tr>
        <tr><td>TOTAL</td><td>$CURRENCYSYMBOL$ $TOTAL$</td></tr>
    </table>

    <!-- Términos en página separada -->
    <div style="page-break-before: always;">
        $QUOTES_TERMS_CONDITIONS$
    </div>
</body>
</html>
```

### 5.2 Reglas mPDF — CRÍTICAS

✅ **Usar:**
- Tablas HTML para layout (NUNCA CSS floats)
- Ancho fijo en mm para TODAS las tablas (180mm para Letter con márgenes 15mm)
- `width` y `height` directamente en `<img>` o `<td>` para imágenes
- El tamaño de página se define en el **constructor** de mPDF (`'format' => 'Letter'`), NO en CSS
- Anchos de columna en tablas: usar atributo HTML `width` en `<col>` + `<th>` + `<td>` simultáneamente (las 3 capas). CSS `style="width:"` y `table-layout:fixed` NO funcionan solos

❌ **Evitar — causan loops infinitos o páginas en blanco:**
- `@page { size: letter; }` → **NO USAR** — causa renderizado de 1 carácter por página. El tamaño se define en el constructor PHP
- `width="100%"` o `width: 100%` en tablas → **NO USAR** — genera cientos de páginas en blanco. Usar mm fijos
- CSS `float: left/right` → **NO USAR** — causa loops infinitos en mPDF Output(). Usar tablas HTML para layout de columnas
- `* { margin: 0; padding: 0; }` → **NO USAR** — interfiere con elementos internos de mPDF
- `@font-face` sin `src` → **NO USAR** — declaración inválida que puede causar lentitud
- CSS Grid o Flexbox
- `simpleTables => true` en constructor mPDF cuando el template usa `colspan`
- Efectos hover, transforms, animaciones
- Tags `</span>` huérfanos (sin `<span>` de apertura)

### 5.3 Constructor mPDF — Configuración correcta
```php
$mpdf = new \Mpdf\Mpdf([
    'mode'    => 'utf-8',
    'format'  => 'Letter',     // Tamaño de página aquí, NO en @page CSS
    'tempDir' => $mpdfTempCache,
]);
```
> ⚠️ NO agregar `simpleTables => true` si el template usa `colspan`.
> ⚠️ NO agregar `size: letter` en `@page` CSS — causa renderizado corrupto.

### 5.4 @page CSS — Solo margins y header/footer
```css
@page {
    /* NO incluir size: letter aquí */
    margin-top: 35mm;
    margin-bottom: 25mm;
    margin-left: 15mm;
    margin-right: 15mm;
    header: html_myHeader;
    footer: html_myFooter;
}
```

---

## 6. Compilación y Empaquetamiento

```bash
bash build.sh        # Genera WordExport.zip
```

`pack.php` incluye directorios `modules/` y `layouts/`, y el archivo `manifest.xml`.
Excluye: `.DS_Store`, `__MACOSX`, `.git`.

---

## 7. manifest.xml — Reglas Críticas

> ⚠️ Error `LBL_INVALID_IMPORT_TRY_AGAIN` si no se respetan estas reglas.

**Orden OBLIGATORIO de elementos:**
```
1. <name>
2. <label>
3. <parent>
4. <type>
5. <version>
6. <dependencies>
7. <license>
8. <files>
9. <tables>
```

**Otros requisitos:**
- Declaración XML con encoding: `<?xml version="1.0" encoding="UTF-8"?>`
- `<license><inline>MIT License</inline></license>` (no directo en `<license>`)
- Sección `<files>` obligatoria
- SQL con `CREATE TABLE IF NOT EXISTS` dentro de `<![CDATA[...]]>`
- Archivo `modules/WordExport/language/en_us.lang.php` debe existir

---

## 8. Debugging

### Ver qué campos tiene el registro
```php
error_log('[WE] Data keys: ' . implode(', ', array_keys($data)));
```

### Ver valor de un campo específico
```php
error_log('[WE] cf_996: ' . var_export($data['cf_996'] ?? null, true));
```

### Ver HTML antes de generar PDF
```php
echo htmlspecialchars($processedHtml); exit;
```

### Ver logs en Docker
```bash
docker logs <CONTAINER> 2>&1 | grep WordExport
docker exec -it <CONTAINER> tail -f /var/log/apache2/error.log | grep WordExport
```

---

## 9. Resolución de Errores Comunes

| Error | Causa | Solución |
|---|---|---|
| `LBL_INVALID_IMPORT_TRY_AGAIN` | Orden XML incorrecto / falta `<files>` / falta lang file | Ver sección 7 |
| Logo no aparece | Ruta no encontrada | `find /var/www/html -name "logoname"` |
| `$COMPANY_VATID$` vacío | `vtiger_organizationdetails` retorna 0 filas | Usar `LIMIT 1` sin `WHERE organizationid=1` (columna es `organization_id`) |
| `$R_*$` vacíos | Regex limpiaba antes del reemplazo manual | Resolver manualmente PRIMERO, luego regex al final |
| `%G_%` sin traducir | vtranslate retorna vacío | Agregar al array `$knownLabels` con str_replace directo |
| Layout roto | CSS Grid/Flexbox | Usar tablas HTML |
| PDF en A4 en lugar de Letter | `format` en mPDF | Verificar `'format' => 'Letter'` en Export.php |
| `Class not found: Mpdf\Mpdf` | mPDF no instalado | `composer require mpdf/mpdf` en raíz de Vtiger |

---

**Guía del Desarrollador v1.2**
WordExport Extension - Vtiger CRM 7.1

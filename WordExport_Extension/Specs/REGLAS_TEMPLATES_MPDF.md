# Reglas para Templates mPDF — WordExport Extension

## Contexto

Este documento nace de la reconstrucción del template `Quote_Template_PDFMaker_Style.html`.
El template original causaba loops infinitos, timeouts y PDFs con cientos de páginas en blanco.
Se reconstruyó progresivamente (v1→v4) para aislar cada problema.

---

## Diferencias: Template Original (NO funciona) vs v4 (SÍ funciona)

### 1. `@page { size: letter; }` → **ELIMINADO**
```css
/* ❌ ORIGINAL — causa 1 carácter por página (201+ páginas) */
@page {
    size: letter;
    margin-top: 35mm;
    ...
}

/* ✅ v4 — tamaño se define en constructor PHP, no en CSS */
@page {
    margin-top: 35mm;
    ...
}
```
**Por qué:** mPDF interpreta `size: letter` en CSS de forma incorrecta en algunas versiones, generando páginas del tamaño de un carácter. El tamaño de página se define en el constructor PHP: `new \Mpdf\Mpdf(['format' => 'Letter'])`.

### 2. `* { margin: 0; padding: 0; }` → **ELIMINADO**
```css
/* ❌ ORIGINAL */
* { margin: 0; padding: 0; }

/* ✅ v4 — no tiene reset universal */
```
**Por qué:** El selector universal `*` afecta elementos internos de mPDF (page containers, headers, footers), causando layout corrupto.

### 3. `@font-face` sin `src` → **ELIMINADO**
```css
/* ❌ ORIGINAL — declaración inválida */
@font-face {
    font-family: SourceSansPro;
    font-family: Ubuntu;
}

/* ✅ v4 — no tiene @font-face */
```
**Por qué:** Un `@font-face` sin `src` es HTML inválido. mPDF puede intentar buscar la fuente indefinidamente, causando lentitud extrema.

### 4. CSS `float` → **Reemplazado con tablas HTML**
```css
/* ❌ ORIGINAL — floats en múltiples elementos */
#client2 { float: left; width: 420px; }
#invoice2 { float: right; text-align: right; }
#logo { float: left; width: 250px; }
#company { float: left; width: 350px; }
```
```html
<!-- ✅ v4 — tabla HTML para layout de 2 columnas -->
<table width="180mm" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td width="100mm" valign="top">...datos cliente...</td>
        <td width="80mm" valign="top" style="text-align: right;">...datos empresa...</td>
    </tr>
</table>
```
**Por qué:** CSS `float` causa loops infinitos en `mPDF->Output()`. El proceso PHP nunca termina. Tablas HTML son el layout correcto para mPDF.

### 5. `width: 100%` en tablas → **Reemplazado con mm fijos**
```css
/* ❌ ORIGINAL */
table.items { width: 100%; }

/* ✅ v4 */
<table width="180mm" ...>
```
**Por qué:** `width: 100%` genera cientos de páginas en blanco en mPDF. Siempre usar ancho fijo en mm. Para Letter con márgenes de 15mm: `180mm`.

### 6. `font-family: SourceSansPro` → **Cambiado a `sans-serif`**
```css
/* ❌ ORIGINAL */
body { font-family: SourceSansPro, sans-serif; }

/* ✅ v4 */
body { font-family: sans-serif; }
```
**Por qué:** `SourceSansPro` no está definida correctamente (el @font-face era inválido). Usar fuentes genéricas o fuentes que mPDF incluye por defecto.

### 7. `font-family:verdana` en spans inline → **ELIMINADO**
```html
<!-- ❌ ORIGINAL — en casi todos los spans -->
<span style="font-family:verdana,geneva,sans-serif;">$PRODUCTTITLE$</span>

<!-- ✅ v4 — sin font-family inline, hereda del body -->
<strong style="color: #7c8c95; font-size: 10px;">$PRODUCTTITLE$</strong>
```
**Por qué:** Múltiples declaraciones `font-family` inline fuerzan a mPDF a cargar y subsetear fuentes repetidamente. Usar la fuente del `body` y solo sobreescribir cuando sea necesario.

### 8. `.clearfix:after` pseudo-elemento → **ELIMINADO**
```css
/* ❌ ORIGINAL */
.clearfix:after { content: ""; display: table; clear: both; }

/* ✅ v4 — no necesario sin floats */
```
**Por qué:** Los pseudo-elementos CSS no son completamente soportados por mPDF y no son necesarios cuando se usan tablas en lugar de floats.

### 9. Tags `</span>` huérfanos → **CORREGIDOS**
```html
<!-- ❌ ORIGINAL — closing tags sin opening tags -->
Tel: (33) 3650-3400, 3650-3659, 1306-0836</span>
administracion@aasystems.com.mx</span></span>
Tel: (55) 5615-6763, 1518-0139</span>

<!-- ✅ v4 — HTML limpio -->
Tel: (33) 3650-3400, 3650-3659, 1306-0836
```
**Por qué:** Tags huérfanos pueden confundir el parser HTML de mPDF y causar comportamiento impredecible.

### 10. `page-break-after` con contenido oculto → **Cambiado a `page-break-before`**
```html
<!-- ❌ ORIGINAL -->
<div style="page-break-after: always;"><span style="display: none;">&nbsp;</span></div>
<span>$QUOTES_TERMS_CONDITIONS$</span>

<!-- ✅ v4 -->
<div style="page-break-before: always;">
    <span>$QUOTES_TERMS_CONDITIONS$</span>
</div>
```
**Por qué:** `page-break-after` con contenido `display:none` puede crear páginas en blanco. `page-break-before` en el contenido que sigue es más limpio y predecible.

### 11. CSS complejo con selectores tfoot/last-child → **Simplificado a inline**
```css
/* ❌ ORIGINAL — muchos selectores CSS complejos */
table.items tbody tr:last-child td { border: none; }
table.items tfoot td { ... }
table.items tfoot .grand-total { ... }

/* ✅ v4 — todo inline, sin clases CSS para tablas */
```
**Por qué:** mPDF tiene soporte limitado de selectores CSS avanzados (`:last-child`, `tfoot`, etc.). Usar estilos inline es más confiable.

### 12. Variables %G_% → **Texto directo en español**
```html
<!-- ❌ ORIGINAL — depende de vtranslate() -->
<strong>%G_Description%</strong>
<strong>%G_List Price%</strong>

<!-- ✅ v4 — texto directo -->
<strong>Descripcion</strong>
<strong>Precio Unitario</strong>
```
**Por qué:** Las variables %G_% funcionan pero agregan una capa de complejidad. Para templates en un solo idioma, texto directo es más simple y predecible. Las %G_% siguen siendo soportadas si se necesitan.

---

## Checklist para Crear Nuevos Templates

### Estructura base
```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style type="text/css">
        @page {
            /* NO incluir size: letter */
            margin-top: 35mm;
            margin-bottom: 25mm;
            margin-left: 15mm;
            margin-right: 15mm;
            header: html_myHeader;
            footer: html_myFooter;
        }
        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #555555;
        }
    </style>
</head>
<body>
    <htmlpageheader name="myHeader" style="display:none">
        <table width="180mm" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td width="60mm">...logo...</td>
                <td width="60mm">...centro...</td>
                <td width="60mm">...derecha...</td>
            </tr>
        </table>
    </htmlpageheader>

    <htmlpagefooter name="myFooter" style="display:none">
        <table width="180mm" border="0" cellpadding="4" cellspacing="0">
            <tr>
                <td width="60mm">...col 1...</td>
                <td width="60mm">...col 2...</td>
                <td width="60mm">...col 3...</td>
            </tr>
        </table>
    </htmlpagefooter>

    <!-- Contenido del body -->
    ...
</body>
</html>
```

### Anchos de columna en tablas de productos
mPDF ignora `table-layout: fixed`, `<colgroup>` y `width` en CSS style por sí solos.
La **única forma confiable** de forzar anchos fijos es usar el **atributo HTML `width`** en las tres capas:

```html
<table width="180mm">
    <colgroup>
        <col width="9mm" />
        <col width="25mm" />
        <col width="72mm" />
        ...
    </colgroup>
    <thead>
        <tr>
            <th width="9mm">...</th>
            <th width="25mm">...</th>
            <th width="72mm">...</th>
            ...
        </tr>
    </thead>
    <tbody>
        <tr>
            <td width="9mm">...</td>
            <td width="25mm">...</td>
            <td width="72mm">Texto largo se ajusta en múltiples renglones</td>
            ...
        </tr>
    </tbody>
</table>
```

**Lo que NO funciona solo:**
- `style="width:72mm"` en CSS → mPDF lo ignora si el contenido es más ancho
- `table-layout: fixed` → no se respeta en mPDF
- `<colgroup>` solo → no es suficiente sin `width` en `<th>` y `<td>`
- `word-wrap: break-word` solo → no fuerza el ancho

**Lo que SÍ funciona:** atributo HTML `width="72mm"` en `<col>` + `<th>` + `<td>` simultáneamente.

### Reglas obligatorias
- [ ] Sin `size:` en `@page`
- [ ] Todas las tablas con ancho en mm (180mm para márgenes 15mm)
- [ ] Sin CSS `float` — usar tablas para columnas
- [ ] Sin `width: 100%` — usar mm fijos
- [ ] Sin `* { margin:0; padding:0 }`
- [ ] Sin `@font-face` sin `src`
- [ ] Sin tags HTML huérfanos
- [ ] `font-family: sans-serif` (o fuente incluida en mPDF)
- [ ] Estilos inline en tablas (no depender de selectores CSS complejos)
- [ ] `page-break-before: always` (no `page-break-after` con contenido oculto)

### Anchos según márgenes
| Márgenes | Ancho disponible |
|---|---|
| 15mm left + 15mm right | 180mm |
| 20mm left + 20mm right | 175mm |
| 25mm left + 25mm right | 165mm |

---

---

## Hallazgo Final

Tras comparar el backup del template con v4, se confirmó que **la ÚNICA línea que causa el fallo es `size: letter` en `@page`**. Todo lo demás (font-family Verdana, variables %G_%, CSS classes, entidades HTML) funciona correctamente con mPDF.

Al crear o modificar templates: verificar que `@page` NO contenga `size:`. Esta es la regla #1 más importante.

---

**Documento creado:** Marzo 2026
**Basado en:** Reconstrucción progresiva v1→v4 del template PDFMaker_Style
**Versión mPDF:** 8.x (instalada via Composer en raíz de Vtiger)

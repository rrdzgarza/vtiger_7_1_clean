# WordExport Extension - Especificaciones y Funcionalidades

## 1. Descripción General

WordExport es una extensión para Vtiger CRM 7.1 que permite exportar registros de módulos (Cotizaciones, Órdenes de Venta, Facturas, Órdenes de Compra) a documentos Word/PDF con templates personalizables.

**Versión:** 1.1
**Compatibilidad:** Vtiger CRM 7.1
**Lenguaje:** PHP
**Renderer PDF:** mPDF (compatible con HTML/CSS)
**Tamaño de página PDF:** Letter (215.9mm × 279.4mm)

---

## 2. Funcionalidades Principales

### 2.1 Exportación a Múltiples Formatos
- **Word (.docx):** Generación de documentos Word usando PHPWord
- **PDF:** Conversión automática de templates HTML a PDF usando mPDF

### 2.2 Múltiples Templates Prediseñados
Se incluyen 4 templates de cotización profesionales:
- **Quote_Template_Professional_v2.html** - Diseño profesional limpio
- **Quote_Template_Executive_v2.html** - Estilo ejecutivo con tipografía serif
- **Quote_Template_Modern_v2.html** - Diseño moderno con tablas
- **Quote_Template_PDFMaker_Style.html** - Basado en estructura de PDFMaker ⭐ (principal)

### 2.3 Resolución Automática de Variables
- **Imágenes estáticas:** `$IMG_nombre$` → Imagen desde `modules/WordExport/images/`
- **Empresa:** `$COMPANY_*$` → Datos desde `vtiger_organizationdetails`
- **Campos directos:** `$CAMPO$` → Valores del registro
- **Campos modulares:** `$MODULO_CAMPO$` → Valores del módulo específico
- **Custom fields:** `$QUOTES_CF_XXX$` → Automático, sin hardcodear
- **Campos relacionados:** `$R_RELACIÓNID_CAMPO$` → Valores de registros relacionados
- **Etiquetas de traducción:** `%G_ETIQUETA%` → Textos en español

### 2.4 Campos Soportados

#### Imágenes Estáticas
```
$IMG_nombre$              - Imagen desde modules/WordExport/images/ (búsqueda case-insensitive)
$IMG_nombre|ancho$        - Con ancho fijo (ej: $IMG_Logo_AAS|40mm$)
$IMG_nombre|ancho|alto$   - Con ancho y alto fijos (ej: $IMG_Logo_AAS|40mm|25mm$)
```
Formatos soportados: `.png`, `.jpg`, `.jpeg`, `.gif`, `.svg`

#### Datos de Empresa (desde vtiger_organizationdetails)
```
$COMPANY_LOGO$     - Logo (base64, sin estilos inline — tamaño controlado por template)
$COMPANY_NAME$     - Nombre de la empresa
$COMPANY_ADDRESS$  - Dirección
$COMPANY_CITY$     - Ciudad
$COMPANY_STATE$    - Estado
$COMPANY_ZIP$      - Código postal (alias: $COMPANY_CODE$)
$COMPANY_CODE$     - Código postal
$COMPANY_VATID$    - RFC / VAT ID
$COMPANY_PHONE$    - Teléfono
$COMPANY_WEBSITE$  - Sitio web
$COMPANY_COUNTRY$  - País
```

#### Campos de Cotización (Quotes)
```
$QUOTES_QUOTE_NO$           - Número de cotización
$QUOTES_ACCOUNT_ID$         - ID de cuenta asociada
$QUOTES_ACCOUNT_NAME$       - Nombre de cuenta (alias: $ACCOUNT_NAME$)
$QUOTES_MODIFIEDTIME$       - Fecha de modificación
$QUOTES_SHIP_STREET$        - Calle envío
$QUOTES_SHIP_CITY$          - Ciudad envío
$QUOTES_SHIP_STATE$         - Estado envío
$QUOTES_SHIP_CODE$          - Código postal envío
$QUOTES_SHIP_COUNTRY$       - País envío
$QUOTES_SHIP_POBOX$         - Apartado postal envío
$QUOTES_TERMS_CONDITIONS$   - Términos y condiciones
$QUOTES_CF_XXX$             - Cualquier campo personalizado (automático)
```

#### Campos Relacionados - Contacto
```
$R_CONTACTID_SALUTATIONTYPE$  - Saludo (Sr./Sra.)
$R_CONTACTID_FIRSTNAME$       - Nombre del contacto
$R_CONTACTID_LASTNAME$        - Apellido del contacto
$R_CONTACTID_CF_XXX$          - Campos personalizados del contacto
```

#### Campos Relacionados - Potencial/Oportunidad
```
$R_POTENTIALID_CF_XXX$  - Campos personalizados del potencial
```

#### Campos Relacionados - Cuenta
```
$R_ACCOUNTID_ACCOUNTNAME$  - Nombre de cuenta (alias: $QUOTES_ACCOUNT_NAME$)
$R_ACCOUNTID_INDUSTRY$     - Industria
$R_ACCOUNTID_CF_XXX$       - Campos personalizados de cuenta
```

#### Campos de Usuario
```
$USERS_FIRST_NAME$  - Nombre del usuario asignado
$USERS_LAST_NAME$   - Apellido del usuario asignado
$USERS_EMAIL1$      - Email principal del usuario asignado
```

#### Campos de Productos/Artículos (dentro de #PRODUCTBLOC_START# ... #PRODUCTBLOC_END#)
```
$PRODUCTPOSITION$               - Posición/número de item
$PRODUCTS_PRODUCTCODE$          - Código del producto
$PRODUCTTITLE$                  - Nombre del producto
$PRODUCTEDITDESCRIPTION$        - Descripción del producto
$PRODUCTLISTPRICE$              - Precio unitario (2 decimales)
$PRODUCTQUANTITY$               - Cantidad (2 decimales)
$PRODUCTSTOTALAFTERDISCOUNT$    - Total con descuento (2 decimales)
```

#### Campos Financieros
```
$TOTALWITHOUTVAT$    - Subtotal (sin impuesto)
$TOTALDISCOUNT$      - Total de descuentos
$VAT$                - Monto de impuesto
$VATPERCENT$         - Porcentaje de impuesto (calculado automáticamente)
$TOTAL$              - Total a pagar
$CURRENCYSYMBOL$     - Símbolo de moneda (desde vtiger_currency_info)
$CURRENCYNAME$       - Nombre de moneda (desde vtiger_currency_info)
```

#### Etiquetas Traducibles (%G_%)
Se reemplazan con `str_replace` directo antes del regex para garantizar traducción:
```
%G_Description%         → Descripción
%G_List Price%          → Precio Unitario
%G_Total%               → Total
%G_Subtotal%            → Subtotal
%G_LBL_DISCOUNT%        → Descuentos
%G_Tax%                 → Impuesto
%G_LBL_GRAND_TOTAL%     → TOTAL
%M_Quote No%            → Cotización
```

---

## 3. Características Técnicas

### 3.1 Estructura de Bloques Dinámicos
```html
#PRODUCTBLOC_START#
    <tr>
        <td>$PRODUCTPOSITION$</td>
        <td>$PRODUCTTITLE$</td>
        <!-- ... más campos ... -->
    </tr>
#PRODUCTBLOC_END#
```

### 3.2 Saltos de Página
```html
<div style="page-break-before: always;">
    <!-- Contenido después del salto -->
</div>
```

### 3.3 Manejo de Saltos de Línea
Los campos `terms_conditions`, `notes`, `description` y `comment` convierten `\n` a `<br />` automáticamente.

### 3.4 Logo de Empresa ($COMPANY_LOGO$)
- Consulta `vtiger_organizationdetails` (columna `logoname`) con `SELECT * LIMIT 1`
- Busca el archivo en múltiples rutas del filesystem
- Retorna `<img src="data:mime;base64,...">` sin estilos inline (el template controla el tamaño)
- Si no encuentra el archivo, retorna string vacío

**Rutas buscadas (en orden):**
1. `$rootDir/test/logo/$logoname`
2. `$rootDir/test/upload/images/$logoname`
3. `$rootDir/test/upload/$logoname`
4. `$rootDir/storage/logo/$logoname`
5. `$rootDir/storage/$logoname`
6. `$rootDir/uploads/logos/$logoname`
7. `$_SERVER[DOCUMENT_ROOT]/test/logo/$logoname`
8. `/var/www/html/test/logo/$logoname`
9. `/var/www/html/test/upload/images/$logoname`

### 3.5 Imágenes Estáticas ($IMG_*)
- Almacenadas en `modules/WordExport/images/`
- Búsqueda case-insensitive (ej: `$IMG_LOGO_AAS$` encuentra `Logo_AAS.png`)
- Soporte de dimensiones: `$IMG_nombre|ancho$` o `$IMG_nombre|ancho|alto$`
- Generan `<img src="data:mime;base64,..." width="..." height="...">`

**Logos disponibles:**
- `Logo_AAS.png` → `$IMG_Logo_AAS$`
- `Logo_AASV3.png` → `$IMG_Logo_AASV3$`

### 3.6 Custom Fields Automáticos
El loop de campos usa `is_array() || is_object()` como filtro (no `is_string`), permitiendo:
- Campos `null` → string vacío
- Campos numéricos → convertidos a string
- Todos los `cf_XXX` del registro resueltos automáticamente como `$QUOTES_CF_XXX$`

### 3.7 Moneda
`$CURRENCYNAME$` y `$CURRENCYSYMBOL$` se obtienen de `vtiger_currency_info` usando el `currency_id` del registro.

### 3.8 Cálculo Automático de Porcentaje de Impuesto
```
$VATPERCENT$ = (Monto Impuesto / Subtotal) × 100
```
Con protección contra división por cero.

### 3.9 Formatos Numéricos
Todos los valores monetarios y cantidades de productos se formatean con `number_format($valor, 2)`.

---

## 4. Templates Incluidos

### 4.1 Quote_Template_PDFMaker_Style.html ⭐
- **Estilo:** Compatible con PDFMaker, principal en uso
- **Colores:** Gris #7c8c95
- **Tamaño:** Letter
- **Layout:** Header fijo con logo + datos cotización, footer 3 columnas
- **Columnas productos (mm):** #(9) + Num Parte(25) + Descripción(72) + Precio(23) + Cant(18) + Total(33) = 180mm

### 4.2 Quote_Template_Professional_v2.html
- **Estilo:** Profesional moderno
- **Colores:** Azul #2c3e50, gris claro

### 4.3 Quote_Template_Executive_v2.html
- **Estilo:** Ejecutivo formal
- **Colores:** Negro #1a1a1a

### 4.4 Quote_Template_Modern_v2.html
- **Estilo:** Contemporáneo, tabla-based (sin CSS Grid)

---

## 5. Arquitectura de Archivos

```
WordExport_Extension/
├── manifest.xml
├── modules/WordExport/
│   ├── WordExport.php
│   ├── actions/
│   │   ├── Export.php          ← Procesador principal
│   │   └── FileAction.php      ← Upload/Delete de templates
│   ├── templates/              ← Templates HTML
│   ├── images/                 ← Imágenes estáticas ($IMG_*$)
│   │   ├── Logo_AAS.png
│   │   └── Logo_AASV3.png
│   ├── language/
│   │   └── en_us.lang.php
│   ├── views/
│   │   └── ListTemplates.php
│   └── vendor/
├── layouts/v7/modules/WordExport/
│   ├── ListTemplates.tpl
│   └── Popup.tpl
├── build.sh
├── pack.php
├── PDFMaker_samples/
└── Specs/
```

---

## 6. Proceso de Exportación

### 6.1 Orden de Procesamiento en processHtmlTemplate()
```
0. $IMG_*$         → Imágenes estáticas (base64)
1. $COMPANY_*$     → Datos de empresa (vtiger_organizationdetails)
2. $USERS_*$       → Datos del usuario asignado
3. %G_%  %M_%      → Etiquetas conocidas (str_replace directo)
4. %*%             → Etiquetas restantes (preg_replace_callback + vtranslate)
5. $CAMPO$         → Campos directos del registro (incluyendo CF_XXX)
6. $R_*$           → Campos relacionados (Contacto, Cuenta, Potencial)
                     → Luego limpia $R_*$ no resueltos
7. #PRODUCTBLOC#   → Bloque de productos
8. Financieros     → Totales, moneda, porcentaje impuesto
9. Fallback %G_%   → str_replace final para etiquetas no procesadas
```

---

## 7. Requisitos y Dependencias

- **PHP 7.2+**
- **PHPWord 0.18.0+** - Generación Word
- **mPDF 8.0+** - PDF (instalado via Composer en raíz de Vtiger)
- Extensiones PHP: `zip`, `gd`, `mbstring`

---

## 8. Instalación

1. Ejecutar `bash build.sh` para generar `WordExport.zip`
2. Vtiger → Configuración → Extensiones → Instalar desde ZIP
3. Al reinstalar, los archivos en `modules/WordExport/images/` se actualizan automáticamente

---

## 9. Uso

### 9.1 Exportar desde Cotización
1. Abrir registro de Cotización en Vtiger
2. Botón "Exportar a Word/PDF"
3. Seleccionar template
4. Elegir formato (Word o PDF)
5. Descarga automática

### 9.2 Upload de Templates desde UI
- Vtiger → WordExport → Lista de Templates → Subir
- El nombre del archivo se conserva sin prefijos de timestamp
- Si existe un template con el mismo nombre, se sobreescribe

### 9.3 Agregar Imagen Estática
1. Copiar imagen a `modules/WordExport/images/`
2. Reconstruir ZIP: `bash build.sh`
3. Reinstalar en Vtiger
4. Usar en template: `$IMG_NombreArchivo|ancho$`

---

## 10. Limitaciones Conocidas y Reglas Críticas de mPDF

### 10.1 Reglas que DEBEN seguirse (causan loops/crashes si no se respetan)

| Regla | Qué pasa si no se sigue |
|---|---|
| **NO usar `@page { size: letter; }`** | Renderiza 1 carácter por página (201+ páginas). Tamaño se define en constructor PHP |
| **NO usar `width="100%"` en tablas** | Genera cientos de páginas en blanco. Usar mm fijos (180mm para márgenes de 15mm) |
| **NO usar CSS `float`** | Loop infinito en mPDF Output() — el proceso nunca termina. Usar tablas HTML |
| **NO usar `simpleTables => true` con colspan** | Loop infinito en mPDF — el proceso nunca termina |
| **NO usar `* { margin:0; padding:0 }`** | Interfiere con elementos internos de mPDF |
| **NO usar `@font-face` sin `src`** | Declaración inválida que puede causar lentitud extrema |

### 10.2 Limitaciones generales
- ❌ CSS Grid y Flexbox no funcionan en mPDF (usar tablas HTML)
- ❌ Efectos hover/animaciones no funcionan en PDF
- ❌ Tags HTML huérfanos (`</span>` sin `<span>`) pueden causar comportamiento impredecible
- ✅ Campos `$R_*$` no encontrados se limpian silenciosamente (no generan error)
- ✅ Si logo no se encuentra, exportación continúa sin interrumpirse

### 10.3 Configuración correcta del constructor mPDF
```php
$mpdf = new \Mpdf\Mpdf([
    'mode'    => 'utf-8',
    'format'  => 'Letter',      // Tamaño AQUÍ, no en @page CSS
    'tempDir' => $mpdfTempCache,
]);
// NO agregar: simpleTables, packTableData (incompatibles con colspan)
```

### 10.4 CSS @page correcto
```css
@page {
    /* SIN size: letter — se define en el constructor PHP */
    margin-top: 35mm;
    margin-bottom: 25mm;
    margin-left: 15mm;
    margin-right: 15mm;
    header: html_myHeader;
    footer: html_myFooter;
}
```

---

## 11. Solución de Problemas

| Problema | Causa | Solución |
|---|---|---|
| PDF con 201+ páginas en blanco | `width="100%"` en tablas | Cambiar a ancho fijo en mm |
| 1 carácter por página | `@page { size: letter }` en CSS | Eliminar `size:` del CSS, usar `format` en constructor PHP |
| mPDF Output() nunca termina | CSS `float` en el template | Reemplazar floats con tablas HTML |
| mPDF Output() nunca termina | `simpleTables => true` con `colspan` | No usar `simpleTables` |
| Timeout 30/120 segundos | Template complejo, timeout PHP bajo | `set_time_limit(300)` + `session_write_close()` |
| Página blanca al exportar | `window.location.href` navega el browser | Usar iframe oculto para descarga |
| Pantalla bloqueada al exportar | PHP session lock durante generación PDF | `session_write_close()` antes de mPDF |
| Logo no se muestra | Archivo no encontrado en ninguna ruta | Verificar con `find /var/www/html -name "logoname"` |
| $COMPANY_VATID$ vacío | Campo `vatid` vacío en DB | Completar datos en Vtiger Admin → Organización |
| $QUOTES_CF_XXX$ vacío | El campo está vacío en la cotización | Verificar valor en Vtiger, no es error del sistema |
| %G_LBL_GRAND_TOTAL% sin traducir | vtranslate no encuentra clave | Ya resuelto con str_replace directo |

---

## 12. Historial de Cambios

### v1.1 (Marzo 2026)
- ✅ Tamaño de PDF cambiado a **Letter**
- ✅ Sistema de imágenes estáticas `$IMG_nombre|ancho|alto$`
- ✅ `$COMPANY_*$` completo desde `vtiger_organizationdetails` (NAME, ADDRESS, CITY, STATE, CODE, VATID, PHONE, WEBSITE, COUNTRY)
- ✅ `$QUOTES_ACCOUNT_NAME$` desde módulo Accounts
- ✅ `$CURRENCYNAME$` y `$CURRENCYSYMBOL$` desde `vtiger_currency_info`
- ✅ Custom fields (`cf_XXX`) resueltos automáticamente sin hardcodear
- ✅ Loop de campos acepta null, int, string (no solo string)
- ✅ Etiquetas `%G_%` con str_replace directo antes del regex
- ✅ Logo retorna `<img>` sin estilos inline (tamaño controlado por template)
- ✅ Upload de templates sin prefijo timestamp
- ✅ Template PDFMaker_Style con colores gris #7c8c95
- ✅ Columnas de tabla de productos en mm fijos (180mm total)
- ✅ Precios con 2 decimales

### v1.0 (Marzo 2026)
- ✅ Soporte completo para exportación a Word y PDF
- ✅ 4 templates profesionales incluidos
- ✅ Resolución de campos relacionados (Contacto, Potencial, Cuenta)
- ✅ Traducción automática de etiquetas %G_%
- ✅ Cálculo automático de porcentaje de impuesto
- ✅ Búsqueda inteligente de logo de empresa
- ✅ Manejo de saltos de línea en campos largos
- ✅ Footer compatible con PDFMaker (3 columnas)

---

**Documento de especificaciones v1.1**
Vtiger CRM 7.1 - WordExport Extension

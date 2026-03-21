# WordExport Extension - Registro de Modificaciones

Este documento resume todas las actualizaciones y parches aplicados al módulo `WordExport_Extension` para Vtiger CRM (versión 7.1) mediante la integración de `mPDF`.

## 1. Migración de Librería a mPDF Global (Docker)
**Problema:** El módulo originalmente dependía de librerías embebidas y pesadas que dificultaban su instalación desde la Interfaz de Vtiger debido a los límites de tamaño de subida de PHP.
**Solución:** 
- La librería `mPDF` fue **eliminada físicamente** del archivo ZIP del módulo.
- En su lugar, se actualizó el archivo `actions/Export.php` para apuntar a la ruta `/vendor/autoload.php` global en la raíz de la instalación de Vtiger (`../../../vendor/autoload.php`).
- Se crearon scripts de Docker (`5-install-mpdf.sh`) para asegurar que el contenedor instale `mpdf/mpdf` globalmente en tiempo de arranque del servidor.

## 2. Parche de Permisos del Caché de mPDF 
**Problema:** En el flujo normal, mPDF intenta escribir sus plantillas temporales dentro de `vendor/mpdf/mpdf/tmp`, lo cual denegaba el acceso bajo Docker, ocasionando la infame "Pantalla Blanca de la Muerte" (WSOD).
**Solución:**
- Se inyectó código en `Export.php` para redirigir forzosamente la carpeta de memoria temporal hacia el directorio volátil del Sistema Operativo base de la Mac/Linux en lugar de la carpeta de composer.
```php
$mpdfTempCache = sys_get_temp_dir() . '/mpdf_exportCache';
if (!is_dir($mpdfTempCache)) {
    mkdir($mpdfTempCache, 0777, true);
}
$mpdf = new \Mpdf\Mpdf(['tempDir' => $mpdfTempCache]);
```

## 3. Remoción de Deprecated Warnings (Anti-Corrupción de PDF)
**Problema:** El archivo final descargado del `.pdf` era ilegible por los lectores PDF en macOS.
**Causa:** El núcleo legado de Vtiger (Core) generaba advertencias textuales transparentes (`Deprecated` y `Notice`) que se incrustaban al mero principio de los binarios del archivo descargado, corrompiendo el header del archivo (antes de `%PDF-1.4`).
**Solución:**
- Se añadió un estrangulador de buffers antes de forzar la descarga de los *Headers* (`ob_clean();`). Esto intercepta y destruye la basura residual de Vtiger antes de que toque la descarga binaria pura del PDF.

## 4. Supresión del Fatal Error (vars.php Fantasma)
**Problema:** Las funciones de `Export.php` y `Preview.php` incluían una cabecera `require_once 'vars.php';` en sus primeras líneas. Al no existir ese archivo, PHP reventaba de manera instantánea arrojando un Error Crítico silenciado.
**Solución:**
- La instrucción a `vars.php` fue completamente eliminada del código fuente de los controladores. 

## 5. Implementación del Motor de Traducciones de Etiquetas de PDF Maker
**Problema:** El usuario final necesitaba inyectar traducciones multilingües directo en el diseño usando variables como `%M_Quote No%`, `%G_Total%`, `%R_ACCOUNTID_CF_856%`, pero el motor de WordExport sólo aceptaba campos estándar que contuvieran símbolos de moneda `$` o sin espacios `\w+`.
**Solución:**
- En `Export.php` y `Preview.php`, se reprogramó enteramente la "Inteligencia de Lectura de Etiquetas Cíclicas" de HTML a través de expresiones regulares tolerantes a los espacios (`/%([^%]+)%/`).
- Se introdujo un clasificador inteligente para las tres arquitecturas de variables de PDF Maker:
   - **`M_` (Module):** Analiza traducciones relativas a la tabla / módulo del objeto actual.
   - **`G_` (Global):** Analiza elementos de diccionarios principales del sistema como "Totales", "Impuestos", forzando a Vtiger a invocar el índice general.
   - **`R_` (Related):** Fuerzan lecturas en árbol entre objetos diferentes relacionados, enrutados vía Fallback global para evitar latencia de queries.

## 6. Fusión de Componentes Estructurales (Header / Body / Footer)
**Problema:** Se requería unificar un diseño complejo desglosado de PDFMaker en un único archivo.
**Solución:**
- Se creó una plantilla unificada oficial (`Quote_Template.html`) fusionándolos todos. 
- Se adaptaron los encabezados flotantes utilizando inyectores nativos y reglas CSS de paginación interna específicas de mPDF (`@page`, `<htmlpageheader>`, `<htmlpagefooter>`) para inyectar bordes elegantes y evitar colisiones visuales entre el cuerpo dinámico del archivo de productos y el pie de página publicitario.

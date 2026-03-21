# WordExport Extension - Documentación

Documentación completa de la extensión WordExport para Vtiger CRM 7.1

## 📚 Documentos Incluidos

### 1. [ESPECIFICACIONES.md](./ESPECIFICACIONES.md)
Especificaciones completas y funcionalidades de WordExport.

**Contenido:**
- Descripción general del módulo
- Funcionalidades principales
- Campos soportados (Cotización, Contacto, Potencial, Usuario, Productos)
- Características técnicas
- 4 templates profesionales incluidos
- Requisitos y dependencias
- Instrucciones de instalación
- Guía de uso
- Configuración y personalización
- Limitaciones conocidas
- Solución de problemas

**Audiencia:** Usuarios finales, administradores Vtiger

---

### 2. [GUIA_DESARROLLADOR.md](./GUIA_DESARROLLADOR.md)
Guía técnica para desarrolladores que necesitan extender o mantener el módulo.

**Contenido:**
- Estructura del código
- Métodos principales y su funcionamiento
- Sistema de variables y reemplazos
- Cómo agregar nuevas variables
- Guía para crear nuevos templates
- Proceso de compilación y empaquetamiento
- Estructura de manifest.xml
- Testing y debugging
- Resolución de errores comunes
- Cómo extender funcionalidad
- Optimización de performance
- Consideraciones de seguridad

**Audiencia:** Desarrolladores, técnicos Vtiger

---

## 🚀 Quick Start

### Instalación Rápida
1. Descargar `WordExport.zip`
2. Vtiger → Configuración → Extensiones → Instalar desde ZIP
3. Seleccionar `WordExport.zip`
4. ✅ Listo para usar

### Uso Básico
1. Abrir registro de Cotización
2. Botón "Exportar a Word" o "Exportar a PDF"
3. Seleccionar template profesional
4. Descargar automáticamente

---

## 📋 Variables Principales

### Cotización
```
$QUOTES_QUOTE_NO$
$QUOTES_ACCOUNT_ID$
$QUOTES_TERMS_CONDITIONS$
```

### Contacto
```
$R_CONTACTID_FIRSTNAME$
$R_CONTACTID_LASTNAME$
$R_CONTACTID_SALUTATIONTYPE$
```

### Potencial (Marca/Equipo)
```
$R_POTENTIALID_CF_984$
```

### Productos
```
#PRODUCTBLOC_START# ... #PRODUCTBLOC_END#
$PRODUCTTITLE$, $PRODUCTQUANTITY$, $PRODUCTSTOTALAFTERDISCOUNT$
```

### Totales
```
$TOTALWITHOUTVAT$, $TOTALDISCOUNT$, $VAT$, $VATPERCENT$, $TOTAL$
```

Para lista completa, ver [ESPECIFICACIONES.md](./ESPECIFICACIONES.md#24-campos-soportados)

---

## 🎨 Templates Incluidos

| Template | Estilo | Color | Tipografía |
|----------|--------|-------|-----------|
| Professional_v2 | Profesional | Azul/Gris | Arial |
| Executive_v2 | Ejecutivo | Negro | Georgia |
| Modern_v2 | Contemporáneo | Gradiente Azul | System Font |
| PDFMaker_Style | PDFMaker | Gris | Verdana |

Todos incluyen:
- ✅ Layout 2 columnas (cliente | datos)
- ✅ Tabla de productos
- ✅ Totales calculados
- ✅ Footer con 3 columnas
- ✅ Términos en página separada

---

## 🔧 Caracteristicas Técnicas

### Formatos Soportados
- **Word (.docx)** - Usando PHPWord
- **PDF** - Usando mPDF

### Módulos Soportados
- Quotes (Cotizaciones)
- SalesOrder (Órdenes Venta)
- Invoice (Facturas)
- PurchaseOrder (Órdenes Compra)

### Variables Automáticas
- Logo de empresa (búsqueda inteligente)
- Porcentaje de impuesto (calculado automáticamente)
- Traducción de etiquetas al español
- Conversión de saltos de línea en campos largos

---

## 📁 Estructura de Archivos

```
WordExport_Extension/
├── modules/WordExport/
│   ├── actions/Export.php          ← Procesador principal
│   ├── templates/                  ← Templates HTML
│   ├── language/en_us.lang.php     ← Traducciones
│   └── ...
├── build.sh                        ← Compilación
├── pack.php                        ← Empaquetamiento
├── manifest.xml                    ← Configuración Vtiger
└── Specs/                          ← Esta documentación
    ├── README.md                   (este archivo)
    ├── ESPECIFICACIONES.md
    └── GUIA_DESARROLLADOR.md
```

---

## ⚙️ Requisitos

- **Vtiger CRM 7.1+**
- **PHP 7.2+**
- **mPDF 8.0+** (incluido)
- **PHPWord 0.18+** (incluido)

---

## 🐛 Solución de Problemas

### P: Las variables no se reemplazan
**R:** Verificar que nombre de variable sea exacto. Ver sección de [campos soportados](./ESPECIFICACIONES.md#24-campos-soportados).

### P: Logo no aparece en PDF
**R:** Verificar ubicación del archivo logo en una de las rutas búsqueda (ver [ESPECIFICACIONES.md](./ESPECIFICACIONES.md#34-logo-de-empresa)).

### P: PDF con layout roto
**R:** mPDF no soporta CSS Grid/Flexbox. Usar tablas HTML (todos los templates incluidos usan tablas).

### P: Error "Template not found"
**R:** Verificar nombre de archivo en `/modules/WordExport/templates/`

Para más problemas, consultar [sección de troubleshooting](./ESPECIFICACIONES.md#12-solución-de-problemas)

---

## 🔗 Enlaces Útiles

- **Vtiger CRM:** https://www.vtiger.com/
- **mPDF:** https://mpdf.github.io/
- **PHPWord:** https://github.com/PHPOffice/PHPWord

---

## 📝 Notas

### Acerca de mPDF
mPDF es un renderer PDF que interpreta HTML/CSS. Tiene algunas limitaciones:
- ❌ No soporta CSS Grid/Flexbox (usar tablas)
- ❌ No soporta efectos hover/animaciones
- ✅ Soporta HTML simple y CSS básico

### Ancho de Página
Por compatibilidad máxima con mPDF:
- Usar ancho fijo de **180mm** para A4 con márgenes 15mm
- Usar **tablas HTML** para layouts
- Evitar **posicionamiento flotante** complejo

### Rendimiento
- Exportación de 50+ productos puede ser lenta
- mPDF requiere memoria temporal
- Considerar servidor con 512MB+ RAM disponible

---

## 📊 Versión

**WordExport Extension v1.0**
- Última actualización: Marzo 2026
- Compatible con: Vtiger CRM 7.1
- Motor PDF: mPDF 8.0+

---

## 📞 Contacto y Soporte

Para reportar bugs o solicitar features, contactar al equipo de desarrollo.

---

## 📄 Documentación Relacionada

| Documento | Audiencia | Propósito |
|-----------|-----------|----------|
| [ESPECIFICACIONES.md](./ESPECIFICACIONES.md) | Usuarios/Admin | Referencia completa |
| [GUIA_DESARROLLADOR.md](./GUIA_DESARROLLADOR.md) | Desarrolladores | Guía técnica |
| manifest.xml | Sistema Vtiger | Configuración módulo |
| en_us.lang.php | Sistema traducción | Textos traducibles |

---

**Happy Exporting! 📄✨**

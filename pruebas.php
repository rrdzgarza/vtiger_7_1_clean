<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start();

echo "<h1>WordExport - Pruebas de Diagnóstico Intensivo</h1>";
echo "<p>Versión de PHP: " . phpversion() . "</p>";

// 1. Carga de dependencias
$composerLoaded = false;
$vendorPath = '';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $composerLoaded = true;
    $vendorPath = 'Raíz de Vtiger';
}

echo "<h3>Estado Base</h3>";
echo "<ul>";
echo "<li>Autoload de Composer: " . ($composerLoaded ? "<span style='color:green;'>Exitoso ($vendorPath)</span>" : "<span style='color:red;'>Fallo (No encontrado)</span>") . "</li>";
echo "<li>Clase mPDF disponible: " . (class_exists('\Mpdf\Mpdf') ? "<span style='color:green;'>Sí</span>" : "<span style='color:red;'>No</span>") . "</li>";
echo "</ul>";

echo "<hr>";

// Acciones basadas en GET
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (empty($action)) {
    // Menú de Pruebas
    echo "
    <table border='1' cellpadding='10' cellspacing='0'>
        <tr style='background:#f0f0f0;'>
            <th>Prueba</th>
            <th>Descripción</th>
            <th>Acción</th>
        </tr>
        <tr>
            <td><strong>Prueba 1: Renderizado HTML Básico</strong></td>
            <td>Imprime un 'Hola Mundo' simple en un DOM de HTML normal. Ayuda a confirmar que el script no muere por dependencias de plantillas.</td>
            <td><a href='?action=test1'><button>Ejecutar</button></a></td>
        </tr>
         <tr>
            <td><strong>Prueba 2: Reemplazo de Variables PHP</strong></td>
            <td>Renderiza un HTML simulando el reemplazo de varibales (\$NOMBRE\$ -> Juan). Valida que las expresiones regulares y funciones string no causen Crash.</td>
            <td><a href='?action=test2'><button>Ejecutar</button></a></td>
        </tr>
        <tr>
            <td><strong>Prueba 3: Generación PDF Mínimo (mPDF nativo)</strong></td>
            <td>Intenta generar un layout de PDF puramente de texto 'Hola mundo', forzando el formato A4 en UTF8 purísimo. Sin tocar funciones complejas.</td>
            <td><a href='?action=test3'><button>Ejecutar</button></a></td>
        </tr>
        <tr>
            <td><strong>Prueba 4: Escritura a Archivo Temp (Permisos)</strong></td>
            <td>Genera el PDF mínimo pero intenta guardarlo en disco en sys_get_temp_dir() y luego forzar la descarga de binarios, imitando el proceso en Export.php.</td>
            <td><a href='?action=test4'><button>Ejecutar</button></a></td>
        </tr>
        <tr>
            <td><strong>Prueba 5: Carga del kernel Vtiger (Sin PDF)</strong></td>
            <td>Incluye los archivos 'vtlib/Vtiger/Module.php' locales para determinar si el problema recae en incluir librerías legacy de vtiger en el entorno CLI/Docker.</td>
            <td><a href='?action=test5'><button>Ejecutar</button></a></td>
        </tr>
    </table>
    ";
} else {
    echo "<a href='pruebas.php'>&laquo; Volver al menú de pruebas</a><hr>";

    try {
        switch ($action) {
            case 'test1':
                echo "<h2>Resultado Prueba 1: Éxito</h2>";
                echo "<p style='font-size:20px; color:blue;'>Hola Mundo Pruebas</p>";
                break;

            case 'test2':
                $html = "<p>El paciente \$NOMBRE\$ de \$EDAD\$ años.</p>";
                $html = str_replace('$NOMBRE$', 'Ricardo', $html);
                $html = str_replace('$EDAD$', '35', $html);
                echo "<h2>Resultado Prueba 2: Éxito</h2>";
                echo "<div style='border:1px solid #ccc; padding:10px;'>" . $html . "</div>";
                break;

            case 'test3':
                if (!class_exists('\Mpdf\Mpdf')) {
                    throw new Exception("La clase \Mpdf\Mpdf no existe en memoria. Composer falló.");
                }
                $mpdfTempCache = sys_get_temp_dir() . '/mpdf_exportCache';
                if (!is_dir($mpdfTempCache)) {
                    mkdir($mpdfTempCache, 0777, true);
                }
                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'tempDir' => $mpdfTempCache
                ]);
                $mpdf->WriteHTML('<h1>Prueba 3: Generación Estándar</h1><p>Si ves esto, mPDF generó binarios PDF al vuelo correctamente.</p>');
                $mpdf->Output('prueba3.pdf', \Mpdf\Output\Destination::INLINE);
                exit; // Parar PHP para enviar Headers de PDF nativos
                break;

            case 'test4':
                if (!class_exists('\Mpdf\Mpdf')) {
                    throw new Exception("La clase \Mpdf\Mpdf no existe. Composer falló.");
                }
                $tempFileName = tempnam(sys_get_temp_dir(), 'TestExport');
                echo "Intento de escritura en: $tempFileName <br>";

                $mpdfTempCache = sys_get_temp_dir() . '/mpdf_exportCache';
                if (!is_dir($mpdfTempCache)) {
                    mkdir($mpdfTempCache, 0777, true);
                }
                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'tempDir' => $mpdfTempCache
                ]);
                $mpdf->WriteHTML('<h1>Prueba 4</h1><p>Guardado a disco exitoso.</p>');
                // Guardar en disco
                $mpdf->Output($tempFileName, \Mpdf\Output\Destination::FILE);

                if (file_exists($tempFileName)) {
                    $size = filesize($tempFileName);
                    echo "<strong style='color:green;'>¡Éxito! PDF compilado y archivo guardado correctamente. ($size bytes)</strong><br>";
                    // Limpieza
                    unlink($tempFileName);
                } else {
                    echo "<strong style='color:red;'>Fallo: El mPDF corrió pero no pudo crear el archivo temporal en disco. Permisos fallaron.</strong>";
                }
                break;

            case 'test5':
                // Simulamos cargar el root base de Vtiger y sus variables
                require_once 'includes/Loader.php';
                require_once 'includes/runtime/LanguageHandler.php';
                echo "<h2 style='color:green;'>Éxito: El Core de Vtiger cargó sin arrojar Fatal Errors por memoria.</h2>";
                break;

            default:
                echo "Prueba no encontrada.";
        }
    } catch (\Throwable $e) {
        echo "<div style='background:red; color:white; padding:20px;'>
              <h2>FATAL ERROR ATRAPADO:</h2>
              <p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>
              <p><strong>Archivo:</strong> " . $e->getFile() . "</p>
              <p><strong>Línea:</strong> " . $e->getLine() . "</p>
              <pre>" . $e->getTraceAsString() . "</pre>
              </div>";
    }
}

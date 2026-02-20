#!/bin/bash
# 5-install-mpdf.sh - Instala dependencias de composer (mPDF) globalmente en Vtiger
set -e

# Solo procedemos si composer y PHP están disponibles
if ! command -v composer &> /dev/null; then
    echo "⚠️ Composer no está instalado. Saltando instalación de mPDF."
    exit 0
fi

if ! command -v php &> /dev/null; then
    echo "⚠️ PHP CLI no está disponible. Saltando instalación de mPDF."
    exit 0
fi

echo "========================================================="
echo " Instalando/Verificando dependencias Composer (mPDF)..."
echo "========================================================="

cd /var/www/html

# Obtenemos versión de PHP (ej. 7.2, 7.4, 8.1)
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

echo "-> Versión de PHP detectada en contenedor: $PHP_VERSION"

# Definimos versión de mPDF basada en PHP
if [ "$PHP_VERSION" = "8.1" ] || [ "$PHP_VERSION" = "8.2" ]; then
    MPDF_REQ="mpdf/mpdf:^8.1"
else
    # 7.2, 7.3, 7.4
    MPDF_REQ="mpdf/mpdf:^8.0"
fi

# Comprobamos si la carpeta vendor/mpdf ya existe para evitar llamadas repetidas
if [ -d "/var/www/html/vendor/mpdf/mpdf" ]; then
    echo "-> mPDF ya está instalado en /var/www/html/vendor/. Saltando composer require."
else
    echo "-> Ejecutando: composer require $MPDF_REQ en /var/www/html/"
    export COMPOSER_ALLOW_SUPERUSER=1
    
    # Init composer info si no existe
    if [ ! -f "composer.json" ]; then
        echo '{}' > composer.json
    fi

    if composer require "$MPDF_REQ" --no-interaction --quiet; then
        echo "✅ mPDF instalado correctamente para PHP $PHP_VERSION."
    else
        echo "⚠️ Falló la instalación de mPDF vía Composer."
    fi
fi


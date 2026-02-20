#!/bin/bash
# Build script for WordExport Module

echo "Building WordExport Module..."

# 1. Install Dependencies locally
# 1. (Composer install skipped, vendor folder already exists)


# 2. Cleanup previous build
rm -f WordExport.zip

# 3. Zip files
echo "Creating ZIP package..."
zip -qr WordExport.zip manifest.xml modules layouts -x "*.DS_Store" -x "__MACOSX/*" -x "*.git*"

echo "Done! WordExport.zip created."

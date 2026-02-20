<?php
$zipFileName = 'WordExport.zip';
if (file_exists($zipFileName)) {
    unlink($zipFileName);
}

$zip = new ZipArchive();
if ($zip->open($zipFileName, ZipArchive::CREATE) !== TRUE) {
    exit("Cannot open <$zipFileName>\n");
}

$directoriesToZip = ['modules', 'layouts'];
$filesToZip = ['manifest.xml'];

foreach ($directoriesToZip as $dir) {
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            $filePath = $file->getPathname();
            // Skip macOS hidden files
            if (strpos($filePath, '.DS_Store') !== false || strpos($filePath, '__MACOSX') !== false) {
                continue;
            }
            $zip->addFile($filePath, $filePath);
        }
    }
}

foreach ($filesToZip as $file) {
    if (file_exists($file)) {
        $zip->addFile($file, $file);
    }
}

echo "Zip created with " . $zip->numFiles . " files.\n";
$zip->close();
?>
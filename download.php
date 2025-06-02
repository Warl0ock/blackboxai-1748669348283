<?php
// Set the file name for the zip archive
$zipname = 'employee_management_system.zip';

// Create new zip object
$zip = new ZipArchive();

// Create a temporary file to store the zip
$tmp_file = tempnam('.', '');

// Open the zip file
if ($zip->open($tmp_file, ZipArchive::CREATE) !== TRUE) {
    die("Could not open archive");
}

// List of directories to include
$dirs = array('.', 'includes', 'sql');

// Add files from directories
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(realpath('.')) + 1);
                
                // Skip the temporary files and .git directory
                if (strpos($relativePath, 'temp') === false && 
                    strpos($relativePath, '.git') === false &&
                    $file->getFilename() != '.' && 
                    $file->getFilename() != '..' &&
                    $file->getFilename() != '.gitignore' &&
                    $file->getFilename() != 'download.php' &&
                    $file->getFilename() != $zipname) {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
    }
}

// Close the zip file
$zip->close();

// Set headers for download
header('Content-Type: application/zip');
header('Content-disposition: attachment; filename=' . $zipname);
header('Content-Length: ' . filesize($tmp_file));

// Send the file to the browser
readfile($tmp_file);

// Delete the temporary file
unlink($tmp_file);
?>

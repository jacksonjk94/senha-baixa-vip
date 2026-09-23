<?php
// Define the base path for the project
function base_path($path = '') {
    return dirname(__DIR__) . ($path ? DIRECTORY_SEPARATOR . $path : '');
}

// Function to delete a directory and its contents
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($filePath) ? deleteDirectory($filePath) : unlink($filePath);
    }
    rmdir($dir);
    echo "Deleted directory: $dir<br>";
}

// Define the paths to the tar.gz files
$filesToExtract = [
    base_path('node_modules.tar.gz'),
    base_path('vendor.tar.gz')
];

// Directories to delete
$directoriesToDelete = [
    base_path('node_modules'),
    base_path('vendor')
];

// Delete the directories only if the corresponding tar.gz file exists
foreach ($directoriesToDelete as $index => $dir) {
    if (file_exists($filesToExtract[$index])) {
        deleteDirectory($dir);
    } else {
        echo "Skipping deletion of $dir because the file {$filesToExtract[$index]} does not exist.<br>";
    }
}

// Function to extract a tar.gz file
function extractTarGz($filePath, $destination) {
    if (!file_exists($filePath)) {
        echo "File not found: $filePath<br>";
        return false;
    }

    echo "Starting extraction of: $filePath<br>";

    try {
        $phar = new PharData($filePath);

        // Remove existing .tar file if it exists
        $tarFile = str_replace('.gz', '', $filePath);
        if (file_exists($tarFile)) {
            echo "Removing existing tar file: $tarFile<br>";
            unlink($tarFile);
        }

        // Decompress the .tar.gz file to .tar
        echo "Decompressing: $filePath<br>";
        $phar->decompress(); // Creates the .tar file

        // Extract the .tar file
        echo "Extracting tar file: $tarFile to $destination<br>";
        $pharTar = new PharData($tarFile);
        $pharTar->extractTo($destination, null, true); // Extract contents, overwrite if necessary

        // Clean up the .tar file
        echo "Removing tar file: $tarFile<br>";
        unlink($tarFile);

        // Clean up the original .tar.gz file
        echo "Removing original tar.gz file: $filePath<br>";
        unlink($filePath);

        echo "Extraction completed for: $filePath<br>";
        return true;
    } catch (Exception $e) {
        echo "Error extracting $filePath: " . $e->getMessage() . "<br>";
        return false;
    }
}

// Loop through each file and extract it
foreach ($filesToExtract as $file) {
    $destination = dirname($file); // Extract to the same directory as the file
    extractTarGz($file, $destination);
}

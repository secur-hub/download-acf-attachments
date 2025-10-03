<?php
/**
 * Serve the temporary ZIP file for download
 * Plugin prefix: bh_
 */

include_once("../../../wp-load.php");

// Only logged-in users can download
if (!is_user_logged_in()) {
    auth_redirect();
}

// Check GET parameters
if (isset($_GET['bh_pretty_filename'], $_GET['bh_real_filename']) && !empty($_GET['bh_real_filename'])) {

    $pretty_filename = sanitize_file_name($_GET['bh_pretty_filename']);
    $real_filename   = sanitize_file_name($_GET['bh_real_filename']);

    $upload_dir = wp_upload_dir();
    $file_path  = $upload_dir['path'] . '/' . $real_filename;

    if (!file_exists($file_path)) {
        http_response_code(404);
        exit('ZIP file not found.');
    }

    // Clean any previous output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Headers for ZIP download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $pretty_filename . '.zip"');
    header('Content-Length: ' . filesize($file_path));
    header('Connection: close');

    // Read and send the file
    readfile($file_path);
    flush();

    // Remove the temporary file after download
    @unlink($file_path);

    exit;
} else {
    http_response_code(400);
    exit('Invalid parameters.');
}

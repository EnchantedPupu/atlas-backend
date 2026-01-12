<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is PP role
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

if ($_SESSION['user_role'] !== 'PP') {
    http_response_code(403);
    echo 'Access denied. This endpoint is only for PP role.';
    exit;
}

// Get filename from request
$filename = isset($_GET['file']) ? $_GET['file'] : '';

if (!$filename) {
    http_response_code(400);
    echo 'Filename is required';
    exit;
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);

$userId = $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Verify that this file belongs to the current PP user
    $stmt = $db->prepare("SELECT sf.id, sf.attachment_name, sf.description, sj.surveyjob_no 
                         FROM sj_files sf 
                         JOIN surveyjob sj ON sf.surveyjob_id = sj.survey_job_id
                         WHERE sf.attachment_name = ? AND sf.pp_id = ?");
    $stmt->execute([$filename, $userId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        echo 'File not found or access denied';
        exit;
    }
    
    $filePath = '../uploads/pp_files/' . $filename;
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo 'File not found on server';
        exit;
    }
    
    // Get file info
    $filesize = filesize($filePath);
    $fileinfo = pathinfo($filePath);
    $extension = isset($fileinfo['extension']) ? $fileinfo['extension'] : '';
    
    // Set appropriate content type
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'dwg' => 'application/acad',
        'dxf' => 'application/dxf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff'
    ];
    
    $contentType = isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream';
    
    // Extract original filename
    $parts = explode('_', $filename, 3);
    $originalName = (count($parts) >= 3) ? substr($parts[2], strlen($parts[1]) + 1) : $filename;
    
    // Set headers for file download
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . $filesize);
    header('Content-Disposition: attachment; filename="' . $originalName . '"');
    header('Cache-Control: private');
    header('Pragma: private');
    header('Expires: 0');
    
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Output file
    readfile($filePath);
    exit;
    
} catch(Exception $e) {
    error_log("Download PP File Error: " . $e->getMessage());
    http_response_code(500);
    echo 'Error downloading file';
    exit;
}
?>

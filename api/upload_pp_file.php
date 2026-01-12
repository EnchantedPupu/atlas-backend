<?php
// api/upload_pp_file.php
// Handles file uploads for PP My Tasks

session_start();
require_once __DIR__ . '/../config/database.php';

define('UPLOAD_DIR', __DIR__ . '/../uploads/pp_files/');

date_default_timezone_set('Asia/Kuala_Lumpur');

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

// Check required fields
$survey_job_id = 0;
if (isset($_POST['survey_job_id'])) {
    $survey_job_id = intval($_POST['survey_job_id']);
} elseif (isset($_POST['modalSurveyJobId'])) {
    $survey_job_id = intval($_POST['modalSurveyJobId']);
}
if ($survey_job_id <= 0) {
    echo json_encode(['error' => 'Missing or invalid survey job ID.']);
    exit;
}

// Description is now compulsory
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
if ($description === '') {
    echo json_encode(['error' => 'Description is required.']);
    exit;
}

// Check for file - accept both 'fileInput' and 'file' field names
$fileField = null;
if (isset($_FILES['fileInput']) && $_FILES['fileInput']['error'] === UPLOAD_ERR_OK) {
    $fileField = 'fileInput';
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $fileField = 'file';
}

if (!$fileField) {
    echo json_encode(['error' => 'No file uploaded or upload error.']);
    exit;
}

$file = $_FILES[$fileField];
$original_name = $file['name'];
$tmp_name = $file['tmp_name'];
$file_size = $file['size'];

// Validate file size (max 20MB)
if ($file_size > 20 * 1024 * 1024) {
    echo json_encode(['error' => 'File size exceeds 20MB limit.']);
    exit;
}

// Validate file extension
$allowed_ext = ['pdf', 'dwg', 'dxf', 'jpg', 'jpeg', 'png', 'tiff', 'tif'];
$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext)) {
    echo json_encode(['error' => 'Invalid file type.']);
    exit;
}

// Ensure upload dir exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Generate unique filename
$unique = uniqid('', true) . '_' . time();
$attachment_name = $unique . '.' . $ext;
$target_path = UPLOAD_DIR . $attachment_name;

if (!move_uploaded_file($tmp_name, $target_path)) {
    echo json_encode(['error' => 'Failed to move uploaded file.']);
    exit;
}

// Insert into sj_files table
try {
    $database = new Database();
    $db = $database->getConnection();

    // Optionally check/add created_at column if needed (as in my_tasks.php)
    try {
        $result = $db->query("SHOW COLUMNS FROM sj_files LIKE 'created_at'");
        if ($result->rowCount() == 0) {
            $db->exec("ALTER TABLE sj_files ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }
    } catch (Exception $e) {
        error_log("Database column check/creation error: " . $e->getMessage());
    }

    $pp_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    if (!$pp_id) {
        echo json_encode(['error' => 'User not logged in.']);
        exit;
    }

    $stmt = $db->prepare('INSERT INTO sj_files (surveyjob_id, pp_id, attachment_name, description, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$survey_job_id, $pp_id, $attachment_name, $description]);
} catch (Exception $e) {
    // Remove file if DB insert fails
    @unlink($target_path);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'File uploaded successfully.']);

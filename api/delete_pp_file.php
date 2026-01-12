<?php
// api/delete_pp_file.php
// Handles file deletion for PP My Tasks

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    echo json_encode(['error' => 'User not logged in.']);
    exit;
}

if ($_SESSION['user_role'] !== 'PP') {
    echo json_encode(['error' => 'Access denied. This endpoint is only for PP role.']);
    exit;
}

$userId = $_SESSION['user_id'];
$fileId = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;

if (!$fileId) {
    echo json_encode(['error' => 'Missing file ID.']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get file info and check permission: allow if file belongs to a job assigned to this user
    $stmt = $db->prepare('SELECT sf.attachment_name FROM sj_files sf INNER JOIN surveyjob sj ON sf.surveyjob_id = sj.survey_job_id WHERE sf.id = ? AND sj.assigned_to = ?');
    $stmt->execute([$fileId, $userId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        echo json_encode(['error' => 'File not found or you do not have permission to delete it.']);
        exit;
    }

    // Delete from database
    $stmt = $db->prepare('DELETE FROM sj_files WHERE id = ?');
    $stmt->execute([$fileId]);

    // Delete physical file
    $filePath = __DIR__ . '/../uploads/pp_files/' . $file['attachment_name'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    echo json_encode(['success' => true, 'message' => 'File deleted successfully.']);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

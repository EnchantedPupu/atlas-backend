<?php
session_start();
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is PP role
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

if ($_SESSION['user_role'] !== 'PP') {
    echo json_encode(['error' => 'Access denied. This endpoint is only for PP role.']);
    exit;
}

// Get survey job ID from request
$surveyJobId = isset($_GET['survey_job_id']) ? intval($_GET['survey_job_id']) : 0;

if (!$surveyJobId) {
    echo json_encode(['error' => 'Survey Job ID is required']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Verify that this survey job is assigned to the current PP user
    $stmt = $db->prepare("SELECT survey_job_id FROM surveyjob WHERE survey_job_id = ? AND assigned_to = ?");
    $stmt->execute([$surveyJobId, $userId]);
    $jobExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$jobExists) {
        echo json_encode(['error' => 'Survey job not found or not assigned to you']);
        exit;
    }
    
    // Get files uploaded by this PP user for this survey job
    $sql = "SELECT 
                sf.id,
                sf.attachment_name,
                sf.description,
                IFNULL(sf.created_at, NOW()) as created_at,
                u.name as uploaded_by_name
            FROM sj_files sf
            LEFT JOIN user u ON sf.pp_id = u.user_id
            WHERE sf.surveyjob_id = ?
            ORDER BY sf.id DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$surveyJobId]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add original filename (extract from attachment_name if needed)
    foreach ($files as &$file) {
        // Try to extract original filename from the unique filename
        $parts = explode('_', $file['attachment_name'], 3);
        if (count($parts) >= 3) {
            $file['original_name'] = substr($parts[2], strlen($parts[1]) + 1);
        } else {
            $file['original_name'] = $file['attachment_name'];
        }
    }
    
    echo json_encode($files);
    
} catch(PDOException $e) {
    error_log("Get PP Files Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    error_log("Get PP Files Error: " . $e->getMessage());
    echo json_encode(['error' => 'Error loading files: ' . $e->getMessage()]);
}
?>

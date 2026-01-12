<?php
session_start();
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Get job ID from request
$jobId = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

if (!$jobId) {
    echo json_encode(['error' => 'Job ID is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
      // Query to get job details - using exact table names
    $sql = "SELECT 
                sj.survey_job_id,
                sj.surveyjob_no,
                sj.hq_ref,
                sj.div_ref,
                sj.projectname,
                sj.attachment_name,
                sj.status,
                sj.pbtstatus,
                sj.created_at,
                sj.updated_at,
                sj.other_attachment,
                creator.name as created_by_name,
                creator.role as created_by_role,
                assignee.name as assigned_to_name,
                assignee.role as assigned_to_role
            FROM surveyjob sj
            LEFT JOIN user creator ON sj.created_by = creator.user_id
            LEFT JOIN user assignee ON sj.assigned_to = assignee.user_id
            WHERE sj.survey_job_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        echo json_encode(['error' => 'Job not found']);
        exit;
    }
    
    // Get forms count and details - using exact table name
    $formsStmt = $db->prepare("
        SELECT 
            COUNT(*) as form_count,
            GROUP_CONCAT(DISTINCT form_type ORDER BY form_type) as form_types
        FROM forms 
        WHERE surveyjob_id = ?
    ");
    $formsStmt->execute([$jobId]);
    $formsInfo = $formsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Add forms information to job data
    $job['form_count'] = (int)($formsInfo['form_count'] ?? 0);
    $job['form_types'] = $formsInfo['form_types'] ? explode(',', $formsInfo['form_types']) : [];
    
    // Get additional attachments from sj_files table
    $sjFilesStmt = $db->prepare("
        SELECT 
            id,
            attachment_name,
            description,
            created_at
        FROM sj_files 
        WHERE surveyjob_id = ?
        ORDER BY created_at DESC
    ");
    $sjFilesStmt->execute([$jobId]);
    $sjFiles = $sjFilesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format sj_files dates and add to job data
    foreach ($sjFiles as &$file) {
        if ($file['created_at']) {
            $file['created_at'] = date('d/m/Y H:i', strtotime($file['created_at']));
        }
    }
    $job['sj_files'] = $sjFiles;
    
    // Format dates 
    if ($job['created_at']) {
        $job['created_at'] = date('d/m/Y H:i', strtotime($job['created_at']));
    }
    if ($job['updated_at']) {
        $job['updated_at'] = date('d/m/Y H:i', strtotime($job['updated_at']));
    }
    
    // Return job details
    echo json_encode($job);
    
} catch(PDOException $e) {
    error_log("Get Job Details Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    error_log("Get Job Details Error: " . $e->getMessage());
    echo json_encode(['error' => 'Error loading job details: ' . $e->getMessage()]);
}
?>

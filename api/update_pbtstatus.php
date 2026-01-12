<?php
session_start();
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/update_pbtstatus_errors.log');

// Debug: Log the request
error_log("=== UPDATE PBTSTATUS DEBUG START ===");
error_log("POST data: " . print_r($_POST, true));
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
    exit;
}

// Get user info
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

error_log("User ID: {$userId}, User Role: {$userRole}");

// Only VO can mark acquisition complete
if ($userRole !== 'VO') {
    error_log("User role '{$userRole}' not authorized");
    echo json_encode(['success' => false, 'error' => 'Only VO can mark acquisition as complete']);
    exit;
}

// Get and validate input
$jobId = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
$newPbtStatus = isset($_POST['pbtstatus']) ? trim($_POST['pbtstatus']) : '';

if (!$jobId) {
    echo json_encode(['success' => false, 'error' => 'Job ID is required']);
    exit;
}

if (!$newPbtStatus) {
    echo json_encode(['success' => false, 'error' => 'PBT Status is required']);
    exit;
}

// Validate pbtstatus value
$allowedStatuses = ['none', 'checking', 'checked', 'acquisition_complete'];
if (!in_array($newPbtStatus, $allowedStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid PBT Status value']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get current job details
    $stmt = $db->prepare("SELECT survey_job_id, surveyjob_no, projectname, status, pbtstatus, assigned_to FROM SurveyJob WHERE survey_job_id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        echo json_encode(['success' => false, 'error' => 'Job not found']);
        exit;
    }
    
    // Validate that the job is assigned to the current VO user
    if ($job['assigned_to'] != $userId) {
        echo json_encode(['success' => false, 'error' => 'Job is not assigned to you']);
        exit;
    }
    
    // Validate workflow: only allow acquisition_complete if pbtstatus is 'checked'
    if ($newPbtStatus === 'acquisition_complete' && $job['pbtstatus'] !== 'checked') {
        echo json_encode(['success' => false, 'error' => 'Job must be checked by OIC before marking as acquisition complete']);
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Update pbtstatus
        $updateStmt = $db->prepare("UPDATE SurveyJob SET pbtstatus = ?, updated_at = NOW() WHERE survey_job_id = ?");
        $updateResult = $updateStmt->execute([$newPbtStatus, $jobId]);
        
        if (!$updateResult) {
            throw new Exception('Failed to update PBT status');
        }
        
        // Record in job history
        $historyStmt = $db->prepare("
            INSERT INTO JobHistory (survey_job_id, from_user_id, to_user_id, from_role, to_role, action_type, status_before, status_after, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $notes = "PBT Status updated from '{$job['pbtstatus']}' to '{$newPbtStatus}' by VO";
        
        $historyResult = $historyStmt->execute([
            $jobId,
            $userId, // from_user_id
            $userId, // to_user_id (same user)
            $userRole, // from_role
            $userRole, // to_role (same role)
            'pbtstatus_update',
            $job['status'], // status_before (job status unchanged)
            $job['status'], // status_after (job status unchanged)
            $notes
        ]);
        
        if (!$historyResult) {
            throw new Exception('Failed to record job history');
        }
        
        // Commit transaction
        $db->commit();
        
        $successMessage = "PBT Status updated to '{$newPbtStatus}' for job '{$job['surveyjob_no']}' ({$job['projectname']})";
        
        echo json_encode([
            'success' => true,
            'message' => $successMessage,
            'job_id' => $jobId,
            'job_number' => $job['surveyjob_no'],
            'project_name' => $job['projectname'],
            'old_pbtstatus' => $job['pbtstatus'],
            'new_pbtstatus' => $newPbtStatus
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch(PDOException $e) {
    error_log("Update PBT Status Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    error_log("Update PBT Status Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error updating PBT status: ' . $e->getMessage()]);
}
?>

<?php
header('Content-Type: application/json');

session_start();
require_once '../config/database.php';
require_once '../config/role_config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in API response

// Function to send JSON response
function sendResponse($success, $data = null, $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    sendResponse(false, null, 'User not logged in', 401);
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

try {
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if we need to mark tasks as seen
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['mark_as_seen'])) {
        // We don't actually change any data in the database, we just use 
        // a session variable to indicate that the user has seen the notifications
        $_SESSION['tasks_viewed_at'] = date('Y-m-d H:i:s');
        sendResponse(true, null, 'Tasks marked as seen');
        exit;
    }
    
    // Get the timestamp of when the user last viewed tasks
    $lastViewedAt = isset($_SESSION['tasks_viewed_at']) ? $_SESSION['tasks_viewed_at'] : null;
    
    // Query to get jobs user is currently assigned to - matching job_list.php logic
    $sql = "SELECT 
                sj.survey_job_id,
                sj.surveyjob_no,
                sj.projectname,
                sj.status,
                sj.assigned_to,
                sj.created_by,
                sj.created_at,
                sj.updated_at,
                sj.pbtstatus,
                sj.attachment_name,
                sj.other_attachment,
                creator.name as created_by_name,
                assignee.name as assigned_to_name,
                CASE 
                    WHEN sj.assigned_to = ? THEN 'currently_assigned'
                    WHEN sj.created_by = ? THEN 'created_by_me'
                    ELSE 'previously_involved'
                END as involvement_type
            FROM surveyjob sj
            LEFT JOIN user creator ON sj.created_by = creator.user_id
            LEFT JOIN user assignee ON sj.assigned_to = assignee.user_id
            WHERE sj.assigned_to = ? 
               OR sj.created_by = ?
               OR EXISTS (
                   SELECT 1 FROM jobhistory jh 
                   WHERE jh.survey_job_id = sj.survey_job_id 
                   AND (jh.from_user_id = ? OR jh.to_user_id = ?)
               )
            ORDER BY sj.updated_at DESC, sj.created_at DESC";
    
    $params = [$userId, $userId, $userId, $userId, $userId, $userId];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Helper function to check if job is newly assigned to current user
    function isNewlyAssigned($job, $userId) {
        if ($job['assigned_to'] != $userId) return false;
        
        // Check if updated_at exists and is not null before using strtotime
        if (!$job['updated_at'] || $job['updated_at'] === null) {
            return false;
        }
        
        // Check if updated within last 24 hours and status is assigned
        $updatedTime = strtotime($job['updated_at']);
        if ($updatedTime === false) {
            return false;
        }
        
        $dayAgo = time() - (24 * 60 * 60);
        
        return $updatedTime > $dayAgo && $job['status'] === 'assigned';
    }
    
    // Count new tasks using the same logic as job_list.php
    $newTaskCount = count(array_filter($jobs, function($job) use ($userId) { 
        return isNewlyAssigned($job, $userId); 
    }));
    
    sendResponse(true, ['count' => $newTaskCount], 'New tasks count retrieved successfully');
    
} catch (Exception $e) {
    sendResponse(false, null, 'Error: ' . $e->getMessage(), 500);
}
?>

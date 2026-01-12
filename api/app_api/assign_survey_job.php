<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['survey_job_id']) || !isset($input['assigned_to_user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'survey_job_id and assigned_to_user_id are required']);
    exit;
}

$survey_job_id = (int)$input['survey_job_id'];
$assigned_to_user_id = (int)$input['assigned_to_user_id'];
$remark = $input['remark'] ?? '';

// Validate non-empty required fields
if (empty($survey_job_id) || empty($assigned_to_user_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'survey_job_id and assigned_to_user_id cannot be empty']);
    exit;
}

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Check if survey job exists and get current details
    $jobQuery = "SELECT sj.*, u.role as current_assignee_role, u.name as current_assignee_name 
                 FROM surveyjob sj 
                 LEFT JOIN user u ON sj.assigned_to = u.user_id 
                 WHERE sj.survey_job_id = ?";
    $jobStmt = $conn->prepare($jobQuery);
    $jobStmt->execute([$survey_job_id]);
    $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        throw new Exception('Survey job not found');
    }
    
    // Check if target user exists and get their details
    $userQuery = "SELECT user_id, name, role FROM user WHERE user_id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute([$assigned_to_user_id]);
    $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$targetUser) {
        throw new Exception('Target user not found');
    }
    
    // Check if already assigned to the same user
    if ($job['assigned_to'] == $assigned_to_user_id) {
        throw new Exception('Survey job is already assigned to ' . $targetUser['name']);
    }
    
    // Check if survey job has a query in review table and update query_returned if needed
    try {
        $reviewCheckQuery = "SELECT review_id, query_info FROM review WHERE surveyjob_id = ? AND query_info IS NOT NULL";
        $reviewCheckStmt = $conn->prepare($reviewCheckQuery);
        $reviewCheckStmt->execute([$survey_job_id]);
        $reviewData = $reviewCheckStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reviewData && !empty($reviewData['query_info'])) {
            $queryInfo = json_decode($reviewData['query_info'], true);
            
            // Check if query_returned is empty or null
            if (empty($queryInfo['query_returned'])) {
                $queryInfo['query_returned'] = date('Y-m-d');
                
                // Update the query_info in review table with the new query_returned date
                $updateReviewQueryQuery = "UPDATE review SET query_info = ? WHERE review_id = ?";
                $updateReviewQueryStmt = $conn->prepare($updateReviewQueryQuery);
                $updateReviewQueryResult = $updateReviewQueryStmt->execute([json_encode($queryInfo), $reviewData['review_id']]);
                
                if (!$updateReviewQueryResult) {
                    throw new Exception('Failed to update query_returned date in review table');
                }
            }
        }
    } catch (PDOException $e) {
        // Don't throw exception here as this is not critical for assignment
        error_log("Error checking/updating review query_info: " . $e->getMessage());
    }
    
    // Store previous assignment details for history
    $previous_assigned_to = $job['assigned_to'];
    $previous_status = $job['status'];
    $previous_role = $job['current_assignee_role'];
    
    // Update survey job assignment
    $updateQuery = "UPDATE surveyjob 
                    SET assigned_to = ?, 
                        status = 'assigned', 
                        remarks = ?, 
                        updated_at = NOW() 
                    WHERE survey_job_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateResult = $updateStmt->execute([$assigned_to_user_id, $remark, $survey_job_id]);
    
    if (!$updateResult) {
        throw new Exception('Failed to update survey job assignment');
    }
    
    // Insert job history record
    $historyQuery = "INSERT INTO jobhistory 
                     (survey_job_id, from_user_id, to_user_id, from_role, to_role, 
                      action_type, status_before, status_after, notes, created_at) 
                     VALUES (?, ?, ?, ?, ?, 'assigned', ?, 'assigned', ?, NOW())";
    
    $historyNotes = "Job assigned from " . ($previous_role ?? 'Unknown') . " to " . $targetUser['role'];
    if (!empty($remark)) {
        $historyNotes .= " - Remark: " . $remark;
    }
    
    $historyStmt = $conn->prepare($historyQuery);
    $historyResult = $historyStmt->execute([
        $survey_job_id,
        $previous_assigned_to,
        $assigned_to_user_id,
        $previous_role,
        $targetUser['role'],
        $previous_status,
        $historyNotes
    ]);
    
    if (!$historyResult) {
        throw new Exception('Failed to record job history');
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Survey job assigned successfully',
        'data' => [
            'survey_job_id' => $survey_job_id,
            'job_number' => $job['surveyjob_no'],
            'project_name' => $job['projectname'],
            'assigned_to' => [
                'user_id' => $assigned_to_user_id,
                'name' => $targetUser['name'],
                'role' => $targetUser['role']
            ],
            'previous_assigned_to' => [
                'user_id' => $previous_assigned_to,
                'name' => $job['current_assignee_name'],
                'role' => $previous_role
            ],
            'status' => 'assigned',
            'remark' => $remark
        ]
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on database error
    if ($conn && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on general error
    if ($conn && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
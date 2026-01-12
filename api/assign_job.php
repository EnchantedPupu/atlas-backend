<?php
session_start();
require_once '../config/database.php';
require_once '../config/role_config.php';

// Set JSON header
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Check if user is logged in and has proper role
    if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Please log in to access this page.']);
        exit;
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'User session is invalid. Please log in again.']);
        exit;
    }

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    // Debug: Log session information
    error_log("=== ASSIGN JOB API DEBUG START ===");
    error_log("User ID: " . $_SESSION['user_id']);
    error_log("User Role: " . $_SESSION['user_role']);
    
    // Get JSON input instead of POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Log both POST and JSON input for debugging
    error_log("POST data: " . print_r($_POST, true));
    error_log("JSON input: " . print_r($input, true));
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Test database connection
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Validate required fields - check both POST and JSON input
    $job_id = $input['jobId'] ?? $_POST['job_id'] ?? null;
    $assign_to_user = $input['assignToUserId'] ?? $_POST['assign_to_user'] ?? $_POST['assign_to_user_id'] ?? null;
    $remarks = $input['remarks'] ?? $_POST['remarks'] ?? '';
    $query_return_items = $input['queryReturnItems'] ?? $_POST['query_return_items'] ?? [];
    $query_info_data = $input['queryInfoData'] ?? $_POST['query_info_data'] ?? null;
    
    if (empty($job_id)) {
        throw new Exception('Job ID is required');
    }
    
    if (empty($assign_to_user)) {
        throw new Exception('User to assign is required');
    }
    
    $job_id = (int)$job_id;
    $assign_to_user = (int)$assign_to_user;
    
    /*
     * SECURITY POLICY: Query Return Functionality Restriction
     * ======================================================
     * Only Verification Officers (VO) are authorized to use the query return functionality.
     * This includes:
     * - Creating query return items
     * - Processing query information data
     * - Accessing query-related UI elements
     * 
     * This restriction ensures proper workflow control and prevents unauthorized
     * users from initiating queries that should only be handled by verification staff.
     */
    
    // Check if user is VO before processing query return functionality
    $currentUserRole = $_SESSION['user_role'];
    
    // Process query return items - only allowed for VO
    $query_return_string = '';
    if (!empty($query_return_items) && is_array($query_return_items)) {
        if ($currentUserRole !== 'VO') {
            throw new Exception('Query return functionality is only available to Verification Officers (VO)');
        }
        $query_return_string = implode(', ', $query_return_items);
        error_log("Query return items (VO only): " . $query_return_string);
    }
    
    // Process query info data - only allowed for VO
    $query_info_json = null;
    if (!empty($query_info_data) && is_array($query_info_data)) {
        if ($currentUserRole !== 'VO') {
            throw new Exception('Query functionality is only available to Verification Officers (VO)');
        }
        $query_info_json = json_encode($query_info_data);
        error_log("Query info data (VO only): " . $query_info_json);
    }
    
    error_log("Processing assignment: Job ID=" . $job_id . ", Assign to User ID=" . $assign_to_user . ", Remarks=" . $remarks . ", Query Return Items=" . $query_return_string);
      // Check if job exists and get current details
    try {
        $jobCheckStmt = $db->prepare("SELECT sj.*, u.role as current_assignee_role FROM SurveyJob sj LEFT JOIN User u ON sj.assigned_to = u.user_id WHERE sj.survey_job_id = ?");
        if (!$jobCheckStmt) {
            throw new Exception('Failed to prepare job check query');
        }
        
        $jobCheckResult = $jobCheckStmt->execute([$job_id]);
        if (!$jobCheckResult) {
            throw new Exception('Failed to execute job check');
        }
        
        $job = $jobCheckStmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            throw new Exception('Job not found');
        }
        
        error_log("Job found: " . json_encode($job));
        
        // Check if job is in a status that allows reassignment
        if ($job['status'] === 'completed' && $_SESSION['user_role'] !== 'OIC' && $_SESSION['user_role'] !== 'VO') {
            throw new Exception('Cannot reassign completed jobs');
        }
        
    } catch (PDOException $e) {
        throw new Exception('Database error during job validation: ' . $e->getMessage());
    }
    
    // Validate target user exists and get their role
    $assignedUserName = null;
    $assignedUserRole = null;
    try {
        $userCheckStmt = $db->prepare("SELECT user_id, name, role FROM User WHERE user_id = ?");
        if (!$userCheckStmt) {
            throw new Exception('Failed to prepare user check query');
        }
        
        $userCheckResult = $userCheckStmt->execute([$assign_to_user]);
        if (!$userCheckResult) {
            throw new Exception('Failed to execute user check');
        }
        
        $assignedUser = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
        if (!$assignedUser) {
            throw new Exception('Selected user does not exist');
        }
        
        $assignedUserName = $assignedUser['name'];
        $assignedUserRole = $assignedUser['role'];
        error_log("Target user validated: ID=" . $assign_to_user . ", Name=" . $assignedUserName . ", Role=" . $assignedUserRole);
        
    } catch (PDOException $e) {
        throw new Exception('Database error during user validation: ' . $e->getMessage());
    }
    
    // Check if already assigned to the same user
    if ($job['assigned_to'] == $assign_to_user) {
        throw new Exception('Job is already assigned to ' . $assignedUserName);
    }
    
    // Check if survey job has a query in review table and update query_returned if needed
    // This runs every time a job is assigned and updates query_returned if it's empty
    try {
        // Only check for existing queries if this is NOT a new query assignment
        if (empty($query_info_data)) {
            $reviewCheckStmt = $db->prepare("SELECT review_id, query_info FROM review WHERE surveyjob_id = ? AND query_info IS NOT NULL ORDER BY review_id DESC LIMIT 1");
            $reviewCheckStmt->execute([$job_id]);
            $reviewData = $reviewCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reviewData && !empty($reviewData['query_info'])) {
                $queryInfo = json_decode($reviewData['query_info'], true);
                
                // Update query_returned if it's empty (regardless of other conditions)
                if (empty($queryInfo['query_returned']) && 
                    (($currentUserRole === 'AS' && $assignedUserRole === 'FI') || 
                     ($currentUserRole === 'PP' && $assignedUserRole === 'SD'))) {
                    $queryInfo['query_returned'] = date('Y-m-d');
                    
                    // Update the most recent query_info in review table with the new query_returned date
                    $updateReviewQueryStmt = $db->prepare("UPDATE review SET query_info = ? WHERE review_id = ?");
                    $updateReviewQueryResult = $updateReviewQueryStmt->execute([json_encode($queryInfo), $reviewData['review_id']]);
                    
                    if (!$updateReviewQueryResult) {
                        throw new Exception('Failed to update query_returned date in review table');
                    }
                    
                    error_log("Updated query_returned date in existing review record for review_id: " . $reviewData['review_id']);
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error checking/updating review query_info: " . $e->getMessage());
        // Don't throw exception here as this is not critical for assignment
    }
    
    // Determine the new status based on the assignment workflow
    $currentUserRole = $_SESSION['user_role'];
    $newStatus = 'assigned';
    $updatePbtStatus = false;
    $newPbtStatus = $job['pbtstatus']; // Keep current pbtstatus by default
    
    // Special case: SS returning job to VO marks it as completed
    if ($currentUserRole === 'SS' && $assignedUserRole === 'VO') {
        $newStatus = 'completed';
    } elseif (in_array($currentUserRole, ['AS', 'PP']) && $assignedUserRole === 'FI') {
        $newStatus = 'submitted';
    } elseif ($currentUserRole === 'PP' && $assignedUserRole === 'SD') {
        $newStatus = 'submitted';
    }
    
    // New pbtstatus workflow: When VO assigns completed job to OIC for checking
    if ($currentUserRole === 'VO' && $assignedUserRole === 'OIC' && $job['status'] === 'completed') {
        $newStatus = 'assigned'; // Job goes back to assigned status for OIC to check
        $newPbtStatus = 'checking'; // Set pbtstatus to checking
        $updatePbtStatus = true;
    }
    
    // When OIC returns checked job back to VO
    if ($currentUserRole === 'OIC' && $assignedUserRole === 'VO' && $job['pbtstatus'] === 'checking') {
        $newStatus = 'completed'; // Job returns to completed status
        $newPbtStatus = 'checked'; // Set pbtstatus to checked, ready for VO to mark as acquisition complete
        $updatePbtStatus = true;
    }
    
    // Begin transaction
    $db->beginTransaction();
    error_log("Transaction started");
    
    try {
        // Update the job assignment - only update necessary fields to avoid constraint violations
        $sql = "UPDATE SurveyJob SET assigned_to = ?, status = ?, updated_at = NOW()";
        $params = [$assign_to_user, $newStatus];
        
        // Only add remarks if it's not empty to avoid potential constraint issues
        if (!empty($remarks)) {
            $sql .= ", remarks = ?";
            $params[] = $remarks;
        }
        
        // Add pbtstatus update if needed
        if ($updatePbtStatus) {
            $sql .= ", pbtstatus = ?";
            $params[] = $newPbtStatus;
        }
        
        $sql .= " WHERE survey_job_id = ?";
        $params[] = $job_id;
        
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            $errorInfo = $db->errorInfo();
            throw new Exception('Failed to prepare update statement: ' . $errorInfo[2]);
        }
        
        error_log("SQL: " . $sql);
        error_log("Update values: " . json_encode($params));
        
        $executeResult = $stmt->execute($params);
        
        if (!$executeResult) {
            $errorInfo = $stmt->errorInfo();
            error_log("SQL execution failed: " . $errorInfo[2]);
            error_log("SQL State: " . $errorInfo[0]);
            error_log("Error Code: " . $errorInfo[1]);
            
            // Check if it's a constraint violation and provide more specific error
            if ($errorInfo[0] === '23000') {
                throw new Exception('Database constraint violation. Please check if all required fields are properly set.');
            }
            
            throw new Exception('Failed to update job assignment. SQL Error: ' . $errorInfo[2]);
        }
        
        $rowsAffected = $stmt->rowCount();
        error_log("Rows affected by job update: " . $rowsAffected);
        
        if ($rowsAffected === 0) {
            error_log("No rows updated - checking if job exists with ID: " . $job_id);
            throw new Exception('No rows were updated. Job may not exist or assignment unchanged.');
        }
        
        error_log("Job assignment updated successfully. Rows affected: " . $rowsAffected);
        
        // Verify the update was successful by checking the database
        $verifyStmt = $db->prepare("SELECT assigned_to, status, pbtstatus FROM SurveyJob WHERE survey_job_id = ?");
        $verifyStmt->execute([$job_id]);
        $updatedJob = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$updatedJob) {
            throw new Exception('Failed to verify job update - job not found after update');
        }
        
        error_log("Job verification: assigned_to=" . $updatedJob['assigned_to'] . ", status=" . $updatedJob['status'] . ", pbtstatus=" . $updatedJob['pbtstatus']);
        
        if ($updatedJob['assigned_to'] != $assign_to_user) {
            throw new Exception('Job assignment verification failed - assignment did not persist');
        }
        
        // Handle query_info if provided - insert new review record with query_date
        if (!empty($query_info_json)) {
            // Decode the query_info to work with it
            $queryInfoForInsert = json_decode($query_info_json, true);
            
            // When creating a new query, ensure query_date is set and query_returned is empty
            // This represents the initial query being sent out
            if (!empty($query_return_items)) {
                $queryInfoForInsert['query_date'] = date('Y-m-d'); // Set when query is sent
                $queryInfoForInsert['query_returned'] = ''; // Empty until job is returned
                $query_info_json = json_encode($queryInfoForInsert);
                error_log("Created new query_info JSON with query_date: " . $query_info_json);
            }
            
            // Insert new review record for the new query
            $insertReviewStmt = $db->prepare("INSERT INTO review (reviewer_id, surveyjob_id, query_info) VALUES (?, ?, ?)");
            $insertReviewResult = $insertReviewStmt->execute([$_SESSION['user_id'], $job_id, $query_info_json]);
            
            if (!$insertReviewResult) {
                $errorInfo = $insertReviewStmt->errorInfo();
                throw new Exception('Failed to insert review query_info: ' . $errorInfo[2]);
            }
            
            error_log("Inserted new review record with query_info containing query_date");
        }
        
        // Record job history
        $historyStmt = $db->prepare("
            INSERT INTO JobHistory (survey_job_id, from_user_id, to_user_id, from_role, to_role, action_type, status_before, status_after, notes, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$historyStmt) {
            $errorInfo = $db->errorInfo();
            error_log("Failed to prepare history statement: " . $errorInfo[2]);
            throw new Exception('Failed to prepare history statement: ' . $errorInfo[2]);
        }
        
        $actionType = ($newStatus === 'completed') ? 'completed' : (($newStatus === 'submitted') ? 'submitted' : 'assigned');
        $notes = "Job {$actionType} from {$currentUserRole} to {$assignedUserRole}";
        
        // Add remarks to notes if provided
        if (!empty($remarks)) {
            $notes .= " - Remarks: " . $remarks;
        }
        
        // Add query return items to notes if provided
        if (!empty($query_return_string)) {
            $notes .= " - Query Return Items: " . $query_return_string;
        }
        
        // Add pbtstatus information to notes if updated
        if ($updatePbtStatus) {
            $notes .= " (PBT Status: " . $newPbtStatus . ")";
        }
        
        $historyParams = [
            $job_id,
            $_SESSION['user_id'], // from_user_id
            $assign_to_user, // to_user_id
            $currentUserRole, // from_role
            $assignedUserRole, // to_role
            $actionType,
            $job['status'], // status_before
            $newStatus, // status_after
            $notes
        ];
        
        error_log("History insert params: " . json_encode($historyParams));
        
        $historyResult = $historyStmt->execute($historyParams);
        
        if (!$historyResult) {
            $errorInfo = $historyStmt->errorInfo();
            error_log("History insert failed: " . $errorInfo[2]);
            throw new Exception('Failed to record job history: ' . $errorInfo[2]);
        }
        
        $historyRowsAffected = $historyStmt->rowCount();
        error_log("Job history recorded successfully. Rows affected: " . $historyRowsAffected);
        
        // Commit the transaction
        if (!$db->commit()) {
            $errorInfo = $db->errorInfo();
            error_log("Transaction commit failed: " . $errorInfo[2]);
            throw new Exception('Failed to commit transaction: ' . $errorInfo[2]);
        }
        
        error_log("Transaction committed successfully");
        error_log("=== ASSIGN JOB API DEBUG END ===");
        
        $successMessage = ($newStatus === 'completed') 
            ? "Job '{$job['surveyjob_no']}' ({$job['projectname']}) has been completed and returned to {$assignedUserName}."
            : "Job '{$job['surveyjob_no']}' ({$job['projectname']}) has been successfully assigned to {$assignedUserName}.";
        
        // Add query return information to success message
        if (!empty($query_return_string)) {
            $successMessage .= " Query return items: " . $query_return_string . ".";
        }
        
        // Add pbtstatus information to success message if updated
        if ($updatePbtStatus) {
            if ($newPbtStatus === 'checking') {
                $successMessage .= " Job sent to OIC for checking.";
            } elseif ($newPbtStatus === 'checked') {
                $successMessage .= " Job has been checked and ready for acquisition completion.";
            }
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => $successMessage,
            'job_id' => $job_id,
            'job_number' => $job['surveyjob_no'],
            'project_name' => $job['projectname'],
            'assigned_to_id' => $assign_to_user,
            'assigned_to_name' => $assignedUserName,
            'assigned_to_role' => $assignedUserRole,
            'previous_assignee' => $job['assigned_to'],
            'new_status' => $newStatus,
            'action_type' => $actionType,
            'pbtstatus' => $newPbtStatus,
            'query_info_updated' => !empty($query_info_json),
            'query_return_items' => $query_return_items
        ]);
        
    } catch (Exception $e) {
        // Rollback on any error
        if ($db->inTransaction()) {
            $db->rollback();
            error_log("Transaction rolled back due to error: " . $e->getMessage());
        }
        throw $e;
    }
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Assign Job API PDO Error: " . $e->getMessage());
    error_log("PDO Error Code: " . $e->getCode());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $error]);
} catch(Exception $e) {
    $error = "Error assigning job: " . $e->getMessage();
    error_log("Assign Job API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error]);
}
?>

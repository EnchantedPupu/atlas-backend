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

    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'OIC') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. Only OIC can create jobs.']);
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
    error_log("=== CREATE JOB API DEBUG START ===");
    error_log("User ID: " . $_SESSION['user_id']);
    error_log("User Role: " . $_SESSION['user_role']);
    error_log("POST data: " . print_r($_POST, true));
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Test database connection
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Test if we can query the database
    try {
        $testQuery = $db->query("SELECT COUNT(*) FROM User");
        $userCount = $testQuery->fetchColumn();
        error_log("Database test successful. User count: " . $userCount);
    } catch (Exception $e) {
        throw new Exception('Database query test failed: ' . $e->getMessage());
    }
    
    // Validate all required fields
    $surveyjob_no = trim($_POST['surveyjob_no'] ?? '');
    $hq_ref = trim($_POST['hq_ref'] ?? '');
    $div_ref = trim($_POST['div_ref'] ?? '');
    $projectname = trim($_POST['projectname'] ?? '');
    $target_project = trim($_POST['target_project'] ?? '');
    $assign_to_user = $_POST['assign_to_user'] ?? null;
    
    // Convert empty string to null for assign_to_user
    if (empty($assign_to_user)) {
        $assign_to_user = null;
    } else {
        $assign_to_user = (int)$assign_to_user;
    }
    
    error_log("Processed form data: " . json_encode([
        'surveyjob_no' => $surveyjob_no,
        'hq_ref' => $hq_ref,
        'div_ref' => $div_ref,
        'projectname' => $projectname,
        'target_project' => $target_project,
        'assign_to_user' => $assign_to_user
    ]));
    
    // Check required fields
    if (empty($surveyjob_no)) {
        throw new Exception('Survey Job Number is required');
    }
    if (empty($hq_ref)) {
        throw new Exception('HQ Reference is required');
    }
    if (empty($div_ref)) {
        throw new Exception('Division Reference is required');
    }
    if (empty($projectname)) {
        throw new Exception('Project Name is required');
    }
    if (empty($target_project)) {
        throw new Exception('Projek Sasaran is required');
    }
    
    // Check for duplicate survey job number
    try {
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM SurveyJob WHERE surveyjob_no = ?");
        if (!$checkStmt) {
            throw new Exception('Failed to prepare duplicate check query');
        }
        
        $checkResult = $checkStmt->execute([$surveyjob_no]);
        if (!$checkResult) {
            throw new Exception('Failed to execute duplicate check');
        }
        
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception('Survey Job Number already exists. Please use a different number.');
        }
    } catch (PDOException $e) {
        throw new Exception('Database error during duplicate check: ' . $e->getMessage());
    }
    
    // Handle file upload - simplified for now
    $attachment_path = '';
    if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
        try {
            $uploadResult = handleFileUpload($_FILES['attachment_file']);
            if ($uploadResult['success']) {
                $attachment_path = $uploadResult['path'];
                error_log("File uploaded successfully: " . $attachment_path);
            } else {
                error_log("File upload failed: " . $uploadResult['error']);
                // Don't fail the job creation for file upload issues
                $attachment_path = 'upload_failed.pdf';
            }
        } catch (Exception $e) {
            error_log("File upload exception: " . $e->getMessage());
            $attachment_path = 'upload_error.pdf';
        }
    } else {
        // For testing, allow creation without file
        $uploadError = $_FILES['attachment_file']['error'] ?? 'No file selected';
        error_log("No file uploaded. Error code: " . $uploadError);
        $attachment_path = 'no_file_uploaded.pdf'; // Placeholder
    }
    
    // Validate assigned user if provided
    $assignedUserName = null;
    if ($assign_to_user) {
        try {
            $userCheckStmt = $db->prepare("SELECT user_id, name FROM User WHERE user_id = ?");
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
            error_log("User assignment validated: ID=" . $assign_to_user . ", Name=" . $assignedUserName);
        } catch (PDOException $e) {
            throw new Exception('Database error during user validation: ' . $e->getMessage());
        }
    }
    
    // Begin transaction
    $db->beginTransaction();
    error_log("Transaction started");
    
    try {
        // Prepare the insert statement
        $sql = "INSERT INTO SurveyJob (surveyjob_no, hq_ref, div_ref, projectname, target_project, attachment_name, status, created_by, assigned_to, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            $errorInfo = $db->errorInfo();
            throw new Exception('Failed to prepare insert statement: ' . $errorInfo[2]);
        }
        
        $status = $assign_to_user ? 'assigned' : 'pending';
        $created_by = (int)$_SESSION['user_id'];
        
        // Prepare values for insertion
        $insertValues = [
            $surveyjob_no, 
            $hq_ref, 
            $div_ref, 
            $projectname, 
            $target_project,
            $attachment_path, 
            $status,
            $created_by,
            $assign_to_user
        ];
        
        error_log("SQL: " . $sql);
        error_log("Insert values: " . json_encode($insertValues));
        
        $executeResult = $stmt->execute($insertValues);
        
        if (!$executeResult) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Failed to insert job into database. SQL Error: ' . $errorInfo[2]);
        }
        
        $survey_job_id = $db->lastInsertId();
        if (!$survey_job_id) {
            throw new Exception('Job insertion appeared successful but could not retrieve the job ID');
        }
        
        error_log("Job created with ID: " . $survey_job_id);
        
        // Commit the transaction
        $db->commit();
        error_log("Transaction committed successfully");
        error_log("=== CREATE JOB API DEBUG END ===");
        
        $successMessage = "Job created successfully with Job ID: {$survey_job_id}";
        if ($assignedUserName) {
            $successMessage .= " and assigned to {$assignedUserName}.";
        } else {
            $successMessage .= " You can assign it to a user later.";
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => $successMessage,
            'job_id' => $survey_job_id,
            'assigned_to' => $assignedUserName
        ]);
        
    } catch (Exception $e) {
        // Rollback on any error
        $db->rollback();
        error_log("Transaction rolled back due to error: " . $e->getMessage());
        throw $e;
    }
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Create Job API PDO Error: " . $e->getMessage());
    error_log("PDO Error Code: " . $e->getCode());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $error]);
} catch(Exception $e) {
    $error = "Error creating job: " . $e->getMessage();
    error_log("Create Job API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error]);
}

function handleFileUpload($file) {
    try {
        // Create upload directory
        $uploadDir = '../uploads/jobs/oic/';
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("Failed to create upload directory: " . $uploadDir);
                return ['success' => false, 'error' => 'Failed to create upload directory'];
            }
        }
        
        // Check if directory is writable
        if (!is_writable($uploadDir)) {
            error_log("Upload directory is not writable: " . $uploadDir);
            return ['success' => false, 'error' => 'Upload directory is not writable'];
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'survey_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
        $uploadPath = $uploadDir . $fileName;
        
        error_log("Attempting to upload file to: " . $uploadPath);
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            error_log("File uploaded successfully: " . $uploadPath);
            return ['success' => true, 'path' => $fileName];
        } else {
            error_log("Failed to move uploaded file");
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }
    } catch (Exception $e) {
        error_log("File upload exception: " . $e->getMessage());
        return ['success' => false, 'error' => 'File upload failed: ' . $e->getMessage()];
    }
}
?>

<?php
session_start();
require_once '../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Please log in to access this page.']);
        exit;
    }

    // Get job ID from request
    $jobId = $_GET['job_id'] ?? null;
    
    if (!$jobId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Job ID is required']);
        exit;
    }
    
    $jobId = (int)$jobId;
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check if JobHistory table exists, if not create it
    try {
        $tableCheck = $db->query("SHOW TABLES LIKE 'JobHistory'");
        if ($tableCheck->rowCount() == 0) {
            // Create the table
            $createTable = $db->exec("
                CREATE TABLE `JobHistory` (
                  `history_id` int PRIMARY KEY AUTO_INCREMENT,
                  `survey_job_id` int,
                  `from_user_id` int,
                  `to_user_id` int,
                  `from_role` varchar(50),
                  `to_role` varchar(50),
                  `action_type` varchar(50),
                  `status_before` varchar(255),
                  `status_after` varchar(255),
                  `notes` text,
                  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                  FOREIGN KEY (`survey_job_id`) REFERENCES `SurveyJob` (`survey_job_id`),
                  FOREIGN KEY (`from_user_id`) REFERENCES `User` (`user_id`),
                  FOREIGN KEY (`to_user_id`) REFERENCES `User` (`user_id`)
                )
            ");
            error_log("Created JobHistory table");
        }
    } catch (Exception $e) {
        error_log("Could not check/create JobHistory table: " . $e->getMessage());
        // Continue without history functionality
        echo json_encode(['success' => true, 'history' => [], 'message' => 'History table not available']);
        exit;
    }
    
    // Fetch job history with user details
    $historyStmt = $db->prepare("
        SELECT 
            jh.*,
            from_user.name as from_user_name,
            to_user.name as to_user_name
        FROM JobHistory jh
        LEFT JOIN User from_user ON jh.from_user_id = from_user.user_id
        LEFT JOIN User to_user ON jh.to_user_id = to_user.user_id
        WHERE jh.survey_job_id = ?
        ORDER BY jh.created_at DESC
    ");
    
    $historyStmt->execute([$jobId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format created_at dates
    foreach ($history as &$entry) {
        if ($entry['created_at']) {
            $entry['created_at'] = date('d/m/Y H:i', strtotime($entry['created_at']));
        }
    }
    
    echo json_encode([
        'success' => true,
        'history' => $history,
        'count' => count($history)
    ]);
    
} catch(PDOException $e) {
    error_log("Get Job History API PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    error_log("Get Job History API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Error loading job history: ' . $e->getMessage()]);
}
?>

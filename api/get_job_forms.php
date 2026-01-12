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
    
    // Debug: Check if forms table exists and has data
    error_log("Checking forms for job ID: " . $jobId);
    
    // Fetch forms for the job - using exact table name from your database
    $formsStmt = $db->prepare("
        SELECT 
            form_id,
            surveyjob_id,
            form_type,
            form_data,
            created_at
        FROM forms
        WHERE surveyjob_id = ?
        ORDER BY form_id DESC
    ");
    
    $formsStmt->execute([$jobId]);
    $forms = $formsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($forms) . " forms for job " . $jobId);
    
    // Process forms to extract form metadata from JSON
    $processedForms = [];
    foreach ($forms as $form) {
        error_log("Processing form_id: " . $form['form_id'] . " with data: " . substr($form['form_data'], 0, 100));
        
        $formData = json_decode($form['form_data'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for form_id " . $form['form_id'] . ": " . json_last_error_msg());
            // Still include the form but with basic info
            $formData = ['form' => 'Invalid JSON', 'form_id' => $form['form_type']];
        }
        
        $processedForm = [
            'form_id' => $form['form_id'],
            'surveyjob_id' => $form['surveyjob_id'],
            'form_type' => $form['form_type'],
            'created_at' => $form['created_at'],
            'form_title' => $formData['form'] ?? 'Unknown Form',
            'form_category' => $formData['form_id'] ?? $form['form_type'],
            'form_data' => $formData
        ];
        
        // Handle the 0000-00-00 00:00:00 date issue
        if ($processedForm['created_at'] && $processedForm['created_at'] !== '0000-00-00 00:00:00') {
            $processedForm['created_at_formatted'] = date('d/m/Y H:i', strtotime($processedForm['created_at']));
        } else {
            $processedForm['created_at_formatted'] = 'Not Set';
        }
        
        $processedForms[] = $processedForm;
    }
    
    error_log("Processed " . count($processedForms) . " forms successfully");
    
    echo json_encode([
        'success' => true,
        'forms' => $processedForms,
        'count' => count($processedForms),
        'debug_job_id' => $jobId
    ]);
    
} catch(PDOException $e) {
    error_log("Get Job Forms API PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    error_log("Get Job Forms API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Error loading job forms: ' . $e->getMessage()]);
}
?>

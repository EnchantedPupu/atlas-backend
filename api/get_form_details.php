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

    // Get form ID from request
    $formId = $_GET['form_id'] ?? null;
    
    if (!$formId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Form ID is required']);
        exit;
    }
    
    $formId = (int)$formId;
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Fetch form details with survey job information
    $formStmt = $db->prepare("
        SELECT 
            f.form_id,
            f.surveyjob_id,
            f.form_type,
            f.form_data,
            f.created_at,
            sj.surveyjob_no,
            sj.projectname
        FROM forms f
        LEFT JOIN surveyjob sj ON f.surveyjob_id = sj.survey_job_id
        WHERE f.form_id = ?
    ");
    
    $formStmt->execute([$formId]);
    $form = $formStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Form not found']);
        exit;
    }
    
    // Parse JSON form data
    $formData = json_decode($form['form_data'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // If JSON is invalid, create a basic structure
        $formData = [
            'form' => 'Invalid JSON Data',
            'form_id' => 'unknown',
            'error' => 'JSON parsing failed: ' . json_last_error_msg(),
            'raw_data' => $form['form_data']
        ];
    }
    
    // Extract lot_no from form_data
    $lotNo = 'No Lot Number';
    if (is_array($formData)) {
        // Check tanah.lot_no (A1, B1, C1 forms)
        if (isset($formData['tanah']['lot_no']) && !empty($formData['tanah']['lot_no'])) {
            $lotNo = trim($formData['tanah']['lot_no']);
        }
        // Check direct lot_no field (LS16 form)
        else if (isset($formData['lot_no']) && !empty($formData['lot_no'])) {
            $lotNo = trim($formData['lot_no']);
        }
        // Check lot field (LS16 form)
        else if (isset($formData['lot']) && !empty($formData['lot'])) {
            $lotNo = trim($formData['lot']);
        }
    }
    
    // Use database form_type as the primary category
    $formCategory = $form['form_type'] ?: 'Other Forms';
    
    // Handle the created_at date formatting
    $createdAtFormatted = 'Not Set';
    if ($form['created_at'] && $form['created_at'] !== '0000-00-00 00:00:00') {
        try {
            $createdAtFormatted = date('d/m/Y H:i', strtotime($form['created_at']));
        } catch (Exception $e) {
            $createdAtFormatted = 'Invalid Date';
        }
    }
    
    // Extract form title from JSON data
    $formTitle = 'Unknown Form';
    if (isset($formData['form'])) {
        $formTitle = $formData['form'];
    } else if (isset($formData['form_id'])) {
        // Map form_id to readable titles
        $formTitleMap = [
            'A1' => 'Laporan Hak Adat Bumiputra',
            'B1' => 'Tanah HakMilik',
            'C1' => 'Laporan Kolam Ikan, Bangunan & Lain-Lain'
        ];
        $formTitle = $formTitleMap[$formData['form_id']] ?? "Form {$formData['form_id']}";
    }
    
    $response = [
        'success' => true,
        'form' => [
            'form_id' => (int)$form['form_id'],
            'surveyjob_id' => (int)$form['surveyjob_id'],
            'form_type' => $form['form_type'],
            'created_at' => $form['created_at'],
            'created_at_formatted' => $createdAtFormatted,
            'job_number' => $form['surveyjob_no'] ?? 'N/A',
            'project_name' => $form['projectname'] ?? 'N/A',
            'form_title' => $formTitle,
            'form_category' => $formCategory,
            'lot_no' => $lotNo,
            'form_data' => $formData
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch(PDOException $e) {
    error_log("Get Form Details API PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error occurred',
        'debug' => $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Get Form Details API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Error loading form details: ' . $e->getMessage()
    ]);
}
?>

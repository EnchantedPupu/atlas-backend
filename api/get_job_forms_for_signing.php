<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is FI role
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in'] || $_SESSION['user_role'] !== 'FI') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$surveyJobId = isset($_POST['survey_job_id']) ? intval($_POST['survey_job_id']) : 0;

if (!$surveyJobId) {
    echo json_encode(['success' => false, 'message' => 'Survey Job ID is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get forms for the survey job
    $sql = "SELECT 
                f.form_id,
                f.surveyjob_id,
                f.form_type,
                f.form_data,
                f.created_at,
                sj.surveyjob_no,
                sj.projectname
            FROM forms f
            JOIN surveyjob sj ON f.surveyjob_id = sj.survey_job_id
            WHERE f.surveyjob_id = ?
            ORDER BY f.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$surveyJobId]);
    $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process forms to add signature status and group by lot number
    $processedForms = [];
    $groupedForms = [];
    
    foreach ($forms as $form) {
        $formData = json_decode($form['form_data'], true);
        $form['has_fi_signature'] = isset($formData['signatures']['fi']);
        $form['fi_signature_date'] = $form['has_fi_signature'] ? $formData['signatures']['fi']['signed_at'] : null;
        
        // Extract lot number from form data
        $lotNumber = 'Unknown Lot';
        if (isset($formData['tanah']['lot_no']) && !empty($formData['tanah']['lot_no'])) {
            $lotNumber = 'Lot ' . $formData['tanah']['lot_no'];
        } elseif (isset($formData['lot_no']) && !empty($formData['lot_no'])) {
            $lotNumber = 'Lot ' . $formData['lot_no'];
        } elseif (isset($formData['lot_number']) && !empty($formData['lot_number'])) {
            $lotNumber = 'Lot ' . $formData['lot_number'];
        }
        
        $form['lot_number'] = $lotNumber;
        
        // Group forms by lot number
        if (!isset($groupedForms[$lotNumber])) {
            $groupedForms[$lotNumber] = [
                'lot_number' => $lotNumber,
                'forms' => [],
                'total_forms' => 0,
                'signed_forms' => 0
            ];
        }
        
        $groupedForms[$lotNumber]['forms'][] = $form;
        $groupedForms[$lotNumber]['total_forms']++;
        if ($form['has_fi_signature']) {
            $groupedForms[$lotNumber]['signed_forms']++;
        }
        
        $processedForms[] = $form;
    }
    
    // Convert grouped forms to indexed array
    $lotGroups = array_values($groupedForms);
    
    echo json_encode([
        'success' => true,
        'forms' => $processedForms,
        'grouped_forms' => $lotGroups,
        'total' => count($processedForms)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_job_forms_for_signing.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>

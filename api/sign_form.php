<?php
session_start();
require_once '../config/database.php';
require_once '../config/role_config.php';

header('Content-Type: application/json');

// Check if user is logged in and is FI role
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Please log in to access this endpoint.']);
    exit;
}

if ($_SESSION['user_role'] !== 'FI') {
    echo json_encode(['success' => false, 'message' => 'Access denied. This endpoint is only for Field Inspector (FI) role.']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Validate required fields
    if (!isset($_POST['form_id']) || empty($_POST['form_id'])) {
        throw new Exception('Form ID is required');
    }
    
    if (!isset($_POST['signature']) || empty($_POST['signature'])) {
        throw new Exception('Signature is required');
    }
    
    $formId = intval($_POST['form_id']);
    $signature = $_POST['signature'];
    $stampImagePath = '';
    
    // Handle stamp image upload if provided - convert to base64
    if (isset($_FILES['stamp_image']) && $_FILES['stamp_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['stamp_image']['tmp_name'];
        $fileName = basename($_FILES['stamp_image']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        
        if (in_array($fileExt, $allowedExts)) {
            // Validate file size (max 5MB)
            if ($_FILES['stamp_image']['size'] > 5 * 1024 * 1024) {
                throw new Exception('Stamp image file is too large. Maximum size is 5MB.');
            }
            
            // Convert image to base64
            $imageData = file_get_contents($fileTmpPath);
            if ($imageData !== false) {
                $mimeType = mime_content_type($fileTmpPath);
                $stampImagePath = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            } else {
                throw new Exception('Failed to read stamp image file.');
            }
        } else {
            throw new Exception('Invalid stamp image file type. Allowed: JPG, PNG, GIF, BMP, WEBP');
        }
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Get form data and verify it exists
    $stmt = $db->prepare("
        SELECT f.*, sj.surveyjob_no, sj.projectname 
        FROM forms f 
        JOIN surveyjob sj ON f.surveyjob_id = sj.survey_job_id 
        WHERE f.form_id = ?
    ");
    $stmt->execute([$formId]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        throw new Exception('Form not found');
    }
    
    // Parse existing form data
    $formData = json_decode($form['form_data'], true);
    if (!$formData) {
        throw new Exception('Invalid form data');
    }
    
    // Check if form is already signed
    if (isset($formData['signatures']['fi'])) {
        throw new Exception('Form has already been signed');
    }
    
    // Update the specific fields in the form data structure
    // First check for pengesahan_pengukur.disemak_oleh, then fallback to pengesahan.disemak_oleh
    $disemakPath = null;
    
    if (isset($formData['pengesahan_pengukur'])) {
        $disemakPath = 'pengesahan_pengukur';
    } elseif (isset($formData['pengesahan'])) {
        $disemakPath = 'pengesahan';
    } else {
        // Default to pengesahan_pengukur if neither exists
        $disemakPath = 'pengesahan_pengukur';
        $formData['pengesahan_pengukur'] = [];
    }
    
    // Initialize disemak_oleh if it doesn't exist
    if (!isset($formData[$disemakPath]['disemak_oleh'])) {
        $formData[$disemakPath]['disemak_oleh'] = [];
    }
    
    // Update signature field
    $formData[$disemakPath]['disemak_oleh']['tandatangan'] = $signature;
    
    // Update stamp field if provided
    if (!empty($stampImagePath)) {
        $formData[$disemakPath]['disemak_oleh']['cop'] = $stampImagePath;
    }
    
    // Also update the reviewer details
    $formData[$disemakPath]['disemak_oleh']['nama'] = $_SESSION['user_name'];
    $formData[$disemakPath]['disemak_oleh']['jawatan'] = 'Field Inspector (FI)';
    $formData[$disemakPath]['disemak_oleh']['tarikh'] = date('Y-m-d');
    
    // Keep the FI signature record for tracking purposes
    if (!isset($formData['signatures'])) {
        $formData['signatures'] = [];
    }
    
    $formData['signatures']['fi'] = [
        'signature' => $signature,
        'stamp_image' => $stampImagePath,
        'signed_by' => $_SESSION['user_name'],
        'signed_at' => date('Y-m-d H:i:s'),
        'user_id' => $userId
    ];
    
    // Update form with signature
    $stmt = $db->prepare("UPDATE forms SET form_data = ? WHERE form_id = ?");
    $updateResult = $stmt->execute([json_encode($formData), $formId]);
    
    if (!$updateResult) {
        throw new Exception('Failed to update form with signature');
    }
    
    // Log the signing activity
    $logStmt = $db->prepare("
        INSERT INTO activity_log (user_id, action, description, created_at) 
        VALUES (?, 'form_signed', ?, NOW())
    ");
    $logStmt->execute([
        $userId, 
        "Signed form ID {$formId} for survey job {$form['surveyjob_no']}"
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Form signed successfully!',
        'form_id' => $formId,
        'survey_job_no' => $form['surveyjob_no']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

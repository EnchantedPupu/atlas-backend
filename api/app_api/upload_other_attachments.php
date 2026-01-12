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

// Check if files were uploaded
if (empty($_FILES) || !isset($_POST['survey_job_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'survey_job_id and files are required']);
    exit;
}

$survey_job_id = (int)$_POST['survey_job_id'];
$attachment_type = $_POST['attachment_type'] ?? 'parent_attachment'; // parent_attachment or child_attachment
$lot_no = $_POST['lot_no'] ?? null; // Required for child_attachment

// Validate input
if (empty($survey_job_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'survey_job_id cannot be empty']);
    exit;
}

if ($attachment_type === 'child_attachment' && empty($lot_no)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'lot_no is required for child_attachment']);
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
    
    // Check if survey job exists
    $jobQuery = "SELECT survey_job_id, other_attachment FROM surveyjob WHERE survey_job_id = ?";
    $jobStmt = $conn->prepare($jobQuery);
    $jobStmt->execute([$survey_job_id]);
    $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        throw new Exception('Survey job not found');
    }
    
    // Get existing other_attachment data or initialize empty structure
    $existing_attachments = [];
    if (!empty($job['other_attachment'])) {
        $existing_attachments = json_decode($job['other_attachment'], true);
        if (!$existing_attachments) {
            $existing_attachments = [];
        }
    }
    
    // Initialize structure if not exists
    if (!isset($existing_attachments['parent_attachment'])) {
        $existing_attachments['parent_attachment'] = [];
    }
    if (!isset($existing_attachments['child_attachment'])) {
        $existing_attachments['child_attachment'] = [];
    }
    
    // Create uploads directory if it doesn't exist
    $upload_base_dir = '../../uploads/attachments/';
    if (!file_exists($upload_base_dir)) {
        mkdir($upload_base_dir, 0777, true);
    }
    
    $uploaded_files = [];
    $upload_errors = [];
    
    // Process uploaded files
    foreach ($_FILES as $file_key => $file_info) {
        if ($file_info['error'] === UPLOAD_ERR_OK) {
            // Generate unique filename
            $file_extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
            $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_base_dir . $unique_filename;
            
            // Validate file type (allow common document types)
            $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'xls', 'xlsx'];
            if (!in_array(strtolower($file_extension), $allowed_types)) {
                $upload_errors[] = "File type not allowed for: " . $file_info['name'];
                continue;
            }
            
            // Validate file size (max 10MB)
            if ($file_info['size'] > 10 * 1024 * 1024) {
                $upload_errors[] = "File too large: " . $file_info['name'];
                continue;
            }
            
            // Move uploaded file
            if (move_uploaded_file($file_info['tmp_name'], $upload_path)) {
                $uploaded_files[] = [
                    'original_name' => $file_info['name'],
                    'unique_name' => $unique_filename,
                    'path' => '/uploads/attachments/' . $unique_filename,
                    'size' => $file_info['size'],
                    'type' => $file_info['type']
                ];
            } else {
                $upload_errors[] = "Failed to upload: " . $file_info['name'];
            }
        } else {
            $upload_errors[] = "Upload error for: " . $file_info['name'];
        }
    }
    
    // If no files were successfully uploaded
    if (empty($uploaded_files)) {
        throw new Exception('No files were successfully uploaded. Errors: ' . implode(', ', $upload_errors));
    }
    
    // Update attachment structure based on type
    if ($attachment_type === 'parent_attachment') {
        // Add to parent_attachment array
        foreach ($uploaded_files as $file) {
            $existing_attachments['parent_attachment'][] = [
                'path' => $file['path']
            ];
        }
    } else if ($attachment_type === 'child_attachment') {
        // Find existing child_attachment with same lot_no or create new one
        $lot_index = -1;
        foreach ($existing_attachments['child_attachment'] as $index => $child) {
            if ($child['lot_no'] === $lot_no) {
                $lot_index = $index;
                break;
            }
        }
        
        if ($lot_index === -1) {
            // Create new lot entry
            $existing_attachments['child_attachment'][] = [
                'lot_no' => $lot_no,
                'attachment' => []
            ];
            $lot_index = count($existing_attachments['child_attachment']) - 1;
        }
        
        // Add files to the lot's attachment array
        foreach ($uploaded_files as $file) {
            $existing_attachments['child_attachment'][$lot_index]['attachment'][] = [
                'path' => $file['path']
            ];
        }
    }
    
    // Update survey job with new attachment data
    $updateQuery = "UPDATE surveyjob SET other_attachment = ?, updated_at = NOW() WHERE survey_job_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $attachment_json = json_encode($existing_attachments, JSON_UNESCAPED_SLASHES);
    $updateResult = $updateStmt->execute([$attachment_json, $survey_job_id]);
    
    if (!$updateResult) {
        throw new Exception('Failed to update survey job attachments');
    }
    
    // Commit transaction
    $conn->commit();
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Files uploaded successfully',
        'data' => [
            'survey_job_id' => $survey_job_id,
            'attachment_type' => $attachment_type,
            'uploaded_files' => $uploaded_files,
            'total_files' => count($uploaded_files),
            'other_attachment' => $existing_attachments
        ]
    ];
    
    if (!empty($upload_errors)) {
        $response['warnings'] = $upload_errors;
    }
    
    if (!empty($lot_no)) {
        $response['data']['lot_no'] = $lot_no;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Rollback transaction on database error
    if ($conn && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    // Clean up uploaded files on error
    foreach ($uploaded_files as $file) {
        $file_path = '../../uploads/attachments/' . $file['unique_name'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
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
    
    // Clean up uploaded files on error
    foreach ($uploaded_files as $file) {
        $file_path = '../../uploads/attachments/' . $file['unique_name'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

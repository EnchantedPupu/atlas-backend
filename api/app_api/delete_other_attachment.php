<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

// Allow both DELETE and POST requests (for compatibility)
if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['survey_job_id']) || !isset($input['file_path'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'survey_job_id and file_path are required']);
    exit;
}

$survey_job_id = (int)$input['survey_job_id'];
$file_path = $input['file_path'];
$lot_no = $input['lot_no'] ?? null; // Required if deleting from child_attachment

// Validate non-empty required fields
if (empty($survey_job_id) || empty($file_path)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'survey_job_id and file_path cannot be empty']);
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
    
    // Get survey job with other_attachment data
    $jobQuery = "SELECT survey_job_id, other_attachment FROM surveyjob WHERE survey_job_id = ?";
    $jobStmt = $conn->prepare($jobQuery);
    $jobStmt->execute([$survey_job_id]);
    $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        throw new Exception('Survey job not found');
    }
    
    // Parse existing other_attachment data
    $existing_attachments = [];
    if (!empty($job['other_attachment'])) {
        $existing_attachments = json_decode($job['other_attachment'], true);
        if (!$existing_attachments) {
            $existing_attachments = [];
        }
    }
    
    $file_found = false;
    $file_deleted = false;
    
    // Search and remove from parent_attachment
    if (isset($existing_attachments['parent_attachment'])) {
        foreach ($existing_attachments['parent_attachment'] as $index => $attachment) {
            if ($attachment['path'] === $file_path) {
                // Remove from array
                array_splice($existing_attachments['parent_attachment'], $index, 1);
                $file_found = true;
                break;
            }
        }
    }
    
    // Search and remove from child_attachment if not found in parent
    if (!$file_found && isset($existing_attachments['child_attachment'])) {
        foreach ($existing_attachments['child_attachment'] as $lot_index => $child_lot) {
            if (isset($child_lot['attachment'])) {
                foreach ($child_lot['attachment'] as $att_index => $attachment) {
                    if ($attachment['path'] === $file_path) {
                        // Remove from attachment array
                        array_splice($existing_attachments['child_attachment'][$lot_index]['attachment'], $att_index, 1);
                        
                        // If no more attachments in this lot, remove the lot entry
                        if (empty($existing_attachments['child_attachment'][$lot_index]['attachment'])) {
                            array_splice($existing_attachments['child_attachment'], $lot_index, 1);
                        }
                        
                        $file_found = true;
                        break 2;
                    }
                }
            }
        }
    }
    
    if (!$file_found) {
        throw new Exception('File not found in attachments');
    }
    
    // Delete physical file
    $physical_file_path = '../../' . ltrim($file_path, '/');
    if (file_exists($physical_file_path)) {
        if (unlink($physical_file_path)) {
            $file_deleted = true;
        }
    } else {
        // File doesn't exist physically, but we'll still update the database
        $file_deleted = true;
    }
    
    // Update survey job with modified attachment data
    $updateQuery = "UPDATE surveyjob SET other_attachment = ?, updated_at = NOW() WHERE survey_job_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $attachment_json = json_encode($existing_attachments, JSON_UNESCAPED_SLASHES);
    $updateResult = $updateStmt->execute([$attachment_json, $survey_job_id]);
    
    if (!$updateResult) {
        throw new Exception('Failed to update survey job attachments');
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'File deleted successfully',
        'data' => [
            'survey_job_id' => $survey_job_id,
            'deleted_file_path' => $file_path,
            'file_physically_deleted' => $file_deleted,
            'updated_other_attachment' => $existing_attachments
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

<?php
// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
include_once '../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get posted data
$data = json_decode(file_get_contents("php://input"), true);

// Check if form ID is provided
if (!isset($data['form_id']) || empty($data['form_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Form ID is required"]);
    exit();
}

$formId = intval($data['form_id']);

try {
    // First check if form exists
    $checkQuery = "SELECT form_id FROM forms WHERE form_id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(":id", $formId);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Form with ID $formId not found"
        ]);
        exit();
    }
    
    // Prepare form data for update
    $formType = isset($data['form_type']) ? $data['form_type'] : null;
    $surveyjobId = isset($data['surveyjob_id']) ? $data['surveyjob_id'] : null;
    
    // Handle form_data - either use the provided form_data or create JSON from the form fields
    if (isset($data['form_data'])) {
        // If form_data is already provided as a string or array
        $formData = is_array($data['form_data']) ? json_encode($data['form_data']) : $data['form_data'];
    } else {
        // Create form_data from the remaining fields
        $formDataArray = $data;
        // Remove non-form fields
        unset($formDataArray['form_id']);
        unset($formDataArray['form_type']);
        unset($formDataArray['surveyjob_id']);
        
        $formData = json_encode($formDataArray);
    }
    
    // Set updated timestamp
    $now = date('Y-m-d H:i:s');
    
    // Build dynamic query based on what fields are provided
    $updateFields = [];
    $params = [":id" => $formId];
    
    if ($formType !== null) {
        $updateFields[] = "form_type = :form_type";
        $params[':form_type'] = $formType;
    }
    
    if ($surveyjobId !== null) {
        $updateFields[] = "surveyjob_id = :surveyjob_id";
        $params[':surveyjob_id'] = $surveyjobId;
    }
    
    if ($formData !== null) {
        $updateFields[] = "form_data = :form_data";
        $params[':form_data'] = $formData;
    }
    
    // Always update created_at timestamp
    $updateFields[] = "created_at = :created_at";
    $params[':created_at'] = $now;
    
    // If no fields to update, return error
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "No fields to update"
        ]);
        exit();
    }
    
    // Prepare and execute update query
    $query = "UPDATE forms SET " . implode(", ", $updateFields) . " WHERE form_id = :id";
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    if ($stmt->execute()) {
        // Return success response
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Form updated successfully",
            "form_id" => $formId
        ]);
    } else {
        // Return error if update fails
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Failed to update form"
        ]);
    }
} catch (PDOException $e) {
    // Return error if database operation fails
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>

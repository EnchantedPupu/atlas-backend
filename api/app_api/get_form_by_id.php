<?php
// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
include_once '../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if form ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Return error response if no ID provided
    http_response_code(400); // Bad request
    echo json_encode(["error" => "Form ID is required"]);
    exit();
}

$formId = intval($_GET['id']);

try {
    // Prepare query to get form by ID - using correct column name 'form_id'
    $query = "SELECT * FROM forms WHERE form_id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $formId);
    $stmt->execute();
    
    // Check if form exists
    if ($stmt->rowCount() > 0) {
        $form = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Parse form_data JSON if it exists
        if (isset($form['form_data']) && !empty($form['form_data'])) {
            $form['form_data'] = json_decode($form['form_data'], true);
        }
        
        // Return success response with form data
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $form
        ]);
    } else {
        // Return error if form not found
        http_response_code(404); // Not found
        echo json_encode([
            "status" => "error",
            "message" => "Form not found"
        ]);
    }
} catch (PDOException $e) {
    // Return error if database operation fails
    http_response_code(500); // Internal server error
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>

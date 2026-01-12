<?php
// Disable error display to prevent HTML output in JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method is allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['surveyjob_id'], $input['form_type'], $input['form_data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$surveyjob_id = $input['surveyjob_id'];
$form_type = $input['form_type'];
$form_data = $input['form_data'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $sql = 'INSERT INTO forms (surveyjob_id, form_type, form_data, created_at) VALUES (:surveyjob_id, :form_type, :form_data, NOW())';
    $stmt = $db->prepare($sql);
    
    $stmt->bindParam(':surveyjob_id', $surveyjob_id, PDO::PARAM_INT);
    $stmt->bindParam(':form_type', $form_type);
    
    // Store JSON string in a variable first to avoid reference issues
    $form_data_json = json_encode($form_data);
    $stmt->bindParam(':form_data', $form_data_json);
    
    $stmt->execute();
    
    // Get the actual insert ID
    $insert_id = $db->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'id' => (int)$insert_id,
        'message' => 'Form uploaded successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error', 
        'details' => $e->getMessage()
    ]);
}
?>
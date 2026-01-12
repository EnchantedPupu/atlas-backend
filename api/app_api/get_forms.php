<?php
// Disable error display to prevent HTML output in JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

// Get surveyjob_id from query parameters
$surveyjob_id = isset($_GET['surveyjob_id']) ? (int)$_GET['surveyjob_id'] : null;

if (!$surveyjob_id) {
    http_response_code(400);
    echo json_encode(['error' => 'surveyjob_id parameter is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Query to get all forms for the given surveyjob_id
    $sql = 'SELECT form_id, surveyjob_id, form_type, form_data, created_at 
            FROM forms 
            WHERE surveyjob_id = :surveyjob_id 
            ORDER BY created_at DESC';
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':surveyjob_id', $surveyjob_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $forms = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $formData = json_decode($row['form_data'], true);
        // Add form_table_id to the decoded JSON
        $formData['form_table_id'] = $row['form_id'];
        $forms[] = $formData;
    }

    echo json_encode([
        'success' => true,
        'total_forms' => count($forms),
        'forms' => $forms
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
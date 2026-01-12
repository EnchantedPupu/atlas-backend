<?php
// Test file to verify query_info functionality
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Test query_info structure
    $test_query_info = [
        "A1" => "Test remark for Form A1",
        "B1" => "",
        "C1" => "Test remark for Form C1", 
        "L&16" => "",
        "L&S3" => "",
        "MP_Pelan" => "Test remark for MP Plan",
        "query_date" => date('Y-m-d'),
        "query_returned" => "Yes"
    ];
    
    $json_data = json_encode($test_query_info);
    
    // Test if JSON is valid
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Query info test successful',
        'test_data' => $test_query_info,
        'json_string' => $json_data,
        'json_valid' => json_last_error() === JSON_ERROR_NONE
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

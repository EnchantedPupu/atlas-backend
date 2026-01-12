<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Prepare and execute query to fetch all users with role 'FI'
    $query = "SELECT user_id, name, username, role, profile_picture, created_at FROM user WHERE role = 'FI' ORDER BY name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    // Fetch all FI users
    $fiUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return successful response with users data
    echo json_encode([
        'success' => true,
        'message' => 'FI users retrieved successfully',
        'data' => $fiUsers,
        'count' => count($fiUsers)
    ]);
    
} catch (PDOException $e) {
    // Database error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // General error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred',
        'error' => $e->getMessage()
    ]);
}
?>
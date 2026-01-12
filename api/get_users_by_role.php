<?php
// Clean start - no output before headers
ob_start();

// Set proper headers for jQuery AJAX
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');

// Start session and include required files
session_start();
require_once '../config/database.php';

// Clean any previous output
ob_clean();

// Function to send JSON response and exit cleanly
function sendResponse($data, $statusCode = 200) {
    ob_clean();
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Debug logging function
function debugLog($message) {
    error_log("[API get_users_by_role] " . $message);
}

debugLog("Request received - Method: " . $_SERVER['REQUEST_METHOD']);
debugLog("GET params: " . json_encode($_GET));
debugLog("Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive'));
debugLog("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
debugLog("Session user_role: " . ($_SESSION['user_role'] ?? 'not set'));

// Check if user is logged in - allow test requests for debugging
$role = trim($_GET['role'] ?? '');
if ($role === 'test') {
    debugLog("Test request received");
    sendResponse(['message' => 'API is working', 'test' => true]);
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    debugLog("Authentication failed - user not logged in");
    sendResponse(['error' => 'Unauthorized', 'message' => 'User session expired. Please log in again.'], 401);
}

// Validate role parameter
if (empty($role)) {
    debugLog("Role parameter missing or empty");
    sendResponse(['error' => 'Bad Request', 'message' => 'Role parameter is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    debugLog("Searching for users with role: " . $role);
    
    // Query to get users by role - exclude current user from results
    $sql = "SELECT user_id, name, username FROM User WHERE role = ? ORDER BY name";
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare SQL statement: " . implode(" ", $db->errorInfo()));
    }
    
    $success = $stmt->execute([$role]);
    if (!$success) {
        throw new Exception("Failed to execute query: " . implode(" ", $stmt->errorInfo()));
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    debugLog("Found " . count($users) . " users for role: " . $role);
    debugLog("Users data: " . json_encode($users));
    
    // Clean output buffer and send response
    sendResponse($users);
    
} catch (PDOException $e) {
    debugLog("Database error: " . $e->getMessage());
    sendResponse(['error' => 'Database Error', 'message' => 'Unable to retrieve users from database'], 500);
} catch (Exception $e) {
    debugLog("General error: " . $e->getMessage());
    sendResponse(['error' => 'Server Error', 'message' => $e->getMessage()], 500);
}
?>

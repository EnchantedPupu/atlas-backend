<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
require_once '../../config/database.php';
require_once '../../config/role_config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in API response

// Function to send JSON response
function sendResponse($success, $data = null, $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Helper function to check if job is newly assigned to user
function isNewlyAssigned($job, $userId) {
    if ($job['assigned_to_id'] != $userId) return false;
    
    if (!$job['updated_at'] || $job['updated_at'] === null) {
        return false;
    }
    
    $updatedTime = strtotime($job['updated_at']);
    if ($updatedTime === false) {
        return false;
    }
    
    $dayAgo = time() - (24 * 60 * 60);
    return $updatedTime > $dayAgo && $job['status'] === 'assigned';
}

// Helper function to check if job needs approval
function needsApproval($job, $userRole) {
    if ($userRole === 'OIC' || $userRole === 'VO') {
        return in_array($job['status'], ['completed', 'submitted']) || 
               ($job['pbtstatus'] === 'checked' && $userRole === 'VO');
    }
    return false;
}

// Get user ID from request parameters
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Validate user ID parameter
if (!$userId) {
    sendResponse(false, null, 'User ID parameter is required', 400);
}

// Get additional query parameters for filtering
$status = isset($_GET['status']) ? $_GET['status'] : null;
$involvement = isset($_GET['involvement']) ? $_GET['involvement'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check if updated_at column exists, if not add it
    try {
        $columnCheck = $db->query("SHOW COLUMNS FROM surveyjob LIKE 'updated_at'");
        if ($columnCheck->rowCount() == 0) {
            $db->exec("ALTER TABLE surveyjob ADD COLUMN updated_at DATETIME DEFAULT NULL AFTER created_at");
            error_log("Added missing updated_at column to surveyjob table");
        }
    } catch (Exception $e) {
        error_log("Could not check/add updated_at column: " . $e->getMessage());
    }
    
    // Get user information for role-based logic
    $userStmt = $db->prepare("SELECT name, role FROM user WHERE user_id = ?");
    $userStmt->execute([$userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        sendResponse(false, null, 'User not found', 404);
    }
    
    $userRole = $userData['role'];
    $userName = $userData['name'];
    
    // Build query to show jobs user is currently assigned to OR has been involved with
    $sql = "SELECT 
                sj.survey_job_id,
                sj.surveyjob_no,
                sj.hq_ref,
                sj.div_ref,
                sj.projectname,
                sj.attachment_name,
                sj.status,
                sj.pbtstatus,
                sj.remarks,
                sj.created_at,
                sj.updated_at,
                sj.created_by,
                sj.assigned_to,
                creator.name as created_by_name,
                creator.role as created_by_role,
                assignee.name as assigned_to_name,
                assignee.role as assigned_to_role,
                assignee.user_id as assigned_to_id,
                CASE 
                    WHEN sj.assigned_to = ? THEN 'currently_assigned'
                    WHEN sj.created_by = ? THEN 'created_by_me'
                    ELSE 'previously_involved'
                END as involvement_type
            FROM surveyjob sj
            LEFT JOIN user creator ON sj.created_by = creator.user_id
            LEFT JOIN user assignee ON sj.assigned_to = assignee.user_id
            WHERE (sj.assigned_to = ? OR sj.created_by = ?)";
    
    $params = [$userId, $userId, $userId, $userId];
    $whereConditions = [];
    
    // Apply status filter
    if ($status && $status !== 'all') {
        $whereConditions[] = "sj.status = ?";
        $params[] = $status;
    }
    
    // Apply involvement filter
    if ($involvement) {
        switch ($involvement) {
            case 'currently_assigned':
                $whereConditions[] = "sj.assigned_to = ?";
                $params[] = $userId;
                break;
            case 'created_by_me':
                $whereConditions[] = "sj.created_by = ?";
                $params[] = $userId;
                break;
            case 'new_tasks':
                $whereConditions[] = "sj.assigned_to = ? AND sj.status = 'assigned' AND sj.updated_at > DATE_SUB(NOW(), INTERVAL 1 DAY)";
                $params[] = $userId;
                break;
        }
    }
    
    // Apply search filter
    if ($search) {
        $searchConditions = [
            "sj.surveyjob_no LIKE ?",
            "sj.projectname LIKE ?",
            "sj.hq_ref LIKE ?",
            "sj.div_ref LIKE ?"
        ];
        $whereConditions[] = "(" . implode(" OR ", $searchConditions) . ")";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    // Add additional WHERE conditions
    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }
    
    // Get total count for pagination (simplified count query)
    $countSql = "SELECT COUNT(*) as total FROM surveyjob sj WHERE (sj.assigned_to = ? OR sj.created_by = ?)";
    $countParams = [$userId, $userId];
    
    // Add same filters to count query
    if ($status && $status !== 'all') {
        $countSql .= " AND sj.status = ?";
        $countParams[] = $status;
    }
    
    if ($involvement) {
        switch ($involvement) {
            case 'currently_assigned':
                $countSql .= " AND sj.assigned_to = ?";
                $countParams[] = $userId;
                break;
            case 'created_by_me':
                $countSql .= " AND sj.created_by = ?";
                $countParams[] = $userId;
                break;
            case 'new_tasks':
                $countSql .= " AND sj.assigned_to = ? AND sj.status = 'assigned' AND sj.updated_at > DATE_SUB(NOW(), INTERVAL 1 DAY)";
                $countParams[] = $userId;
                break;
        }
    }
    
    if ($search) {
        $countSql .= " AND (sj.surveyjob_no LIKE ? OR sj.projectname LIKE ? OR sj.hq_ref LIKE ? OR sj.div_ref LIKE ?)";
        $searchParam = "%$search%";
        $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($countParams);
    $totalJobs = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Add ordering - prioritize currently assigned jobs
    $sql .= " ORDER BY 
                CASE 
                    WHEN sj.assigned_to = ? THEN 1 
                    ELSE 2 
                END,
                COALESCE(sj.updated_at, sj.created_at) DESC";
    $params[] = $userId;
    
    // Add pagination if limit is specified
    if ($limit) {
        $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the jobs data
    $formattedJobs = [];
    foreach ($jobs as $job) {
        $isNewTask = isNewlyAssigned($job, $userId);
        $requiresApproval = needsApproval($job, $userRole);
        
        $formattedJobs[] = [
            'id' => intval($job['survey_job_id']),
            'job_number' => $job['surveyjob_no'],
            'project_name' => $job['projectname'],
            'references' => [
                'hq_ref' => $job['hq_ref'],
                'div_ref' => $job['div_ref']
            ],
            'status' => $job['status'],
            'pbt_status' => $job['pbtstatus'],
            'remarks' => $job['remarks'],
            'attachment_name' => $job['attachment_name'],
            'involvement_type' => $job['involvement_type'],
            'flags' => [
                'is_new_task' => $isNewTask,
                'needs_approval' => $requiresApproval,
                'has_attachment' => !empty($job['attachment_name']) && $job['attachment_name'] !== 'no_file_uploaded.pdf'
            ],
            'created_by' => [
                'id' => $job['created_by'] ? intval($job['created_by']) : null,
                'name' => $job['created_by_name'],
                'role' => $job['created_by_role']
            ],
            'assigned_to' => [
                'id' => $job['assigned_to_id'] ? intval($job['assigned_to_id']) : null,
                'name' => $job['assigned_to_name'],
                'role' => $job['assigned_to_role']
            ],
            'dates' => [
                'created_at' => $job['created_at'],
                'updated_at' => $job['updated_at'],
                'created_formatted' => date('d/m/Y H:i', strtotime($job['created_at'])),
                'updated_formatted' => $job['updated_at'] ? date('d/m/Y H:i', strtotime($job['updated_at'])) : null
            ]
        ];
    }
    
    // Calculate user-specific summary statistics
    $currentlyAssigned = count(array_filter($jobs, function($job) use ($userId) {
        return $job['assigned_to_id'] == $userId;
    }));
    
    $newTasks = count(array_filter($jobs, function($job) use ($userId) {
        return isNewlyAssigned($job, $userId);
    }));
    
    $completedTasks = count(array_filter($jobs, function($job) {
        return in_array($job['status'], ['completed', 'reviewed', 'approved']);
    }));
    
    $statusCounts = [
        'pending' => 0,
        'assigned' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'reviewed' => 0,
        'approved' => 0,
        'submitted' => 0
    ];
    
    foreach ($jobs as $job) {
        if (isset($statusCounts[$job['status']])) {
            $statusCounts[$job['status']]++;
        }
    }
    
    // Prepare response data
    $responseData = [
        'user_info' => [
            'id' => $userId,
            'name' => $userName,
            'role' => $userRole
        ],
        'jobs' => $formattedJobs,
        'pagination' => [
            'total' => intval($totalJobs),
            'count' => count($formattedJobs),
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $limit ? ($offset + $limit < $totalJobs) : false
        ],
        'summary' => [
            'total_jobs' => intval($totalJobs),
            'currently_assigned' => $currentlyAssigned,
            'new_tasks' => $newTasks,
            'completed_tasks' => $completedTasks,
            'status_counts' => $statusCounts
        ],
        'filters_applied' => [
            'status' => $status,
            'involvement' => $involvement,
            'search' => $search
        ]
    ];
    
    sendResponse(true, $responseData, 'User tasks retrieved successfully');
    
} catch(PDOException $e) {
    error_log("Get User Task API Error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch(Exception $e) {
    error_log("Get User Task API Error: " . $e->getMessage());
    sendResponse(false, null, 'An error occurred while retrieving user tasks: ' . $e->getMessage(), 500);
}
?>
}
?>

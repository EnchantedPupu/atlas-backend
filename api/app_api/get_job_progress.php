<?php
// filepath: c:\xampp\htdocs\api\app_api\get_job_progress.api
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

// Get query parameters for filtering
$status = isset($_GET['status']) ? $_GET['status'] : null;
$role = isset($_GET['role']) ? $_GET['role'] : null;
$assignedTo = isset($_GET['assigned_to']) ? $_GET['assigned_to'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Get user context from session if available
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;

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
    
    // Build base query
    $sql = "SELECT 
                sj.survey_job_id,
                sj.surveyjob_no,
                sj.hq_ref,
                sj.div_ref,
                sj.projectname,
                sj.attachment_name,
                sj.status,
                sj.pbtstatus,
                sj.created_at,
                sj.updated_at,
                creator.name as created_by_name,
                creator.role as created_by_role,
                creator.user_id as created_by_id,
                assignee.name as assigned_to_name,
                assignee.role as assigned_to_role,
                assignee.user_id as assigned_to_id,
                assigner.name as assigned_by_name,
                assigner.role as assigned_by_role,
                assigner.user_id as assigned_by_id
            FROM surveyjob sj
            LEFT JOIN user creator ON sj.created_by = creator.user_id
            LEFT JOIN user assignee ON sj.assigned_to = assignee.user_id
            LEFT JOIN (
                SELECT 
                    jh.survey_job_id,
                    jh.from_user_id as assigned_by_user_id,
                    ROW_NUMBER() OVER (PARTITION BY jh.survey_job_id ORDER BY jh.created_at DESC) as rn
                FROM jobhistory jh
                WHERE jh.action_type = 'assigned'
            ) latest_assignment ON sj.survey_job_id = latest_assignment.survey_job_id AND latest_assignment.rn = 1
            LEFT JOIN user assigner ON latest_assignment.assigned_by_user_id = assigner.user_id";
    
    $whereConditions = [];
    $params = [];
    
    // Apply role-based filtering
    // Note: Currently showing all jobs for OIC, VO, SS as per original code
    // Add role-based restrictions here if needed
    
    // Apply status filter
    if ($status && $status !== 'all') {
        $whereConditions[] = "sj.status = :status";
        $params[':status'] = $status;
    }
    
    // Apply assigned to filter
    if ($assignedTo) {
        if ($assignedTo === 'unassigned') {
            $whereConditions[] = "sj.assigned_to IS NULL";
        } else {
            $whereConditions[] = "sj.assigned_to = :assigned_to";
            $params[':assigned_to'] = $assignedTo;
        }
    }
    
    // Apply search filter
    if ($search) {
        $searchConditions = [
            "sj.surveyjob_no LIKE :search",
            "sj.projectname LIKE :search",
            "sj.hq_ref LIKE :search",
            "sj.div_ref LIKE :search",
            "creator.name LIKE :search",
            "assignee.name LIKE :search"
        ];
        $whereConditions[] = "(" . implode(" OR ", $searchConditions) . ")";
        $params[':search'] = "%$search%";
    }
    
    // Add WHERE clause if there are conditions
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as filtered_jobs";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalJobs = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Add ordering
    $sql .= " ORDER BY sj.created_at DESC";
    
    // Add pagination if limit is specified
    if ($limit) {
        $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    }
    
    $stmt = $db->prepare($sql);
    
    // Bind parameters (excluding limit and offset as they're now inline)
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the jobs data
    $formattedJobs = [];
    foreach ($jobs as $job) {
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
            'attachment_name' => $job['attachment_name'],
            'created_by' => [
                'id' => $job['created_by_id'] ? intval($job['created_by_id']) : null,
                'name' => $job['created_by_name'],
                'role' => $job['created_by_role']
            ],
            'assigned_to' => [
                'id' => $job['assigned_to_id'] ? intval($job['assigned_to_id']) : null,
                'name' => $job['assigned_to_name'],
                'role' => $job['assigned_to_role']
            ],
            'assigned_by' => [
                'id' => $job['assigned_by_id'] ? intval($job['assigned_by_id']) : null,
                'name' => $job['assigned_by_name'] ?: $job['created_by_name'],
                'role' => $job['assigned_by_role'] ?: $job['created_by_role']
            ],
            'dates' => [
                'created_at' => $job['created_at'],
                'updated_at' => $job['updated_at'],
                'created_formatted' => date('d/m/Y H:i', strtotime($job['created_at'])),
                'updated_formatted' => $job['updated_at'] ? date('d/m/Y H:i', strtotime($job['updated_at'])) : null
            ]
        ];
    }
    
    // Calculate summary statistics
    $statusCounts = [
        'pending' => 0,
        'assigned' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'reviewed' => 0,
        'approved' => 0
    ];
    
    $pbtStatusCounts = [
        'none' => 0,
        'checking' => 0,
        'checked' => 0,
        'acquisition-complete' => 0
    ];
    
    foreach ($jobs as $job) {
        // Count status
        if (isset($statusCounts[$job['status']])) {
            $statusCounts[$job['status']]++;
        }
        
        // Count PBT status
        $pbtStatus = strtolower($job['pbtstatus'] ?? 'none');
        if (isset($pbtStatusCounts[$pbtStatus])) {
            $pbtStatusCounts[$pbtStatus]++;
        }
    }
    
    // Prepare response data
    $responseData = [
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
            'status_counts' => $statusCounts,
            'pbt_status_counts' => $pbtStatusCounts,
            'in_progress_total' => $statusCounts['assigned'] + $statusCounts['in_progress'],
            'completed_total' => $statusCounts['completed'] + $statusCounts['reviewed'] + $statusCounts['approved']
        ],
        'filters_applied' => [
            'status' => $status,
            'role' => $role,
            'assigned_to' => $assignedTo,
            'search' => $search
        ],
        'user_context' => [
            'user_id' => $userId ? intval($userId) : null,
            'user_role' => $userRole
        ]
    ];
    
    sendResponse(true, $responseData, 'Job progress data retrieved successfully');
    
} catch(PDOException $e) {
    error_log("Job Progress API Error: " . $e->getMessage());
    sendResponse(false, null, 'Database error occurred', 500);
} catch(Exception $e) {
    error_log("Job Progress API Error: " . $e->getMessage());
    sendResponse(false, null, 'An error occurred while retrieving job progress data', 500);
}
?>
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get survey job ID from GET parameter
    $survey_job_id = isset($_GET['survey_job_id']) ? intval($_GET['survey_job_id']) : 0;
    
    if ($survey_job_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid survey job ID'
        ]);
        exit;
    }
    
    // Query to get survey job details with creator and assignee info
    $query = "SELECT 
                sj.survey_job_id,
                sj.surveyjob_no,
                sj.hq_ref,
                sj.div_ref,
                sj.projectname,
                sj.attachment_name,
                sj.status,
                sj.created_at,
                sj.updated_at,
                sj.pbtstatus,
                sj.remarks,
                creator.name as created_by_name,
                creator.role as created_by_role,
                assignee.name as assigned_to_name,
                assignee.role as assigned_to_role,
                assignee.user_id as assigned_to_id
              FROM surveyjob sj
              LEFT JOIN user creator ON sj.created_by = creator.user_id
              LEFT JOIN user assignee ON sj.assigned_to = assignee.user_id
              WHERE sj.survey_job_id = :survey_job_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':survey_job_id', $survey_job_id);
    $stmt->execute();
    
    $survey_job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$survey_job) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Survey job not found'
        ]);
        exit;
    }
    
    // Get job history
    $history_query = "SELECT 
                        jh.history_id,
                        jh.action_type,
                        jh.status_before,
                        jh.status_after,
                        jh.notes,
                        jh.created_at,
                        jh.from_role,
                        jh.to_role,
                        from_user.name as from_user_name,
                        to_user.name as to_user_name
                      FROM jobhistory jh
                      LEFT JOIN user from_user ON jh.from_user_id = from_user.user_id
                      LEFT JOIN user to_user ON jh.to_user_id = to_user.user_id
                      WHERE jh.survey_job_id = :survey_job_id
                      ORDER BY jh.created_at DESC";
    
    $history_stmt = $db->prepare($history_query);
    $history_stmt->bindParam(':survey_job_id', $survey_job_id);
    $history_stmt->execute();
    
    $job_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare response data
    $response = [
        'success' => true,
        'data' => [
            'survey_job' => [
                'survey_job_id' => $survey_job['survey_job_id'],
                'surveyjob_no' => $survey_job['surveyjob_no'],
                'hq_ref' => $survey_job['hq_ref'],
                'div_ref' => $survey_job['div_ref'],
                'projectname' => $survey_job['projectname'],
                'attachment_name' => $survey_job['attachment_name'],
                'status' => $survey_job['status'],
                'pbtstatus' => $survey_job['pbtstatus'],
                'remarks' => $survey_job['remarks'],
                'created_at' => $survey_job['created_at'],
                'updated_at' => $survey_job['updated_at'],
                'created_by' => [
                    'name' => $survey_job['created_by_name'],
                    'role' => $survey_job['created_by_role']
                ],
                'assigned_to' => [
                    'user_id' => $survey_job['assigned_to_id'],
                    'name' => $survey_job['assigned_to_name'],
                    'role' => $survey_job['assigned_to_role']
                ]
            ],
            'job_history' => $job_history
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch(PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $exception->getMessage()
    ]);
} catch(Exception $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $exception->getMessage()
    ]);
}
?>

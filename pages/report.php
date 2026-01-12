<?php
session_start();
require_once '../config/database.php';
require_once '../config/role_config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    echo '<div class="error-message">Please log in to access this page.</div>';
    exit;
}

// Get user role for filtering
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];

// Initialize variables
$error = '';
$reportData = [];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get completed jobs data
    $completedJobsQuery = "
        SELECT 
            sj.survey_job_id,
            sj.surveyjob_no,
            sj.hq_ref,
            sj.div_ref,
            sj.projectname,
            sj.target_project,
            sj.status,
            sj.pbtstatus,
            sj.created_at,
            sj.updated_at,
            creator.name as created_by_name,
            creator.role as created_by_role,
            assignee.name as assigned_to_name,
            assignee.role as assigned_to_role,
            acquisition_complete_ranked.acquisition_date
        FROM SurveyJob sj
        LEFT JOIN User creator ON sj.created_by = creator.user_id
        LEFT JOIN User assignee ON sj.assigned_to = assignee.user_id
        LEFT JOIN (
            SELECT 
                jh.survey_job_id,
                jh.created_at as acquisition_date,
                ROW_NUMBER() OVER (PARTITION BY jh.survey_job_id ORDER BY jh.created_at DESC) as rn
            FROM jobhistory jh
            WHERE jh.action_type = 'pbtstatus_update'
            AND (jh.notes LIKE '%acquisition_complete%' OR jh.status_after = 'completed')
        ) acquisition_complete_ranked ON sj.survey_job_id = acquisition_complete_ranked.survey_job_id AND acquisition_complete_ranked.rn = 1
        WHERE sj.status IN ('completed', 'reviewed', 'approved') 
           OR sj.pbtstatus = 'acquisition_complete'
        ORDER BY sj.updated_at DESC
    ";
    
    $stmt = $db->prepare($completedJobsQuery);
    $stmt->execute();
    $reportData['completed_jobs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status IN ('completed', 'reviewed', 'approved') THEN 1 ELSE 0 END) as completed_jobs,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_jobs,
            SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_jobs,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_jobs,
            SUM(CASE WHEN pbtstatus = 'acquisition_complete' THEN 1 ELSE 0 END) as acquisition_complete_jobs,
            DATE(MIN(created_at)) as earliest_job,
            DATE(MAX(created_at)) as latest_job
        FROM SurveyJob
    ";
    
    $stmt = $db->prepare($summaryQuery);
    $stmt->execute();
    $reportData['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get monthly completion data for charts
    $monthlyQuery = "
        SELECT 
            DATE_FORMAT(updated_at, '%Y-%m') as month,
            COUNT(*) as completed_count
        FROM SurveyJob 
        WHERE status IN ('completed', 'reviewed', 'approved')
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(updated_at, '%Y-%m')
        ORDER BY month DESC
    ";
    
    $stmt = $db->prepare($monthlyQuery);
    $stmt->execute();
    $reportData['monthly_completion'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user performance data
    $userPerformanceQuery = "
        SELECT 
            u.name,
            u.role,
            COUNT(sj.survey_job_id) as total_jobs,
            SUM(CASE WHEN sj.status IN ('completed', 'reviewed', 'approved') THEN 1 ELSE 0 END) as completed_jobs,
            AVG(DATEDIFF(sj.updated_at, sj.created_at)) as avg_completion_days
        FROM User u
        LEFT JOIN SurveyJob sj ON u.user_id = sj.assigned_to
        WHERE u.role IN ('VO', 'SS', 'FI', 'AS', 'SD', 'PP')
        GROUP BY u.user_id, u.name, u.role
        HAVING total_jobs > 0
        ORDER BY completed_jobs DESC
    ";
    
    $stmt = $db->prepare($userPerformanceQuery);
    $stmt->execute();
    $reportData['user_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get project summary data with form counts by categories
    $projectSummaryQuery = "
        SELECT 
            sj.survey_job_id,
            sj.surveyjob_no,
            sj.projectname,
            sj.hq_ref,
            sj.div_ref,
            sj.target_project,
            sj.status,
            sj.created_at,
            COUNT(f.form_id) as total_forms,
            -- Count forms by category
            SUM(CASE WHEN f.form_type = 'Tanah Berhakmilik' THEN 1 ELSE 0 END) as tanah_berhakmilik_forms,
            SUM(CASE WHEN f.form_type = 'Hak Adat Bumiputera' THEN 1 ELSE 0 END) as hak_adat_bumiputera_forms,
            SUM(CASE WHEN f.form_type = 'Native Customary Land' THEN 1 ELSE 0 END) as native_customary_land_forms,
            SUM(CASE WHEN f.form_type = 'Registered State Land' THEN 1 ELSE 0 END) as registered_state_land_forms,
            -- Count unique lots by form category (excluding NULL and empty values)
            COUNT(DISTINCT CASE WHEN f.form_type = 'Tanah Berhakmilik' 
                AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL 
                AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END) as tanah_berhakmilik_lots,
            COUNT(DISTINCT CASE WHEN f.form_type = 'Hak Adat Bumiputera' 
                AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL 
                AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END) as hak_adat_bumiputera_lots,
            COUNT(DISTINCT CASE WHEN f.form_type = 'Native Customary Land' 
                AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL 
                AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END) as native_customary_land_lots,
            COUNT(DISTINCT CASE WHEN f.form_type = 'Registered State Land' 
                AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL 
                AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END) as registered_state_land_lots
        FROM surveyjob sj
        LEFT JOIN forms f ON sj.survey_job_id = f.surveyjob_id
        GROUP BY sj.survey_job_id, sj.surveyjob_no, sj.projectname, sj.hq_ref, sj.div_ref, sj.target_project, sj.status, sj.created_at
        ORDER BY sj.created_at DESC
    ";
    
    $stmt = $db->prepare($projectSummaryQuery);
    $stmt->execute();
    $reportData['project_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get query form data from review table
    $queryFormQuery = "
        SELECT 
            r.review_id,
            r.reviewer_id,
            r.surveyjob_id,
            r.query_info,
            sj.surveyjob_no,
            sj.projectname,
            sj.hq_ref,
            sj.div_ref,
            sj.target_project,
            sj.created_at as job_created_at,
            reviewer.name as reviewer_name,
            reviewer.role as reviewer_role
        FROM review r
        INNER JOIN surveyjob sj ON r.surveyjob_id = sj.survey_job_id
        INNER JOIN user reviewer ON r.reviewer_id = reviewer.user_id
        WHERE r.query_info IS NOT NULL 
        AND r.query_info != '{}' 
        AND r.query_info != ''
        ORDER BY sj.created_at DESC
    ";
    
    $stmt = $db->prepare($queryFormQuery);
    $stmt->execute();
    $reportData['query_forms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process query form data for statistics
    $queryStats = [
        'total_queries' => 0,
        'form_counts' => [
            'A1' => 0,
            'B1' => 0,
            'C1' => 0,
            'L&16' => 0,
            'L&S3' => 0,
            'MP_Pelan' => 0
        ],
        'projects_with_queries' => 0
    ];
    
    $processedProjects = [];
    
    foreach ($reportData['query_forms'] as $queryForm) {
        $queryInfo = json_decode($queryForm['query_info'], true);
        if ($queryInfo) {
            // Count queries per form type
            foreach (['A1', 'B1', 'C1', 'L&16', 'L&S3', 'MP_Pelan'] as $formType) {
                if (!empty($queryInfo[$formType])) {
                    $queryStats['total_queries']++;
                    $queryStats['form_counts'][$formType]++;
                }
            }
            
            // Count unique projects with queries
            if (!in_array($queryForm['surveyjob_id'], $processedProjects)) {
                $processedProjects[] = $queryForm['surveyjob_id'];
                $queryStats['projects_with_queries']++;
            }
        }
    }
    
    $reportData['query_stats'] = $queryStats;
    
    // Get distinct target projects for filter
    $targetProjectsQuery = "SELECT DISTINCT target_project FROM SurveyJob WHERE target_project IS NOT NULL AND target_project != '' ORDER BY target_project";
    $stmt = $db->prepare($targetProjectsQuery);
    $stmt->execute();
    $reportData['target_projects'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get query summary data by year
    // Note: We need to sum up individual project totals to match project summary methodology
    $querySummaryQuery = "
        WITH project_totals AS (
            SELECT 
                YEAR(sj.created_at) as year,
                sj.survey_job_id,
                -- Count distinct lots per project (same logic as project summary)
                (COUNT(DISTINCT CASE WHEN f.form_type = 'Tanah Berhakmilik' AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                    THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END) +
                 COUNT(DISTINCT CASE WHEN f.form_type = 'Hak Adat Bumiputera' AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                    THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END) +
                 COUNT(DISTINCT CASE WHEN f.form_type = 'Native Customary Land' AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                    THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END) +
                 COUNT(DISTINCT CASE WHEN f.form_type = 'Registered State Land' AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                    THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END)) as project_total_lots,
                    
                COUNT(DISTINCT f.form_id) as project_total_forms,
                
                -- Check if project has queries
                CASE WHEN EXISTS(
                    SELECT 1 FROM review r 
                    WHERE r.surveyjob_id = sj.survey_job_id 
                    AND r.query_info IS NOT NULL 
                    AND r.query_info != '{}' 
                    AND r.query_info != ''
                ) THEN 1 ELSE 0 END as has_queries,
                
                -- Count forms with queries for this project
                COUNT(DISTINCT CASE WHEN EXISTS(
                    SELECT 1 FROM review r2 
                    WHERE r2.surveyjob_id = f.surveyjob_id 
                    AND r2.query_info IS NOT NULL 
                    AND r2.query_info != '{}' 
                    AND r2.query_info != ''
                ) THEN f.form_id END) as project_forms_with_queries,
                
                -- Count lots with queries for this project (same approach as above)
                (COUNT(DISTINCT CASE WHEN f.form_type = 'Tanah Berhakmilik' AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                    AND EXISTS(
                        SELECT 1 FROM review r3 
                        WHERE r3.surveyjob_id = f.surveyjob_id 
                        AND r3.query_info IS NOT NULL 
                        AND r3.query_info != '{}' 
                        AND r3.query_info != ''
                    ) THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END) +
                 COUNT(DISTINCT CASE WHEN f.form_type = 'Hak Adat Bumiputera' AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                    AND EXISTS(
                        SELECT 1 FROM review r4 
                        WHERE r4.surveyjob_id = f.surveyjob_id 
                        AND r4.query_info IS NOT NULL 
                        AND r4.query_info != '{}' 
                        AND r4.query_info != ''
                    ) THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END) +
                 COUNT(DISTINCT CASE WHEN f.form_type = 'Native Customary Land' AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                    AND EXISTS(
                        SELECT 1 FROM review r5 
                        WHERE r5.surveyjob_id = f.surveyjob_id 
                        AND r5.query_info IS NOT NULL 
                        AND r5.query_info != '{}' 
                        AND r5.query_info != ''
                    ) THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END) +
                 COUNT(DISTINCT CASE WHEN f.form_type = 'Registered State Land' AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') IS NOT NULL AND JSON_EXTRACT(f.form_data, '$.tanah.lot_no') != ''
                    AND EXISTS(
                        SELECT 1 FROM review r6 
                        WHERE r6.surveyjob_id = f.surveyjob_id 
                        AND r6.query_info IS NOT NULL 
                        AND r6.query_info != '{}' 
                        AND r6.query_info != ''
                    ) THEN JSON_EXTRACT(f.form_data, '$.tanah.lot_no') END)) as project_lots_with_queries
                    
            FROM surveyjob sj
            LEFT JOIN forms f ON sj.survey_job_id = f.surveyjob_id
            WHERE sj.created_at IS NOT NULL
            GROUP BY sj.survey_job_id, YEAR(sj.created_at)
        )
        SELECT 
            year,
            COUNT(*) as total_projects,
            SUM(has_queries) as projects_with_queries,
            SUM(project_total_forms) as total_forms,
            SUM(project_forms_with_queries) as forms_with_queries,
            SUM(project_total_lots) as total_lots,
            SUM(project_lots_with_queries) as lots_with_queries
        FROM project_totals
        GROUP BY year
        ORDER BY year DESC
    ";
    
    $stmt = $db->prepare($querySummaryQuery);
    $stmt->execute();
    $reportData['query_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate percentages for each year
    foreach ($reportData['query_summary'] as &$yearData) {
        $yearData['project_query_percentage'] = $yearData['total_projects'] > 0 
            ? round(($yearData['projects_with_queries'] / $yearData['total_projects']) * 100, 1) 
            : 0;
        $yearData['form_query_percentage'] = $yearData['total_forms'] > 0 
            ? round(($yearData['forms_with_queries'] / $yearData['total_forms']) * 100, 1) 
            : 0;
        $yearData['lot_query_percentage'] = $yearData['total_lots'] > 0 
            ? round(($yearData['lots_with_queries'] / $yearData['total_lots']) * 100, 1) 
            : 0;
    }
    unset($yearData); // Break reference
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Report Error: " . $e->getMessage());
} catch(Exception $e) {
    $error = "Error loading report data: " . $e->getMessage();
    error_log("Report Error: " . $e->getMessage());
}

// Helper functions
function formatDate($dateString) {
    if (!$dateString) return 'N/A';
    return date('d/m/Y H:i', strtotime($dateString));
}

function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'pending': return 'status-pending';
        case 'assigned': return 'status-assigned';
        case 'in_progress': return 'status-progress';
        case 'completed': return 'status-completed';
        case 'reviewed': return 'status-reviewed';
        case 'approved': return 'status-approved';
        default: return 'status-default';
    }
}
?>

<div class="page-header">
    <h2>📊 Reports</h2>
</div>

<?php if (!empty($error)): ?>
    <div class="error-message">
        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="report-container">
    <!-- Report Navigation Tabs -->
    <div class="report-tabs">
        <button class="tab-button active" onclick="window.showReportTab('completed-jobs', this)">
            <div class="tab-icon">✅</div>
            <div class="tab-title">Completed Jobs</div>
            <div class="tab-description">View all completed survey jobs</div>
        </button>
        <button class="tab-button" onclick="window.showReportTab('query-form', this)" id="query-form-tab">
            <div class="tab-icon">📝</div>
            <div class="tab-title">Query Form Report</div>
            <div class="tab-description">Analysis of project queries</div>
        </button>
        <button class="tab-button" onclick="window.showReportTab('project-summary', this)">
            <div class="tab-icon">📈</div>
            <div class="tab-title">Project Summary</div>
            <div class="tab-description">Comprehensive project metrics</div>
        </button>
        <button class="tab-button" onclick="window.showReportTab('query-summary', this)">
            <div class="tab-icon">🔍</div>
            <div class="tab-title">Query Summary</div>
            <div class="tab-description">Query resolution analytics</div>
        </button>
    </div>

    <!-- Report Actions -->
    <div class="report-actions">
        <div class="target-project-filters">
            <label for="targetProjectFilter">Filter by Target Project:</label>
            <select id="targetProjectFilter" onchange="window.applyTargetProjectFilter()">
                <option value="">All Projects</option>
                <?php foreach ($reportData['target_projects'] as $targetProject): ?>
                    <option value="<?php echo htmlspecialchars($targetProject); ?>">
                        <?php echo htmlspecialchars($targetProject); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button onclick="window.resetTargetProjectFilter()" class="btn-reset">Reset</button>
        </div>
        
        <div class="export-actions">
            <button onclick="window.exportReport('pdf')" class="btn-export">📄 Export PDF</button>
            <button onclick="window.exportReport('excel')" class="btn-export">📊 Export Excel</button>
            <button onclick="window.toggleFullscreen()" class="btn-export" id="fullscreenBtn" title="View Table in Fullscreen">🔳 Table Fullscreen</button>
            <button onclick="window.printReport()" class="btn-export">🖨️ Print</button>
        </div>
    </div>

    <!-- Completed Jobs Report -->
    <div id="completed-jobs" class="report-section active">
        <div class="report-header">
            <h3>✅ Completed Jobs Report</h3>
            <div class="summary-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($reportData['completed_jobs']); ?></span>
                    <span class="stat-label">Completed Jobs</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $reportData['summary']['acquisition_complete_jobs']; ?></span>
                    <span class="stat-label">Acquisition Complete</span>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="report-table" id="completedJobsTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>SJ Number</th>
                        <th>Project Name</th>
                        <th>Div Reference</th>
                        <th>HQ Reference</th>
                        <th>Status Job</th>
                        <th>Timeline (Job Completed)</th>
                        <th>Land Acquisition Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($reportData['completed_jobs'] as $job): 
                    ?>
                        <tr data-created-date="<?php echo $job['created_at']; ?>" data-updated-date="<?php echo $job['updated_at']; ?>" data-target-project="<?php echo htmlspecialchars($job['target_project'] ?? ''); ?>">
                            <td><?php echo $counter++; ?></td>
                            <td>
                                <a href="javascript:void(0)" onclick="viewJobDetails(<?php echo $job['survey_job_id']; ?>)" 
                                   class="job-link" title="Click to view job details">
                                    <strong><?php echo htmlspecialchars($job['surveyjob_no']); ?></strong>
                                </a>
                                <?php if (!empty($job['target_project'])): ?>
                                    <br><small class="target-project-info">
                                        <span class="target-project-badge">
                                            <?php echo htmlspecialchars($job['target_project']); ?>
                                        </span>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($job['projectname']); ?></td>
                            <td><?php echo htmlspecialchars($job['div_ref'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($job['hq_ref'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="status-badge <?php echo getStatusBadgeClass($job['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                </span>
                            </td>
                            <td class="date-cell"><?php echo formatDate($job['updated_at']); ?></td>
                            <td class="pbtstatus-cell">
                                <span class="pbtstatus-badge pbtstatus-<?php echo strtolower($job['pbtstatus'] ?? 'none'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $job['pbtstatus'] ?? 'None')); ?>
                                </span>
                                <?php if (strtolower($job['pbtstatus']) === 'acquisition_complete' && $job['acquisition_date']): ?>
                                    <div class="acquisition-date">
                                        <small><?php echo formatDate($job['acquisition_date']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Query Form Report -->
    <div id="query-form" class="report-section">
        <div class="report-header">
            <h3>📝 Query Form Report</h3>
        </div>
        
        <!-- Detailed Query List -->
        <?php if (!empty($reportData['query_forms'])): ?>
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>SJ Number</th>
                            <th>Project Name</th>
                            <th>Reference</th>
                            <th>Reviewer</th>
                            <th>Form Types with Queries</th>
                            <th>Query Details</th>
                            <th>Query Date</th>
                            <th>Query Returned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['query_forms'] as $queryForm): 
                            $queryInfo = json_decode($queryForm['query_info'], true);
                            $formsWithQueries = [];
                            $queryDetails = [];
                            
                            if ($queryInfo) {
                                foreach (['A1', 'B1', 'C1', 'L&16', 'L&S3', 'MP_Pelan'] as $formType) {
                                    if (!empty($queryInfo[$formType])) {
                                        $formsWithQueries[] = $formType;
                                        $queryDetails[] = $formType . ': ' . htmlspecialchars($queryInfo[$formType]);
                                    }
                                }
                            }
                        ?>
                            <tr data-created-date="<?php echo $queryForm['job_created_at']; ?>" data-target-project="<?php echo htmlspecialchars($queryForm['target_project'] ?? ''); ?>">
                                <td>
                                    <a href="javascript:void(0)" onclick="viewJobDetails(<?php echo $queryForm['surveyjob_id']; ?>)" 
                                       class="job-link" title="Click to view job details">
                                        <strong><?php echo htmlspecialchars($queryForm['surveyjob_no']); ?></strong>
                                    </a>
                                    <?php if (!empty($queryForm['target_project'])): ?>
                                        <br><small class="target-project-info">
                                            <span class="target-project-badge">
                                                <?php echo htmlspecialchars($queryForm['target_project']); ?>
                                            </span>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="project-info">
                                        <?php echo htmlspecialchars($queryForm['projectname']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="ref-item">HQ: <?php echo htmlspecialchars($queryForm['hq_ref']); ?></div>
                                    <div class="ref-item">Div: <?php echo htmlspecialchars($queryForm['div_ref']); ?></div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-name"><?php echo htmlspecialchars($queryForm['reviewer_name']); ?></div>
                                        <div class="user-role"><?php echo htmlspecialchars($queryForm['reviewer_role']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-types">
                                        <?php foreach ($formsWithQueries as $formType): ?>
                                            <span class="form-type-badge"><?php echo htmlspecialchars($formType); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="query-details">
                                        <?php foreach ($queryDetails as $detail): ?>
                                            <div class="query-item"><?php echo $detail; ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="date-cell"><?php echo formatDate($queryInfo['query_date']); ?></td>
                                <td class="date-cell"><?php echo formatDate($queryInfo['query_returned']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data">
                <h4>📭 No Query Data Available</h4>
                <p>No queries have been recorded in the system yet.</p>
                <p>Queries will appear here once they are submitted through the job assignment process.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Project Summary -->
    <div id="project-summary" class="report-section">
        <div class="report-header">
            <h3>📈 Project Summary Report</h3>
            <p>Detailed breakdown of projects with form types and lot counts</p>
            <div class="summary-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($reportData['project_summary']); ?></span>
                    <span class="stat-label">Total Projects</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo array_sum(array_column($reportData['project_summary'], 'total_forms')); ?></span>
                    <span class="stat-label">Total Forms</span>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="report-table" id="projectSummaryTable">
                <thead>
                    <tr>
                        <th rowspan="2">No</th>
                        <th rowspan="2">Project Name</th>
                        <th rowspan="2">SJ Number</th>
                        <th rowspan="2">Total Forms</th>
                        <th colspan="4" class="form-type-header">Number of Forms by Category</th>
                        <th colspan="4" class="lot-count-header">Number of Lots by Form Category</th>
                    </tr>
                    <tr>
                        <!-- Form Category Counts -->
                        <th class="form-type-subheader">Tanah Berhakmilik</th>
                        <th class="form-type-subheader">Hak Adat Bumiputera</th>
                        <th class="form-type-subheader">Native Customary Land</th>
                        <th class="form-type-subheader">Registered State Land</th>
                        <!-- Lot Counts -->
                        <th class="lot-count-subheader">Tanah Berhakmilik</th>
                        <th class="lot-count-subheader">Hak Adat Bumiputera</th>
                        <th class="lot-count-subheader">Native Customary Land</th>
                        <th class="lot-count-subheader">Registered State Land</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($reportData['project_summary'] as $project): 
                    ?>
                        <tr data-created-date="<?php echo $project['created_at']; ?>" data-target-project="<?php echo htmlspecialchars($project['target_project'] ?? ''); ?>">
                            <td><?php echo $counter++; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($project['projectname']); ?></strong>
                                <br><small class="text-muted"><?php echo date('M d, Y', strtotime($project['created_at'])); ?></small>
                            </td>
                            <td>
                                <a href="javascript:void(0)" onclick="viewJobDetails(<?php echo $project['survey_job_id']; ?>)" 
                                   class="job-link" title="Click to view job details">
                                    <strong><?php echo htmlspecialchars($project['surveyjob_no']); ?></strong>
                                </a>
                                <?php if (!empty($project['target_project'])): ?>
                                    <br><small class="target-project-info">
                                        <span class="target-project-badge">
                                            <?php echo htmlspecialchars($project['target_project']); ?>
                                        </span>
                                    </small>
                                <?php endif; ?>
                            </td>
                                    <strong><?php echo htmlspecialchars($project['surveyjob_no']); ?></strong>
                                </a>
                            </td>
                            <td class="total-forms"><strong><?php echo $project['total_forms']; ?></strong></td>
                            
                            <!-- Form Category Counts -->
                            <td class="form-count tanah-berhakmilik-count"><?php echo $project['tanah_berhakmilik_forms'] ?: '-'; ?></td>
                            <td class="form-count hak-adat-bumiputera-count"><?php echo $project['hak_adat_bumiputera_forms'] ?: '-'; ?></td>
                            <td class="form-count native-customary-land-count"><?php echo $project['native_customary_land_forms'] ?: '-'; ?></td>
                            <td class="form-count registered-state-land-count"><?php echo $project['registered_state_land_forms'] ?: '-'; ?></td>
                            
                            <!-- Lot Counts -->
                            <td class="lot-count tanah-berhakmilik-lots"><?php echo $project['tanah_berhakmilik_lots'] ?: '-'; ?></td>
                            <td class="lot-count hak-adat-bumiputera-lots"><?php echo $project['hak_adat_bumiputera_lots'] ?: '-'; ?></td>
                            <td class="lot-count native-customary-land-lots"><?php echo $project['native_customary_land_lots'] ?: '-'; ?></td>
                            <td class="lot-count registered-state-land-lots"><?php echo $project['registered_state_land_lots'] ?: '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <!-- Form Count Totals Row -->
                    <tr class="summary-row forms-total-row">
                        <td colspan="3"><strong>TOTAL FORMS</strong></td>
                        <td class="total-forms">
                            <strong><?php echo array_sum(array_column($reportData['project_summary'], 'total_forms')); ?></strong>
                        </td>
                        <!-- Form Category Totals -->
                        <td class="form-total tanah-berhakmilik-total">
                            <strong><?php echo array_sum(array_column($reportData['project_summary'], 'tanah_berhakmilik_forms')); ?></strong>
                        </td>
                        <td class="form-total hak-adat-bumiputera-total">
                            <strong><?php echo array_sum(array_column($reportData['project_summary'], 'hak_adat_bumiputera_forms')); ?></strong>
                        </td>
                        <td class="form-total native-customary-land-total">
                            <strong><?php echo array_sum(array_column($reportData['project_summary'], 'native_customary_land_forms')); ?></strong>
                        </td>
                        <td class="form-total registered-state-land-total">
                            <strong><?php echo array_sum(array_column($reportData['project_summary'], 'registered_state_land_forms')); ?></strong>
                        </td>
                        <!-- Empty cells for lot columns -->
                        <td colspan="4" class="lot-totals-spacer"></td>
                    </tr>
                    <!-- Lot Count Totals Row -->
                    <tr class="summary-row lots-total-row">
                        <td colspan="3"><strong>TOTAL LOTS</strong></td>
                        <td class="total-lots">
                            <strong><?php 
                                $totalLots = array_sum(array_column($reportData['project_summary'], 'tanah_berhakmilik_lots')) +
                                           array_sum(array_column($reportData['project_summary'], 'hak_adat_bumiputera_lots')) +
                                           array_sum(array_column($reportData['project_summary'], 'native_customary_land_lots')) +
                                           array_sum(array_column($reportData['project_summary'], 'registered_state_land_lots'));
                                echo $totalLots;
                            ?></strong>
                        </td>
                        <!-- Empty cells for form columns -->
                        <td colspan="4" class="form-totals-spacer"></td>
                        <!-- Lot Count Totals -->
                        <td class="lot-total tanah-berhakmilik-lot-total">
                            <strong><?php echo array_sum(array_column($reportData['project_summary'], 'tanah_berhakmilik_lots')); ?></strong>
                        </td>
                        <td class="lot-total hak-adat-bumiputera-lot-total">
                            <strong><?php echo array_sum(array_column($reportData['project_summary'], 'hak_adat_bumiputera_lots')); ?></strong>
                        </td>
                        <td class="lot-total native-customary-land-lot-total">
                            <strong><?php echo array_sum(array_column($reportData['project_summary'], 'native_customary_land_lots')); ?></strong>
                        </td>
                        <td class="lot-total registered-state-land-lot-total">
                            <strong><?php echo array_sum(array_column($reportData['project_summary'], 'registered_state_land_lots')); ?></strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Query Summary -->
    <div id="query-summary" class="report-section">
        <div class="report-header">
            <h3>🔍 Query Summary by Year</h3>
            <p>Yearly breakdown of projects, lots, and forms with query analysis</p>
            <div class="summary-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($reportData['query_summary']); ?></span>
                    <span class="stat-label">Years Analyzed</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo array_sum(array_column($reportData['query_summary'], 'projects_with_queries')); ?></span>
                    <span class="stat-label">Total Projects with Queries</span>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="report-table" id="querySummaryTable">
                <thead>
                    <tr>
                        <th rowspan="3">Year</th>
                        <th colspan="3" class="projects-header">No of Projects</th>
                        <th colspan="3" class="lots-header">No of Lots</th>
                        <th colspan="3" class="forms-header">No of Forms</th>
                    </tr>
                    <tr>
                        <th class="project-subheader">Total Acquired</th>
                        <th class="project-subheader">With Query</th>
                        <th class="project-subheader">Query %</th>
                        <th class="lot-subheader">Total Lots</th>
                        <th class="lot-subheader">With Query</th>
                        <th class="lot-subheader">Query %</th>
                        <th class="form-subheader">Total Forms</th>
                        <th class="form-subheader">With Query</th>
                        <th class="form-subheader">Query %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reportData['query_summary'])): ?>
                        <?php foreach ($reportData['query_summary'] as $yearData): ?>
                            <tr>
                                <td class="year-cell">
                                    <strong><?php echo $yearData['year']; ?></strong>
                                </td>
                                <!-- Project Data -->
                                <td class="project-total"><?php echo $yearData['total_projects']; ?></td>
                                <td class="project-query"><?php echo $yearData['projects_with_queries']; ?></td>
                                <td class="project-percentage">
                                    <span class="percentage-badge <?php echo $yearData['project_query_percentage'] > 20 ? 'high' : ($yearData['project_query_percentage'] > 10 ? 'medium' : 'low'); ?>">
                                        <?php echo $yearData['project_query_percentage']; ?>%
                                    </span>
                                </td>
                                <!-- Lot Data -->
                                <td class="lot-total"><?php echo $yearData['total_lots']; ?></td>
                                <td class="lot-query"><?php echo $yearData['lots_with_queries']; ?></td>
                                <td class="lot-percentage">
                                    <span class="percentage-badge <?php echo $yearData['lot_query_percentage'] > 20 ? 'high' : ($yearData['lot_query_percentage'] > 10 ? 'medium' : 'low'); ?>">
                                        <?php echo $yearData['lot_query_percentage']; ?>%
                                    </span>
                                </td>
                                <!-- Form Data -->
                                <td class="form-total"><?php echo $yearData['total_forms']; ?></td>
                                <td class="form-query"><?php echo $yearData['forms_with_queries']; ?></td>
                                <td class="form-percentage">
                                    <span class="percentage-badge <?php echo $yearData['form_query_percentage'] > 20 ? 'high' : ($yearData['form_query_percentage'] > 10 ? 'medium' : 'low'); ?>">
                                        <?php echo $yearData['form_query_percentage']; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="no-data-cell">
                                <div class="no-data-message">
                                    <h4>📭 No Query Summary Data Available</h4>
                                    <p>No yearly data found for query analysis.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="summary-row">
                        <td><strong>TOTAL</strong></td>
                        <!-- Project Totals -->
                        <td class="total-cell">
                            <strong><?php echo array_sum(array_column($reportData['query_summary'], 'total_projects')); ?></strong>
                        </td>
                        <td class="total-cell">
                            <strong><?php echo array_sum(array_column($reportData['query_summary'], 'projects_with_queries')); ?></strong>
                        </td>
                        <td class="total-percentage">
                            <?php 
                                $totalProjects = array_sum(array_column($reportData['query_summary'], 'total_projects'));
                                $totalProjectsWithQueries = array_sum(array_column($reportData['query_summary'], 'projects_with_queries'));
                                $overallProjectPercentage = $totalProjects > 0 ? round(($totalProjectsWithQueries / $totalProjects) * 100, 1) : 0;
                            ?>
                            <strong class="percentage-badge <?php echo $overallProjectPercentage > 20 ? 'high' : ($overallProjectPercentage > 10 ? 'medium' : 'low'); ?>">
                                <?php echo $overallProjectPercentage; ?>%
                            </strong>
                        </td>
                        <!-- Lot Totals -->
                        <td class="total-cell">
                            <strong><?php echo array_sum(array_column($reportData['query_summary'], 'total_lots')); ?></strong>
                        </td>
                        <td class="total-cell">
                            <strong><?php echo array_sum(array_column($reportData['query_summary'], 'lots_with_queries')); ?></strong>
                        </td>
                        <td class="total-percentage">
                            <?php 
                                $totalLots = array_sum(array_column($reportData['query_summary'], 'total_lots'));
                                $totalLotsWithQueries = array_sum(array_column($reportData['query_summary'], 'lots_with_queries'));
                                $overallLotPercentage = $totalLots > 0 ? round(($totalLotsWithQueries / $totalLots) * 100, 1) : 0;
                            ?>
                            <strong class="percentage-badge <?php echo $overallLotPercentage > 20 ? 'high' : ($overallLotPercentage > 10 ? 'medium' : 'low'); ?>">
                                <?php echo $overallLotPercentage; ?>%
                            </strong>
                        </td>
                        <!-- Form Totals -->
                        <td class="total-cell">
                            <strong><?php echo array_sum(array_column($reportData['query_summary'], 'total_forms')); ?></strong>
                        </td>
                        <td class="total-cell">
                            <strong><?php echo array_sum(array_column($reportData['query_summary'], 'forms_with_queries')); ?></strong>
                        </td>
                        <td class="total-percentage">
                            <?php 
                                $totalForms = array_sum(array_column($reportData['query_summary'], 'total_forms'));
                                $totalFormsWithQueries = array_sum(array_column($reportData['query_summary'], 'forms_with_queries'));
                                $overallFormPercentage = $totalForms > 0 ? round(($totalFormsWithQueries / $totalForms) * 100, 1) : 0;
                            ?>
                            <strong class="percentage-badge <?php echo $overallFormPercentage > 20 ? 'high' : ($overallFormPercentage > 10 ? 'medium' : 'low'); ?>">
                                <?php echo $overallFormPercentage; ?>%
                            </strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Job Details Modal -->
<div id="jobDetailsModal" class="modal" style="display: none;">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Job Details</h3>
            <span class="modal-close" onclick="closeJobDetailsModal()">&times;</span>
        </div>
        <div class="modal-body" id="jobDetailsContent">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<script>
// Report page initialization - functions will be loaded from report.js
// Main initialization is at the end of the file
</script>

<style>
.page-header {
    margin-bottom: 2rem;
    text-align: center;
    background: #ffffff;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
}

.page-header h2 {
    color: #1e293b;
    margin-bottom: 0.5rem;
    font-size: 2rem;
    font-weight: 700;
}

.page-header p {
    color: #64748b;
    font-size: 1.1rem;
}

.report-container {
    max-width: 1400px;
    margin: 0 auto;
}

.report-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 3rem;
    background: #ffffff;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
    margin-bottom: 3rem;
}

.tab-button {
    padding: 2rem 1.5rem;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    color: #64748b;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    min-height: 160px;
    position: relative;
    overflow: hidden;
}

.tab-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s;
}

.tab-button:hover::before {
    left: 100%;
}

.tab-button:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.15);
}

.tab-button.active {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.tab-button.active:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(59, 130, 246, 0.4);
}

.tab-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    opacity: 0.9;
}

.tab-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    line-height: 1.2;
}

.tab-description {
    font-size: 0.875rem;
    opacity: 0.8;
    font-weight: 400;
    line-height: 1.3;
}

.tab-button.active .tab-description {
    opacity: 0.9;
}

.report-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    gap: 1rem;
    flex-wrap: wrap;
    background: #ffffff;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

.date-filters {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.date-filters label {
    font-weight: 500;
    color: #374151;
}

.date-filters input {
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.9rem;
}

.export-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-export, .btn-reset {
    padding: 0.6rem 1rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-export {
    background: #10b981;
    color: white;
}

.btn-export:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-reset {
    background: #f59e0b;
    color: white;
}

.btn-reset:hover {
    background: #d97706;
}

/* Table Fullscreen styles */
.table-fullscreen-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 9999 !important;
    background: #ffffff !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
}

.table-fullscreen-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 1rem 2rem !important;
    background: #f8fafc !important;
    border-bottom: 2px solid #e2e8f0 !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
    flex-shrink: 0 !important;
}

.table-fullscreen-title {
    margin: 0 !important;
    color: #1e293b !important;
    font-size: 1.5rem !important;
    font-weight: 700 !important;
}

.table-fullscreen-close {
    padding: 0.6rem 1rem !important;
    border: none !important;
    border-radius: 6px !important;
    background: #ef4444 !important;
    color: white !important;
    cursor: pointer !important;
    font-size: 0.9rem !important;
    font-weight: 500 !important;
    transition: all 0.3s ease !important;
}

.table-fullscreen-close:hover {
    background: #dc2626 !important;
    transform: translateY(-1px) !important;
}

.table-fullscreen-content {
    flex: 1 !important;
    overflow: auto !important;
    padding: 1rem 2rem !important;
    margin: 0 !important;
}

.table-fullscreen-content .report-table {
    font-size: 1rem !important;
    width: 100% !important;
}

.table-fullscreen-content .report-table th {
    background: #f8fafc !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
    padding: 1.25rem 1rem !important;
    font-size: 0.95rem !important;
    font-weight: 600 !important;
}

.table-fullscreen-content .report-table td {
    padding: 1.25rem 1rem !important;
    font-size: 0.95rem !important;
}

.table-fullscreen-content .report-table tr:hover {
    background: #f1f5f9 !important;
}

/* Enhanced button styling for fullscreen */
.fullscreen-exit-btn {
    background: #ef4444 !important;
    color: white !important;
}

.fullscreen-exit-btn:hover {
    background: #dc2626 !important;
}

/* Custom scrollbar for table fullscreen */
.table-fullscreen-content::-webkit-scrollbar {
    width: 12px;
    height: 12px;
}

.table-fullscreen-content::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 6px;
}

.table-fullscreen-content::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 6px;
    border: 2px solid #f1f5f9;
}

.table-fullscreen-content::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.table-fullscreen-content::-webkit-scrollbar-corner {
    background: #f1f5f9;
}

.report-section {
    display: none;
    background: #ffffff;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    margin-bottom: 2rem;
}

.report-section.active {
    display: block;
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f1f5f9;
}

.report-header h3 {
    color: #1e293b;
    font-size: 1.5rem;
    margin: 0;
}

.summary-stats {
    display: flex;
    gap: 2rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: bold;
    color: #3b82f6;
}

.stat-label {
    color: #64748b;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-responsive {
    overflow-x: auto;
    margin: 1rem 0;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.report-table th {
    background: #f8fafc;
    padding: 1rem 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}

.report-table td {
    padding: 1rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
}

.report-table tr:hover {
    background: #f8fafc;
}

.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-assigned { background: #dbeafe; color: #1e40af; }
.status-progress { background: #fed7d7; color: #c53030; }
.status-completed { background: #d1fae5; color: #065f46; }
.status-reviewed { background: #e0e7ff; color: #3730a3; }
.status-approved { background: #d1fae5; color: #064e3b; }
.status-default { background: #f1f5f9; color: #64748b; }

.pbtstatus-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.pbtstatus-cell {
    text-align: center;
    min-width: 120px;
}

.pbtstatus-none { background: #f1f5f9; color: #64748b; }
.pbtstatus-checking { background: #fef3c7; color: #92400e; }
.pbtstatus-checked { background: #dbeafe; color: #1e40af; }
.pbtstatus-acquisition-complete { background: #d1fae5; color: #065f46; }

.acquisition-date {
    margin-top: 0.25rem;
    font-size: 0.7rem;
    color: #065f46;
    font-weight: 500;
    text-align: center;
}

.acquisition-date small {
    background: rgba(209, 250, 229, 0.8);
    padding: 0.125rem 0.375rem;
    border-radius: 8px;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.user-info {
    line-height: 1.3;
}

.user-name {
    font-weight: 500;
    color: #1e293b;
    margin-bottom: 0.125rem;
}

.user-role {
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.unassigned {
    color: #94a3b8;
    font-style: italic;
    font-size: 0.85rem;
}

.date-cell {
    font-family: monospace;
    font-size: 0.85rem;
    color: #64748b;
    white-space: nowrap;
}

.ref-item {
    margin-bottom: 0.25rem;
    font-size: 0.85rem;
}

.query-stats, .summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.query-card, .summary-card {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    text-align: center;
    transition: transform 0.3s ease;
}

.query-card:hover, .summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.query-card h4, .summary-card h4 {
    color: #1e293b;
    margin-bottom: 1rem;
}

.query-count {
    font-size: 2.5rem;
    font-weight: bold;
    color: #3b82f6;
    margin-bottom: 0.5rem;
}

.stats-list {
    text-align: left;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e2e8f0;
}

.stat-row:last-child {
    border-bottom: none;
}

.chart-container {
    margin: 2rem 0;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.chart-container h4 {
    margin-bottom: 1rem;
    color: #1e293b;
}

.performance-section {
    margin: 2rem 0;
}

.performance-section h4 {
    color: #1e293b;
    margin-bottom: 1rem;
}

.role-badge {
    padding: 0.25rem 0.5rem;
    background: #e0e7ff;
    color: #3730a3;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.coming-soon {
    text-align: center;
    padding: 3rem 2rem;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-radius: 12px;
    border: 1px solid #f59e0b;
    margin: 2rem 0;
}

.coming-soon h4 {
    color: #92400e;
    margin-bottom: 1rem;
}

.coming-soon p {
    color: #b45309;
    margin-bottom: 0.5rem;
}

.query-analysis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin: 2rem 0;
}

.analysis-card {
    background: #f8fafc;
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.analysis-card h4 {
    color: #1e293b;
    margin-bottom: 1rem;
}

.category-list, .resolution-stats {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.category-item, .resolution-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.category-name, .resolution-item span {
    color: #374151;
    font-weight: 500;
}

.category-count {
    background: #3b82f6;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: bold;
}

.error-message {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.error-message {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.form-types {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.form-type-badge {
    background: #dbeafe;
    color: #1e40af;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

#projectSummaryTable .form-count,
#projectSummaryTable .lot-count {
    text-align: center;
    vertical-align: middle;
    font-weight: 500;
    color: #374151;
}

/* Project Summary Table Styles */
#projectSummaryTable {
    font-size: 0.9rem;
}

#projectSummaryTable th {
    text-align: center;
    vertical-align: middle;
    padding: 0.75rem 0.5rem;
    font-size: 0.8rem;
    font-weight: 600;
}

#projectSummaryTable .form-type-header {
    background: linear-gradient(135deg, #3b82f6, #1e40af);
    color: white;
    border-color: #1e40af;
}

#projectSummaryTable .lot-count-header {
    background: linear-gradient(135deg, #3b82f6, #1e40af);
    color: white;
    border-color: #1e40af;
}

#projectSummaryTable .form-type-subheader {
    background: #dbeafe;
    color: #1e40af;
    font-weight: 500;
    font-size: 0.8rem;
}

#projectSummaryTable .lot-count-subheader {
    background: #dbeafe;
    color: #1e40af;
    font-weight: 500;
    font-size: 0.8rem;
}

#projectSummaryTable .form-count,
#projectSummaryTable .lot-count {
    text-align: center;
    vertical-align: middle;
    font-weight: 500;
}

#projectSummaryTable .total-forms {
    background: #f3f4f6;
    font-weight: 600;
    text-align: center;
}

#projectSummaryTable .summary-row {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    font-weight: 600;
    border-top: 2px solid #e2e8f0;
}

#projectSummaryTable .summary-row td {
    padding: 0.75rem;
    text-align: center;
    border-top: 2px solid #e2e8f0;
}

/* Specific styling for forms total row */
#projectSummaryTable .forms-total-row {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
}

#projectSummaryTable .forms-total-row td {
    border-top: 2px solid #3b82f6;
}

#projectSummaryTable .total-forms,
#projectSummaryTable .form-total {
    background: #3b82f6;
    color: white;
    font-weight: 700;
}

/* Specific styling for lots total row */
#projectSummaryTable .lots-total-row {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
}

#projectSummaryTable .lots-total-row td {
    border-top: 2px solid #10b981;
}

#projectSummaryTable .total-lots,
#projectSummaryTable .lot-total {
    background: #10b981;
    color: white;
    font-weight: 700;
}

/* Additional project summary styling */
#projectSummaryTable .text-muted {
    color: #6b7280;
    font-size: 0.75rem;
}

/* Query Summary Table - Minimized Styling */
#querySummaryTable {
    font-size: 0.8rem;
}

#querySummaryTable th {
    text-align: center;
    vertical-align: middle;
    padding: 0.5rem 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
}

#querySummaryTable .projects-header,
#querySummaryTable .lots-header,
#querySummaryTable .forms-header {
    background: #374151;
    color: white;
    border-color: #374151;
}

#querySummaryTable .project-subheader,
#querySummaryTable .lot-subheader,
#querySummaryTable .form-subheader {
    background: #f3f4f6;
    color: #374151;
    font-weight: 500;
    font-size: 0.7rem;
}

#querySummaryTable td {
    padding: 0.5rem 0.4rem;
    text-align: center;
    vertical-align: middle;
    font-size: 0.75rem;
}

#querySummaryTable .year-cell {
    font-weight: 600;
    background: #f9fafb;
}

#querySummaryTable .percentage-badge {
    padding: 0.15rem 0.4rem;
    border-radius: 8px;
    font-size: 0.65rem;
    font-weight: 500;
    white-space: nowrap;
}

#querySummaryTable .percentage-badge.high {
    background: #fecaca;
    color: #b91c1c;
}

#querySummaryTable .percentage-badge.medium {
    background: #fed7aa;
    color: #ea580c;
}

#querySummaryTable .percentage-badge.low {
    background: #d1fae5;
    color: #065f46;
}

#querySummaryTable .summary-row {
    background: #f3f4f6;
    font-weight: 600;
    border-top: 1px solid #d1d5db;
}

#querySummaryTable .summary-row td {
    border-top: 1px solid #d1d5db;
    font-size: 0.8rem;
}

#querySummaryTable .total-cell {
    background: #e5e7eb;
    font-weight: 700;
}

#querySummaryTable .total-percentage {
    background: #e5e7eb;
}

.query-insights {
    margin-top: 1.5rem;
}

.insight-card {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.insight-card h4 {
    color: #374151;
    margin-bottom: 0.75rem;
    font-size: 1rem;
}

.insights-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.insight-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #4b5563;
}

.insight-icon {
    font-size: 1rem;
    opacity: 0.8;
}

.insight-text {
    line-height: 1.4;
}

/* Modal styles moved to bottom of file to match job list page */

/* Forms Section Styles - Simple Tree Structure */
.forms-section {
    margin-top: 1.5rem;
}

.forms-section h4 {
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.forms-summary {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.forms-summary p {
    color: #64748b;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
}

.btn-load-forms {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-load-forms:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-load-forms:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
}

.forms-container {
    margin-top: 1rem;
}

/* Simple Tree Structure Styles */
.forms-tree {
    background: #ffffff;
    border-radius: 8px;
    overflow: hidden;
}

.forms-summary-header {
    background: #f1f5f9;
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.forms-summary-header h4 {
    color: #1e293b;
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
}

.forms-summary-header p {
    color: #64748b;
    margin: 0;
    font-size: 0.9rem;
}

.form-category {
    border-bottom: 1px solid #e2e8f0;
}

.category-header {
    background: #ffffff;
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
}

.category-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.category-icon {
    font-size: 1.5rem;
}

.category-info h5 {
    color: #1e293b;
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
    font-weight: 600;
}

.category-count {
    color: #64748b;
    font-size: 0.85rem;
    font-weight: 500;
}

.category-lots {
    background: #fafbfc;
}

.lot-group {
    border-bottom: 1px solid #f1f5f9;
}

.lot-group:last-child {
    border-bottom: none;
}

.lot-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.5rem;
    cursor: pointer;
    transition: background-color 0.2s ease;
    user-select: none;
}

.lot-header:hover {
    background: #f1f5f9;
}

.lot-header:active {
    background: #e2e8f0;
}

.lot-icon {
    font-size: 1.2rem;
    color: #64748b;
}

.lot-title {
    flex: 1;
    color: #374151;
    font-weight: 500;
    font-size: 0.95rem;
}

.lot-count {
    color: #64748b;
    font-size: 0.85rem;
    background: #f1f5f9;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.lot-toggle-icon {
    color: #9ca3af;
    font-size: 0.8rem;
    transition: transform 0.3s ease;
}

.lot-toggle-icon.expanded {
    transform: rotate(180deg);
}

.lot-forms {
    background: #ffffff;
    overflow: hidden;
    transition: all 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 500px;
    }
}

.form-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 2rem;
    border-bottom: 1px solid #f8fafc;
    cursor: pointer;
    transition: all 0.2s ease;
}

.form-item:hover {
    background: #f8fafc;
    transform: translateX(4px);
}

.form-item:last-child {
    border-bottom: none;
}

.form-icon {
    color: #3b82f6;
    font-size: 1.1rem;
}

.form-info {
    flex: 1;
    min-width: 0;
}

.form-title {
    color: #1e293b;
    font-weight: 500;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.form-type {
    color: #64748b;
    font-size: 0.8rem;
    margin-bottom: 0.2rem;
}

.form-date {
    color: #9ca3af;
    font-size: 0.75rem;
}

/* Responsive adjustments for forms tree */
@media (max-width: 768px) {
    .lot-header {
        padding: 0.75rem 1rem;
        gap: 0.5rem;
    }
    
    .form-item {
        padding: 0.75rem 1.5rem;
        gap: 0.5rem;
    }
    
    .category-header {
        padding: 0.75rem;
    }
}

@media (max-width: 480px) {
    .forms-summary-header {
        padding: 0.75rem;
    }
    
    .lot-header {
        padding: 0.5rem 0.75rem;
    }
    
    .form-item {
        padding: 0.5rem 1rem;
    }
}

.parent-attachments-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.parent-attachment-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e2e8f0;
}

.parent-attachment-name {
    flex: 1;
    color: #374151;
    font-size: 0.9rem;
    word-break: break-word;
}

.btn-open-parent-attachment {
    background: #10b981;
    color: white;
    border: none;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-open-parent-attachment:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-open-parent-attachment:active {
    transform: translateY(0);
}

/* Attachments Section Styles */
.attachments-section {
    margin-top: 1.5rem;
}

.attachments-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.attachments-icon {
    font-size: 1.25rem;
    color: #3b82f6;
}

.attachments-title {
    color: #1e293b;
    font-weight: 600;
}

.attachments-list {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.attachment-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: all 0.2s ease;
}

.attachment-item:hover {
    background: #f8fafc;
    transform: translateX(2px);
}

.attachment-item:last-child {
    border-bottom: none;
}

.attachment-icon {
    color: #64748b;
    font-size: 1.1rem;
}

.attachment-name {
    flex: 1;
    color: #1e293b;
    font-weight: 500;
    font-size: 0.9rem;
    word-break: break-word;
}

.btn-open-attachment {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-open-attachment:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-open-attachment:active {
    transform: translateY(0);
}

/* Loading and error states */
.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    gap: 1rem;
}

.loading-spinner div {
    font-size: 2rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.error-state, .no-forms, .no-attachments {
    text-align: center;
    padding: 2rem;
    color: #64748b;
}

.error-state h5 {
    color: #dc2626;
    margin-bottom: 0.5rem;
}

/* Detail row styling to match job list page */
.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row .label {
    font-weight: 600;
    color: #374151;
    min-width: 140px;
    flex-shrink: 0;
}

.detail-row .value {
    color: #1e293b;
    text-align: right;
    flex: 1;
    word-break: break-word;
}

/* PBT Status badges */
.pbtstatus-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.pbtstatus-none { background: #f1f5f9; color: #64748b; }
.pbtstatus-checking { background: #fef3c7; color: #92400e; }
.pbtstatus-checked { background: #dbeafe; color: #1e40af; }
.pbtstatus-acquisition-complete { background: #d1fae5; color: #065f46; }

/* Complete Modal Styles from Job List */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(4px);
}

.modal-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease-out;
}

.modal-large {
    max-width: 800px;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
}

.modal-header h3 {
    margin: 0;
    color: #374151;
}

.modal-close {
    cursor: pointer;
    font-size: 1.5rem;
    color: #6b7280;
    background: #f3f4f6;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    border: none;
}

.modal-close:hover {
    background: #ef4444;
    color: white;
    transform: scale(1.1);
}

.modal-body {
    padding: 2rem;
    overflow-y: auto;
    max-height: calc(85vh - 80px);
}

.job-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin: 1rem 0;
}

.detail-section {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.detail-section h4 {
    margin: 0 0 0.75rem 0;
    color: #374151;
    font-size: 1rem;
}

/* Forms Section Styles */
.forms-section {
    background: linear-gradient(135deg, #fefce8, #fef3c7);
    border: 1px solid #f59e0b;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.forms-section h4 {
    margin: 0 0 1rem 0;
    color: #92400e;
    font-size: 1.1rem;
    font-weight: 600;
}

.forms-summary {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #fbbf24;
    margin-bottom: 1rem;
}

.forms-summary p {
    margin: 0 0 1rem 0;
    color: #92400e;
    font-weight: 500;
}

.btn-load-forms {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
}

.btn-load-forms:hover {
    background: linear-gradient(135deg, #d97706, #b45309);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(245, 158, 11, 0.4);
}

.btn-load-forms:disabled {
    background: #d1d5db;
    color: #9ca3af;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.forms-container {
    margin-top: 1rem;
}

/* Forms Tree Structure */
.forms-tree {
    margin-top: 1rem;
}

.forms-summary-header {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    border: 2px solid #0ea5e9;
    text-align: center;
}

.forms-summary-header h4 {
    margin: 0 0 0.5rem 0;
    color: #0c4a6e;
    font-size: 1.3rem;
    font-weight: 700;
}

.forms-summary-header p {
    margin: 0;
    color: #0369a1;
    font-size: 1rem;
}

.form-category {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.category-header {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 1rem 1.5rem;
    border-bottom: 2px solid #e2e8f0;
}

.category-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.category-icon {
    font-size: 1.5rem;
    opacity: 0.8;
}

.category-info h5 {
    margin: 0 0 0.25rem 0;
    color: #1e293b;
    font-size: 1.1rem;
    font-weight: 600;
}

.category-count {
    color: #64748b;
    font-size: 0.85rem;
    font-weight: 500;
}

.category-lots {
    padding: 1rem;
    background: #fefefe;
}

.lot-group {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 1rem;
    overflow: hidden;
}

.lot-group:last-child {
    margin-bottom: 0;
}

.lot-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: white;
    border-bottom: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    user-select: none;
}

.lot-header:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.lot-header:active {
    background: #f1f5f9;
    transform: translateY(1px);
}

.lot-icon {
    font-size: 1rem;
    color: #10b981;
}

.lot-title {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.95rem;
    flex: 1;
}

.lot-count {
    background: #e0f2fe;
    color: #0c4a6e;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.lot-toggle-icon {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: bold;
    transition: all 0.3s ease;
    margin-left: 0.5rem;
}

.lot-toggle-icon.expanded {
    color: #3b82f6;
    transform: rotate(180deg);
}

.lot-forms {
    padding: 0.5rem;
    background: #fefefe;
    border-top: 1px solid #f1f5f9;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        max-height: 500px;
        transform: translateY(0);
    }
}

.form-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: white;
    border: 1px solid #f1f5f9;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    transition: all 0.2s ease;
    cursor: pointer;
}

.form-item:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.form-item:last-child {
    margin-bottom: 0;
}

.form-icon {
    font-size: 1rem;
    color: #3b82f6;
    flex-shrink: 0;
}

.form-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.form-title {
    font-weight: 500;
    color: #1e293b;
    font-size: 0.9rem;
}

.form-type {
    color: #64748b;
    font-size: 0.8rem;
    font-family: monospace;
    background: #f1f5f9;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    display: inline-block;
    width: fit-content;
}

.form-date {
    color: #94a3b8;
    font-size: 0.75rem;
    font-family: monospace;
    flex-shrink: 0;
}

/* Parent Attachments Section */
.parent-attachments-container {
    margin-bottom: 1.5rem;
}

.parent-attachments-section {
    padding: 1rem;
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-radius: 8px;
    border: 1px solid #0ea5e9;
}

.parent-attachments-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #bae6fd;
}

.parent-attachments-icon {
    font-size: 1.1rem;
    color: #0c4a6e;
}

.parent-attachments-title {
    font-weight: 600;
    color: #0c4a6e;
    font-size: 1rem;
}

.parent-attachments-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.parent-attachment-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #bae6fd;
    transition: all 0.2s ease;
}

.parent-attachment-item:hover {
    border-color: #7dd3fc;
    box-shadow: 0 2px 4px rgba(14, 165, 233, 0.1);
}

.parent-attachment-icon {
    font-size: 1.1rem;
    color: #0c4a6e;
}

.parent-attachment-name {
    flex: 1;
    font-size: 0.9rem;
    color: #0c4a6e;
    font-weight: 500;
    word-break: break-word;
}

.btn-open-parent-attachment {
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-open-parent-attachment:hover {
    background: linear-gradient(135deg, #0284c7, #0369a1);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(14, 165, 233, 0.3);
}

/* Attachments Section */
.attachments-section {
    margin-top: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.attachments-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #cbd5e1;
}

.attachments-icon {
    font-size: 1rem;
    color: #64748b;
}

.attachments-title {
    font-weight: 600;
    color: #475569;
    font-size: 0.9rem;
}

.attachments-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.attachment-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}

.attachment-item:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.attachment-icon {
    font-size: 1.1rem;
    color: #64748b;
}

.attachment-name {
    flex: 1;
    font-size: 0.9rem;
    color: #374151;
    font-weight: 500;
    word-break: break-word;
}

.btn-open-attachment {
    padding: 0.4rem 0.8rem;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-open-attachment:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
}

.attachment-section {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.attachment-section h4 {
    margin: 0 0 1rem 0;
    color: #1e293b;
    font-size: 1.1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.attachment-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.attachment-date {
    color: #64748b;
    font-size: 0.75rem;
    font-family: monospace;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.additional-attachments {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 2px solid #e2e8f0;
}

.additional-attachments h5 {
    margin: 0 0 1rem 0;
    color: #1e293b;
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-view {
    padding: 0.4rem 0.8rem;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-view:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background: #6b7280;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

.loading-spinner {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
}

.loading-spinner div {
    font-size: 2rem;
    margin-bottom: 1rem;
}

.no-forms {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

/* Custom scrollbar for modal */
.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Responsive modal adjustments */
@media (max-width: 768px) {
    .modal-content {
        max-width: 95%;
        margin: 0.5rem;
    }
    
    .modal-header {
        padding: 1rem;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .job-details-grid {
        grid-template-columns: 1fr;
    }
    
    .forms-summary-header {
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .category-header {
        padding: 0.75rem 1rem;
    }
    
    .category-title {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .lot-header {
        padding: 0.5rem 0.75rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .form-item {
        padding: 0.5rem;
        gap: 0.5rem;
    }
    
    .attachment-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .attachment-info {
        width: 100%;
    }
}
</style>

<script src="../assets/js/pages/report.js"></script>
<script>
// Initialize the report page when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing report page and modal functionality');
    
    // Initialize the main report page
    initializeReportPage();
    
    // Ensure modal functionality works
    console.log('Setting up modal event handlers');
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('jobDetailsModal');
        if (event.target === modal && typeof window.closeJobDetailsModal === 'function') {
            console.log('Closing modal via outside click');
            window.closeJobDetailsModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && typeof window.closeJobDetailsModal === 'function') {
            console.log('Closing modal via Escape key');
            window.closeJobDetailsModal();
        }
    });
});
</script>

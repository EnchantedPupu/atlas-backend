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
$jobs = [];
$error = '';
$totalJobs = 0;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check if updated_at column exists, if not add it
    try {
        $columnCheck = $db->query("SHOW COLUMNS FROM SurveyJob LIKE 'updated_at'");
        if ($columnCheck->rowCount() == 0) {
            // Add the missing column
            $db->exec("ALTER TABLE SurveyJob ADD COLUMN updated_at DATETIME DEFAULT NULL AFTER created_at");
            error_log("Added missing updated_at column to SurveyJob table");
        }
    } catch (Exception $e) {
        error_log("Could not check/add updated_at column: " . $e->getMessage());
        // Continue without updated_at functionality
    }
      // Build query based on user role
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
                assignee.name as assigned_to_name,
                assignee.role as assigned_to_role,
                assigner.name as assigned_by_name,
                assigner.role as assigned_by_role,
                fi_ta_assignment_ranked.fi_ta_date,
                completed_status.is_completed,
                acquisition_complete_ranked.acquisition_date
            FROM SurveyJob sj
            LEFT JOIN User creator ON sj.created_by = creator.user_id
            LEFT JOIN User assignee ON sj.assigned_to = assignee.user_id
            LEFT JOIN (
                SELECT 
                    jh.survey_job_id,
                    jh.from_user_id as assigned_by_user_id,
                    ROW_NUMBER() OVER (PARTITION BY jh.survey_job_id ORDER BY jh.created_at DESC) as rn
                FROM jobhistory jh
                WHERE jh.action_type = 'assigned'
            ) latest_assignment ON sj.survey_job_id = latest_assignment.survey_job_id AND latest_assignment.rn = 1
            LEFT JOIN User assigner ON latest_assignment.assigned_by_user_id = assigner.user_id
            LEFT JOIN (
                SELECT 
                    jh.survey_job_id,
                    jh.created_at as fi_ta_date,
                    ROW_NUMBER() OVER (PARTITION BY jh.survey_job_id ORDER BY jh.created_at DESC) as rn
                FROM jobhistory jh
                WHERE jh.action_type = 'assigned' 
                AND jh.from_role = 'FI' 
                AND jh.to_role = 'AS'
            ) fi_ta_assignment_ranked ON sj.survey_job_id = fi_ta_assignment_ranked.survey_job_id AND fi_ta_assignment_ranked.rn = 1
            LEFT JOIN (
                SELECT 
                    jh.survey_job_id,
                    MAX(CASE WHEN jh.status_after = 'completed' THEN 1 ELSE 0 END) as is_completed
                FROM jobhistory jh
                WHERE jh.survey_job_id IN (
                    SELECT DISTINCT survey_job_id 
                    FROM jobhistory 
                    WHERE from_role = 'FI' AND to_role = 'AS'
                )
                AND jh.created_at = (
                    SELECT MAX(jh2.created_at)
                    FROM jobhistory jh2
                    WHERE jh2.survey_job_id = jh.survey_job_id
                )
                GROUP BY jh.survey_job_id
            ) completed_status ON sj.survey_job_id = completed_status.survey_job_id
            LEFT JOIN (
                SELECT 
                    jh.survey_job_id,
                    jh.created_at as acquisition_date,
                    ROW_NUMBER() OVER (PARTITION BY jh.survey_job_id ORDER BY jh.created_at DESC) as rn
                FROM jobhistory jh
                WHERE jh.action_type = 'pbtstatus_update'
                AND (jh.notes LIKE '%acquisition_complete%' OR jh.status_after = 'completed')
            ) acquisition_complete_ranked ON sj.survey_job_id = acquisition_complete_ranked.survey_job_id AND acquisition_complete_ranked.rn = 1";
    
    // OIC, VO, SS can see all jobs
    
    $sql .= " ORDER BY sj.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalJobs = count($jobs);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Job Progress Error: " . $e->getMessage());
} catch(Exception $e) {
    $error = "Error loading jobs: " . $e->getMessage();
    error_log("Job Progress Error: " . $e->getMessage());
}

// Helper function to get status badge class
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

// Helper function to format date
function formatDate($dateString) {
    return date('d/m/Y H:i', strtotime($dateString));
}

// Helper function to calculate timeline from FI to AS assignment
function calculateTimeline($fiTaDate, $isCompleted) {
    if (!$fiTaDate) {
        return '<span class="timeline-none">N/A</span>';
    }
    
    $assignmentDate = new DateTime($fiTaDate);
    $currentDate = new DateTime();
    $targetDate = clone $assignmentDate;
    $targetDate->add(new DateInterval('P60D')); // Add 60 days
    
    // If completed, show completion status
    if ($isCompleted) {
        return '<span class="timeline-completed">✅ Completed</span>';
    }
    
    // Calculate days remaining
    $interval = $currentDate->diff($targetDate);
    $daysRemaining = $interval->invert ? -$interval->days : $interval->days;
    
    if ($daysRemaining < 0) {
        // Overdue
        $overdueClass = 'timeline-overdue';
        return '<span class="' . $overdueClass . '">⚠️ ' . abs($daysRemaining) . ' days overdue</span>';
    } elseif ($daysRemaining <= 7) {
        // Due soon (within 7 days)
        $urgentClass = 'timeline-urgent';
        return '<span class="' . $urgentClass . '">🔔 ' . $daysRemaining . ' days left</span>';
    } else {
        // Normal countdown
        $normalClass = 'timeline-normal';
        return '<span class="' . $normalClass . '">⏰ ' . $daysRemaining . ' days left</span>';
    }
}
?>

<div class="page-header">
    <h2>📈 Job Progress</h2>
    <p>Track the progress of all survey jobs in the system</p>
</div>

<?php if (!empty($error)): ?>
    <div class="error-message">
        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="job-progress-container">
    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="card-icon">📊</div>
            <div class="card-content">
                <div class="card-number"><?php echo $totalJobs; ?></div>
                <div class="card-label">Total Jobs</div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="card-icon">⏳</div>
            <div class="card-content">
                <div class="card-number"><?php echo count(array_filter($jobs, function($job) { return $job['status'] === 'pending'; })); ?></div>
                <div class="card-label">Pending</div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="card-icon">🔄</div>
            <div class="card-content">
                <div class="card-number"><?php echo count(array_filter($jobs, function($job) { return in_array($job['status'], ['assigned', 'in_progress']); })); ?></div>
                <div class="card-label">In Progress</div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="card-icon">✅</div>
            <div class="card-content">
                <div class="card-number"><?php echo count(array_filter($jobs, function($job) { return in_array($job['status'], ['completed', 'reviewed', 'approved']); })); ?></div>
                <div class="card-label">Completed</div>
            </div>
        </div>
    </div>
      <!-- Filters and Search -->
    <div class="filters-section">
        <div class="filter-header">
            <h3>🔍 Search & Filter</h3>
            <div class="filter-header-actions">
                <div class="filter-presets">
                    <button onclick="applyFilterPreset('my_tasks')" class="btn-preset" title="Show jobs assigned to me">My Tasks</button>
                    <button onclick="applyFilterPreset('pending_jobs')" class="btn-preset" title="Show pending jobs">Pending</button>
                    <button onclick="applyFilterPreset('unassigned_jobs')" class="btn-preset" title="Show unassigned jobs">Unassigned</button>
                    <button onclick="exportFilteredJobs()" class="btn-export" title="Export filtered results">📊 Export</button>
                </div>
                <button onclick="resetFilters()" class="btn-reset-all">🔄 Reset All</button>
            </div>
        </div>
        
        <div class="search-box">
            <div class="search-input-container">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchInput" placeholder="Search by job number, project name, reference, or assignee..." autocomplete="off" />
                <button onclick="clearSearch()" class="clear-search" title="Clear search">✕</button>
            </div>
            <div class="search-suggestions" id="searchSuggestions" style="display: none;"></div>
        </div>
    </div>

    <!-- Jobs Table -->
    <div class="jobs-table-container">
        <?php if (empty($jobs) && empty($error)): ?>
            <div class="no-jobs-message">
                <div class="no-jobs-icon">📋</div>
                <h3>No Jobs Found</h3>
                <p>There are no survey jobs to display based on your current access level.</p>
                <?php if ($userRole === 'OIC'): ?>
                    <button onclick="loadPage('create_job.php', 'Create Job')" class="btn-primary">Create First Job</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="jobs-table" id="jobsTable">                    <thead>
                        <tr>
                            <th>Job Number</th>
                            <th>Project Name</th>
                            <th>References</th>
                            <th>Status</th>
                            <th>PBT Status</th>
                            <th>Assigned By</th>
                            <th>Assigned To</th>
                            <th>Timeline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr class="job-row" 
                                data-status="<?php echo strtolower($job['status']); ?>"
                                data-created-role="<?php echo $job['created_by_role']; ?>"
                                data-assigned-role="<?php echo $job['assigned_to_role'] ?? ''; ?>">
                                <td class="job-number">
                                    <strong><?php echo htmlspecialchars($job['surveyjob_no']); ?></strong>
                                </td>
                                <td class="project-name">
                                    <?php echo htmlspecialchars($job['projectname']); ?>
                                </td>
                                <td class="references">
                                    <div class="ref-item">
                                        <small>HQ:</small> <?php echo htmlspecialchars($job['hq_ref']); ?>
                                    </div>
                                    <div class="ref-item">
                                        <small>DIV:</small> <?php echo htmlspecialchars($job['div_ref']); ?>
                                    </div>
                                </td>                                <td class="status-cell">
                                    <span class="status-badge <?php echo getStatusBadgeClass($job['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                    </span>
                                </td>
                                <td class="pbtstatus-cell">
                                    <span class="pbtstatus-badge pbtstatus-<?php echo strtolower($job['pbtstatus'] ?? 'none'); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $job['pbtstatus'] ?? 'none')); ?>
                                    </span>
                                    <?php if (strtolower($job['pbtstatus']) === 'acquisition_complete' && $job['acquisition_date']): ?>
                                        <div class="acquisition-date">
                                            <small><?php echo formatDate($job['acquisition_date']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="assignedby-cell">
                                    <?php if ($job['assigned_by_name']): ?>
                                        <div class="user-info">
                                            <div class="user-name"><?php echo htmlspecialchars($job['assigned_by_name']); ?></div>
                                            <div class="user-role"><?php echo htmlspecialchars($job['assigned_by_role']); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="user-info">
                                            <div class="user-name"><?php echo htmlspecialchars($job['created_by_name'] ?? 'Unknown'); ?></div>
                                            <div class="user-role"><?php echo htmlspecialchars($job['created_by_role'] ?? ''); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="assignee-cell">
                                    <?php if ($job['assigned_to_name']): ?>
                                        <div class="user-info">
                                            <div class="user-name"><?php echo htmlspecialchars($job['assigned_to_name']); ?></div>
                                            <div class="user-role"><?php echo htmlspecialchars($job['assigned_to_role']); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="unassigned">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="timeline-cell">
                                    <?php echo calculateTimeline($job['fi_ta_date'], $job['is_completed']); ?>
                                </td>
                                <td class="actions-cell">
                                    <div class="action-buttons">
                                        <button onclick="viewJobDetails(<?php echo $job['survey_job_id']; ?>)" 
                                                class="btn-action btn-view" title="View Details">
                                            👁️
                                        </button>
                                        <?php if ($job['attachment_name'] && $job['attachment_name'] !== 'no_file_uploaded.pdf'): ?>
                                            <button onclick="downloadAttachment('<?php echo htmlspecialchars($job['attachment_name']); ?>')" 
                                                    class="btn-action btn-download" title="Download Attachment">
                                                📎
                                            </button>                                        <?php endif; ?>                                        
                                        <?php if (($userRole === 'OIC' || $userRole === 'SS') && $job['status'] === 'pending'): ?>
                                            <button onclick="showAssignJobModal(<?php echo $job['survey_job_id']; ?>, '<?php echo $userRole; ?>')" 
                                                    class="btn-action btn-assign" title="Assign Job">
                                                👤
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($userRole === 'VO' && $job['pbtstatus'] === 'checked'): ?>
                                            <button onclick="markAcquisitionComplete(<?php echo $job['survey_job_id']; ?>)" 
                                                    class="btn-action btn-complete" title="Mark as Acquisition Complete">
                                                ✅
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="table-pagination">
                <div class="pagination-info">
                    Showing <span id="showingCount"><?php echo count($jobs); ?></span> of <?php echo $totalJobs; ?> jobs
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Job Details Modal if not already present -->
<?php if (!strpos($html_content ?? '', 'jobDetailsModal')): ?>
<!-- Job Details Modal -->
<div id="jobDetailsModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>📋 Job Details</h3>
            <span class="modal-close" onclick="closeJobDetailsModal()">&times;</span>
        </div>
        <div class="modal-body" id="jobDetailsContent">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Assignment Modal -->
<div id="assignJobModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Assign Job</h3>
            <span class="modal-close" onclick="closeAssignJobModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="assignJobForm" method="POST">
                <input type="hidden" name="action" value="assign_job">
                <input type="hidden" name="job_id" id="modalJobId">
                
                <div class="form-group">
                    <label for="modalAssignToRole">Assign to Role:</label>
                    <select id="modalAssignToRole" name="assign_to_role" onchange="loadUsersForAssignment()" required>
                        <option value="">Select Role</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="modalAssignToUser">Select User:</label>
                    <select id="modalAssignToUser" name="assign_to_user_id" required disabled>
                        <option value="">Select role first</option>
                    </select>
                </div>
                
                <div class="assignment-workflow">
                    <h4>Assignment Workflow:</h4>
                    <div id="workflowInfo"></div>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Assign Job</button>
                    <button type="button" class="btn-secondary" onclick="closeAssignJobModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

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

.job-progress-container {
    max-width: 1400px;
    margin: 0 auto;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.card-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.card-content {
    flex: 1;
}

.card-number {
    font-size: 2rem;
    font-weight: bold;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.card-label {
    color: #64748b;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filters-section {
    background: #ffffff;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    margin-bottom: 2rem;
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f1f5f9;
}

.filter-header h3 {
    color: #1e293b;
    font-size: 1.2rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-reset-all {
    padding: 0.6rem 1.2rem;
    background: #dc2626;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-reset-all:hover {
    background: #b91c1c;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.search-box {
    margin-bottom: 1.5rem;
}

.search-input-container {
    position: relative;
    display: flex;
    align-items: center;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.search-input-container:focus-within {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    background: white;
}

.search-icon {
    padding: 0 1rem;
    color: #64748b;
    font-size: 1.1rem;
}

.search-input-container input {
    flex: 1;
    padding: 1rem 0;
    border: none;
    background: transparent;
    font-size: 1rem;
    color: #1e293b;
    outline: none;
}

.search-input-container input::placeholder {
    color: #94a3b8;
}

.clear-search {
    padding: 0.5rem;
    margin: 0.5rem;
    background: #f1f5f9;
    border: none;
    border-radius: 6px;
    color: #64748b;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.clear-search:hover {
    background: #dc2626;
    color: white;
    transform: scale(1.1);
}

.jobs-table-container {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.table-responsive {
    overflow-x: auto;
}

.jobs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.jobs-table th {
    background: #f8fafc;
    padding: 1rem 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}

.jobs-table td {
    padding: 1rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
}

.job-row:hover {
    background: #f8fafc;
}

.job-number strong {
    color: #3b82f6;
    font-family: monospace;
    font-size: 0.95rem;
}

.project-name {
    max-width: 200px;
    word-wrap: break-word;
    line-height: 1.4;
    color: #1e293b;
}

.references {
    font-size: 0.8rem;
    line-height: 1.3;
}

.ref-item {
    margin-bottom: 0.25rem;
}

.ref-item small {
    color: #64748b;
    font-weight: 500;
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

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-assigned {
    background: #dbeafe;
    color: #1e40af;
}

.status-progress {
    background: #fed7d7;
    color: #c53030;
}

.status-completed {
    background: #d1fae5;
    color: #065f46;
}

.status-reviewed {
    background: #e0e7ff;
    color: #3730a3;
}

.status-approved {
    background: #d1fae5;
    color: #064e3b;
}

.status-default {
    background: #f1f5f9;
    color: #64748b;
}

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

.pbtstatus-none {
    background: #f1f5f9;
    color: #64748b;
}

.pbtstatus-checking {
    background: #fef3c7;
    color: #92400e;
}

.pbtstatus-checked {
    background: #dbeafe;
    color: #1e40af;
}

.pbtstatus-acquisition-complete {
    background: #d1fae5;
    color: #065f46;
}

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
    font-family: monospace;
    font-size: 0.65rem;
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

.timeline-cell {
    font-family: monospace;
    font-size: 0.85rem;
    white-space: nowrap;
}

.timeline-none {
    color: #94a3b8;
    font-style: italic;
}

.timeline-completed {
    color: #065f46;
    font-weight: 600;
    background: #d1fae5;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
}

.timeline-normal {
    color: #1e40af;
    font-weight: 500;
}

.timeline-urgent {
    color: #dc2626;
    font-weight: 600;
    background: #fee2e2;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
    animation: pulse 2s infinite;
}

.timeline-overdue {
    color: #991b1b;
    font-weight: 600;
    background: #fecaca;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-action {
    padding: 0.5rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s;
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-view {
    background: #dbeafe;
    color: #1e40af;
}

.btn-view:hover {
    background: #bfdbfe;
    transform: translateY(-1px);
}

.btn-download {
    background: #d1fae5;
    color: #065f46;
}

.btn-download:hover {
    background: #a7f3d0;
    transform: translateY(-1px);
}

.btn-assign {
    background: #fef3c7;
    color: #92400e;
}

.btn-assign:hover {
    background: #fde68a;
    transform: translateY(-1px);
}

.btn-complete {
    background: #d1fae5;
    color: #065f46;
}

.btn-complete:hover {
    background: #a7f3d0;
    transform: translateY(-1px);
}

.no-jobs-message {
    text-align: center;
    padding: 4rem 2rem;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.no-jobs-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-jobs-message h3 {
    color: #1e293b;
    margin-bottom: 1rem;
}

.no-jobs-message p {
    color: #64748b;
    margin-bottom: 2rem;
}

.btn-primary {
    background: #3b82f6;
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.table-pagination {
    padding: 1rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    text-align: center;
}

.pagination-info {
    color: #64748b;
    font-size: 0.9rem;
}

.error-message {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

/* Modal Styles */
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
}

.modal-content {
    background: #ffffff;
    border-radius: 12px;
    max-width: 90vw;
    max-height: 85vh;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
    border: 1px solid #e2e8f0;
    position: relative;
    z-index: 10000;
    animation: modalSlideIn 0.3s ease-out;
}

.modal-large {
    width: 800px;
    max-width: 90vw;
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
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}

.modal-header h3 {
    margin: 0;
    color: #1e293b;
}

.modal-close {
    cursor: pointer;
    font-size: 1.5rem;
    color: #64748b;
    background: #f1f5f9;
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
    background: #dc2626;
    color: white;
    transform: scale(1.1);
}

.modal-body {
    padding: 2rem;
    overflow-y: auto;
    max-height: calc(85vh - 80px);
}

/* Job Details Grid */
.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
}

.assignment-workflow {
    margin: 1.5rem 0;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.assignment-workflow h4 {
    margin: 0 0 0.5rem 0;
    color: #374151;
    font-size: 1rem;
}

.workflow-step {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0.5rem 0;
    font-size: 0.9rem;
    color: #6b7280;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
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

.detail-row {
    display: flex;
    justify-content: space-between;
    margin: 0.5rem 0;
    font-size: 0.9rem;
}

.detail-row .label {
    font-weight: 500;
    color: #6b7280;
}

.detail-row .value {
    color: #374151;
    font-family: monospace;
}
/* Status Badge in Modal */
.detail-row .status-badge {
    padding: 0.375rem 0.875rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
}

/* Attachment Section */
.attachment-section {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border: 1px solid #0ea5e9;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.attachment-section h4 {
    margin: 0 0 1rem 0;
    color: #0c4a6e;
    font-size: 1.1rem;
    font-weight: 600;
}

.attachment-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #bae6fd;
}

.attachment-item span:first-child {
    font-size: 1.5rem;
}

.attachment-item span:nth-child(2) {
    flex: 1;
    color: #0c4a6e;
    font-weight: 500;
}

.attachment-item button {
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
}

.attachment-item button:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.4);
}

/* Modal Actions */
.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.modal-actions button {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.modal-actions button:first-child {
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    color: #374151;
    border: 1px solid #d1d5db;
}

.modal-actions button:first-child:hover {
    background: linear-gradient(135deg, #e5e7eb, #d1d5db);
    transform: translateY(-1px);
}

.modal-actions button:last-child {
    background: linear-gradient(135deg, #6b46c1, #553c9a);
    color: white;
    box-shadow: 0 2px 8px rgba(107, 70, 193, 0.3);
}

.modal-actions button:last-child:hover {
    background: linear-gradient(135deg, #553c9a, #4c1d95);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.4);
}

/* Loading Spinner */
.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #6b7280;
}

.loading-spinner div:first-child {
    font-size: 2rem;
    margin-bottom: 1rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Error Message */
.error-message {
    text-align: center;
    padding: 2rem;
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    border: 1px solid #f87171;
    border-radius: 12px;
    color: #b91c1c;
}

.error-message h4 {
    margin: 0 0 1rem 0;
    color: #991b1b;
}

.error-message p {
    margin: 0 0 1rem 0;
    color: #dc2626;
}

.error-message button {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #6b46c1, #553c9a);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.error-message button:hover {
    background: linear-gradient(135deg, #553c9a, #4c1d95);
    transform: translateY(-1px);
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-content {
        max-width: 95vw;
        max-height: 90vh;
        margin: 0.5rem;
    }
    
    .modal-header {
        padding: 1rem 1.5rem;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .job-details-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .detail-row span:first-child {
        margin-right: 0;
    }
    
    .detail-row span:last-child {
        text-align: left;
    }
    
    .modal-actions {
        flex-direction: column;
    }
    
    .attachment-item {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .modal-header h3 {
        font-size: 1.1rem;
    }
    
    .modal-close {
        width: 28px;
        height: 28px;
        font-size: 1.25rem;
    }
    
    .detail-section {
        padding: 1rem;
    }
    
    .attachment-section {
        padding: 1rem;
    }
}

/* Enhanced Filter Styles */
.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.filter-header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.filter-presets {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-preset {
    padding: 0.5rem 1rem;
    background: #f3f4f6;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    color: #374151;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-preset:hover {
    background: #6b46c1;
    color: white;
    border-color: #6b46c1;
    transform: translateY(-1px);
}

.btn-export {
    padding: 0.5rem 1rem;
    background: #10b981;
    border: 2px solid #10b981;
    border-radius: 8px;
    color: white;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-export:hover {
    background: #059669;
    border-color: #059669;
    transform: translateY(-1px);
}

.btn-reset-all {
    padding: 0.5rem 1rem;
    background: #ef4444;
    border: 2px solid #ef4444;
    border-radius: 8px;
    color: white;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-reset-all:hover {
    background: #dc2626;
    border-color: #dc2626;
    transform: translateY(-1px);
}

/* Active Filters Display */
.active-filters-list {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 1rem;
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.filter-label {
    font-weight: 600;
    color: #92400e;
    margin-right: 0.5rem;
}

.filter-tag {
    padding: 0.25rem 0.75rem;
    background: white;
    border: 1px solid #d97706;
    border-radius: 20px;
    font-size: 0.875rem;
    color: #92400e;
    white-space: nowrap;
}

.clear-all-filters {
    padding: 0.25rem 0.75rem;
    background: #ef4444;
    border: 1px solid #ef4444;
    border-radius: 20px;
    color: white;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.clear-all-filters:hover {
    background: #dc2626;
    border-color: #dc2626;
}

/* Search Suggestions */
.search-box {
    position: relative;
}

.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e5e7eb;
    border-top: none;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    max-height: 200px;
    overflow-y: auto;
    z-index: 10;
}

.suggestion-item {
    padding: 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.suggestion-item:hover {
    background-color: #f8fafc;
}

.suggestion-item:last-child {
    border-bottom: none;
}

.suggestion-type {
    font-size: 0.75rem;
    color: #6b7280;
    background: #f3f4f6;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    min-width: fit-content;
}

.suggestion-value {
    color: #374151;
    font-weight: 500;
}

/* Enhanced Table Animations */
.job-row {
    transition: all 0.3s ease;
}

.job-row:hover {
    background-color: #f8fafc;
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Filter Results Enhancement */
.filter-results {
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-results strong {
    color: #6b46c1;
}

/* Mobile Responsive Enhancements */
@media (max-width: 768px) {
    .filter-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-header-actions {
        justify-content: center;
    }
    
    .filter-presets {
        justify-content: center;
    }
    
    .btn-preset, .btn-export, .btn-reset-all {
        flex: 1;
        min-width: 0;
        text-align: center;
    }
    
    .active-filters-list {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-tag {
        text-align: center;
    }
}
</style>

<script>
// Page will be initialized by dashboard.js loading the appropriate module
// No need for duplicate JavaScript here
</script>

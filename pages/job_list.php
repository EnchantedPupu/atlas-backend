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
$success = '';

// Handle job assignment and progress updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($_POST['action'] === 'assign_job') {
            $jobId = (int)$_POST['job_id'];
            $assignToUserId = (int)$_POST['assign_to_user_id'];
            $assignToRole = $_POST['assign_to_role'];
            $remarks = $_POST['remarks'] ?? '';
            
            // Get current job details
            $jobCheckStmt = $db->prepare("SELECT sj.*, u.role as current_assignee_role FROM SurveyJob sj LEFT JOIN User u ON sj.assigned_to = u.user_id WHERE sj.survey_job_id = ?");
            $jobCheckStmt->execute([$jobId]);
            $currentJob = $jobCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentJob) {
                throw new Exception('Job not found');
            }
            
            // Validate assignment permissions based on role hierarchy
            $canAssign = false;
            $newStatus = 'assigned';
            
            // Check if current user can assign based on role hierarchy
            if ($userRole === 'OIC' && $assignToRole === 'VO') {
                $canAssign = true;
            } elseif ($userRole === 'VO' && $assignToRole === 'SS') {
                $canAssign = true;
            } elseif ($userRole === 'SS' && in_array($assignToRole, ['FI'])) {
                $canAssign = true;
            } elseif ($userRole === 'FI' && in_array($assignToRole, ['AS', 'PP', 'SD'])) {
                $canAssign = true;
            } elseif ($userRole === 'AS' && $assignToRole === 'FI') {
                $canAssign = true;
                $newStatus = 'submitted'; // AS submits back to FI
            } elseif ($userRole === 'PP' && $assignToRole === 'SD') {
                $canAssign = true;
                $newStatus = 'submitted'; // PP returns to SD
            } elseif ($userRole === 'SD' && in_array($assignToRole, ['PP', 'SS'])) {
                $canAssign = true;
            } elseif ($userRole === 'SS' && $assignToRole === 'VO') {
                $canAssign = true;
                $newStatus = 'completed'; // SS returns to VO - mark as complete
            }
            
            if ($canAssign) {
                // Begin transaction for atomic operations
                $db->beginTransaction();
                
                try {
                    // Update job assignment
                    $updateStmt = $db->prepare("UPDATE SurveyJob SET assigned_to = ?, status = ?, remarks = ?, updated_at = NOW() WHERE survey_job_id = ?");
                    $updateStmt->execute([$assignToUserId, $newStatus, $remarks, $jobId]);
                    
                    // Get user details for history
                    $userStmt = $db->prepare("SELECT name, role FROM User WHERE user_id = ?");
                    $userStmt->execute([$assignToUserId]);
                    $assignedUser = $userStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Record job history
                    $historyStmt = $db->prepare("
                        INSERT INTO JobHistory (survey_job_id, from_user_id, to_user_id, from_role, to_role, action_type, status_before, status_after, notes, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $actionType = ($newStatus === 'completed') ? 'completed' : (($newStatus === 'submitted') ? 'submitted' : 'assigned');
                    $notes = "Job {$actionType} from {$userRole} to {$assignToRole}";
                    
                    // Add remarks to notes if provided
                    if (!empty($remarks)) {
                        $notes .= " - Remarks: " . $remarks;
                    }
                    
                    $historyStmt->execute([
                        $jobId,
                        $userId, // from_user_id
                        $assignToUserId, // to_user_id
                        $userRole, // from_role
                        $assignToRole, // to_role
                        $actionType,
                        $currentJob['status'], // status_before
                        $newStatus, // status_after
                        $notes
                    ]);
                    
                    $db->commit();
                    
                    if ($newStatus === 'completed') {
                        $success = "Job completed and returned to " . htmlspecialchars($assignedUser['name']) . " (VO)";
                    } else {
                        $success = "Job successfully assigned to " . htmlspecialchars($assignedUser['name']);
                    }
                    
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
            } else {
                $error = "You don't have permission to assign this job to the selected role.";
            }
        } elseif ($_POST['action'] === 'update_progress' || $_POST['action'] === 'update_status') {
            $jobId = (int)$_POST['job_id'];
            $newStatus = $_POST['new_status'];
            
            // Get current job details
            $jobCheckStmt = $db->prepare("SELECT * FROM SurveyJob WHERE survey_job_id = ?");
            $jobCheckStmt->execute([$jobId]);
            $currentJob = $jobCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentJob) {
                throw new Exception('Job not found');
            }
            
            // Validate that user can update this job
            $canUpdate = false;
            if ($userRole === 'OIC' || $userRole === 'SS') {
                $canUpdate = true;
            } else {
                // Check if user is assigned to this job
                if ($currentJob['assigned_to'] == $userId) {
                    $canUpdate = true;
                }
            }
            
            if ($canUpdate) {
                // Begin transaction
                $db->beginTransaction();
                
                try {
                    $updateStmt = $db->prepare("UPDATE SurveyJob SET status = ?, updated_at = NOW() WHERE survey_job_id = ?");
                    $updateStmt->execute([$newStatus, $jobId]);
                    
                    // Record status change in history
                    $historyStmt = $db->prepare("
                        INSERT INTO JobHistory (survey_job_id, from_user_id, to_user_id, from_role, to_role, action_type, status_before, status_after, notes, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $historyStmt->execute([
                        $jobId,
                        $userId, // from_user_id
                        $userId, // to_user_id (same user updating status)
                        $userRole, // from_role
                        $userRole, // to_role
                        'status_update',
                        $currentJob['status'], // status_before
                        $newStatus, // status_after
                        "Status updated by {$userRole}"
                    ]);

                    $db->commit();
                    $success = "Job status updated successfully to " . ucfirst(str_replace('_', ' ', $newStatus));
                    
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
            } else {
                $error = "You don't have permission to update this job.";
            }
        }
    } catch (Exception $e) {
        $error = "Error updating job: " . $e->getMessage();
        error_log("Job Update Error: " . $e->getMessage());
    }
}

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
      // Modified query to show jobs user is currently assigned to OR has been involved with
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
            FROM SurveyJob sj
            LEFT JOIN User creator ON sj.created_by = creator.user_id
            LEFT JOIN User assignee ON sj.assigned_to = assignee.user_id
            WHERE (sj.assigned_to = ? OR sj.created_by = ?)
            ORDER BY 
                CASE 
                    WHEN sj.assigned_to = ? THEN 1 
                    ELSE 2 
                END,
                sj.updated_at DESC,
                sj.created_at DESC";
    
    $params = [$userId, $userId, $userId, $userId, $userId];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalJobs = count($jobs);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Job List Error: " . $e->getMessage());
} catch(Exception $e) {
    $error = "Error loading jobs: " . $e->getMessage();
    error_log("Job List Error: " . $e->getMessage());
}

// Helper functions
function getStatusBadgeClass($status) {
    $statusClasses = [
        'pending' => 'status-pending',
        'assigned' => 'status-assigned',
        'in_progress' => 'status-progress',
        'completed' => 'status-completed',
        'reviewed' => 'status-reviewed',
        'approved' => 'status-approved',
        'submitted' => 'status-submitted'
    ];
    return $statusClasses[strtolower($status)] ?? 'status-default';
}

function formatDate($dateString) {
    if (!$dateString || $dateString === null) {
        return 'N/A';
    }
    
    $timestamp = strtotime($dateString);
    if ($timestamp === false) {
        return 'Invalid Date';
    }
    
    return date('d/m/Y H:i', $timestamp);
}

function getStatusOptions($currentStatus, $userRole) {
    $allStatuses = [
        'pending' => 'Pending',
        'assigned' => 'Assigned',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'reviewed' => 'Reviewed',
        'approved' => 'Approved',
        'submitted' => 'Submitted'
    ];
    
    // Define allowed transitions based on role
    $allowedTransitions = [
        'OIC' => $allStatuses,
        'SS' => $allStatuses,
        'VO' => ['reviewed' => 'Reviewed', 'approved' => 'Approved'],
        'FI' => ['in_progress' => 'In Progress', 'completed' => 'Completed'],
        'AS' => ['in_progress' => 'In Progress', 'completed' => 'Completed'],
        'PP' => ['in_progress' => 'In Progress', 'completed' => 'Completed'],
        'SD' => ['in_progress' => 'In Progress', 'completed' => 'Completed']
    ];
    
    return $allowedTransitions[$userRole] ?? [];
}

// Add role hierarchy helper function
function getAssignableRoles($currentRole) {
    $assignments = [
        'OIC' => ['VO'],
        'VO' => ['SS'],
        'SS' => ['FI', 'VO'], // SS can assign to FI or return to VO (completion)
        'FI' => ['AS', 'PP', 'SD'],
        'AS' => ['FI'], // Submit back to FI
        'PP' => ['SD'], // Return to SD
        'SD' => ['PP', 'SS']
    ];
    
    return $assignments[$currentRole] ?? [];
}

// Helper function to check if job is newly assigned to current user
function isNewlyAssigned($job, $userId) {
    if ($job['assigned_to_id'] != $userId) return false;
    
    // Check if updated_at exists and is not null before using strtotime
    if (!$job['updated_at'] || $job['updated_at'] === null) {
        return false;
    }
    
    // Check if updated within last 24 hours and status is assigned
    $updatedTime = strtotime($job['updated_at']);
    if ($updatedTime === false) {
        return false;
    }
    
    $dayAgo = time() - (24 * 60 * 60);
    
    return $updatedTime > $dayAgo && $job['status'] === 'assigned';
}

// Helper function to check if job needs approval
function needsApproval($job, $userRole) {
    // Job needs approval if:
    // 1. Status is 'completed' or 'submitted' and user is OIC/VO
    // 2. PBT status is 'checked' and user is VO
    // 3. Job is assigned to user and status requires approval
    
    if ($userRole === 'OIC' || $userRole === 'VO') {
        return in_array($job['status'], ['completed', 'submitted']) || 
               ($job['pbtstatus'] === 'checked' && $userRole === 'VO');
    }
    
    return false;
}
?>

<div class="page-header">
    <h2>Job List</h2>
    <p>View jobs assigned to you and track your task history</p>
</div>

<?php if (!empty($success)): ?>
    <div class="success-message">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="error-message">
        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="job-list-container">
    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $totalJobs; ?></div>
                <div class="stat-label">Total Jobs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎯</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count(array_filter($jobs, function($job) use ($userId) { return $job['assigned_to_id'] == $userId; })); ?></div>
                <div class="stat-label">Currently Assigned</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🆕</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count(array_filter($jobs, function($job) use ($userId) { return isNewlyAssigned($job, $userId); })); ?></div>
                <div class="stat-label">New Tasks</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count(array_filter($jobs, function($job) { return in_array($job['status'], ['completed', 'reviewed', 'approved']); })); ?></div>
                <div class="stat-label">Finished</div>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
        <div class="search-section">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" id="jobSearch" placeholder="Search jobs..." onkeyup="filterJobs()">
                <button onclick="clearJobSearch()" class="clear-btn">✕</button>
            </div>
        </div>
        
        <div class="filter-section">
            <select id="statusFilter" onchange="filterJobs()">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="assigned">Assigned</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="submitted">Submitted</option>
                <option value="reviewed">Reviewed</option>
                <option value="approved">Approved</option>
            </select>
            
            <select id="involvementFilter" onchange="filterJobs()">
                <option value="">All Jobs</option>
                <option value="currently_assigned">Currently Assigned</option>
                <option value="new_tasks">My New Tasks</option>
                <option value="created_by_me">Created by Me</option>
                <option value="previously_involved">Previously Involved</option>
            </select>
            
            <select id="pbtstatusFilter" onchange="filterJobs()">
                <option value="">All PBT Status</option>
                <option value="none">None</option>
                <option value="checking">Checking</option>
                <option value="checked">Checked</option>
                <option value="acquisition_complete">Acquisition Complete</option>
            </select>
        </div>
        
        <div class="filter-buttons">
            <button id="newFilterBtn" class="btn-filter-toggle" title="Show only new tasks">
                🆕 New
            </button>
            <button id="approvalFilterBtn" class="btn-filter-toggle" title="Show jobs requiring approval">
                ✅ Approval
            </button>
        </div>
        
        <div class="action-buttons">
            <button onclick="clearAllFilters()" class="btn-clear-filters">
                🗑️ Clear Filters
            </button>
            <button onclick="refreshJobList()" class="btn-refresh">
                🔄 Refresh
            </button>
        </div>
    </div>

    <!-- Jobs Grid -->
    <div class="jobs-grid" id="jobsGrid">
        <?php if (empty($jobs) && empty($error)): ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <h3>No Jobs Found</h3>
                <p>You don't have any jobs assigned to you or in your history.</p>
                <p>Check back later or contact your supervisor for new assignments.</p>
            </div>
        <?php else: ?>
            <?php foreach ($jobs as $job): ?>
                <div class="job-card <?php echo isNewlyAssigned($job, $userId) ? 'new-task' : ''; ?> <?php echo $job['involvement_type']; ?>" 
                     data-job-id="<?php echo $job['survey_job_id']; ?>" 
                     data-status="<?php echo strtolower($job['status']); ?>"
                     data-assignee="<?php echo htmlspecialchars($job['assigned_to_name'] ?? 'unassigned'); ?>"
                     data-involvement="<?php echo $job['involvement_type']; ?>"
                     data-is-new="<?php echo isNewlyAssigned($job, $userId) ? 'true' : 'false'; ?>"
                     data-needs-approval="<?php echo needsApproval($job, $userRole) ? 'true' : 'false'; ?>"
                     data-created-by-name="<?php echo htmlspecialchars($job['created_by_name'] ?? 'Unknown'); ?>"
                     data-created-by-role="<?php echo htmlspecialchars($job['created_by_role'] ?? ''); ?>"
                     data-pbtstatus="<?php echo strtolower($job['pbtstatus'] ?? 'none'); ?>">
                    
                    <?php if (isNewlyAssigned($job, $userId)): ?>
                        <div class="new-task-badge">🆕 New Task</div>
                    <?php endif; ?>
                    
                    <!-- Involvement indicator -->
                    <div class="involvement-indicator">
                        <?php 
                        switch($job['involvement_type']) {
                            case 'currently_assigned':
                                echo '<span class="involvement-badge current">📌 Currently Assigned</span>';
                                break;
                            case 'created_by_me':
                                echo '<span class="involvement-badge created">👤 Created by Me</span>';
                                break;
                            case 'previously_involved':
                                echo '<span class="involvement-badge history">📋 Previously Involved</span>';
                                break;
                        }
                        ?>
                    </div>
                    
                    <div class="job-header">
                        <div class="job-number">
                            <strong><?php echo htmlspecialchars($job['surveyjob_no']); ?></strong>
                        </div>
                        <div class="job-status">
                            <span class="status-badge <?php echo getStatusBadgeClass($job['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                            </span>
                            <?php if (!empty($job['pbtstatus']) && $job['pbtstatus'] !== 'none'): ?>
                                <span class="pbtstatus-badge pbtstatus-<?php echo strtolower($job['pbtstatus']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $job['pbtstatus'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="job-content">
                        <h3 class="project-name"><?php echo htmlspecialchars($job['projectname']); ?></h3>
                    </div>
                    
                    <div class="job-actions">
                        <button onclick="viewJobDetails(<?php echo $job['survey_job_id']; ?>)" 
                                class="btn-action btn-view" title="View Details">
                            👁️ View
                        </button>
                        
                        <?php if ($job['attachment_name'] && $job['attachment_name'] !== 'no_file_uploaded.pdf'): ?>
                            <button onclick="downloadAttachment('<?php echo htmlspecialchars($job['attachment_name']); ?>')" 
                                    class="btn-action btn-download" title="Download Attachment">
                                📎 File
                            </button>
                        <?php endif; ?>
                        
                        <?php 
                        // Only show assign button if currently assigned to user and user can assign to others
                        $assignableRoles = getAssignableRoles($userRole);
                        if (!empty($assignableRoles) && $job['assigned_to_id'] == $userId): ?>
                            <button onclick="showAssignJobModal(<?php echo $job['survey_job_id']; ?>, '<?php echo $userRole; ?>')" 
                                    class="btn-action btn-assign" title="Assign Job">
                                👤 Assign
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($userRole === 'VO' && $job['pbtstatus'] === 'checked' && $job['assigned_to_id'] == $userId): ?>
                            <button onclick="markAcquisitionComplete(<?php echo $job['survey_job_id']; ?>)" 
                                    class="btn-action btn-complete" title="Mark as Acquisition Complete">
                                ✅ Complete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="results-info">
        <span id="resultsCount"><?php echo count($jobs); ?></span> of <?php echo $totalJobs; ?> jobs shown
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
            <form id="assignJobForm">
                <input type="hidden" name="job_id" id="modalJobId">
                
                <div class="form-group">
                    <label for="modalAssignToRole">Assign to Role:</label>
                    <select id="modalAssignToRole" name="assign_to_role" onchange="loadUsersForAssignment()" required>
                        <option value="">Select Role</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="modalAssignToUser">Select User:</label>
                    <select id="modalAssignToUser" name="assign_to_user" required disabled>
                        <option value="">Select role first</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="modalRemarks">Remarks (Optional):</label>
                    <textarea id="modalRemarks" name="remarks" rows="3" placeholder="Enter any remarks for this assignment..."></textarea>
                </div>
                
                <!-- 
                SECURITY POLICY: Query Return Functionality Restriction
                ======================================================
                Only Verification Officers (VO) are authorized to access query return functionality.
                This ensures proper workflow control and maintains data integrity.
                -->
                
                <!-- Query Return Selection (initially hidden) - Only for VO -->
                <?php if ($userRole === 'VO'): ?>
                <div class="form-group" id="queryReturnSection" style="display: none;">
                    <label for="queryReturnItems">Query:</label>
                    <div class="query-return-options">
                        <div class="checkbox-grid">
                            <label class="checkbox-item">
                                <input type="checkbox" name="query_return_items[]" value="Form A1">
                                <span class="checkbox-label">Form A1</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="query_return_items[]" value="Form B1">
                                <span class="checkbox-label">Form B1</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="query_return_items[]" value="Form C1">
                                <span class="checkbox-label">Form C1</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="query_return_items[]" value="MP Plan">
                                <span class="checkbox-label">MP Plan</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="query_return_items[]" value="L&S3">
                                <span class="checkbox-label">L&S3</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="query_return_items[]" value="Form L&16">
                                <span class="checkbox-label">Form L&16</span>
                            </label>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Assign Job</button>
                    <?php if ($userRole === 'VO'): ?>
                    <!-- Query Return Button - Only available for VO role -->
                    <button type="button" class="btn-query-return" onclick="toggleQueryReturn()">Query Return</button>
                    <?php endif; ?>
                    <button type="button" class="btn-secondary" onclick="closeAssignJobModal()">Cancel</button>
                </div>
            </form>
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

.job-list-container {
    max-width: 1400px;
    margin: 0 auto;
}

.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
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

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.stat-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.stat-label {
    color: #64748b;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.action-bar {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-section {
    flex: 1;
    min-width: 300px;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.search-box:focus-within {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.search-icon {
    padding: 0 1rem;
    color: #64748b;
}

.search-box input {
    flex: 1;
    padding: 0.75rem 0;
    border: none;
    background: transparent;
    font-size: 1rem;
    outline: none;
    color: #1e293b;
}

.clear-btn {
    padding: 0.5rem;
    margin: 0.25rem;
    background: #f1f5f9;
    border: none;
    border-radius: 6px;
    color: #64748b;
    cursor: pointer;
    transition: all 0.3s ease;
}

.clear-btn:hover {
    background: #dc2626;
    color: white;
}

.filter-section {
    display: flex;
    gap: 1rem;
}

.filter-section select {
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: border-color 0.3s ease;
}

.filter-section select:focus {
    outline: none;
    border-color: #3b82f6;
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-filter-toggle {
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    color: #1e293b;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-filter-toggle:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #f8fafc;
}

.btn-filter-toggle.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.action-buttons {
    display: flex;
    gap: 1rem;
}

.btn-refresh, .btn-clear-filters {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-refresh {
    background: #f1f5f9;
    color: #1e293b;
    border: 1px solid #d1d5db;
}

.btn-refresh:hover {
    background: #e2e8f0;
}

.btn-clear-filters {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fbbf24;
}

.btn-clear-filters:hover {
    background: #fde68a;
    border-color: #f59e0b;
}

.jobs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.job-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
    padding-top: 2rem;
    min-height: 200px;
    display: flex;
    flex-direction: column;
}

.job-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.job-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    gap: 0.5rem;
    flex-shrink: 0;
}

.job-number strong {
    color: #3b82f6;
    font-family: monospace;
    font-size: 0.95rem;
}

.job-status {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: flex-end;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 16px;
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-assigned { background: #dbeafe; color: #1e40af; }
.status-progress { background: #fed7d7; color: #c53030; }
.status-completed { background: #d1fae5; color: #065f46; }
.status-reviewed { background: #e0e7ff; color: #3730a3; }
.status-approved { background: #d1fae5; color: #064e3b; }
.status-submitted { background: #fef3c7; color: #92400e; }
.status-default { background: #f1f5f9; color: #64748b; }

.job-content {
    padding: 1rem;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 60px;
}

.project-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    line-height: 1.4;
    text-align: center;
    width: 100%;
    word-wrap: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
}

.job-actions {
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
    flex-shrink: 0;
    margin-top: auto;
}

.btn-action {
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 500;
    transition: all 0.3s ease;
    white-space: nowrap;
    min-width: 65px;
    text-align: center;
    flex: 0 0 auto;
}

.btn-view {
    background: #3b82f6;
    color: white;
}

.btn-view:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-download {
    background: #10b981;
    color: white;
}

.btn-download:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-assign {
    background: #f59e0b;
    color: white;
}

.btn-assign:hover {
    background: #d97706;
    transform: translateY(-1px);
}

.btn-progress {
    background: #8b5cf6;
    color: white;
}

.btn-progress:hover {
    background: #7c3aed;
    transform: translateY(-1px);
}

.btn-complete {
    background: #dc2626;
    color: white;
}

.btn-complete:hover {
    background: #b91c1c;
    transform: translateY(-1px);
}

.involvement-indicator {
    position: absolute;
    top: 0.4rem;
    left: 0.4rem;
    z-index: 10;
}

.involvement-badge {
    padding: 0.2rem 0.4rem;
    border-radius: 10px;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.job-card.currently_assigned {
    border-left: 4px solid #10b981;
}

.job-card.created_by_me {
    border-left: 4px solid #3b82f6;
}

.job-card.previously_involved {
    border-left: 4px solid #f59e0b;
    opacity: 0.85;
}

.new-task {
    border: 2px solid #10b981;
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.2);
}

.new-task-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #10b981;
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 16px;
    font-size: 0.65rem;
    font-weight: 600;
    z-index: 20;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
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

.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

.form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.btn-query-return {
    padding: 0.75rem 1.5rem;
    border: 2px solid #f59e0b;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    background: #fef3c7;
    color: #92400e;
}

.btn-query-return:hover {
    background: #f59e0b;
    color: white;
    transform: translateY(-1px);
}

.btn-query-return.active {
    background: #f59e0b;
    color: white;
    border-color: #d97706;
    position: relative;
}

.btn-query-return.active::after {
    content: '';
    position: absolute;
    top: -2px;
    right: -2px;
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    border: 2px solid white;
}

.query-return-options {
    margin-top: 0.5rem;
    padding: 1rem;
    background: #fef3c7;
    border-radius: 8px;
    border: 1px solid #fbbf24;
}

.query-return-info {
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: #fffbeb;
    border: 1px solid #fbbf24;
    border-radius: 6px;
    border-left: 4px solid #f59e0b;
}

.query-return-info p {
    margin: 0;
    font-size: 0.85rem;
    color: #92400e;
    line-height: 1.4;
}

.query-return-info strong {
    color: #78350f;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.75rem;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.3s ease;
}

.checkbox-item:hover {
    border-color: #f59e0b;
    background: #fffbeb;
}

.checkbox-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #f59e0b;
    cursor: pointer;
}

.checkbox-label {
    font-size: 0.9rem;
    color: #374151;
    font-weight: 500;
    cursor: pointer;
    user-select: none;
}

.checkbox-item input[type="checkbox"]:checked + .checkbox-label {
    color: #92400e;
    font-weight: 600;
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
    
    .checkbox-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .modal-actions button {
        width: 100%;
        order: 2;
    }
    
    .btn-query-return {
        order: 1;
    }
    
    .btn-secondary {
        order: 3;
    }
}

/* Animation for query return section */
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

#queryReturnSection {
    animation: slideDown 0.3s ease-out;
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

/* Involvement indicators */
.involvement-indicator {
    position: absolute;
    top: 0.4rem;
    left: 0.4rem;
    z-index: 10;
}

.involvement-badge {
    padding: 0.2rem 0.4rem;
    border-radius: 10px;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.job-card.currently_assigned {
    border-left: 4px solid #10b981;
}

.job-card.created_by_me {
    border-left: 4px solid #3b82f6;
}

.job-card.previously_involved {
    border-left: 4px solid #f59e0b;
    opacity: 0.85;
}

/* Parent Attachments Section Styles */
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

.btn-open-parent-attachment:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(14, 165, 233, 0.3);
}

/* Attachments Section Styles */
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

.btn-open-attachment:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
}

/* Responsive styles for attachments */
@media (max-width: 768px) {
    .attachments-section {
        padding: 0.75rem;
        margin-top: 0.75rem;
    }
    
    .attachment-item {
        padding: 0.5rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .attachment-name {
        font-size: 0.85rem;
        min-width: 120px;
    }
    
    .btn-open-attachment {
        padding: 0.4rem 0.8rem;
        font-size: 0.75rem;
    }
}

/* Forms Section Styles - Simple Tree Structure */
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

/* Simple Tree Structure Styles */
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

/* Responsive adjustments for forms tree */
@media (max-width: 768px) {
    .forms-summary-header {
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .forms-summary-header h4 {
        font-size: 1.1rem;
    }
    
    .category-header {
        padding: 0.75rem 1rem;
    }
    
    .category-title {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .category-icon {
        font-size: 1.25rem;
    }
    
    .category-lots {
        padding: 0.75rem;
    }
    
    .lot-header {
        padding: 0.5rem 0.75rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .lot-title {
        font-size: 0.9rem;
        flex: 1;
        min-width: 120px;
    }
    
    .lot-count {
        margin-left: 0;
        font-size: 0.7rem;
    }
    
    .lot-toggle-icon {
        font-size: 0.7rem;
        margin-left: 0.25rem;
    }
    
    .lot-forms {
        padding: 0.25rem;
    }
    
    .form-item {
        padding: 0.5rem;
        gap: 0.5rem;
    }
    
    .form-info {
        gap: 0.2rem;
    }
    
    .form-title {
        font-size: 0.85rem;
    }
    
    .form-type {
        font-size: 0.75rem;
    }
    
    .form-date {
        font-size: 0.7rem;
    }
}

@media (max-width: 480px) {
    .forms-section {
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .forms-summary-header {
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }
    
    .form-category {
        margin-bottom: 1rem;
    }
    
    .category-header {
        padding: 0.5rem 0.75rem;
    }
    
    .category-lots {
        padding: 0.5rem;
    }
    
    .lot-group {
        margin-bottom: 0.75rem;
    }
    
    .lot-header {
        padding: 0.4rem 0.6rem;
    }
    
    .lot-forms {
        padding: 0.2rem;
    }
    
    .form-item {
        padding: 0.4rem;
    }
}

.parent-attachments-section {
    padding: 0.75rem;
}

.parent-attachment-item {
    padding: 0.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.parent-attachment-name {
    font-size: 0.85rem;
    min-width: 120px;
}

.btn-open-parent-attachment {
    padding: 0.4rem 0.8rem;
    font-size: 0.75rem;
}

/* Additional Attachments from sj_files */
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

.attachment-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 0.75rem;
    transition: all 0.2s ease;
}

.attachment-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.attachment-item:last-child {
    margin-bottom: 0;
}

.attachment-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.attachment-name {
    font-weight: 500;
    color: #1e293b;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.attachment-date {
    color: #64748b;
    font-size: 0.75rem;
    font-family: monospace;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Responsive adjustments for attachments */
@media (max-width: 768px) {
    .attachment-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .attachment-info {
        width: 100%;
    }
    
    .attachment-name {
        font-size: 0.85rem;
    }
    
    .attachment-date {
        font-size: 0.7rem;
    }
}

/* Error message styling */
.error-message {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    text-align: center;
}

.error-message h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.error-message p {
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
}

/* Button styling */
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
    margin: 0 0.25rem;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}
</style>

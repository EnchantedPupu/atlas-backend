<?php
session_start();
require_once '../config/database.php';
require_once '../config/role_config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has proper role
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    echo '<div class="error-message">Please log in to access this page.</div>';
    exit;
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'OIC') {
    echo '<div class="error-message">Access denied. Only OIC can create jobs.</div>';
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo '<div class="error-message">User session is invalid. Please log in again.</div>';
    exit;
}

// Initialize variables
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug: Log session information
        error_log("=== CREATE JOB DEBUG START ===");
        error_log("User ID: " . $_SESSION['user_id']);
        error_log("User Role: " . $_SESSION['user_role']);
        error_log("POST data: " . print_r($_POST, true));
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Test database connection
        if (!$db) {
            throw new Exception('Database connection failed');
        }
        
        // Test if we can query the database
        try {
            $testQuery = $db->query("SELECT COUNT(*) FROM User");
            $userCount = $testQuery->fetchColumn();
            error_log("Database test successful. User count: " . $userCount);
        } catch (Exception $e) {
            throw new Exception('Database query test failed: ' . $e->getMessage());
        }
        
        // Validate all required fields
        $surveyjob_no = trim($_POST['surveyjob_no'] ?? '');
        $hq_ref = trim($_POST['hq_ref'] ?? '');
        $div_ref = trim($_POST['div_ref'] ?? '');
        $projectname = trim($_POST['projectname'] ?? '');
        $target_project = trim($_POST['target_project'] ?? '');
        $assign_to_user = $_POST['assign_to_user'] ?? null;
        
        // Convert empty string to null for assign_to_user
        if (empty($assign_to_user)) {
            $assign_to_user = null;
        } else {
            $assign_to_user = (int)$assign_to_user;
        }
        
        error_log("Processed form data: " . json_encode([
            'surveyjob_no' => $surveyjob_no,
            'hq_ref' => $hq_ref,
            'div_ref' => $div_ref,
            'projectname' => $projectname,
            'target_project' => $target_project,
            'assign_to_user' => $assign_to_user
        ]));
        
        // Check required fields
        if (empty($surveyjob_no)) {
            throw new Exception('Survey Job Number is required');
        }
        if (empty($hq_ref)) {
            throw new Exception('HQ Reference is required');
        }
        if (empty($div_ref)) {
            throw new Exception('Division Reference is required');
        }
        if (empty($projectname)) {
            throw new Exception('Project Name is required');
        }
        if (empty($target_project)) {
            throw new Exception('Target Project is required');
        }
        
        // Check for duplicate survey job number
        try {
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM SurveyJob WHERE surveyjob_no = ?");
            if (!$checkStmt) {
                throw new Exception('Failed to prepare duplicate check query');
            }
            
            $checkResult = $checkStmt->execute([$surveyjob_no]);
            if (!$checkResult) {
                throw new Exception('Failed to execute duplicate check');
            }
            
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('Survey Job Number already exists. Please use a different number.');
            }
        } catch (PDOException $e) {
            throw new Exception('Database error during duplicate check: ' . $e->getMessage());
        }
        
        // Handle file upload - simplified for now
        $attachment_path = '';
        if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
            try {
                $uploadResult = handleFileUpload($_FILES['attachment_file']);
                if ($uploadResult['success']) {
                    $attachment_path = $uploadResult['path'];
                    error_log("File uploaded successfully: " . $attachment_path);
                } else {
                    error_log("File upload failed: " . $uploadResult['error']);
                    // Don't fail the job creation for file upload issues
                    $attachment_path = 'upload_failed.pdf';
                }
            } catch (Exception $e) {
                error_log("File upload exception: " . $e->getMessage());
                $attachment_path = 'upload_error.pdf';
            }
        } else {
            // For testing, allow creation without file
            $uploadError = $_FILES['attachment_file']['error'] ?? 'No file selected';
            error_log("No file uploaded. Error code: " . $uploadError);
            $attachment_path = 'no_file_uploaded.pdf'; // Placeholder
        }
        
        // Validate assigned user if provided
        $assignedUserName = null;
        if ($assign_to_user) {
            try {
                $userCheckStmt = $db->prepare("SELECT user_id, name FROM User WHERE user_id = ?");
                if (!$userCheckStmt) {
                    throw new Exception('Failed to prepare user check query');
                }
                
                $userCheckResult = $userCheckStmt->execute([$assign_to_user]);
                if (!$userCheckResult) {
                    throw new Exception('Failed to execute user check');
                }
                
                $assignedUser = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
                if (!$assignedUser) {
                    throw new Exception('Selected user does not exist');
                }
                
                $assignedUserName = $assignedUser['name'];
                error_log("User assignment validated: ID=" . $assign_to_user . ", Name=" . $assignedUserName);
            } catch (PDOException $e) {
                throw new Exception('Database error during user validation: ' . $e->getMessage());
            }
        }
        
        // Begin transaction
        $db->beginTransaction();
        error_log("Transaction started");
        
        try {
            // Prepare the insert statement
            $sql = "INSERT INTO SurveyJob (surveyjob_no, hq_ref, div_ref, projectname, target_project, attachment_name, status, created_by, assigned_to, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                $errorInfo = $db->errorInfo();
                throw new Exception('Failed to prepare insert statement: ' . $errorInfo[2]);
            }
            
            $status = $assign_to_user ? 'assigned' : 'pending';
            $created_by = (int)$_SESSION['user_id'];
            
            // Prepare values for insertion
            $insertValues = [
                $surveyjob_no, 
                $hq_ref, 
                $div_ref, 
                $projectname, 
                $target_project,
                $attachment_path, 
                $status,
                $created_by,
                $assign_to_user
            ];
            
            error_log("SQL: " . $sql);
            error_log("Insert values: " . json_encode($insertValues));
            
            $executeResult = $stmt->execute($insertValues);
            
            if (!$executeResult) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception('Failed to insert job into database. SQL Error: ' . $errorInfo[2]);
            }
            
            $survey_job_id = $db->lastInsertId();
            if (!$survey_job_id) {
                throw new Exception('Job insertion appeared successful but could not retrieve the job ID');
            }
            
            error_log("Job created with ID: " . $survey_job_id);
            
            // Commit the transaction
            $db->commit();
            error_log("Transaction committed successfully");
            error_log("=== CREATE JOB DEBUG END ===");
            
            $successMessage = "Job created successfully with Job ID: {$survey_job_id}";
            if ($assignedUserName) {
                $successMessage .= " and assigned to {$assignedUserName}.";
            } else {
                $successMessage .= " You can assign it to a user later.";
            }
            
            $success = $successMessage;
            
            // Clear form data after successful submission
            $_POST = [];
            
        } catch (Exception $e) {
            // Rollback on any error
            $db->rollback();
            error_log("Transaction rolled back due to error: " . $e->getMessage());
            throw $e;
        }
        
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("Create Job PDO Error: " . $e->getMessage());
        error_log("PDO Error Code: " . $e->getCode());
    } catch(Exception $e) {
        $error = "Error creating job: " . $e->getMessage();
        error_log("Create Job Error: " . $e->getMessage());
    }
}

function handleFileUpload($file) {
    try {
        // Create upload directory
        $uploadDir = '../uploads/jobs/oic/';
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("Failed to create upload directory: " . $uploadDir);
                return ['success' => false, 'error' => 'Failed to create upload directory'];
            }
        }
        
        // Check if directory is writable
        if (!is_writable($uploadDir)) {
            error_log("Upload directory is not writable: " . $uploadDir);
            return ['success' => false, 'error' => 'Upload directory is not writable'];
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'survey_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
        $uploadPath = $uploadDir . $fileName;
        
        error_log("Attempting to upload file to: " . $uploadPath);
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            error_log("File uploaded successfully: " . $uploadPath);
            return ['success' => true, 'path' => $fileName];
        } else {
            error_log("Failed to move uploaded file");
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }
    } catch (Exception $e) {
        error_log("File upload exception: " . $e->getMessage());
        return ['success' => false, 'error' => 'File upload failed: ' . $e->getMessage()];
    }
}

// Get database connection for user dropdown
try {
    $database = new Database();
    $db = $database->getConnection();
    $assignableRoles = RoleConfig::getAssignableRoles($_SESSION['user_role']);
} catch (Exception $e) {
    error_log("Database connection failed for user dropdown: " . $e->getMessage());
    $assignableRoles = [];
}
?>

<div class="page-header">
    <h2>Create New Survey Job</h2>
    <p>Create a new survey job and assign it to team members</p>
</div>

<?php if (!empty($success)): ?>
    <div class="success-message">
        <h3>✅ Success!</h3>
        <p><?php echo htmlspecialchars($success); ?></p>
        <div class="success-actions">
            <button type="button" class="btn-primary" onclick="createAnotherJob()">Create Another Job</button>
            <button type="button" class="btn-secondary" onclick="goBackToDashboard()">Back to Dashboard</button>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="error-message">
        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        <br><small>Check the browser console and server logs for more details.</small>
    </div>
    
    <!-- Debug Information -->
    <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; font-family: monospace; font-size: 12px;">
        <strong>Debug Info:</strong><br>
        Session User ID: <?php echo $_SESSION['user_id'] ?? 'NOT SET'; ?><br>
        Session User Role: <?php echo $_SESSION['user_role'] ?? 'NOT SET'; ?><br>
        PHP Version: <?php echo phpversion(); ?><br>
        Database File Exists: <?php echo file_exists('../config/database.php') ? 'Yes' : 'No'; ?><br>
        Current Time: <?php echo date('Y-m-d H:i:s'); ?><br>
        POST Method: <?php echo $_SERVER['REQUEST_METHOD']; ?><br>
        POST Data: <?php echo empty($_POST) ? 'Empty' : 'Has Data'; ?><br>
    </div>
<?php endif; ?>

<div class="form-container">
    <form class="job-form" id="createJobForm" method="POST" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label for="surveyjob_no">Survey Job Number *</label>
                <input type="text" id="surveyjob_no" name="surveyjob_no" 
                       value="<?php echo htmlspecialchars($_POST['surveyjob_no'] ?? ''); ?>"
                       placeholder="e.g., SJ2024-001" required>
                <small>Must be unique identifier for this survey job</small>
            </div>
            
            <div class="form-group">
                <label for="hq_ref">HQ Reference *</label>
                <input type="text" id="hq_ref" name="hq_ref" 
                       value="<?php echo htmlspecialchars($_POST['hq_ref'] ?? ''); ?>"
                       placeholder="e.g., HQ/2024/001" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="div_ref">Division Reference *</label>
                <input type="text" id="div_ref" name="div_ref" 
                       value="<?php echo htmlspecialchars($_POST['div_ref'] ?? ''); ?>"
                       placeholder="e.g., DIV/2024/001" required>
            </div>
        </div>
        
        <div class="form-group full-width">
            <label for="projectname">Project Name *</label>
            <input type="text" id="projectname" name="projectname" 
                   value="<?php echo htmlspecialchars($_POST['projectname'] ?? ''); ?>"
                   placeholder="Enter descriptive project name" required>
        </div>
        
        <div class="form-group full-width">
            <label for="target_project">Target Project *</label>
            <select id="target_project" name="target_project" required>
                <option value="">Select Target Project</option>
                <option value="Sasaran Projek 1.4" <?php echo (($_POST['target_project'] ?? '') === 'Sasaran Projek 1.4') ? 'selected' : ''; ?>>Sasaran Projek 1.4</option>
                <option value="Sasaran Projek 1.7" <?php echo (($_POST['target_project'] ?? '') === 'Sasaran Projek 1.7') ? 'selected' : ''; ?>>Sasaran Projek 1.7</option>
                <option value="Sasaran Projek 1.10" <?php echo (($_POST['target_project'] ?? '') === 'Sasaran Projek 1.10') ? 'selected' : ''; ?>>Sasaran Projek 1.10</option>
                <option value="Projek Prioriti" <?php echo (($_POST['target_project'] ?? '') === 'Projek Prioriti') ? 'selected' : ''; ?>>Projek Prioriti</option>
            </select>
        </div>
        
        <div class="form-group full-width">
            <label for="attachment_file">Project Attachment (PDF)</label>
            <div class="file-upload-area" id="fileUploadArea">
                <input type="file" id="attachment_file" name="attachment_file" accept=".pdf,.doc,.docx,.xls,.xlsx">
                <div class="file-upload-text">
                    <span class="file-icon">📄</span>
                    <span>Choose file or drag and drop</span>
                    <small>PDF, DOC, DOCX, XLS, XLSX (Max 10MB)</small>
                </div>
            </div>
            <div class="file-preview" id="filePreview"></div>
            <small>Upload project documents, specifications, or reference materials</small>
        </div>
        
        <?php if (!empty($assignableRoles)): ?>
        <div class="assignment-section">
            <h3>Job Assignment</h3>
            <p>Assign to</p><br>
            <div class="form-row">
                <div class="form-group">
                    <label for="assign_to_role">Select Role</label>
                    <select id="assign_to_role" name="assign_to_role">
                        <option value="">Select Role</option>
                        <?php foreach ($assignableRoles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role); ?>" 
                                    <?php echo (($_POST['assign_to_role'] ?? '') === $role) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="assign_to_user">Assign to User</label>
                    <select id="assign_to_user" name="assign_to_user" disabled>
                        <option value="">Select role first</option>
                    </select>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="button" id="createJobBtn" class="btn-primary">Create Job</button>
            <button type="button" class="btn-secondary" onclick="resetCreateJobForm()">Clear Form</button>
        </div>    </form>
</div>

<!-- Include file upload CSS -->
<link rel="stylesheet" href="../assets/css/file-upload.css">

<!-- Modern SaaS Styling -->
<style>
/* Modern Design System */
:root {
    --color-primary: #2563EB;
    --color-primary-hover: #1D4ED8;
    --color-primary-light: #DBEAFE;
    --color-bg-primary: #F8FAFC;
    --color-bg-secondary: #FFFFFF;
    --color-text-primary: #0F172A;
    --color-text-secondary: #64748B;
    --color-border: #E2E8F0;
    --color-success: #10B981;
    --color-success-bg: #D1FAE5;
    --color-error: #EF4444;
    --color-error-bg: #FEE2E2;
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 16px;
}

.page-header {
    margin-bottom: 32px;
    text-align: left;
    background: var(--color-bg-secondary);
    padding: 32px;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--color-border);
}

.page-header h2 {
    color: var(--color-text-primary);
    margin-bottom: 8px;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -0.5px;
}

.page-header p {
    color: var(--color-text-secondary);
    font-size: 16px;
}

.form-container {
    background: var(--color-bg-secondary);
    padding: 32px;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--color-border);
    max-width: 900px;
    margin: 0 auto;
}

.job-form {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--color-text-primary);
    font-size: 14px;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 12px 16px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 15px;
    transition: all 0.2s ease;
    background: var(--color-bg-secondary);
    color: var(--color-text-primary);
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px var(--color-primary-light);
}

.form-group small {
    margin-top: 6px;
    font-size: 13px;
    color: var(--color-text-secondary);
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-start;
    margin-top: 8px;
    padding-top: 24px;
    border-top: 1px solid var(--color-border);
}

.btn-primary, .btn-secondary {
    padding: 12px 24px;
    border: none;
    border-radius: var(--radius-md);
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-primary {
    background: var(--color-primary);
    color: white;
    box-shadow: var(--shadow-sm);
}

.btn-primary:hover {
    background: var(--color-primary-hover);
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
}

.btn-secondary {
    background: var(--color-bg-primary);
    color: var(--color-text-primary);
    border: 1px solid var(--color-border);
}

.btn-secondary:hover {
    background: #E2E8F0;
    border-color: #CBD5E1;
}

.success-message {
    background: var(--color-success-bg);
    border: 1px solid var(--color-success);
    color: #065F46;
    padding: 32px;
    border-radius: var(--radius-xl);
    margin-bottom: 32px;
    box-shadow: var(--shadow-md);
}

.success-message h3 {
    margin: 0 0 16px 0;
    color: #065F46;
    font-size: 22px;
    font-weight: 700;
}

.success-message p {
    margin-bottom: 24px;
    font-size: 16px;
    line-height: 1.6;
}

.success-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.success-actions .btn-primary {
    background: var(--color-success);
}

.success-actions .btn-primary:hover {
    background: #059669;
}

.success-actions .btn-secondary {
    background: var(--color-primary);
    color: white;
    border: none;
}

.success-actions .btn-secondary:hover {
    background: var(--color-primary-hover);
}

.error-message {
    background: var(--color-error-bg);
    border: 1px solid var(--color-error);
    color: #991B1B;
    padding: 20px;
    border-radius: var(--radius-lg);
    margin-bottom: 24px;
    font-size: 14px;
    line-height: 1.6;
}

.error-message strong {
    font-weight: 700;
    display: block;
    margin-bottom: 8px;
}

.assignment-section {
    background: var(--color-bg-primary);
    padding: 24px;
    border-radius: var(--radius-lg);
    margin: 8px 0;
    border: 1px solid var(--color-border);
}

.assignment-section h3 {
    margin: 0 0 16px 0;
    color: var(--color-text-primary);
    font-size: 18px;
    font-weight: 700;
}

.assignment-section p {
    color: var(--color-text-secondary);
    font-size: 14px;
    margin-bottom: 0;
}

.file-upload-area {
    position: relative;
    border: 2px dashed var(--color-border);
    border-radius: var(--radius-lg);
    padding: 32px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    min-height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-bg-primary);
}

.file-upload-area:hover {
    border-color: var(--color-primary);
    background-color: var(--color-primary-light);
}

.file-upload-area.has-file {
    border-color: var(--color-success);
    background-color: var(--color-success-bg);
}

.file-upload-area input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.file-upload-text {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    pointer-events: none;
}

.file-upload-text span:first-of-type {
    font-weight: 600;
    color: var(--color-text-primary);
    font-size: 15px;
}

.file-upload-text small {
    color: var(--color-text-secondary);
    font-size: 13px;
}

.file-icon {
    font-size: 32px;
    margin-bottom: 4px;
}

.file-preview {
    margin-top: 16px;
    display: none;
}

.file-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
}

.file-item .file-icon {
    font-size: 24px;
}

.file-name {
    flex: 1;
    font-weight: 600;
    color: var(--color-text-primary);
    font-size: 14px;
}

.file-size {
    color: var(--color-text-secondary);
    font-size: 13px;
}

.remove-file {
    background: var(--color-error);
    color: white;
    border: none;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transition: background-color 0.2s;
}

.remove-file:hover {
    background: #DC2626;
}

select:disabled {
    background-color: var(--color-bg-primary);
    color: var(--color-text-secondary);
    cursor: not-allowed;
    opacity: 0.6;
}

.form-group input.error,
.form-group select.error {
    border-color: var(--color-error);
    box-shadow: 0 0 0 3px var(--color-error-bg);
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-container {
        padding: 24px;
    }
    
    .page-header {
        padding: 24px;
    }
    
    .page-header h2 {
        font-size: 24px;
    }
}
</style>

<!-- Include create job JavaScript -->
<script src="../assets/js/pages/create-job.js"></script>

<!-- Data attributes to pass PHP values to JavaScript -->
<div id="create-job-data" style="display: none;" 
     data-max-file-size="<?php echo RoleConfig::getMaxFileSize($_SESSION['user_role']); ?>"
     data-user-role="<?php echo $_SESSION['user_role']; ?>">
</div>

<?php
// Create the missing API file
if (!file_exists('../api/get_users_by_role.php')) {
    $apiDir = '../api';
    if (!is_dir($apiDir)) {
        mkdir($apiDir, 0755, true);
    }
    
    $apiContent = '<?php
session_start();
require_once "../config/database.php";

header("Content-Type: application/json");

try {
    if (!isset($_SESSION["user_logged_in"]) || !$_SESSION["user_logged_in"]) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }
    
    $role = $_GET["role"] ?? "";
    
    if ($role === "test") {
        echo json_encode(["test" => true, "message" => "API is working"]);
        exit;
    }
    
    if (empty($role)) {
        echo json_encode(["error" => "Role parameter is required"]);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT user_id, name, username FROM User WHERE role = ? ORDER BY name");
    $stmt->execute([$role]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($users);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Internal server error", "message" => $e->getMessage()]);
}
?>';
    
    file_put_contents($apiDir . '/get_users_by_role.php', $apiContent);
}
?>
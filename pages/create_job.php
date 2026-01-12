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

<!-- Move styles to a separate section that will be processed -->
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

.form-container {
    background: #ffffff;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
    border: 1px solid #e2e8f0;
    max-width: 800px;
    margin: 0 auto;
}

.job-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #1e293b;
}

.form-group input {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
    background: #ffffff;
}

.form-group input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 1rem;
}

.btn-primary, .btn-secondary {
    padding: 0.75rem 2rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 500;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #f1f5f9;
    color: #1e293b;
    border: 1px solid #d1d5db;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.success-message {
    background: #d1fae5;
    border: 1px solid #10b981;
    color: #065f46;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
}

.success-message h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #065f46;
    font-size: 1.5rem;
}

.success-message p {
    margin-bottom: 1.5rem;
    font-size: 1.1rem;
}

.success-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.success-actions .btn-primary,
.success-actions .btn-secondary {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
    font-weight: 500;
}

.success-actions .btn-primary {
    background: #10b981;
    color: white;
}

.success-actions .btn-primary:hover {
    background: #059669;
    transform: translateY(-1px);
}

.success-actions .btn-secondary {
    background: #3b82f6;
    color: white;
}

.success-actions .btn-secondary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.error-message {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.assignment-section {
    background: #f8fafc;
    padding: 1.5rem;
    border-radius: 12px;
    margin: 1rem 0;
    border: 1px solid #e2e8f0;
}

.assignment-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #1e293b;
    font-size: 1.1rem;
}

.file-upload-area {
    position: relative;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
    min-height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
}

.file-upload-area:hover {
    border-color: #3b82f6;
    background-color: #f1f5f9;
}

.file-upload-area.has-file {
    border-color: #10b981;
    background-color: #f0fdf4;
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
    gap: 0.5rem;
    pointer-events: none;
}

.file-icon {
    font-size: 2rem;
    color: #64748b;
}

.file-upload-area:hover .file-icon {
    color: #3b82f6;
}

.file-preview {
    margin-top: 1rem;
    display: none;
}

.file-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.file-item .file-icon {
    font-size: 1.5rem;
    color: #dc2626;
}

.file-name {
    flex: 1;
    font-weight: 500;
    color: #1e293b;
}

.file-size {
    color: #64748b;
    font-size: 0.875rem;
}

.remove-file {
    background: #dc2626;
    color: white;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    transition: background-color 0.3s;
}

.remove-file:hover {
    background: #b91c1c;
}

select {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    width: 100%;
    transition: border-color 0.3s;
    background: #ffffff;
}

select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

select:disabled {
    background-color: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
}

/* Additional CSS for form validation */
.form-group input.error,
.form-group select.error {
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
}

.loading-spinner {
    text-align: center;
    padding: 2rem;
    color: #64748b;
}

.loading-spinner::after {
    content: "⏳";
    font-size: 2rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .success-actions {
        flex-direction: column;
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
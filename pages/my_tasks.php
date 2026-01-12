<?php
session_start();
require_once '../config/database.php';
require_once '../config/role_config.php';

// Check if user is logged in and is PP role
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    echo '<div class="error-message">Please log in to access this page.</div>';
    exit;
}

if ($_SESSION['user_role'] !== 'PP') {
    echo '<div class="error-message">Access denied. This page is only for Pelukis Pelan (PP) role.</div>';
    exit;
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';
$tasks = [];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_file') {
    try {
        $surveyJobId = intval($_POST['survey_job_id']);
        $description = trim($_POST['description']);
        
        if (!$surveyJobId) {
            throw new Exception('Survey Job ID is required');
        }
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please select a valid file to upload');
        }
        
        $file = $_FILES['file'];
        $fileSize = $file['size'];
        $fileName = $file['name'];
        $fileTmp = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validate file size (max 20MB)
        if ($fileSize > 20971520) {
            throw new Exception('File size must be less than 20MB');
        }
        
        // Validate file type
        $allowedTypes = ['pdf', 'dwg', 'dxf', 'jpg', 'jpeg', 'png', 'tiff', 'tif'];
        if (!in_array($fileExt, $allowedTypes)) {
            throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowedTypes));
        }
        
        // Create unique filename
        $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExt;
        $uploadDir = '../uploads/pp_files/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }
        
        $uploadPath = $uploadDir . $uniqueFileName;
        
        // Move uploaded file
        if (!move_uploaded_file($fileTmp, $uploadPath)) {
            throw new Exception('Failed to upload file');
        }
        
        // Save to database
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if created_at column exists, if not add it
        try {
            $result = $db->query("SHOW COLUMNS FROM sj_files LIKE 'created_at'");
            if ($result->rowCount() == 0) {
                $db->exec("ALTER TABLE sj_files ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            }
        } catch (Exception $e) {
            // Column might already exist or other issue, continue
            error_log("Database column check/creation error: " . $e->getMessage());
        }
        
        $stmt = $db->prepare("INSERT INTO sj_files (surveyjob_id, pp_id, attachment_name, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$surveyJobId, $userId, $uniqueFileName, $description]);
        
        $success = 'File uploaded successfully!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    try {
        $fileId = intval($_POST['file_id']);
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Get file info first
        $stmt = $db->prepare("SELECT attachment_name FROM sj_files WHERE id = ? AND pp_id = ?");
        $stmt->execute([$fileId, $userId]);
        $fileInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fileInfo) {
            throw new Exception('File not found or you do not have permission to delete it');
        }
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM sj_files WHERE id = ? AND pp_id = ?");
        $stmt->execute([$fileId, $userId]);
        
        // Delete physical file
        $filePath = '../uploads/pp_files/' . $fileInfo['attachment_name'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        $success = 'File deleted successfully!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get assigned tasks for PP user
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get survey jobs assigned to this PP user
    $sql = "SELECT 
                sj.survey_job_id,
                sj.surveyjob_no,
                sj.projectname,
                sj.status,
                sj.created_at,
                sj.updated_at,
                creator.name as created_by_name,
                (SELECT COUNT(*) FROM sj_files sf WHERE sf.surveyjob_id = sj.survey_job_id AND sf.pp_id = ?) as file_count
            FROM surveyjob sj
            LEFT JOIN user creator ON sj.created_by = creator.user_id
            WHERE sj.assigned_to = ? 
            AND sj.status IN ('assigned', 'in_progress')
            ORDER BY sj.updated_at DESC, sj.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId, $userId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading tasks: " . $e->getMessage();
}

// Helper function to format date
function formatDate($dateString) {
    if (!$dateString) return 'N/A';
    return date('d/m/Y H:i', strtotime($dateString));
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    $classes = [
        'assigned' => 'status-assigned',
        'in_progress' => 'status-progress',
        'completed' => 'status-completed'
    ];
    return $classes[strtolower($status)] ?? 'status-default';
}
?>

<div class="page-header">
    <h2>My Tasks</h2>
    <p>Upload files for survey jobs assigned to you</p>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="my-tasks-container">
    <!-- Tasks Overview -->
    <div class="tasks-overview">
        <div class="overview-card">
            <div class="overview-icon">📋</div>
            <div class="overview-content">
                <h3><?php echo count($tasks); ?></h3>
                <p>Active Tasks</p>
            </div>
        </div>
        <div class="overview-card">
            <div class="overview-icon">📁</div>
            <div class="overview-content">
                <h3><?php echo array_sum(array_column($tasks, 'file_count')); ?></h3>
                <p>Files Uploaded</p>
            </div>
        </div>
    </div>

    <!-- Tasks List -->
    <div class="tasks-section">
        <h3>Assigned Survey Jobs</h3>
        
        <?php if (empty($tasks)): ?>
            <div class="no-tasks">
                <div class="no-tasks-icon">📭</div>
                <h4>No Active Tasks</h4>
                <p>You don't have any survey jobs assigned to you at the moment.</p>
            </div>
        <?php else: ?>
            <div class="tasks-grid">
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card">
                        <div class="task-header">
                            <div class="task-info">
                                <h4><?php echo htmlspecialchars($task['surveyjob_no']); ?></h4>
                                <span class="status-badge <?php echo getStatusBadgeClass($task['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($task['status'])); ?>
                                </span>
                            </div>
                            <div class="task-meta">
                                <small>Assigned: <?php echo formatDate($task['updated_at']); ?></small>
                            </div>
                        </div>
                        
                        <div class="task-content">
                            <p class="project-name"><?php echo htmlspecialchars($task['projectname']); ?></p>
                            <div class="task-stats">
                                <span class="file-count">📁 <?php echo $task['file_count']; ?> files uploaded</span>
                            </div>
                        </div>
                        
                        <div class="task-actions">
                            <button class="btn-upload" 
                                    onclick="showUploadModal(<?php echo $task['survey_job_id']; ?>, '<?php echo htmlspecialchars($task['surveyjob_no']); ?>')"
                                    data-job-id="<?php echo $task['survey_job_id']; ?>"
                                    data-job-number="<?php echo htmlspecialchars($task['surveyjob_no']); ?>">
                                📤 Upload File
                            </button>
                            <button class="btn-view-files" 
                                    onclick="viewTaskFiles(<?php echo $task['survey_job_id']; ?>)"
                                    data-job-id="<?php echo $task['survey_job_id']; ?>">
                                👁️ View Files
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload File Modal -->
<div id="uploadModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Upload File</h3>
            <button class="modal-close" onclick="closeUploadModal()">&times;</button>
        </div>
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_file">
            <input type="hidden" name="survey_job_id" id="modalSurveyJobId">
            
            <div class="modal-body">
                <div class="upload-info">
                    <p><strong>Survey Job:</strong> <span id="modalJobNumber"></span></p>
                </div>
                
                <div class="form-group">
                    <label for="fileInput">Select File:</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="file" id="fileInput" required accept=".pdf,.dwg,.dxf,.jpg,.jpeg,.png,.tiff,.tif">
                        <div class="file-input-info">
                            <small>Allowed types: PDF, DWG, DXF, JPG, PNG, TIFF (Max: 20MB)</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descriptionInput">Description (Optional):</label>
                    <textarea name="description" id="descriptionInput" placeholder="Enter file description..." rows="3" required></textarea>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeUploadModal()">Cancel</button>
                <button type="submit" class="btn-upload-submit">Upload File</button>
            </div>
        </form>
    </div>
</div>

<!-- View Files Modal -->
<div id="filesModal" class="modal" style="display: none;">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Uploaded Files</h3>
            <button class="modal-close" onclick="closeFilesModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="filesContent">
                <!-- Files will be loaded here -->
            </div>
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
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    border: 1px solid;
}

.alert-success {
    background-color: #d1fae5;
    border-color: #10b981;
    color: #064e3b;
}

.alert-error {
    background-color: #fee2e2;
    border-color: #ef4444;
    color: #991b1b;
}

.my-tasks-container {
    max-width: 1200px;
    margin: 0 auto;
}

.tasks-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.overview-card {
    background: #ffffff;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.overview-icon {
    font-size: 2rem;
    padding: 0.5rem;
    background: #f8fafc;
    border-radius: 8px;
}

.overview-content h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.overview-content p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
}

.tasks-section {
    background: #ffffff;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

.tasks-section h3 {
    margin-bottom: 1.5rem;
    color: #1e293b;
    font-size: 1.25rem;
}

.no-tasks {
    text-align: center;
    padding: 3rem;
    color: #64748b;
}

.no-tasks-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.no-tasks h4 {
    margin: 0 0 0.5rem 0;
    color: #475569;
}

.tasks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.task-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.2s ease;
}

.task-card:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    transform: translateY(-1px);
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.task-info h4 {
    margin: 0 0 0.5rem 0;
    color: #1e293b;
    font-size: 1.1rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
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

.status-default {
    background: #f1f5f9;
    color: #64748b;
}

.task-meta small {
    color: #64748b;
    font-size: 0.8rem;
}

.task-content .project-name {
    color: #374151;
    margin-bottom: 1rem;
    font-weight: 500;
}

.task-stats {
    margin-bottom: 1.5rem;
}

.file-count {
    color: #64748b;
    font-size: 0.9rem;
}

.task-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-upload, .btn-view-files, .btn-upload-submit, .btn-cancel {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.btn-upload {
    background: #3b82f6;
    color: white;
}

.btn-upload:hover {
    background: #2563eb;
}

.btn-view-files {
    background: #64748b;
    color: white;
}

.btn-view-files:hover {
    background: #475569;
}

.btn-upload-submit {
    background: #10b981;
    color: white;
}

.btn-upload-submit:hover {
    background: #059669;
}

.btn-cancel {
    background: #6b7280;
    color: white;
}

.btn-cancel:hover {
    background: #4b5563;
}

/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-large {
    max-width: 800px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #1e293b;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #64748b;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.modal-close:hover {
    background: #f1f5f9;
    color: #374151;
}

.modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    max-height: calc(90vh - 140px);
}

.modal-actions {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.upload-info {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border: 1px solid #e2e8f0;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.file-input-wrapper input[type="file"] {
    width: 100%;
    padding: 0.75rem;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    background: #f9fafb;
    cursor: pointer;
}

.file-input-wrapper input[type="file"]:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}

.file-input-info {
    margin-top: 0.5rem;
}

.file-input-info small {
    color: #6b7280;
}

.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    resize: vertical;
    font-family: inherit;
}

.form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Responsive */
@media (max-width: 768px) {
    .tasks-grid {
        grid-template-columns: 1fr;
    }
    
    .task-actions {
        flex-direction: column;
    }
    
    .btn-upload, .btn-view-files {
        text-align: center;
        justify-content: center;
    }
    
    .modal-content {
        margin: 1rem;
        max-width: calc(100% - 2rem);
    }
}

/* Files List Styles */
.files-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.file-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.file-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.file-icon {
    font-size: 1.5rem;
    margin-right: 1rem;
    color: #64748b;
}

.file-info {
    flex: 1;
    min-width: 0;
}

.file-name {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
    word-break: break-word;
}

.file-meta {
    font-size: 0.875rem;
    color: #64748b;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.file-description {
    font-style: italic;
    color: #374151;
}

.file-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.btn-download, .btn-delete {
    padding: 0.375rem 0.75rem;
    border: none;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-download {
    background: #3b82f6;
    color: white;
}

.btn-download:hover {
    background: #2563eb;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
}

@media (max-width: 640px) {
    .file-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .file-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>

<script src="../assets/js/pages/my-tasks.js"></script>
<script>
// Debug: Check if functions are loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded');
    console.log('showUploadModal function exists:', typeof showUploadModal !== 'undefined');
    console.log('viewTaskFiles function exists:', typeof viewTaskFiles !== 'undefined');
    
    // Add backup event handlers if functions don't exist
    if (typeof showUploadModal === 'undefined') {
        console.error('showUploadModal function not found - JavaScript file not loaded properly');
        window.showUploadModal = function(surveyJobId, jobNumber) {
            alert('Function not loaded properly. Please refresh the page.');
        };
    }
    
    if (typeof viewTaskFiles === 'undefined') {
        console.error('viewTaskFiles function not found - JavaScript file not loaded properly');
        window.viewTaskFiles = function(surveyJobId) {
            alert('Function not loaded properly. Please refresh the page.');
        };
    }
});
</script>

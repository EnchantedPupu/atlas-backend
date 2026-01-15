<?php
session_start();
require_once '../config/database.php';
require_once '../config/role_config.php';

// Check if user is logged in and is FI role
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    echo '<div class="error-message">Please log in to access this page.</div>';
    exit;
}

if ($_SESSION['user_role'] !== 'FI') {
    echo '<div class="error-message">Access denied. This page is only for Field Inspector (FI) role.</div>';
    exit;
}

$userId = $_SESSION['user_id'];

// Get survey jobs with forms that need FI signature
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get active survey jobs assigned to this FI user that have forms ready for review
    $sql = "SELECT DISTINCT
                sj.survey_job_id,
                sj.surveyjob_no,
                sj.projectname,
                sj.status,
                sj.created_at,
                sj.updated_at,
                creator.name as created_by_name,
                COUNT(f.form_id) as form_count,
                SUM(CASE WHEN JSON_EXTRACT(f.form_data, '$.signatures.fi') IS NOT NULL THEN 1 ELSE 0 END) as signed_forms
            FROM surveyjob sj
            LEFT JOIN user creator ON sj.created_by = creator.user_id
            LEFT JOIN forms f ON sj.survey_job_id = f.surveyjob_id
            WHERE sj.status IN ('assigned', 'in_progress', 'pending_review')
            AND sj.status NOT IN ('completed', 'cancelled', 'archived', 'closed')
            AND sj.assigned_to = ?
            AND f.form_id IS NOT NULL
            GROUP BY sj.survey_job_id, sj.surveyjob_no, sj.projectname, sj.status, sj.created_at, sj.updated_at, creator.name
            ORDER BY sj.updated_at DESC, sj.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);
    $surveyJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $surveyJobs = [];
    // Error will be logged but not displayed since we're using API now
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
        'pending_review' => 'status-review',
        'completed' => 'status-completed'
    ];
    return $classes[strtolower($status)] ?? 'status-default';
}
?>

<div class="page-header">
    <h2>Sign Forms</h2>
    <p>Review and sign survey forms for approval</p>
</div>

<div class="sign-forms-container">
    <!-- Overview Cards -->
    <div class="overview-cards">
        <div class="overview-card">
            <div class="overview-icon">📋</div>
            <div class="overview-content">
                <h3><?php echo count($surveyJobs); ?></h3>
                <p>Survey Jobs</p>
            </div>
        </div>
        <div class="overview-card">
            <div class="overview-icon">📝</div>
            <div class="overview-content">
                <h3><?php echo array_sum(array_column($surveyJobs, 'form_count')); ?></h3>
                <p>Total Forms</p>
            </div>
        </div>
        <div class="overview-card">
            <div class="overview-icon">✅</div>
            <div class="overview-content">
                <h3><?php echo array_sum(array_column($surveyJobs, 'signed_forms')); ?></h3>
                <p>Signed Forms</p>
            </div>
        </div>
    </div>

    <!-- Survey Jobs List -->
    <div class="jobs-section">
        <h3>Survey Jobs with Forms</h3>
        
        <?php if (empty($surveyJobs)): ?>
            <div class="no-jobs">
                <div class="no-jobs-icon">📭</div>
                <h4>No Survey Jobs Found</h4>
                <p>There are no survey jobs with forms available for signing at the moment.</p>
            </div>
        <?php else: ?>
            <div class="jobs-grid">
                <?php foreach ($surveyJobs as $job): ?>
                    <div class="job-card">
                        <div class="job-header">
                            <div class="job-info">
                                <h4><?php echo htmlspecialchars($job['surveyjob_no']); ?></h4>
                                <span class="status-badge <?php echo getStatusBadgeClass($job['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="job-content">
                            <div class="project-name">
                                <strong>Project:</strong> <?php echo htmlspecialchars($job['projectname']); ?>
                            </div>
                            
                            <div class="job-meta">
                                <small>Created by: <?php echo htmlspecialchars($job['created_by_name']); ?></small><br>
                                <small>Created: <?php echo formatDate($job['created_at']); ?></small><br>
                                <small>Updated: <?php echo formatDate($job['updated_at']); ?></small>
                            </div>
                            
                            <div class="form-stats">
                                <div class="stat-item">
                                    <span class="stat-label">Total Forms:</span>
                                    <span class="stat-value"><?php echo $job['form_count']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Signed:</span>
                                    <span class="stat-value signed"><?php echo $job['signed_forms']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Pending:</span>
                                    <span class="stat-value pending"><?php echo $job['form_count'] - $job['signed_forms']; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="job-actions">
                            <button class="btn-view-forms" onclick="viewJobForms(<?php echo $job['survey_job_id']; ?>, '<?php echo htmlspecialchars($job['surveyjob_no']); ?>')">
                                📋 View Forms
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Forms Modal -->
<div id="formsModal" class="modal" style="display: none;">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Survey Job Forms - <span id="modalJobNumber"></span></h3>
            <button class="modal-close" onclick="closeFormsModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="formsContent">
                <!-- Forms will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Sign Form Modal -->
<div id="signModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Sign Form</h3>
            <button class="modal-close" onclick="closeSignModal()">&times;</button>
        </div>
        <form id="signForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="sign_form">
            <input type="hidden" name="form_id" id="signFormId">
            
            <div class="modal-body">
                <div class="form-info">
                    <p><strong>Form Type:</strong> <span id="signFormType"></span></p>
                    <p><strong>Survey Job:</strong> <span id="signJobNumber"></span></p>
                </div>
                
                <div class="signature-section">
                    <label for="signature">Digital Signature *</label>
                    <div class="signature-pad-container">
                        <canvas id="signaturePad" width="400" height="200" style="border: none; background: #ffffff;"></canvas>
                        <input type="hidden" name="signature" id="signatureData">
                    </div>
                    <div class="signature-controls">
                        <button type="button" class="btn-clear-signature" onclick="clearSignature()">Clear</button>
                        <small class="signature-note">Draw your signature in the box above</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="stamp_image">Upload Stamp Image</label>
                    <input type="file" name="stamp_image" id="stamp_image" accept="image/*" class="file-input">
                    <small class="file-note">Supported formats: JPG, PNG, GIF, BMP, WEBP</small>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeSignModal()">Cancel</button>
                <button type="submit" class="btn-sign-submit">Sign Form</button>
            </div>
        </form>
    </div>
</div>

<style>
.page-header {
    margin-bottom: 2rem;
    padding: 0 0 1.5rem;
    border-bottom: 1px solid #E2E8F0;
}

.page-header h2 {
    color: #0F172A;
    font-size: 1.875rem;
    font-weight: 600;
    letter-spacing: -0.5px;
    margin-bottom: 0.25rem;
}

.page-header p {
    color: #64748B;
    font-size: 0.9375rem;
}

.alert {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border-left: 4px solid;
    animation: slideInDown 0.3s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success {
    background-color: #ECFDF5;
    border-color: #10B981;
    color: #065F46;
}

.alert-error {
    background-color: #FEF2F2;
    border-color: #EF4444;
    color: #991B1B;
}

.sign-forms-container {
    max-width: 1200px;
    margin: 0 auto;
}

.overview-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.overview-card {
    background: #FFFFFF;
    padding: 1.75rem;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.overview-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #10B981, #059669);
    opacity: 0;
    transition: opacity 0.2s;
}

.overview-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.1);
    border-color: #CBD5E1;
}

.overview-card:hover::before {
    opacity: 1;
}

.overview-icon {
    font-size: 2.25rem;
    padding: 0.875rem;
    background: linear-gradient(135deg, #ECFDF5, #D1FAE5);
    border-radius: 12px;
    line-height: 1;
}

.overview-content h3 {
    margin: 0 0 0.125rem;
    font-size: 1.875rem;
    font-weight: 700;
    color: #0F172A;
    letter-spacing: -0.5px;
}

.overview-content p {
    margin: 0;
    color: #64748B;
    font-size: 0.875rem;
    font-weight: 500;
}

.jobs-section {
    background: #ffffff;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}

.jobs-section h3 {
    margin-bottom: 1.5rem;
    color: #1e293b;
    font-size: 1.25rem;
}

.no-jobs {
    text-align: center;
    padding: 3rem;
    color: #64748b;
}

.no-jobs-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.no-jobs h4 {
    margin: 0 0 0.5rem 0;
    color: #475569;
}

.jobs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
}

.job-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 1.75rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.job-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, #10B981, #059669);
    opacity: 0;
    transition: opacity 0.25s;
}

.job-card:hover {
    box-shadow: 0 12px 24px -8px rgba(16, 185, 129, 0.15);
    transform: translateY(-3px);
    border-color: #CBD5E1;
}

.job-card:hover::before {
    opacity: 1;
}

.job-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.job-info h4 {
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
    background: #fef3c7;
    color: #d97706;
}

.status-review {
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

.job-content {
    margin-bottom: 1.5rem;
}

.project-name {
    color: #374151;
    margin-bottom: 1rem;
    font-weight: 500;
}

.job-meta small {
    color: #64748b;
    font-size: 0.8rem;
    line-height: 1.4;
}

.form-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 1rem;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.stat-item {
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 0.25rem;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-value.signed {
    color: #059669;
}

.stat-value.pending {
    color: #dc2626;
}

.job-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-view-forms, .btn-sign-submit, .btn-cancel, .btn-clear-signature {
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

.btn-view-forms {
    background: #3b82f6;
    color: white;
    flex: 1;
    justify-content: center;
}

.btn-view-forms:hover {
    background: #2563eb;
}

.btn-sign-submit {
    background: #10b981;
    color: white;
}

.btn-sign-submit:hover {
    background: #059669;
}

.btn-cancel {
    background: #6b7280;
    color: white;
}

.btn-cancel:hover {
    background: #4b5563;
}

.btn-clear-signature {
    background: #ef4444;
    color: white;
    padding: 0.375rem 0.75rem;
    font-size: 0.8rem;
}

.btn-clear-signature:hover {
    background: #dc2626;
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
    max-width: 900px;
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

.form-info {
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

.file-input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-family: inherit;
    background: #ffffff;
    cursor: pointer;
}

.file-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.file-note {
    color: #6b7280;
    font-size: 0.875rem;
    font-style: italic;
    margin-top: 0.25rem;
    display: block;
}

.signature-section {
    margin-bottom: 1.5rem;
}

.signature-section label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.signature-pad-container {
    border: 2px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    margin-bottom: 0.5rem;
    overflow: hidden;
    position: relative;
    min-height: 200px;
    width: 100%;
    display: block;
}

#signaturePad {
    display: block;
    width: 100%;
    height: 200px;
    cursor: crosshair;
    touch-action: none;
    background: #ffffff;
    border: none;
    outline: none;
    position: relative;
    z-index: 1;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.signature-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.signature-note {
    color: #6b7280;
    font-style: italic;
}

/* Forms List in Modal */
.forms-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Tree Structure Styles */
.forms-tree {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.lot-group {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    overflow: hidden;
}

.lot-header {
    padding: 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    cursor: pointer;
    user-select: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background 0.2s ease;
}

.lot-header:hover {
    background: #f1f5f9;
}

.lot-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.lot-toggle {
    font-size: 0.8rem;
    font-weight: bold;
    color: #64748b;
    transition: transform 0.2s ease;
    width: 16px;
    text-align: center;
}

.lot-title {
    font-weight: 600;
    color: #1e293b;
    font-size: 1rem;
}

.lot-count {
    color: #64748b;
    font-size: 0.875rem;
}

.lot-progress {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.progress-bar {
    width: 120px;
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 500;
    white-space: nowrap;
}

.completion-badge {
    background: #d1fae5;
    color: #065f46;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.lot-forms {
    padding: 1rem;
    background: #ffffff;
}

.tree-form-item {
    margin-left: 1rem;
    border-left: 2px solid #e2e8f0;
    padding-left: 1rem;
    position: relative;
}

.tree-form-item::before {
    content: '';
    position: absolute;
    left: -1px;
    top: 50%;
    width: 12px;
    height: 1px;
    background: #e2e8f0;
}

.forms-list .tree-form-item:last-child {
    border-left-color: transparent;
}

.forms-list .tree-form-item:last-child::after {
    content: '';
    position: absolute;
    left: -1px;
    top: 50%;
    bottom: 0;
    width: 1px;
    background: #ffffff;
}

.form-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.form-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.form-details {
    flex: 1;
    min-width: 0;
}

.form-type {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.form-meta {
    font-size: 0.875rem;
    color: #64748b;
}

.form-status {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.signature-status {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.signature-signed {
    background: #d1fae5;
    color: #065f46;
}

.signature-pending {
    background: #fef3c7;
    color: #d97706;
}

.btn-sign-form, .btn-view-signature {
    padding: 0.375rem 0.75rem;
    border: none;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-sign-form {
    background: #3b82f6;
    color: white;
}

.btn-sign-form:hover {
    background: #2563eb;
}

.btn-view-signature {
    background: #64748b;
    color: white;
}

.btn-view-signature:hover {
    background: #475569;
}

/* Responsive */
@media (max_width: 768px) {
    .jobs-grid {
        grid-template-columns: 1fr;
    }
    
    .form-stats {
        grid-template-columns: 1fr;
        gap: 0.25rem;
    }
    
    .stat-item {
        text-align: left;
    }
    
    .modal-content {
        margin: 1rem;
        max-width: calc(100% - 2rem);
    }
    
    .signature-controls {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
    
    .form-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .form-status {
        flex-direction: row;
        width: 100%;
        justify-content: space-between;
    }
    
    .lot-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .lot-progress {
        width: 100%;
        justify-content: space-between;
    }
    
    .progress-bar {
        width: 100px;
    }
    
    .tree-form-item {
        margin-left: 0.5rem;
        padding-left: 0.5rem;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script src="../assets/js/pages/sign-forms.js"></script>
<script>
// Ensure SignaturePad is loaded before initializing
document.addEventListener('DOMContentLoaded', function() {
    console.log('Sign forms page loaded');
    console.log('SignaturePad available:', typeof SignaturePad !== 'undefined');
    
    // Initialize the sign forms page functionality
    if (typeof window.initializeSignFormsPage === 'function') {
        window.initializeSignFormsPage();
    }
});

// Handle window resize for signature pad
window.addEventListener('resize', function() {
    if (window.signaturePad && typeof resizeSignaturePad === 'function') {
        resizeSignaturePad();
    }
});
</script>

// My Tasks Page JavaScript

console.log('My Tasks JavaScript file loaded');

// Initialize My Tasks Page
function initializeMyTasksPage() {
    console.log('Initializing My Tasks Page');
    
    // Initialize modals
    initializeModals();
    
    // Initialize file upload functionality
    initializeFileUpload();
    
    console.log('My Tasks Page initialized');
}

// Initialize modals
function initializeModals() {
    // Close modals when clicking outside
    window.onclick = function(event) {
        const uploadModal = document.getElementById('uploadModal');
        const filesModal = document.getElementById('filesModal');
        
        if (event.target === uploadModal) {
            closeUploadModal();
        }
        if (event.target === filesModal) {
            closeFilesModal();
        }
    }
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeUploadModal();
            closeFilesModal();
        }
    });
}

// Initialize file upload form
function initializeFileUpload() {
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleFileUpload();
        });
    }
    
    // File input change event
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            validateFileInput(this);
        });
    }
    
    // Also attach click handlers to buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-upload')) {
            const jobId = e.target.getAttribute('data-job-id');
            const jobNumber = e.target.getAttribute('data-job-number');
            if (jobId && jobNumber) {
                showUploadModal(jobId, jobNumber);
            }
        }
        
        if (e.target.classList.contains('btn-view-files')) {
            const jobId = e.target.getAttribute('data-job-id');
            if (jobId) {
                viewTaskFiles(jobId);
            }
        }
    });
}

// Show upload modal
function showUploadModal(surveyJobId, jobNumber) {
    console.log(`showUploadModal called with surveyJobId: ${surveyJobId}, jobNumber: ${jobNumber}`);
    
    const modal = document.getElementById('uploadModal');
    const jobIdInput = document.getElementById('modalSurveyJobId');
    const jobNumberSpan = document.getElementById('modalJobNumber');
    const form = document.getElementById('uploadForm');
    
    console.log('Modal elements found:', {
        modal: !!modal,
        jobIdInput: !!jobIdInput,
        jobNumberSpan: !!jobNumberSpan,
        form: !!form
    });
    
    if (!modal || !jobIdInput || !jobNumberSpan || !form) {
        console.error('Upload modal elements not found');
        alert('Modal elements not found. Please check the HTML structure.');
        return;
    }
    
    // Reset form and set values
    form.reset();
    jobIdInput.value = surveyJobId;
    jobNumberSpan.textContent = jobNumber;
    
    // Show modal
    modal.style.display = 'flex';
    
    // Focus on file input
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        setTimeout(() => fileInput.focus(), 100);
    }
}

// Close upload modal
function closeUploadModal() {
    const modal = document.getElementById('uploadModal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Clear any error messages
    clearFileError();
}

// Handle file upload
function handleFileUpload() {
    const form = document.getElementById('uploadForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    // Show loading state
    submitBtn.textContent = 'Uploading...';
    submitBtn.disabled = true;
    
    const formData = new FormData(form);
    
    // Use the correct API endpoint for uploading PP files
    fetch('../api/upload_pp_file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Upload response status:', response.status);
        return response.text();
    })
    .then(data => {
        console.log('Upload response:', data);
        try {
            // Try to parse as JSON
            const jsonData = JSON.parse(data);
            if (jsonData.error) {
                throw new Error(jsonData.error);
            }
        } catch (e) {
            console.log('Response is not JSON or has error:', e);
        }
        
        // Reload the My Tasks page via dashboard AJAX if available
        if (typeof loadPage === 'function') {
            loadPage('my_tasks.php', 'My Tasks');
        } else {
            // Fallback: reload the whole page
            location.reload();
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showFileError('Upload failed: ' + error.message);
    })
    .finally(() => {
        // Reset button state
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

// Validate file input
function validateFileInput(input) {
    const file = input.files[0];
    if (!file) return;
    
    // Clear previous errors
    clearFileError();
    
    // Check file size (20MB max)
    const maxSize = 20 * 1024 * 1024; // 20MB
    if (file.size > maxSize) {
        showFileError('File size must be less than 20MB');
        input.value = '';
        return;
    }
    
    // Check file type
    const allowedExtensions = ['pdf', 'dwg', 'dxf', 'jpg', 'jpeg', 'png', 'tiff', 'tif'];
    const fileExt = file.name.split('.').pop().toLowerCase();
    
    if (!allowedExtensions.includes(fileExt)) {
        showFileError('Invalid file type. Allowed: ' + allowedExtensions.join(', '));
        input.value = '';
        return;
    }
    
    console.log('File validated:', file.name);
}

// Show file error
function showFileError(message) {
    const errorDiv = document.querySelector('.file-input-error');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    } else {
        // Create error div if it doesn't exist
        const fileWrapper = document.querySelector('.file-input-wrapper');
        if (fileWrapper) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'file-input-error';
            errorDiv.style.color = '#ef4444';
            errorDiv.style.fontSize = '0.875rem';
            errorDiv.style.marginTop = '0.5rem';
            errorDiv.textContent = message;
            fileWrapper.appendChild(errorDiv);
        }
    }
}

// Clear file error
function clearFileError() {
    const errorDiv = document.querySelector('.file-input-error');
    if (errorDiv) {
        errorDiv.style.display = 'none';
    }
}

// View task files
function viewTaskFiles(surveyJobId) {
    console.log(`Loading files for job ${surveyJobId}`);
    
    const modal = document.getElementById('filesModal');
    const content = document.getElementById('filesContent');
    
    if (!modal || !content) {
        console.error('Files modal elements not found');
        return;
    }
    
    // Show loading
    content.innerHTML = `
        <div class="loading-spinner" style="text-align: center; padding: 2rem;">
            <div style="font-size: 2rem; margin-bottom: 1rem;">⏳</div>
            <p>Loading files...</p>
        </div>
    `;
    
    modal.style.display = 'flex';
    
    // Fetch files via API
    fetch(`../api/get_pp_files.php?survey_job_id=${surveyJobId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            displayFiles(data);
        })
        .catch(error => {
            console.error('Error loading files:', error);
            content.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #ef4444;">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">❌</div>
                    <h4>Error Loading Files</h4>
                    <p>${error.message}</p>
                    <button class="btn-retry" onclick="viewTaskFiles(${surveyJobId})" style="margin-top: 1rem; padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer;">Retry</button>
                </div>
            `;
        });
}

// View file (open in new tab for inline viewing)
function viewFile(filename) {
    window.open(`../uploads/pp_files/${encodeURIComponent(filename)}`, '_blank');
}

// Display files in modal
function displayFiles(files) {
    const content = document.getElementById('filesContent');
    
    let html = '';
    
    if (files.length === 0) {
        html = `
            <div style="text-align: center; padding: 2rem; color: #64748b;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                <h4>No Files Uploaded</h4>
                <p>No files have been uploaded for this task yet.</p>
            </div>
        `;
    } else {
        html = '<div class="files-list">';
        files.forEach(file => {
            const uploadDate = new Date(file.created_at).toLocaleDateString('en-GB', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            html += `
                <div class="file-item">
                    <div class="file-icon">${getFileIcon(file.attachment_name)}</div>
                    <div class="file-info">
                        <div class="file-name">${escapeHtml(file.original_name || file.attachment_name)}</div>
                        <div class="file-meta">
                            <span>Uploaded: ${uploadDate}</span>
                            ${file.description ? `<span class="file-description">${escapeHtml(file.description)}</span>` : ''}
                        </div>
                    </div>
                    <div class="file-actions">
                        <button class="btn-view" onclick="viewFile('${escapeHtml(file.attachment_name)}')">View</button>
                        <button class="btn-delete" onclick="deleteFile(${file.id}, '${escapeHtml(file.original_name || file.attachment_name)}')">Delete</button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
    }
    
    content.innerHTML = html;
}

// Get file icon based on extension
function getFileIcon(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    const icons = {
        'pdf': '📄',
        'dwg': '📐',
        'dxf': '📐',
        'jpg': '🖼️',
        'jpeg': '🖼️',
        'png': '🖼️',
        'tiff': '🖼️',
        'tif': '🖼️'
    };
    return icons[ext] || '📄';
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close files modal
function closeFilesModal() {
    const modal = document.getElementById('filesModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Download file
function downloadFile(filename) {
    console.log(`Downloading file: ${filename}`);
    window.open(`../api/download_pp_file.php?file=${encodeURIComponent(filename)}`, '_blank');
}

// Delete file
function deleteFile(fileId, fileName) {
    if (!confirm(`Are you sure you want to delete "${fileName}"?`)) {
        return;
    }
    
    console.log(`Deleting file: ${fileName} (ID: ${fileId})`);
    
    const formData = new FormData();
    formData.append('action', 'delete_file');
    formData.append('file_id', fileId);
    
    fetch('../api/delete_pp_file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        // Reload the My Tasks page via dashboard AJAX if available
        if (typeof loadPage === 'function') {
            loadPage('my_tasks.php', 'My Tasks');
        } else {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        alert('Error deleting file: ' + error.message);
    });
}

// Mark task as complete
function markTaskComplete(surveyJobId) {
    if (!confirm('Mark this task as complete?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'mark_complete');
    formData.append('survey_job_id', surveyJobId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        location.reload();
    })
    .catch(error => {
        console.error('Complete task error:', error);
        alert('Error marking task as complete: ' + error.message);
    });
}

// Initialize page when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('My Tasks page DOM loaded');
    initializeMyTasksPage();
});

// Export functions for global access
window.showUploadModal = showUploadModal;
window.closeUploadModal = closeUploadModal;
window.viewTaskFiles = viewTaskFiles;
window.closeFilesModal = closeFilesModal;
window.downloadFile = downloadFile;
window.deleteFile = deleteFile;
window.markTaskComplete = markTaskComplete;
window.viewFile = viewFile;

/**
 * Job Details Modal - Centralized modal functionality
 * This file handles all job details modal operations across the application
 */

// Global flag to track current page context (for API paths)
let isReportPage = false;

/**
 * Set the page context for API paths
 * @param {boolean} isReport - True if called from report page, false otherwise
 */
function setJobDetailsModalContext(isReport = false) {
    isReportPage = isReport;
}

/**
 * Get the appropriate API base path based on context
 * @returns {string} API base path
 */
function getApiBasePath() {
    return isReportPage ? '../api/' : 'api/';
}

/**
 * View job details in modal
 * @param {number} jobId - The ID of the job to view
 */
function viewJobDetails(jobId) {
    console.log(`Viewing job details for ID: ${jobId}`);
    
    // Auto-detect context based on current page location
    // Report page is in pages/report.php, all others are in root with pages/ loaded
    const currentPath = window.location.pathname;
    const isOnReportPage = currentPath.includes('/pages/report.php') || currentPath.includes('\\pages\\report.php');
    setJobDetailsModalContext(isOnReportPage);
    
    const modal = document.getElementById('jobDetailsModal');
    const content = document.getElementById('jobDetailsContent');
    
    if (!modal || !content) {
        console.error('Job details modal elements not found');
        return;
    }
    
    // Clear any previous content
    content.innerHTML = '';
    
    // Show loading state
    content.innerHTML = `
        <div class="loading-spinner">
            <div>⏳</div>
            <p>Loading job details...</p>
        </div>
    `;
    
    modal.style.display = 'flex';
    
    // Fetch job details from API
    const apiPath = getApiBasePath();
    console.log(`Using API path: ${apiPath} (isReportPage: ${isReportPage})`);
    fetch(`${apiPath}get_job_details.php?job_id=${jobId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            displayJobDetails(data, jobId);
        })
        .catch(error => {
            console.error('Error loading job details:', error);
            content.innerHTML = `
                <div class="error-message">
                    <h4>❌ Error Loading Job Details</h4>
                    <p>${error.message || 'Failed to load job details. Please try again.'}</p>
                    <button onclick="viewJobDetails(${jobId})" class="btn-secondary">🔄 Retry</button>
                    <button onclick="closeJobDetailsModal()" class="btn-secondary">Close</button>
                </div>
            `;
        });
}

/**
 * Display job details in the modal
 * @param {object} job - Job data object
 * @param {number} jobId - Job ID for reference
 */
function displayJobDetails(job, jobId) {
    const content = document.getElementById('jobDetailsContent');
    
    const html = `
        <div class="job-quick-info">
            <div class="info-card primary-info">
                <div class="info-header">
                    <h3>📋 ${job.projectname || 'N/A'}</h3>
                    <div class="status-badges">
                        <span class="status-badge status-${job.status?.toLowerCase() || 'default'}">${job.status || 'Unknown'}</span>
                        ${job.pbtstatus && job.pbtstatus !== 'none' ? 
                            `<span class="pbtstatus-badge pbtstatus-${job.pbtstatus?.toLowerCase()}">${job.pbtstatus}</span>` 
                            : ''}
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-icon">🔢</span>
                        <div class="info-content">
                            <div class="info-label">Job Number</div>
                            <div class="info-value">${job.surveyjob_no || 'N/A'}</div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-icon">📌</span>
                        <div class="info-content">
                            <div class="info-label">HQ Reference</div>
                            <div class="info-value">${job.hq_ref || 'N/A'}</div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-icon">🏢</span>
                        <div class="info-content">
                            <div class="info-label">Division Reference</div>
                            <div class="info-value">${job.div_ref || 'N/A'}</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="info-card assignment-info">
                <div class="info-header">
                    <h4>👥 Assignment & Dates</h4>
                </div>
                <div class="assignment-grid">
                    <div class="assignment-item">
                        <div class="assignment-label">Created By</div>
                        <div class="assignment-value">
                            <span class="user-name">${job.created_by_name || 'Unknown'}</span>
                            <span class="user-role">${job.created_by_role || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="assignment-item">
                        <div class="assignment-label">Assigned To</div>
                        <div class="assignment-value">
                            ${job.assigned_to_name ? 
                                `<span class="user-name">${job.assigned_to_name}</span>
                                 <span class="user-role">${job.assigned_to_role || 'N/A'}</span>` 
                                : '<span class="unassigned">Not Assigned</span>'}
                        </div>
                    </div>
                    <div class="assignment-item">
                        <div class="assignment-label">Created</div>
                        <div class="assignment-value">
                            <span class="date-value">${formatDateTime(job.created_at)}</span>
                        </div>
                    </div>
                    <div class="assignment-item">
                        <div class="assignment-label">Last Updated</div>
                        <div class="assignment-value">
                            <span class="date-value">${formatDateTime(job.updated_at)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        ${job.form_count > 0 ? `
            <div class="forms-quick-view">
                <div class="quick-view-header">
                    <h4>📄 Forms Summary</h4>
                    <span class="form-count-badge">${job.form_count} Forms</span>
                </div>
                <div class="forms-types">
                    ${job.form_types && job.form_types.length > 0 ? 
                        job.form_types.map(type => `<span class="form-type-tag">${type}</span>`).join('') 
                        : '<span class="form-type-tag">N/A</span>'}
                </div>
                <button onclick="loadJobForms(${job.survey_job_id || jobId})" class="btn-load-forms">
                    📋 View All Forms
                </button>
                <div id="formsContainer" class="forms-container" style="display: none;">
                    <!-- Forms will be loaded here -->
                </div>
            </div>
        ` : `
            <div class="forms-quick-view empty">
                <div class="empty-forms-message">
                    <span class="empty-icon">📄</span>
                    <p>No forms available for this job</p>
                </div>
            </div>
        `}
        
        ${job.attachment_name && job.attachment_name !== 'no_file_uploaded.pdf' || (job.sj_files && job.sj_files.length > 0) ? `
            <div class="attachments-quick-view">
                <div class="quick-view-header">
                    <h4>📎 Attachments</h4>
                    <span class="attachment-count-badge">${(job.attachment_name && job.attachment_name !== 'no_file_uploaded.pdf' ? 1 : 0) + (job.sj_files ? job.sj_files.length : 0)}</span>
                </div>
                <div class="attachments-list">
                    ${job.attachment_name && job.attachment_name !== 'no_file_uploaded.pdf' ? `
                        <div class="attachment-card">
                            <span class="file-icon">📄</span>
                            <div class="file-info">
                                <div class="file-name">${job.attachment_name}</div>
                                <div class="file-label">Main Attachment</div>
                            </div>
                            <button onclick="openAttachment('uploads/jobs/oic/${job.attachment_name}')" class="btn-download-file">
                                💾 Download
                            </button>
                        </div>
                    ` : ''}
                    ${job.sj_files && job.sj_files.length > 0 ? 
                        job.sj_files.map(file => `
                            <div class="attachment-card">
                                <span class="file-icon">📎</span>
                                <div class="file-info">
                                    <div class="file-name">${file.description || file.attachment_name}</div>
                                    <div class="file-date">${formatDateTime(file.created_at)}</div>
                                </div>
                                <button onclick="openSjFile('${file.attachment_name}')" class="btn-download-file">
                                    💾 Download
                                </button>
                            </div>
                        `).join('') 
                    : ''}
                </div>
            </div>
        ` : ''}
        
        <div class="modal-actions">
            <button onclick="closeJobDetailsModal()" class="btn-secondary">Close</button>
        </div>
    `;
    
    content.innerHTML = html;
}

/**
 * Close the job details modal
 */
function closeJobDetailsModal() {
    const modal = document.getElementById('jobDetailsModal');
    const content = document.getElementById('jobDetailsContent');
    
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Clear content to prevent state retention between page navigations
    if (content) {
        content.innerHTML = '';
    }
}

/**
 * Open an attachment in a new tab
 * @param {string} path - Path to the attachment
 */
function openAttachment(path) {
    console.log(`Opening attachment: ${path}`);
    
    // Remove leading slash if present
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    
    // Open in new tab
    window.open(cleanPath, '_blank');
}

/**
 * Open an sj_files attachment in a new tab
 * @param {string} filename - Filename of the sj_file
 */
function openSjFile(filename) {
    console.log(`Opening sj_file: ${filename}`);
    
    // sj_files are stored in uploads/pp_files/ directory
    const path = `uploads/pp_files/${filename}`;
    
    // Open in new tab
    window.open(path, '_blank');
}

/**
 * Download an attachment (legacy support)
 * @param {string} filename - Filename to download
 */
function downloadAttachment(filename) {
    console.log(`Downloading attachment: ${filename}`);
    
    // Use openAttachment for consistency
    openAttachment(`uploads/jobs/oic/${filename}`);
}

/**
 * Format date and time
 * @param {string} dateString - Date string to format
 * @returns {string} Formatted date/time or 'N/A'
 */
function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        
        return date.toLocaleDateString('en-GB') + ' ' + date.toLocaleTimeString('en-GB', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    } catch (e) {
        return 'N/A';
    }
}

/**
 * Format status text
 * @param {string} status - Status to format
 * @returns {string} Formatted status
 */
function formatStatus(status) {
    if (!status) return 'N/A';
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

/**
 * Get status CSS class
 * @param {string} status - Status value
 * @returns {string} CSS class name
 */
function getStatusClass(status) {
    const statusMap = {
        'pending': 'status-pending',
        'assigned': 'status-assigned',
        'in_progress': 'status-progress',
        'completed': 'status-completed',
        'reviewed': 'status-reviewed',
        'approved': 'status-approved'
    };
    return statusMap[status] || 'status-default';
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('jobDetailsModal');
    if (event.target === modal) {
        closeJobDetailsModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    const modal = document.getElementById('jobDetailsModal');
    if (event.key === 'Escape' && modal && modal.style.display === 'flex') {
        closeJobDetailsModal();
    }
});

/**
 * Load job forms into the forms container
 * @param {number} jobId - The ID of the job to load forms for
 */
function loadJobForms(jobId) {
    console.log(`Loading forms for job ${jobId}`);
    
    const container = document.getElementById('formsContainer');
    const loadButton = document.querySelector('.btn-load-forms');
    
    if (!container) {
        console.error('Forms container not found');
        return;
    }
    
    // Show loading
    container.innerHTML = `
        <div class="loading-spinner">
            <div>⏳</div>
            <p>Loading forms...</p>
        </div>
    `;
    container.style.display = 'block';
    
    // Update button text
    if (loadButton) {
        loadButton.textContent = '🔄 Loading...';
        loadButton.disabled = true;
    }
    
    // Get API path based on context
    const apiPath = getApiBasePath();
    
    // Fetch forms and job details (for other_attachment)
    Promise.all([
        fetch(`${apiPath}get_job_forms.php?job_id=${jobId}`),
        fetch(`${apiPath}get_job_details.php?job_id=${jobId}`)
    ])
    .then(responses => Promise.all(responses.map(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })))
    .then(([formsData, jobData]) => {
        if (!formsData.success) {
            throw new Error(formsData.error || 'Failed to load forms');
        }
        
        if (jobData.error) {
            throw new Error(jobData.error);
        }
        
        const forms = formsData.forms || [];
        
        if (forms.length === 0) {
            container.innerHTML = `
                <div class="no-forms">
                    <p>📭 No forms found for this job</p>
                </div>
            `;
            if (loadButton) {
                loadButton.textContent = '📋 View All Forms';
                loadButton.disabled = false;
            }
            return;
        }
        
        // Group forms by category
        const categorizedForms = groupFormsByCategory(forms);
        
        // Build categorized forms HTML
        container.innerHTML = buildCategorizedFormsHTML(categorizedForms);
        
        // Load attachments if they exist
        if (jobData.other_attachment) {
            try {
                const otherAttachment = typeof jobData.other_attachment === 'string' 
                    ? JSON.parse(jobData.other_attachment) 
                    : jobData.other_attachment;
                
                // Load parent attachments
                if (otherAttachment.parent_attachment) {
                    loadParentAttachments(otherAttachment.parent_attachment);
                }
                
                // Load child attachments for each lot
                if (otherAttachment.child_attachment) {
                    loadChildAttachments(otherAttachment.child_attachment);
                }
            } catch (e) {
                console.warn('Error parsing other_attachment:', e);
            }
        }
        
        // Update button
        if (loadButton) {
            loadButton.textContent = '📋 Forms Loaded';
            loadButton.disabled = false;
            loadButton.style.display = 'none'; // Hide button after loading
        }
    })
    .catch(error => {
        console.error('Error loading forms:', error);
        container.innerHTML = `
            <div class="error-message">
                <h4>❌ Error Loading Forms</h4>
                <p>${error.message || 'Failed to load forms. Please try again.'}</p>
                <button onclick="loadJobForms(${jobId})" class="btn-secondary">🔄 Retry</button>
            </div>
        `;
        
        if (loadButton) {
            loadButton.textContent = '📋 View All Forms';
            loadButton.disabled = false;
        }
    });
}

/**
 * Load parent attachments
 * @param {Array} parentAttachments - Array of parent attachment objects
 */
function loadParentAttachments(parentAttachments) {
    if (!parentAttachments || parentAttachments.length === 0) return;
    
    const container = document.getElementById('parentAttachmentsContainer');
    if (!container) return;
    
    container.innerHTML = `
        <div class="parent-attachments-section">
            <div class="parent-attachments-header">
                <span class="parent-attachments-icon">📎</span>
                <span class="parent-attachments-title">General Attachments (${parentAttachments.length})</span>
            </div>
            <div class="parent-attachments-list">
                ${parentAttachments.map((attachment, index) => {
                    const fileName = attachment.path.split('/').pop() || `attachment_${index + 1}.pdf`;
                    return `
                        <div class="parent-attachment-item">
                            <span class="parent-attachment-icon">📄</span>
                            <span class="parent-attachment-name">${fileName}</span>
                            <button onclick="openAttachment('${attachment.path}')" class="btn-open-parent-attachment" title="Open Attachment">
                                🔗 Open
                            </button>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `;
}

/**
 * Load child attachments for each lot
 * @param {Array} childAttachments - Array of lot attachment objects
 */
function loadChildAttachments(childAttachments) {
    childAttachments.forEach(lotAttachment => {
        const lotNo = lotAttachment.lot_no;
        const attachments = lotAttachment.attachment || [];
        
        if (attachments.length === 0) return;
        
        // Find all lot attachment containers for this lot number
        const attachmentContainers = document.querySelectorAll(`[id^="lotAttachments_"][id$="_${lotNo}"]`);
        
        attachmentContainers.forEach(container => {
            container.innerHTML = `
                <div class="attachments-section">
                    <div class="attachments-header">
                        <span class="attachments-icon">📎</span>
                        <span class="attachments-title">Attachments (${attachments.length})</span>
                    </div>
                    <div class="attachments-list">
                        ${attachments.map((attachment, index) => {
                            const fileName = attachment.path.split('/').pop() || `attachment_${index + 1}.pdf`;
                            return `
                                <div class="attachment-item">
                                    <span class="attachment-icon">📄</span>
                                    <span class="attachment-name">${fileName}</span>
                                    <button onclick="openAttachment('${attachment.path}')" class="btn-open-attachment" title="Open Attachment">
                                        🔗 Open
                                    </button>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        });
    });
}

/**
 * Group forms by category and lot number
 * @param {Array} forms - Array of form objects
 * @returns {Object} Categorized forms object
 */
function groupFormsByCategory(forms) {
    const categories = {};
    
    forms.forEach(form => {
        // Use the database form_type field directly as the category
        let category = form.form_type || 'Other Forms';
        
        // Fallback to form_category if form_type is empty
        if (!category || category.trim() === '') {
            category = form.form_category || 'Other Forms';
        }
        
        // Extract lot_no from form_data
        let lotNo = extractLotNoFromFormData(form.form_data);
        
        if (!categories[category]) {
            categories[category] = {};
        }
        
        if (!categories[category][lotNo]) {
            categories[category][lotNo] = [];
        }
        
        categories[category][lotNo].push(form);
    });
    
    return categories;
}

/**
 * Extract lot number from form data JSON
 * @param {string|Object} formDataStr - Form data as string or object
 * @returns {string} Lot number or default
 */
function extractLotNoFromFormData(formDataStr) {
    try {
        let formData;
        if (typeof formDataStr === 'string') {
            formData = JSON.parse(formDataStr);
        } else {
            formData = formDataStr || {};
        }
        
        // Try different possible locations for lot_no in the JSON structure
        let lotNo = null;
        
        // Check tanah.lot_no (A1, B1, C1 forms)
        if (formData.tanah && formData.tanah.lot_no) {
            lotNo = formData.tanah.lot_no;
        }
        // Check direct lot_no field (LS16 form)
        else if (formData.lot_no) {
            lotNo = formData.lot_no;
        }
        // Check lot field (LS16 form)
        else if (formData.lot) {
            lotNo = formData.lot;
        }
        
        // Return formatted lot number or default
        return lotNo && lotNo.trim() !== '' ? lotNo.trim() : 'No Lot Number';
        
    } catch (e) {
        console.warn('Error extracting lot_no from form data:', e);
        return 'No Lot Number';
    }
}

/**
 * Build categorized forms HTML structure
 * @param {Object} categorizedForms - Categorized forms object
 * @returns {string} HTML string
 */
function buildCategorizedFormsHTML(categorizedForms) {
    let html = `
        <div class="forms-tree">
            <div class="forms-summary-header">
                <h4>📄 Forms Overview</h4>
                <p>All forms organized by category and lot</p>
            </div>
            <div class="parent-attachments-container" id="parentAttachmentsContainer">
                <!-- Parent attachments will be loaded here -->
            </div>
    `;
    
    // Sort categories for consistent display
    const sortedCategories = Object.keys(categorizedForms).sort();
    
    sortedCategories.forEach(category => {
        const lotGroups = categorizedForms[category];
        const icon = getCategoryIcon(category);
        
        // Count total forms in this category
        const totalForms = Object.values(lotGroups).reduce((sum, forms) => sum + forms.length, 0);
        
        html += `
            <div class="form-category">
                <div class="category-header">
                    <div class="category-title">
                        <span class="category-icon">${icon}</span>
                        <div class="category-info">
                            <h5>${category}</h5>
                            <span class="category-count">${totalForms} Form${totalForms !== 1 ? 's' : ''}</span>
                        </div>
                    </div>
                </div>
                <div class="category-lots">
        `;
        
        // Sort lot numbers for consistent display
        const sortedLots = Object.keys(lotGroups).sort((a, b) => {
            // Put "No Lot Number" at the end
            if (a === 'No Lot Number') return 1;
            if (b === 'No Lot Number') return -1;
            return a.localeCompare(b, undefined, { numeric: true });
        });
        
        sortedLots.forEach(lotNo => {
            const forms = lotGroups[lotNo];
            
            html += `
                <div class="lot-group">
                    <div class="lot-header" onclick="toggleLotForms('${category}', '${lotNo}')" style="cursor: pointer;">
                        <span class="lot-icon">📍</span>
                        <span class="lot-title">Lot: ${lotNo}</span>
                        <span class="lot-count">${forms.length} Form${forms.length !== 1 ? 's' : ''}</span>
                        <span class="lot-toggle-icon" id="toggleIcon_${category}_${lotNo}">▼</span>
                    </div>
                    <div class="lot-forms" id="lotForms_${category}_${lotNo}" style="display: none;">
            `;
            
            forms.forEach(form => {
                // Extract form identifier for display
                let formIdentifier = 'UNKNOWN';
                try {
                    let formData;
                    if (typeof form.form_data === 'string') {
                        formData = JSON.parse(form.form_data);
                    } else {
                        formData = form.form_data || {};
                    }
                    formIdentifier = formData.form_id || form.form_type || 'UNKNOWN';
                } catch (e) {
                    formIdentifier = form.form_type || 'UNKNOWN';
                }
                
                html += `
                    <div class="form-item" style="cursor:pointer" onclick="openFormView('${form.form_id}')">
                        <span class="form-icon">📋</span>
                        <div class="form-info">
                            <span class="form-title">${form.form_title}</span>
                            <span class="form-type">${formIdentifier}</span>
                        </div>
                        <span class="form-date">${form.created_at_formatted || form.created_at || ''}</span>
                    </div>
                `;
            });
            
            // Add child attachments section for this lot
            html += `
                        <div class="lot-attachments" id="lotAttachments_${category}_${lotNo}">
                            <!-- Child attachments will be loaded here -->
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    return html;
}

/**
 * Get category icon based on category name
 * @param {string} category - Category name
 * @returns {string} Icon emoji
 */
function getCategoryIcon(category) {
    const categoryIcons = {
        'Hak Adat Bumiputera': '🏛️',
        'Tanah BerhakMilik': '🏡',
        'Native Customary Land': '🌿',
        'Registered State Land': '📋',
        'Other Forms': '📄'
    };
    
    return categoryIcons[category] || '📄';
}

/**
 * Toggle lot forms visibility
 * @param {string} category - Category name
 * @param {string} lotNo - Lot number
 */
function toggleLotForms(category, lotNo) {
    const formsContainer = document.getElementById(`lotForms_${category}_${lotNo}`);
    const toggleIcon = document.getElementById(`toggleIcon_${category}_${lotNo}`);
    
    if (!formsContainer || !toggleIcon) {
        console.error('Lot forms container or toggle icon not found');
        return;
    }
    
    if (formsContainer.style.display === 'none' || formsContainer.style.display === '') {
        // Show forms
        formsContainer.style.display = 'block';
        toggleIcon.textContent = '▲';
        toggleIcon.classList.add('expanded');
    } else {
        // Hide forms
        formsContainer.style.display = 'none';
        toggleIcon.textContent = '▼';
        toggleIcon.classList.remove('expanded');
    }
}

/**
 * Open form view in new tab
 * @param {string} formId - Form ID to view
 */
function openFormView(formId) {
    if (!formId) return;
    
    // Determine the correct path based on context
    const path = isReportPage ? `../pages/view_form.php?form_id=${encodeURIComponent(formId)}` 
                               : `pages/view_form.php?form_id=${encodeURIComponent(formId)}`;
    
    window.open(path, '_blank');
}

// Export functions to global scope
window.viewJobDetails = viewJobDetails;
window.closeJobDetailsModal = closeJobDetailsModal;
window.openAttachment = openAttachment;
window.openSjFile = openSjFile;
window.downloadAttachment = downloadAttachment;
window.setJobDetailsModalContext = setJobDetailsModalContext;
window.loadJobForms = loadJobForms;
window.loadParentAttachments = loadParentAttachments;
window.loadChildAttachments = loadChildAttachments;
window.groupFormsByCategory = groupFormsByCategory;
window.extractLotNoFromFormData = extractLotNoFromFormData;
window.buildCategorizedFormsHTML = buildCategorizedFormsHTML;
window.getCategoryIcon = getCategoryIcon;
window.toggleLotForms = toggleLotForms;
window.openFormView = openFormView;

console.log('Job Details Modal module loaded');

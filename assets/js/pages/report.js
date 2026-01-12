// Report page JavaScript functionality

// Global functions for report tab switching
window.showReportTab = function(tabId, button) {
    console.log('Switching to tab:', tabId);
    
    // Hide all report sections
    const sections = document.querySelectorAll('.report-section');
    sections.forEach(section => section.classList.remove('active'));
    
    // Remove active class from all tabs
    const tabs = document.querySelectorAll('.tab-button');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    // Show selected section and activate tab
    const targetSection = document.getElementById(tabId);
    if (targetSection) {
        targetSection.classList.add('active');
        button.classList.add('active');
        console.log('Successfully activated tab:', tabId);
    } else {
        console.error('Target section not found:', tabId);
        alert('Error: Report section not found. Please refresh the page.');
    }
};

window.applyTargetProjectFilter = function() {
    const targetProject = document.getElementById('targetProjectFilter').value;
    
    const tables = document.querySelectorAll('.report-table tbody');
    tables.forEach(tbody => {
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(row => {
            if (row.classList.contains('no-data-cell') || row.classList.contains('summary-row')) {
                return; // Skip special rows
            }
            
            const rowTargetProject = row.getAttribute('data-target-project') || '';
            
            if (!targetProject || rowTargetProject === targetProject) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
};

window.resetTargetProjectFilter = function() {
    document.getElementById('targetProjectFilter').value = '';
    
    const tables = document.querySelectorAll('.report-table tbody');
    tables.forEach(tbody => {
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(row => {
            row.style.display = '';
        });
    });
};

window.exportReport = function(format) {
    const activeTab = document.querySelector('.tab-button.active');
    const tabText = activeTab ? activeTab.textContent.trim() : 'Report';
    
    if (format === 'pdf') {
        window.print();
    } else if (format === 'excel') {
        // Get the export button to show loading state
        const exportButton = event.target;
        const originalText = exportButton.textContent;
        
        // Show loading state
        exportButton.textContent = '⏳ Exporting...';
        exportButton.disabled = true;
        
        // Use setTimeout to allow UI to update before starting export
        setTimeout(() => {
            try {
                exportToExcel(tabText);
            } finally {
                // Restore button state
                exportButton.textContent = originalText;
                exportButton.disabled = false;
            }
        }, 100);
    }
};

// Function to export current report tab to Excel
function exportToExcel(tabName) {
    try {
        // Check if SheetJS library is available
        if (typeof XLSX === 'undefined') {
            // Load SheetJS library dynamically
            loadSheetJSAndExport(tabName);
            return;
        }
        
        // Get the active report section
        const activeSection = document.querySelector('.report-section.active');
        if (!activeSection) {
            alert('No active report section found');
            return;
        }
        
        // Get all tables in the active section
        const tables = activeSection.querySelectorAll('.report-table');
        if (tables.length === 0) {
            alert('No tables found in the current report section');
            return;
        }
        
        // Create a new workbook
        const workbook = XLSX.utils.book_new();
        let hasData = false;
        
        // Process each table
        tables.forEach((table, index) => {
            const tableData = extractTableData(table);
            if (tableData.length > 0) {
                hasData = true;
                
                // Create worksheet from table data
                const worksheet = XLSX.utils.aoa_to_sheet(tableData);
                
                // Auto-fit column widths
                const colWidths = calculateColumnWidths(tableData);
                worksheet['!cols'] = colWidths;
                
                // Determine sheet name
                let sheetName = tabName;
                if (tables.length > 1) {
                    sheetName = `${tabName}_Table_${index + 1}`;
                }
                
                // Ensure sheet name is valid (max 31 chars, no special chars)
                sheetName = sanitizeSheetName(sheetName);
                
                // Add worksheet to workbook
                XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);
            }
        });
        
        // Check if we have any data to export
        if (!hasData) {
            alert('No data available to export. Please check your filters or ensure the report contains data.');
            return;
        }
        
        // Generate filename with current date
        const now = new Date();
        const dateStr = now.toISOString().split('T')[0]; // YYYY-MM-DD format
        const timeStr = now.toTimeString().split(' ')[0].replace(/:/g, '-'); // HH-MM-SS format
        const filename = `${sanitizeFilename(tabName)}_${dateStr}_${timeStr}.xlsx`;
        
        // Export the workbook
        XLSX.writeFile(workbook, filename);
        
        // Show success message
        showExportSuccess(filename, tabName);
        
        console.log(`Successfully exported ${tabName} to ${filename}`);
        
    } catch (error) {
        console.error('Error exporting to Excel:', error);
        alert('Error exporting to Excel: ' + error.message);
    }
}

// Function to dynamically load SheetJS library
function loadSheetJSAndExport(tabName) {
    // Get the export button to maintain loading state
    const exportButton = document.querySelector('.btn-export[onclick*="excel"]');
    
    // Show loading message
    const loadingMsg = document.createElement('div');
    loadingMsg.id = 'excel-loading';
    loadingMsg.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        z-index: 10000;
        text-align: center;
        border: 2px solid #3b82f6;
    `;
    loadingMsg.innerHTML = `
        <div style="font-size: 16px; margin-bottom: 10px;">📊 Loading Excel Export Library...</div>
        <div style="font-size: 12px; color: #666;">First time setup - please wait...</div>
        <div style="margin-top: 15px;">
            <div style="width: 200px; height: 4px; background: #e5e7eb; border-radius: 2px; overflow: hidden;">
                <div style="height: 100%; background: #3b82f6; width: 0%; animation: loadProgress 3s linear infinite;"></div>
            </div>
        </div>
        <style>
            @keyframes loadProgress {
                0% { width: 0%; }
                50% { width: 70%; }
                100% { width: 100%; }
            }
        </style>
    `;
    document.body.appendChild(loadingMsg);
    
    // Load SheetJS from CDN
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
    script.onload = function() {
        // Remove loading message
        if (document.body.contains(loadingMsg)) {
            document.body.removeChild(loadingMsg);
        }
        
        // Try export again
        exportToExcel(tabName);
        
        // Restore button if still loading
        if (exportButton && exportButton.disabled) {
            exportButton.textContent = '📊 Export Excel';
            exportButton.disabled = false;
        }
    };
    script.onerror = function() {
        // Remove loading message
        if (document.body.contains(loadingMsg)) {
            document.body.removeChild(loadingMsg);
        }
        
        alert('Failed to load Excel export library. Please check your internet connection and try again.');
        
        // Restore button
        if (exportButton && exportButton.disabled) {
            exportButton.textContent = '📊 Export Excel';
            exportButton.disabled = false;
        }
    };
    document.head.appendChild(script);
}

// Function to extract data from a table
function extractTableData(table) {
    const data = [];
    
    // Get all rows
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        // Skip hidden rows (filtered out by target project filter)
        if (row.style.display === 'none') {
            return;
        }
        
        const rowData = [];
        const cells = row.querySelectorAll('th, td');
        
        cells.forEach(cell => {
            // Get text content, removing extra whitespace
            let cellText = cell.textContent.trim();
            
            // Handle special cases - remove status badges HTML and get clean text
            const statusBadge = cell.querySelector('.status-badge, .pbtstatus-badge');
            if (statusBadge) {
                cellText = statusBadge.textContent.trim();
            }
            
            // Handle form type badges
            const formBadges = cell.querySelectorAll('.form-type-badge');
            if (formBadges.length > 0) {
                cellText = Array.from(formBadges).map(badge => badge.textContent.trim()).join(', ');
            }
            
            // Handle user info (name and role)
            const userInfo = cell.querySelector('.user-info');
            if (userInfo) {
                const userName = userInfo.querySelector('.user-name');
                const userRole = userInfo.querySelector('.user-role');
                if (userName && userRole) {
                    cellText = `${userName.textContent.trim()} (${userRole.textContent.trim()})`;
                }
            }
            
            // Handle reference items
            const refItems = cell.querySelectorAll('.ref-item');
            if (refItems.length > 0) {
                cellText = Array.from(refItems).map(item => item.textContent.trim()).join(' | ');
            }
            
            // Handle acquisition dates
            const acqDate = cell.querySelector('.acquisition-date');
            if (acqDate) {
                const mainText = cellText.replace(acqDate.textContent.trim(), '').trim();
                const dateText = acqDate.textContent.trim();
                cellText = `${mainText} (${dateText})`;
            }
            
            // Replace multiple spaces and newlines with single space
            cellText = cellText.replace(/\s+/g, ' ').trim();
            
            // Handle empty cells
            if (!cellText || cellText === 'N/A' || cellText === '-') {
                cellText = '';
            }
            
            rowData.push(cellText);
        });
        
        // Only add rows that have content
        if (rowData.some(cell => cell.length > 0)) {
            data.push(rowData);
        }
    });
    
    return data;
}

// Function to calculate column widths for better formatting
function calculateColumnWidths(data) {
    if (data.length === 0) return [];
    
    const maxCols = Math.max(...data.map(row => row.length));
    const colWidths = [];
    
    for (let col = 0; col < maxCols; col++) {
        let maxWidth = 10; // Minimum width
        
        data.forEach(row => {
            if (row[col]) {
                const cellLength = row[col].toString().length;
                maxWidth = Math.max(maxWidth, Math.min(cellLength * 1.2, 50)); // Max width of 50
            }
        });
        
        colWidths.push({ wch: maxWidth });
    }
    
    return colWidths;
}

// Function to sanitize sheet names for Excel compatibility
function sanitizeSheetName(name) {
    // Remove invalid characters and limit length
    let sanitized = name.replace(/[\\\/\?\*\[\]]/g, '').substring(0, 31);
    
    // Ensure it's not empty
    if (!sanitized) {
        sanitized = 'Report';
    }
    
    return sanitized;
}

// Function to sanitize filename
function sanitizeFilename(name) {
    // Remove invalid filename characters
    return name.replace(/[<>:"\/\\|?*]/g, '').replace(/\s+/g, '_');
}

// Function to show export success message
function showExportSuccess(filename, tabName) {
    // Create success notification
    const successMsg = document.createElement('div');
    successMsg.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
        z-index: 10001;
        font-family: Arial, sans-serif;
        font-size: 14px;
        max-width: 300px;
        animation: slideInRight 0.3s ease-out;
    `;
    successMsg.innerHTML = `
        <div style="font-weight: bold; margin-bottom: 5px;">✅ Export Successful!</div>
        <div style="font-size: 12px; opacity: 0.9;">
            ${tabName} exported as<br>
            <strong>${filename}</strong>
        </div>
    `;
    
    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    
    document.body.appendChild(successMsg);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        successMsg.style.animation = 'slideOutRight 0.3s ease-in forwards';
        setTimeout(() => {
            if (document.body.contains(successMsg)) {
                document.body.removeChild(successMsg);
            }
            document.head.removeChild(style);
        }, 300);
    }, 4000);
}

window.printReport = function() {
    window.print();
};

// Fullscreen functionality - for tables only
window.toggleFullscreen = function() {
    const activeSection = document.querySelector('.report-section.active');
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    
    if (!activeSection || !fullscreenBtn) {
        console.error('Active section or fullscreen button not found');
        return;
    }
    
    // Find the table in the active section
    const tableContainer = activeSection.querySelector('.table-responsive');
    if (!tableContainer) {
        alert('No table found in the current report section');
        return;
    }
    
    if (tableContainer.classList.contains('table-fullscreen')) {
        // Exit fullscreen
        tableContainer.classList.remove('table-fullscreen');
        fullscreenBtn.innerHTML = '🔳 Table Fullscreen';
        fullscreenBtn.title = 'View Table in Fullscreen';
        fullscreenBtn.classList.remove('fullscreen-exit-btn');
        document.body.style.overflow = 'auto';
        
        // Remove the fullscreen overlay if it exists
        const overlay = document.getElementById('table-fullscreen-overlay');
        if (overlay) {
            overlay.remove();
        }
    } else {
        // Enter fullscreen
        createTableFullscreenOverlay(tableContainer, activeSection);
        fullscreenBtn.innerHTML = '❌ Exit Fullscreen';
        fullscreenBtn.title = 'Exit Table Fullscreen';
        fullscreenBtn.classList.add('fullscreen-exit-btn');
        document.body.style.overflow = 'hidden';
    }
};

// Create fullscreen overlay for table
function createTableFullscreenOverlay(tableContainer, activeSection) {
    // Create overlay
    const overlay = document.createElement('div');
    overlay.id = 'table-fullscreen-overlay';
    overlay.className = 'table-fullscreen-overlay';
    
    // Create header with title and close button
    const header = document.createElement('div');
    header.className = 'table-fullscreen-header';
    
    const title = document.createElement('h3');
    const sectionTitle = activeSection.querySelector('.report-header h3');
    title.textContent = sectionTitle ? sectionTitle.textContent : 'Report Table';
    title.className = 'table-fullscreen-title';
    
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '❌ Exit Fullscreen';
    closeBtn.className = 'table-fullscreen-close';
    closeBtn.onclick = window.toggleFullscreen;
    
    header.appendChild(title);
    header.appendChild(closeBtn);
    
    // Clone the table container
    const clonedTable = tableContainer.cloneNode(true);
    clonedTable.classList.add('table-fullscreen-content');
    
    // Make sure job links work in cloned table
    const jobLinks = clonedTable.querySelectorAll('.job-link');
    jobLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const onclick = this.getAttribute('onclick');
            if (onclick) {
                eval(onclick);
            }
        });
    });
    
    // Assemble overlay
    overlay.appendChild(header);
    overlay.appendChild(clonedTable);
    
    // Add to body
    document.body.appendChild(overlay);
    
    // Mark original table as fullscreen
    tableContainer.classList.add('table-fullscreen');
}

// Handle ESC key to exit fullscreen
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const overlay = document.getElementById('table-fullscreen-overlay');
        if (overlay) {
            window.toggleFullscreen();
        }
    }
});

// Initialize report page
function initializeReportPage() {
    console.log('Report page initialized');
    
    // Initialize tab functionality
    const tabButtons = document.querySelectorAll('.tab-button');
    console.log('Found tab buttons:', tabButtons.length);
    
    tabButtons.forEach((button, index) => {
        console.log(`Tab button ${index}:`, button.onclick);
        
        // Ensure onclick handler is properly set
        if (!button.onclick) {
            let tabId = '';
            
            if (button.textContent.includes('Completed')) {
                tabId = 'completed-jobs';
            } else if (button.textContent.includes('Query Form')) {
                tabId = 'query-form';
            } else if (button.textContent.includes('Project Summary')) {
                tabId = 'project-summary';
            } else if (button.textContent.includes('Query Summary')) {
                tabId = 'query-summary';
            }
            
            if (tabId) {
                button.onclick = function() {
                    showReportTab(tabId, this);
                };
            }
        }
    });
    
    // Verify report sections exist
    const reportSections = document.querySelectorAll('.report-section');
    console.log('Found report sections:', reportSections.length);
    
    reportSections.forEach((section, index) => {
        console.log(`Report section ${index}:`, section.id);
    });
    
    // Initialize target project filter
    const targetProjectFilter = document.getElementById('targetProjectFilter');
    
    if (targetProjectFilter) {
        console.log('Target project filter found');
        // Filter is ready to use - no default setup needed
    }
    
    // Initialize chart placeholder
    const canvas = document.getElementById('monthlyChart');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#f3f4f6';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        ctx.fillStyle = '#6b7280';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('Chart functionality requires Chart.js library', canvas.width/2, canvas.height/2);
        ctx.fillText('Feature coming soon!', canvas.width/2, canvas.height/2 + 20);
    }
    
    // Force activation of the first tab if none are active
    const activeTab = document.querySelector('.tab-button.active');
    if (!activeTab) {
        const firstTab = document.querySelector('.tab-button');
        if (firstTab) {
            firstTab.click();
        }
    }
}

// Modal functions for job details
window.viewJobDetails = function(jobId) {
    console.log(`Viewing job details for ID: ${jobId}`);
    
    const modal = document.getElementById('jobDetailsModal');
    const content = document.getElementById('jobDetailsContent');
    
    if (!modal || !content) {
        console.error('Modal elements not found');
        return;
    }
    
    // Show loading
    content.innerHTML = `
        <div class="loading-spinner">
            <div>⏳</div>
            <p>Loading job details...</p>
        </div>
    `;
    
    modal.style.display = 'flex';
    
    // Fetch actual job details from API
    fetch(`../api/get_job_details.php?job_id=${jobId}`)
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
            displayJobDetails(data);
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
};

window.closeJobDetailsModal = function() {
    const modal = document.getElementById('jobDetailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
};

// Function to display job details in modal
function displayJobDetails(job) {
    const content = document.getElementById('jobDetailsContent');
    
    const html = `
        <div class="job-details-grid">
            <div class="detail-section">
                <h4>📄 Basic Information</h4>
                <div class="detail-row">
                    <span class="label">SJ Number:</span>
                    <span class="value">${job.surveyjob_no || 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Project Name:</span>
                    <span class="value">${job.projectname || 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="label">HQ Reference:</span>
                    <span class="value">${job.hq_ref || 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Division Reference:</span>
                    <span class="value">${job.div_ref || 'N/A'}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Status:</span>
                    <span class="value"><span class="status-badge ${getStatusClass(job.status)}">${formatStatus(job.status)}</span></span>
                </div>
                <div class="detail-row">
                    <span class="label">PBT Status:</span>
                    <span class="value"><span class="pbtstatus-badge pbtstatus-${job.pbtstatus || 'none'}">${formatStatus(job.pbtstatus || 'none')}</span></span>
                </div>
            </div>
            
            <div class="detail-section">
                <h4>👥 Assignment Information</h4>
                <div class="detail-row">
                    <span class="label">Created By:</span>
                    <span class="value">${job.created_by_name || 'Unknown'} (${job.created_by_role || 'N/A'})</span>
                </div>
                <div class="detail-row">
                    <span class="label">Assigned To:</span>
                    <span class="value">${job.assigned_to_name || 'Unassigned'} ${job.assigned_to_role ? '(' + job.assigned_to_role + ')' : ''}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Created Date:</span>
                    <span class="value">${formatDateTime(job.created_at)}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Last Updated:</span>
                    <span class="value">${formatDateTime(job.updated_at)}</span>
                </div>
            </div>
        </div>
        
        ${job.form_count > 0 ? `
            <div class="forms-section">
                <h4>� Forms (${job.form_count})</h4>
                <div class="forms-summary">
                    <p>Available Form Types: ${job.form_types ? job.form_types.join(', ') : 'N/A'}</p>
                    <button onclick="loadJobForms(${job.survey_job_id})" class="btn-load-forms">
                        � View All Forms
                    </button>
                </div>
                <div id="formsContainer" class="forms-container" style="display: none;">
                    <!-- Forms will be loaded here -->
                </div>
            </div>
        ` : `
            <div class="forms-section">
                <h4>📄 Forms</h4>
                <div class="no-forms">
                    <p>No forms available for this job.</p>
                </div>
            </div>
        `}
        
        ${job.attachment_name && job.attachment_name !== 'no_file_uploaded.pdf' || (job.sj_files && job.sj_files.length > 0) ? `
            <div class="attachment-section">
                <h4>📎 Attachments</h4>
                ${job.attachment_name && job.attachment_name !== 'no_file_uploaded.pdf' ? `
                    <div class="attachment-item">
                        <span>📄 ${job.attachment_name}</span>
                        <button onclick="openAttachment('uploads/jobs/oic/${job.attachment_name}')" class="btn-view">
                             View
                        </button>
                    </div>
                ` : ''}
                ${job.sj_files && job.sj_files.length > 0 ? `
                    <div class="additional-attachments">
                        <h5>📋 Additional Files (${job.sj_files.length})</h5>
                        ${job.sj_files.map(file => `
                            <div class="attachment-item">
                                <div class="attachment-info">
                                    <span class="attachment-name">📄 ${file.description || file.attachment_name}</span>
                                    <span class="attachment-date">📅 ${file.created_at}</span>
                                </div>
                                <button onclick="openSjFile('${file.attachment_name}')" class="btn-view">
                                     View
                                </button>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
            </div>
        ` : ''}
        
        <div class="modal-actions">
            <button onclick="closeJobDetailsModal()" class="btn-secondary">Close</button>
        </div>
    `;
    
    content.innerHTML = html;
}

// Helper functions for modal
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

function formatStatus(status) {
    if (!status) return 'N/A';
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB') + ' ' + date.toLocaleTimeString('en-GB', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('jobDetailsModal');
    if (event.target === modal && typeof window.closeJobDetailsModal === 'function') {
        window.closeJobDetailsModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && typeof window.closeJobDetailsModal === 'function') {
        window.closeJobDetailsModal();
    }
});

// Export for dashboard initialization
window.initializeReportPage = initializeReportPage;

// Load job forms
window.loadJobForms = function(jobId) {
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
        loadButton.textContent = '⏳ Loading...';
        loadButton.disabled = true;
    }
    
    // Fetch forms and job details (for other_attachment)
    Promise.all([
        fetch(`../api/get_job_forms.php?job_id=${jobId}`),
        fetch(`../api/get_job_details.php?job_id=${jobId}`)
    ])
    .then(responses => Promise.all(responses.map(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })))
    .then(([formsData, jobData]) => {
        console.log('Forms data:', formsData);
        console.log('Job data:', jobData);
        
        if (!formsData.success) {
            throw new Error(formsData.error || formsData.message || 'Failed to load forms');
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
            return;
        }
        
        // Parse parent attachments if they exist
        let parentAttachments = [];
        if (jobData.other_attachment) {
            try {
                const otherAttachment = typeof jobData.other_attachment === 'string' 
                    ? JSON.parse(jobData.other_attachment) 
                    : jobData.other_attachment;
                
                if (otherAttachment.parent_attachment) {
                    parentAttachments = otherAttachment.parent_attachment;
                }
            } catch (e) {
                console.warn('Error parsing other_attachment:', e);
            }
        }
        
        // Load parent attachments if they exist
        if (parentAttachments.length > 0) {
            loadParentAttachments(parentAttachments);
        }
        
        // Group forms by category
        const categorizedForms = groupFormsByCategory(forms);
        
        // Build the forms tree HTML
        const formsHTML = buildCategorizedFormsHTML(categorizedForms);
        
        container.innerHTML = formsHTML;
        
        // Load child attachments for each lot
        if (jobData.other_attachment) {
            try {
                const otherAttachment = typeof jobData.other_attachment === 'string' 
                    ? JSON.parse(jobData.other_attachment) 
                    : jobData.other_attachment;
                
                // Load child attachments for each lot
                if (otherAttachment.child_attachment) {
                    loadChildAttachments(otherAttachment.child_attachment);
                }
            } catch (e) {
                console.warn('Error parsing child attachments:', e);
            }
        }
        
    })
    .catch(error => {
        console.error('Error loading forms:', error);
        container.innerHTML = `
            <div class="error-state">
                <h5>❌ Error Loading Forms</h5>
                <p>Unable to load forms: ${error.message}</p>
                <button onclick="loadJobForms(${jobId})" class="btn btn-secondary">🔄 Retry</button>
            </div>
        `;
    })
    .finally(() => {
        if (loadButton) {
            loadButton.textContent = '📂 Load Forms';
            loadButton.disabled = false;
        }
    });
};

// Display forms in categorized tree structure
function displayForms(forms, parentAttachments) {
    const container = document.getElementById('formsContainer');
    
    if (!forms || forms.length === 0) {
        container.innerHTML = `
            <div class="no-forms">
                <p>📭 No forms found for this job</p>
            </div>
        `;
        return;
    }
    
    // Load parent attachments if they exist
    if (parentAttachments && parentAttachments.length > 0) {
        loadParentAttachments(parentAttachments);
    }
    
    // Group forms by category
    const categorizedForms = groupFormsByCategory(forms);
    
    // Build the forms tree HTML
    const formsHTML = buildCategorizedFormsHTML(categorizedForms);
    
    container.innerHTML = `
        <div class="forms-tree">
            <div class="forms-summary-header">
                <h4>📋 Forms Overview</h4>
                <p>Total: ${forms.length} form(s) found</p>
            </div>
            ${formsHTML}
        </div>
    `;
}

// Group forms by category
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

// Extract lot_no from form_data JSON
function extractLotNoFromFormData(formDataStr) {
    try {
        const formData = JSON.parse(formDataStr);
        return formData.lot_no || null;
    } catch (e) {
        return null;
    }
}

// Build categorized forms HTML
function buildCategorizedFormsHTML(categorizedForms) {
    let html = '';
    
    Object.keys(categorizedForms).forEach(category => {
        const categoryIcon = getCategoryIcon(category);
        const lots = categorizedForms[category];
        const totalFormsInCategory = Object.values(lots).reduce((sum, forms) => sum + forms.length, 0);
        
        html += `
            <div class="form-category">
                <div class="category-header">
                    <div class="category-title">
                        <span class="category-icon">${categoryIcon}</span>
                        <div class="category-info">
                            <h5>${category}</h5>
                            <span class="category-count">${totalFormsInCategory} form(s)</span>
                        </div>
                    </div>
                </div>
                <div class="category-lots">
        `;
        
        Object.keys(lots).forEach(lotNo => {
            const forms = lots[lotNo];
            const isExpanded = Object.keys(lots).length === 1; // Auto-expand if only one lot
            
            html += `
                <div class="lot-group">
                    <div class="lot-header" onclick="toggleLot('${category}', '${lotNo}')">
                        <span class="lot-icon">📦</span>
                        <span class="lot-title">Lot ${lotNo}</span>
                        <span class="lot-count">${forms.length} form(s)</span>
                        <span class="lot-toggle-icon ${isExpanded ? 'expanded' : ''}">▼</span>
                    </div>
                    <div class="lot-forms" id="lot-${category}-${lotNo}" style="display: ${isExpanded ? 'block' : 'none'}">
            `;
            
            forms.forEach(form => {
                const formDate = form.created_at ? new Date(form.created_at).toLocaleDateString('en-GB') : 'N/A';
                html += `
                    <div class="form-item" onclick="openFormView(${form.id})">
                        <span class="form-icon">📄</span>
                        <div class="form-info">
                            <div class="form-title">Form #${form.id}</div>
                            <div class="form-type">${form.form_type || 'N/A'}</div>
                            <div class="form-date">${formDate}</div>
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    });
    
    return html;
}

// Get category icon
function getCategoryIcon(category) {
    const categoryIcons = {
        // Form types (A1, B1, C1, etc.)
        'A1': '📄',
        'B1': '📋',
        'C1': '📝',
        'LS16': '📊',
        // Category descriptions
        'Hak Adat Bumiputera': '�️',
        'Tanah BerhakMilik': '🏡',
        'Native Customary Land': '🌿',
        'Registered State Land': '📋',
        'Other Forms': '📄'
    };
    
    return categoryIcons[category] || '📄';
}

// Toggle lot forms visibility
window.toggleLot = function(category, lotNo) {
    const lotForms = document.getElementById(`lot-${category}-${lotNo}`);
    const toggleIcon = event.currentTarget.querySelector('.lot-toggle-icon');
    
    if (lotForms.style.display === 'none') {
        lotForms.style.display = 'block';
        toggleIcon.classList.add('expanded');
    } else {
        lotForms.style.display = 'none';
        toggleIcon.classList.remove('expanded');
    }
};

// Load job attachments
window.loadJobAttachments = function(jobId) {
    console.log('Loading attachments for job', jobId);
    
    const container = document.getElementById('attachmentsContainer');
    
    if (!container) {
        console.error('Attachments container not found');
        return;
    }
    
    // Show loading
    container.innerHTML = `
        <div class="loading-spinner">
            <div>⏳</div>
            <p>Loading attachments...</p>
        </div>
    `;
    
    // This function can be expanded later to load additional attachments
    // For now, it's just a placeholder to match the job-list.js interface
    setTimeout(() => {
        container.innerHTML = `
            <div class="no-attachments">
                <p>📎 No additional attachments found</p>
                <small>General attachments are shown in the forms section above</small>
            </div>
        `;
    }, 1000);
};

// Display attachments
function displayAttachments(attachments) {
    const container = document.getElementById('attachmentsContainer');
    
    if (!attachments || attachments.length === 0) {
        container.innerHTML = `
            <div class="no-attachments">
                <p>📭 No additional attachments found</p>
            </div>
        `;
        return;
    }
    
    let html = `
        <h5>📎 Additional Attachments (${attachments.length})</h5>
        <div class="attachment-section">
    `;
    
    attachments.forEach(attachment => {
        const attachmentDate = attachment.upload_date ? 
            new Date(attachment.upload_date).toLocaleDateString('en-GB') : 'N/A';
        
        html += `
            <div class="attachment-item" onclick="openSjFile('${attachment.filename}')">
                <span class="attachment-icon">📎</span>
                <div class="attachment-info">
                    <div class="attachment-name">${attachment.original_name || attachment.filename}</div>
                    <div class="attachment-date">${attachmentDate}</div>
                </div>
            </div>
        `;
    });
    
    html += `
        </div>
    `;
    
    container.innerHTML = html;
}

// Load parent attachments
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
                                Open
                            </button>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `;
}

// Load child attachments for each lot
function loadChildAttachments(childAttachments) {
    childAttachments.forEach(lotAttachment => {
        const lotNo = lotAttachment.lot_no;
        const lotContainer = document.querySelector(`[data-lot="${lotNo}"] .lot-attachments`);
        
        if (lotContainer && lotAttachment.attachments) {
            lotContainer.innerHTML = lotAttachment.attachments.map(attachment => {
                const fileName = attachment.path.split('/').pop() || 'attachment.pdf';
                return `
                    <div class="lot-attachment-item">
                        <span class="lot-attachment-icon">📄</span>
                        <span class="lot-attachment-name">${fileName}</span>
                        <button onclick="openAttachment('${attachment.path}')" class="btn-open-lot-attachment" title="Open Attachment">
                            Open
                        </button>
                    </div>
                `;
            }).join('');
        }
    });
}

// Open attachment
function openAttachment(path) {
    console.log(`Opening attachment: ${path}`);
    
    // Remove leading slash if present
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    
    // Open in new tab
    window.open(cleanPath, '_blank');
}

// Open sj_files attachment
function openSjFile(filename) {
    console.log(`Opening sj_file: ${filename}`);
    
    // sj_files are stored in uploads/pp_files/ directory
    const path = `uploads/pp_files/${filename}`;
    
    // Open in new tab
    window.open(path, '_blank');
}

// Group forms by category
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

// Extract lot_no from form_data JSON
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

// Build categorized forms HTML
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
        const categoryIcon = getCategoryIcon(category);
        const lots = categorizedForms[category];
        const totalFormsInCategory = Object.values(lots).reduce((sum, forms) => sum + forms.length, 0);
        
        html += `
            <div class="form-category">
                <div class="category-header">
                    <div class="category-title">
                        <span class="category-icon">${categoryIcon}</span>
                        <div class="category-info">
                            <h5>${category}</h5>
                            <span class="category-count">${totalFormsInCategory} form(s)</span>
                        </div>
                    </div>
                </div>
                <div class="category-lots">
        `;
        
        // Sort lot numbers for consistent display
        const sortedLots = Object.keys(lots).sort();
        
        sortedLots.forEach(lotNo => {
            const lotForms = lots[lotNo];
            const lotId = `${category}_${lotNo}`.replace(/[^a-zA-Z0-9]/g, '_');
            
            html += `
                <div class="lot-group" data-lot="${lotNo}">
                    <div class="lot-header" onclick="toggleLotForms('${category}', '${lotNo}')">
                        <span class="lot-icon">📍</span>
                        <span class="lot-title">Lot ${lotNo}</span>
                        <span class="lot-count">${lotForms.length} form(s)</span>
                        <span class="lot-toggle-icon" id="toggleIcon_${lotId}">▼</span>
                    </div>
                    <div class="lot-forms" id="lotForms_${lotId}" style="display: none;">
            `;
            
            lotForms.forEach(form => {
                html += `
                    <div class="form-item" onclick="openFormView('${form.form_id}')">
                        <span class="form-icon">📄</span>
                        <div class="form-info">
                            <div class="form-title">${form.form_title || 'Untitled Form'}</div>
                            <div class="form-type">Type: ${form.form_type || 'Unknown'}</div>
                            <div class="form-date">Created: ${form.created_at_formatted || 'Not Set'}</div>
                        </div>
                    </div>
                `;
            });
            
            html += `
                        <div class="lot-attachments">
                            <!-- Lot-specific attachments will be loaded here -->
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

// Get category icon
function getCategoryIcon(category) {
    const categoryIcons = {
        // Form types (A1, B1, C1, etc.)
        'A1': '📄',
        'B1': '📋',
        'C1': '📝',
        'LS16': '📊',
        // Category descriptions
        'Hak Adat Bumiputera': '🏛️',
        'Tanah BerhakMilik': '🏡',
        'Native Customary Land': '🌿',
        'Registered State Land': '📋',
        'Other Forms': '📄'
    };
    
    return categoryIcons[category] || '📄';
}

// Toggle lot forms visibility
function toggleLotForms(category, lotNo) {
    const lotId = `${category}_${lotNo}`.replace(/[^a-zA-Z0-9]/g, '_');
    const formsContainer = document.getElementById(`lotForms_${lotId}`);
    const toggleIcon = document.getElementById(`toggleIcon_${lotId}`);
    
    if (!formsContainer || !toggleIcon) {
        console.warn(`Could not find elements for lot ${lotId}`);
        return;
    }
    
    if (formsContainer.style.display === 'none' || formsContainer.style.display === '') {
        formsContainer.style.display = 'block';
        toggleIcon.textContent = '▲';
        toggleIcon.classList.add('expanded');
    } else {
        formsContainer.style.display = 'none';
        toggleIcon.textContent = '▼';
        toggleIcon.classList.remove('expanded');
    }
}

// Close job details modal
function closeJobDetailsModal() {
    const modal = document.getElementById('jobDetailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Open form view in new tab
function openFormView(formId) {
    if (!formId) return;
    window.open(`../pages/view_form.php?form_id=${encodeURIComponent(formId)}`, '_blank');
}

// Make functions globally available
// Note: viewJobDetails is already assigned to window at line 169
// Note: closeJobDetailsModal is already assigned to window at line 218
// Note: loadJobAttachments is already assigned to window at line 577
window.loadJobForms = loadJobForms;
window.loadParentAttachments = loadParentAttachments;
window.loadChildAttachments = loadChildAttachments;
window.openAttachment = openAttachment;
window.openSjFile = openSjFile;
window.groupFormsByCategory = groupFormsByCategory;
window.extractLotNoFromFormData = extractLotNoFromFormData;
window.buildCategorizedFormsHTML = buildCategorizedFormsHTML;
window.getCategoryIcon = getCategoryIcon;
window.toggleLotForms = toggleLotForms;
window.openFormView = openFormView;

// Make closeJobDetailsModal available globally without window prefix for consistency with job list
window.closeJobDetailsModal = closeJobDetailsModal;

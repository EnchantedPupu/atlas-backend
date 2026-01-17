// Report page JavaScript functionality

// Set job details modal context for report page (uses ../api/ path)
if (typeof setJobDetailsModalContext === 'function') {
    setJobDetailsModalContext(true);
}

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

// Note: Job Details modal functions (viewJobDetails, closeJobDetailsModal, openAttachment, openSjFile, downloadAttachment) 
// are now in the centralized job-details-modal.js file

// Note: All form loading functions (loadJobForms, loadParentAttachments, loadChildAttachments, groupFormsByCategory,
// extractLotNoFromFormData, buildCategorizedFormsHTML, getCategoryIcon, toggleLotForms, openFormView, etc.)
// are now in the centralized job-details-modal.js file and work for all pages including report page

// Export for dashboard initialization
window.initializeReportPage = initializeReportPage;

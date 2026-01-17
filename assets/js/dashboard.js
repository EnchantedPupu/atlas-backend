// Main Dashboard JavaScript - Core functionality and shared utilities

// Global variables
let currentPage = 'dashboard';
let isSidebarCollapsed = false;

// Shared utility functions
function showLoading() {
    const content = document.getElementById('main-content');
    if (content) {
        content.innerHTML = `
            <div class="loading-container">
                <div class="loading-spinner">⏳</div>
                <p>Loading...</p>
            </div>
        `;
    }
}

function showError(message) {
    const content = document.getElementById('main-content');
    if (content) {
        content.innerHTML = `
            <div class="error-container">
                <div class="error-icon">❌</div>
                <h3>Error</h3>
                <p>${message}</p>
                <button onclick="showDashboard()" class="btn-primary">Back to Dashboard</button>
            </div>
        `;
    }
}

function showComingSoon(featureName, event) {
    if (event) {
        event.preventDefault();
        updateActiveNavItem(event.target.closest('.nav-item'));
    }
    
    const content = document.getElementById('main-content');
    if (content) {
        content.innerHTML = `
            <div class="coming-soon-container">
                <div class="coming-soon-icon">🚧</div>
                <h2>${featureName} - Coming Soon</h2>
                <p>This feature is currently under development and will be available in a future update.</p>
                <div class="coming-soon-features">
                    <h4>What to expect:</h4>
                    <ul>
                        <li>Enhanced user experience</li>
                        <li>Comprehensive functionality</li>
                        <li>Mobile-responsive design</li>
                        <li>Real-time updates</li>
                    </ul>
                </div>
                <button onclick="showDashboard()" class="btn-primary">Back to Dashboard</button>
            </div>
        `;
    }
}

// Navigation functions
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const contentArea = document.getElementById('contentArea');
    const toggleIcon = document.getElementById('toggle-icon');
    
    isSidebarCollapsed = !isSidebarCollapsed;
    
    if (isSidebarCollapsed) {
        sidebar.classList.add('collapsed');
        contentArea.classList.add('expanded');
        toggleIcon.textContent = '►';
    } else {
        sidebar.classList.remove('collapsed');
        contentArea.classList.remove('expanded');
        toggleIcon.textContent = '◄';
    }
}

function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const contentArea = document.getElementById('contentArea');
    sidebar.classList.remove('collapsed');
    contentArea.classList.remove('expanded');
    isSidebarCollapsed = false;
    document.getElementById('toggle-icon').textContent = '◄';
}

function openMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    sidebar.classList.add('mobile-open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

function updateActiveNavItem(activeItem) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    if (activeItem) {
        activeItem.classList.add('active');
    }
}

function updateBreadcrumbs(pageTitle = null, pageIcon = '🏠') {
    const breadcrumbs = document.getElementById('breadcrumbs');
    if (!breadcrumbs) return;
    
    if (!pageTitle) {
        // Dashboard view - just show Dashboard
        breadcrumbs.innerHTML = `
            <div class="breadcrumb-item">
                <span class="breadcrumb-icon">🏠</span>
                <span class="active">Dashboard</span>
            </div>
        `;
    } else {
        // Sub-page view - show Dashboard > Page
        breadcrumbs.innerHTML = `
            <div class="breadcrumb-item">
                <span class="breadcrumb-icon">🏠</span>
                <a href="#" onclick="showDashboard(); return false;">Dashboard</a>
            </div>
            <span class="breadcrumb-separator">›</span>
            <div class="breadcrumb-item">
                <span class="breadcrumb-icon">${pageIcon}</span>
                <span class="active">${pageTitle}</span>
            </div>
        `;
    }
}

function showDashboard(event = null) {
    if (event) {
        event.preventDefault();
    }
    
    // Always update the dashboard nav item to active
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        const onclickAttr = item.getAttribute('onclick');
        if (onclickAttr && onclickAttr.includes('showDashboard')) {
            updateActiveNavItem(item);
        }
    });
    
    currentPage = 'dashboard';
    
    // Update breadcrumbs to show only Dashboard
    updateBreadcrumbs();
    
    const content = document.getElementById('main-content');
    if (content) {
        content.innerHTML = `
            <div class="welcome-card">
                <h2>Welcome, ${window.userName}!</h2>
                <p>You are logged in as a <strong>${window.userRoleLabel}</strong></p>
                <span class="role-badge">${window.userRole}</span>
            </div>
            
            <div class="features-grid">
                ${Object.entries(window.roleMenus).map(([key, menu]) => `
                    <div class="feature-card" id="feature-card-${key}">
                        ${key === 'job_list' ? `
                            <div class="new-task-counter" id="newTaskCounter" style="display: none;">
                                <span id="newTaskCount">0</span>
                            </div>
                        ` : ''}
                        <h3>${menu.title}</h3>
                        <p>Access your ${menu.title.toLowerCase()} functionality.</p>
                        <button class="btn-feature" onclick="loadPage('${menu.file}', '${menu.title}')">
                            ${menu.icon} Open ${menu.title}
                        </button>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    // Fetch and display new task count (always refresh when showing dashboard)
    setTimeout(() => {
        fetchNewTaskCount();
    }, 100);
    
    closeMobileSidebar();
}

// Dynamic page loading function
async function loadPage(filename, pageTitle, event = null) {
    if (event) {
        event.preventDefault();
        updateActiveNavItem(event.target.closest('.nav-item'));
    } else {
        // Find and activate the nav item that corresponds to this page
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            const onclickAttr = item.getAttribute('onclick');
            if (onclickAttr && onclickAttr.includes(filename)) {
                updateActiveNavItem(item);
            }
        });
    }
    
    currentPage = filename.replace('.php', '');
    
    // Get icon from roleMenus if available
    let pageIcon = '📄';
    if (window.roleMenus) {
        for (const [key, menu] of Object.entries(window.roleMenus)) {
            if (menu.file === filename) {
                pageIcon = menu.icon;
                break;
            }
        }
    }
    
    // Update breadcrumbs
    updateBreadcrumbs(pageTitle, pageIcon);
    
    showLoading();
    
    try {
        const response = await fetch(`pages/${filename}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const html = await response.text();
        const content = document.getElementById('main-content');
        if (content) {
            content.innerHTML = html;
        }
        
        // Load page-specific JavaScript
        await loadPageScript(currentPage);
        
        closeMobileSidebar();
        
    } catch (error) {
        console.error('Error loading page:', error);
        showError(`Failed to load ${pageTitle}. Please try again.`);
    }
}

// Load page-specific JavaScript files
async function loadPageScript(pageName) {
    const scriptId = `${pageName}-script`;
    
    // Remove existing script if present
    const existingScript = document.getElementById(scriptId);
    if (existingScript) {
        existingScript.remove();
    }
    
    // Script mapping
    const scriptMap = {
        'create_job': 'create-job.js',
        'job_list': 'job-list.js',
        'job_progress': 'job-progress.js',
        'manage_jobs': 'manage-jobs.js',
        'view_jobs': 'view-jobs.js',
        'my_tasks': 'my-tasks.js',
        'sign_forms': 'sign-forms.js',
        'report': 'report.js' // Add report page script
    };
    
    const scriptFile = scriptMap[pageName];
    if (scriptFile) {
        const script = document.createElement('script');
        script.id = scriptId;
        script.src = `assets/js/pages/${scriptFile}`;
        script.onload = () => {
            console.log(`Loaded script for ${pageName}`);
            // Initialize page-specific functions
            initializePage(pageName);
        };
        script.onerror = () => {
            console.warn(`Failed to load script for ${pageName}`);
        };
        document.head.appendChild(script);
    }
}

// Initialize page-specific functionality
function initializePage(pageName) {
    // Reset job details modal context for all pages except report
    // Report page uses ../api/ paths, all other pages use api/ paths
    if (typeof setJobDetailsModalContext === 'function') {
        setJobDetailsModalContext(pageName === 'report');
    }
    
    switch(pageName) {
        case 'create_job':
            if (typeof initializeCreateJobPage === 'function') {
                initializeCreateJobPage();
            }
            break;
        case 'job_list':
            if (typeof initializeJobListPage === 'function') {
                initializeJobListPage();
            }
            break;
        case 'job_progress':
            if (typeof initializeJobProgressPage === 'function') {
                initializeJobProgressPage();
            }
            break;
        case 'manage_jobs':
            if (typeof initializeManageJobsPage === 'function') {
                initializeManageJobsPage();
            }
            break;
        case 'view_jobs':
            if (typeof initializeViewJobsPage === 'function') {
                initializeViewJobsPage();
            }
            break;
        case 'my_tasks':
            if (typeof initializeMyTasksPage === 'function') {
                initializeMyTasksPage();
            }
            break;
        case 'sign_forms':
            if (typeof initializeSignFormsPage === 'function') {
                initializeSignFormsPage();
            }
            break;
        case 'report':
            if (typeof initializeReportPage === 'function') {
                initializeReportPage();
            }
            break;
    }
}

// Add global assignment functions for job list
window.showAssignJobModal = function(jobId, currentRole) {
    if (typeof showAssignJobModal === 'function') {
        showAssignJobModal(jobId, currentRole);
    } else {
        console.error('showAssignJobModal function not available');
        alert('Assignment function not loaded. Please refresh the page.');
    }
};

window.closeAssignJobModal = function() {
    if (typeof closeAssignJobModal === 'function') {
        closeAssignJobModal();
    } else {
        console.error('closeAssignJobModal function not available');
    }
};

window.loadUsersForAssignment = function() {
    if (typeof loadUsersForAssignment === 'function') {
        loadUsersForAssignment();
    } else {
        console.error('loadUsersForAssignment function not available');
        alert('User loading function not available. Please refresh the page.');
    }
};

window.updateJobProgress = function(jobId) {
    if (typeof updateJobProgress === 'function') {
        updateJobProgress(jobId);
    } else {
        console.error('updateJobProgress function not available');
    }
};

window.filterJobs = function() {
    if (typeof filterJobs === 'function') {
        filterJobs();
    } else {
        console.error('filterJobs function not available');
    }
};

window.clearJobSearch = function() {
    if (typeof clearJobSearch === 'function') {
        clearJobSearch();
    } else {
        console.error('clearJobSearch function not available');
    }
};

window.refreshJobList = function() {
    if (typeof refreshJobList === 'function') {
        refreshJobList();
    } else {
        location.reload();
    }
};

// Add job details functions
window.viewJobDetails = function(jobId) {
    if (typeof viewJobDetails === 'function') {
        viewJobDetails(jobId);
    } else {
        console.error('viewJobDetails function not available');
    }
};

window.closeJobDetailsModal = function() {
    if (typeof closeJobDetailsModal === 'function') {
        closeJobDetailsModal();
    } else {
        console.error('closeJobDetailsModal function not available');
    }
};

window.downloadAttachment = function(filename) {
    if (typeof downloadAttachment === 'function') {
        downloadAttachment(filename);
    } else {
        console.error('downloadAttachment function not available');
    }
};

// Add global functions for create job page
window.resetCreateJobForm = function() {
    if (typeof resetCreateJobForm === 'function') {
        resetCreateJobForm();
    } else {
        // Fallback reset functionality
        const form = document.getElementById('createJobForm');
        if (form) {
            form.reset();
            
            // Reset file upload
            const previewContainer = document.getElementById('filePreview');
            if (previewContainer) {
                previewContainer.style.display = 'none';
                previewContainer.innerHTML = '';
            }
            
            // Reset user dropdown
            const userSelect = document.getElementById('assign_to_user');
            if (userSelect) {
                userSelect.disabled = true;
                userSelect.innerHTML = '<option value="">Select role first</option>';
            }
            
            // Reset upload area
            const uploadArea = document.getElementById('fileUploadArea');
            if (uploadArea) {
                uploadArea.classList.remove('has-file');
            }
        }
    }
};

window.createAnotherJob = function() {
    window.resetCreateJobForm();
};

window.goBackToDashboard = function() {
    showDashboard();
};

// Add global functions for sign forms page
window.viewJobForms = function(surveyJobId, jobNumber) {
    if (typeof viewJobForms === 'function') {
        viewJobForms(surveyJobId, jobNumber);
    } else {
        console.error('viewJobForms function not available');
        alert('Sign forms function not loaded. Please refresh the page.');
    }
};

window.signForm = function(formId, formType, jobNumber) {
    if (typeof signForm === 'function') {
        signForm(formId, formType, jobNumber);
    } else {
        console.error('signForm function not available');
        alert('Sign form function not loaded. Please refresh the page.');
    }
};

window.closeFormsModal = function() {
    if (typeof closeFormsModal === 'function') {
        closeFormsModal();
    } else {
        console.error('closeFormsModal function not available');
    }
};

window.closeSignModal = function() {
    if (typeof closeSignModal === 'function') {
        closeSignModal();
    } else {
        console.error('closeSignModal function not available');
    }
};

window.clearSignature = function() {
    if (typeof clearSignature === 'function') {
        clearSignature();
    } else {
        console.error('clearSignature function not available');
    }
};

window.viewSignature = function(formId) {
    if (typeof viewSignature === 'function') {
        viewSignature(formId);
    } else {
        console.error('viewSignature function not available');
    }
};

window.initializeSignaturePad = function() {
    if (typeof initializeSignaturePad === 'function') {
        initializeSignaturePad();
    } else {
        console.error('initializeSignaturePad function not available');
    }
};

window.resizeSignaturePad = function() {
    if (typeof resizeSignaturePad === 'function') {
        resizeSignaturePad();
    } else {
        console.error('resizeSignaturePad function not available');
    }
};

window.toggleLotGroup = function(lotId) {
    if (typeof toggleLotGroup === 'function') {
        toggleLotGroup(lotId);
    } else {
        console.error('toggleLotGroup function not available');
    }
};

// Function to fetch and display new task count
async function fetchNewTaskCount() {
    try {
        const response = await fetch('api/get_new_tasks_count.php');
        const data = await response.json();
        
        if (data.success && data.data && data.data.count !== undefined) {
            const count = parseInt(data.data.count);
            updateNewTaskCounter(count);
        } else {
            console.error('Error fetching new task count:', data.message || 'Unknown error');
        }
    } catch (error) {
        console.error('Error fetching new task count:', error);
    }
}

// Function to update the new task counter badge
function updateNewTaskCounter(count) {
    const counter = document.getElementById('newTaskCounter');
    const countElement = document.getElementById('newTaskCount');
    
    if (counter && countElement) {
        countElement.textContent = count;
        
        if (count > 0) {
            counter.style.display = 'flex';
        } else {
            counter.style.display = 'none';
        }
    }
}

// Function to refresh new task count (called when returning to dashboard)
function refreshNewTaskCount() {
    if (currentPage === 'dashboard') {
        fetchNewTaskCount();
    }
}

// Make functions globally available
window.fetchNewTaskCount = fetchNewTaskCount;
window.updateNewTaskCounter = updateNewTaskCounter;
window.refreshNewTaskCount = refreshNewTaskCount;

// Load Job Details Modal script (centralized)
function loadJobDetailsModalScript() {
    const scriptId = 'job-details-modal-script';
    
    // Check if script already exists
    if (document.getElementById(scriptId)) {
        console.log('Job Details Modal script already loaded');
        return;
    }
    
    const script = document.createElement('script');
    script.id = scriptId;
    script.src = 'assets/js/job-details-modal.js';
    script.onload = () => {
        console.log('Job Details Modal script loaded successfully');
    };
    script.onerror = () => {
        console.error('Failed to load Job Details Modal script');
    };
    document.head.appendChild(script);
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard initialized');
    
    // Load the centralized Job Details Modal script
    loadJobDetailsModalScript();
    
    // Set initial sidebar state based on screen size
    if (window.innerWidth <= 768) {
        isSidebarCollapsed = true;
        const sidebar = document.getElementById('sidebar');
        const contentArea = document.getElementById('contentArea');
        sidebar.classList.add('collapsed');
        contentArea.classList.add('expanded');
    }
    
    // Fetch initial new task count
    setTimeout(() => {
        fetchNewTaskCount();
    }, 500);
    
    // Set up periodic refresh of new task count (every 2 minutes)
    setInterval(() => {
        if (currentPage === 'dashboard') {
            fetchNewTaskCount();
        }
    }, 120000);
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            closeMobileSidebar();
        }
    });
});

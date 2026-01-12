// Job Progress Page JavaScript

// Global variables for job progress
let allJobs = [];
let filteredJobs = [];
let currentFilters = {};

// Initialize Job Progress Page
function initializeJobProgressPage() {
    console.log('Initializing Job Progress Page');
    
    // Get all jobs data from the page
    allJobs = Array.from(document.querySelectorAll('.job-row')).map(row => ({
        id: row.querySelector('.job-number strong').textContent.trim(),
        projectName: row.querySelector('.project-name').textContent.trim(),
        status: row.dataset.status,
        createdRole: row.dataset.createdRole,
        assignedRole: row.dataset.assignedRole,
        element: row
    }));
    
    filteredJobs = [...allJobs];
    
    // Initialize search functionality
    initializeSearch();
    
    // Initialize filters
    initializeFilters();
    
    // Initialize modals
    initializeModals();
    
    console.log(`Job Progress initialized with ${allJobs.length} jobs`);
}

// Initialize search functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
        searchInput.addEventListener('focus', showSearchSuggestions);
        searchInput.addEventListener('blur', hideSearchSuggestions);
    }
}

// Handle search input
function handleSearch(event) {
    const searchTerm = event.target.value.toLowerCase().trim();
    
    if (searchTerm === '') {
        filteredJobs = [...allJobs];
    } else {
        filteredJobs = allJobs.filter(job => 
            job.id.toLowerCase().includes(searchTerm) ||
            job.projectName.toLowerCase().includes(searchTerm) ||
            job.status.toLowerCase().includes(searchTerm)
        );
    }
    
    updateJobDisplay();
}

// Clear search
function clearSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = '';
        filteredJobs = [...allJobs];
        updateJobDisplay();
    }
}

// Show search suggestions
function showSearchSuggestions() {
    // Implementation for search suggestions
    console.log('Showing search suggestions');
}

// Hide search suggestions  
function hideSearchSuggestions() {
    setTimeout(() => {
        const suggestions = document.getElementById('searchSuggestions');
        if (suggestions) {
            suggestions.style.display = 'none';
        }
    }, 200);
}

// Initialize filters
function initializeFilters() {
    // Add event listeners for filter presets
    const presetButtons = document.querySelectorAll('.btn-preset');
    presetButtons.forEach(button => {
        button.addEventListener('click', handlePresetFilter);
    });
}

// Handle preset filter clicks
function handlePresetFilter(event) {
    const preset = event.target.textContent.trim();
    
    switch(preset) {
        case 'My Tasks':
            applyFilterPreset('my_tasks');
            break;
        case 'Pending':
            applyFilterPreset('pending_jobs');
            break;
        case 'Unassigned':
            applyFilterPreset('unassigned_jobs');
            break;
    }
}

// Apply filter presets
function applyFilterPreset(preset) {
    console.log(`Applying filter preset: ${preset}`);
    
    switch(preset) {
        case 'my_tasks':
            // Filter jobs assigned to current user
            filteredJobs = allJobs.filter(job => {
                // This would need user session data
                return true; // Placeholder
            });
            break;
            
        case 'pending_jobs':
            filteredJobs = allJobs.filter(job => job.status === 'pending');
            break;
            
        case 'unassigned_jobs':
            filteredJobs = allJobs.filter(job => !job.assignedRole);
            break;
    }
    
    updateJobDisplay();
}

// Reset all filters
function resetFilters() {
    console.log('Resetting all filters');
    
    // Clear search
    clearSearch();
    
    // Reset filters
    currentFilters = {};
    filteredJobs = [...allJobs];
    
    updateJobDisplay();
}

// Update job display
function updateJobDisplay() {
    const tbody = document.querySelector('.jobs-table tbody');
    if (!tbody) return;
    
    // Hide all rows first
    allJobs.forEach(job => {
        job.element.style.display = 'none';
    });
    
    // Show filtered rows
    filteredJobs.forEach(job => {
        job.element.style.display = '';
    });
    
    // Update showing count
    const showingCount = document.getElementById('showingCount');
    if (showingCount) {
        showingCount.textContent = filteredJobs.length;
    }
}

// Initialize modals
function initializeModals() {
    // Job details modal
    initializeJobDetailsModal();
    
    // Assignment modal  
    initializeAssignmentModal();
}

// Initialize job details modal
function initializeJobDetailsModal() {
    // Modal will be handled by existing functions
    console.log('Job details modal initialized');
}

// Initialize assignment modal
function initializeAssignmentModal() {
    // Modal will be handled by existing functions
    console.log('Assignment modal initialized');
}

// View job details
function viewJobDetails(jobId) {
    console.log(`Viewing job details for ID: ${jobId}`);
    
    const modal = document.getElementById('jobDetailsModal');
    const content = document.getElementById('jobDetailsContent');
    
    if (!modal || !content) {
        console.error('Job details modal not found');
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
    fetch(`api/get_job_details.php?job_id=${jobId}`)
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
            
            // Build job details HTML
            content.innerHTML = `
                <div class="job-details-grid">
                    <div class="detail-section">
                        <h4>📋 Job Information</h4>
                        <div class="detail-row">
                            <span class="label">Job Number:</span>
                            <span class="value">${data.surveyjob_no || 'N/A'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">HQ Reference:</span>
                            <span class="value">${data.hq_ref || 'N/A'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Division Reference:</span>
                            <span class="value">${data.div_ref || 'N/A'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Project Name:</span>
                            <span class="value">${data.projectname || 'N/A'}</span>
                        </div>                        <div class="detail-row">
                            <span class="label">Status:</span>
                            <span class="value">
                                <span class="status-badge status-${data.status?.toLowerCase() || 'default'}">${data.status || 'Unknown'}</span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="label">PBT Status:</span>
                            <span class="value">
                                <span class="pbtstatus-badge pbtstatus-${data.pbtstatus?.toLowerCase() || 'none'}">${data.pbtstatus || 'none'}</span>
                            </span>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4>👥 Assignment & Dates</h4>
                        <div class="detail-row">
                            <span class="label">Created By:</span>
                            <span class="value">${data.created_by_name || 'Unknown'} (${data.created_by_role || 'N/A'})</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Assigned To:</span>
                            <span class="value">${data.assigned_to_name ? `${data.assigned_to_name} (${data.assigned_to_role || 'N/A'})` : 'Not Assigned'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Created Date:</span>
                            <span class="value">${data.created_at || 'N/A'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Last Updated:</span>
                            <span class="value">${data.updated_at || 'N/A'}</span>
                        </div>
                    </div>
                </div>
                
                ${data.attachment_name && data.attachment_name !== 'no_file_uploaded.pdf' ? `
                    <div class="attachment-section">
                        <h4>📎 Attachments</h4>
                        <div class="attachment-item">
                            <span>📄 ${data.attachment_name}</span>
                            <button onclick="downloadAttachment('${data.attachment_name}')" class="btn-download">
                                📥 Download
                            </button>
                        </div>
                    </div>
                ` : ''}
                
                <div class="modal-actions">
                    <button onclick="closeJobDetailsModal()" class="btn-secondary">Close</button>
                </div>
            `;
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

// Close job details modal
function closeJobDetailsModal() {
    const modal = document.getElementById('jobDetailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Assign job
function assignJob(jobId) {
    console.log(`Assigning job ID: ${jobId}`);
    
    const modal = document.getElementById('assignJobModal');
    if (modal) {
        // Set job ID
        const jobIdInput = document.getElementById('modalJobId');
        if (jobIdInput) {
            jobIdInput.value = jobId;
        }
        
        // Initialize assignment form if not already done
        const form = document.getElementById('assignJobForm');
        if (form && !form.hasEventListener) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                submitAssignment();
            });
            form.hasEventListener = true;
        }
        
        modal.style.display = 'flex';
    }
}

// Submit job assignment
function submitAssignment() {
    const form = document.getElementById('assignJobForm');
    const formData = new FormData(form);
    
    // Get form values for validation
    const jobId = formData.get('job_id');
    const assignToRole = formData.get('assign_to_role');
    const assignToUserId = formData.get('assign_to_user_id');
    
    console.log('Submitting assignment:', { jobId, assignToRole, assignToUserId });
    
    // Validate form
    if (!jobId || !assignToRole || !assignToUserId) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Show loading
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    submitButton.textContent = 'Assigning...';
    submitButton.disabled = true;
    
    // Submit assignment via AJAX
    fetch('api/assign_job.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Assignment response:', data);
        
        if (data.success) {
            alert('Job assigned successfully!');
            closeAssignJobModal();
            location.reload(); // Refresh to show updated data
        } else {
            alert('Error: ' + (data.error || 'Unknown error occurred'));
        }
    })
    .catch(error => {
        console.error('Error submitting assignment:', error);
        alert('Error submitting assignment. Please try again.');
    })
    .finally(() => {
        // Reset button
        submitButton.textContent = originalText;
        submitButton.disabled = false;
    });
}

// Close assignment modal
function closeAssignJobModal() {
    const modal = document.getElementById('assignJobModal');
    if (modal) {
        modal.style.display = 'none';
        
        // Reset form
        const form = document.getElementById('assignJobForm');
        if (form) {
            form.reset();
        }
        
        // Reset user select
        const userSelect = document.getElementById('modalAssignToUser');
        if (userSelect) {
            userSelect.innerHTML = '<option value="">Select role first</option>';
            userSelect.disabled = true;
        }
    }
}

// Show assignment modal
function showAssignJobModal(jobId, currentRole) {
    console.log(`Showing assignment modal for job ${jobId}, current role: ${currentRole}`);
    
    const modal = document.getElementById('assignJobModal');
    const jobIdInput = document.getElementById('modalJobId');
    const roleSelect = document.getElementById('modalAssignToRole');
    const userSelect = document.getElementById('modalAssignToUser');
    
    if (!modal || !jobIdInput || !roleSelect || !userSelect) {
        console.error('Assignment modal elements not found');
        alert('Assignment modal not properly loaded. Please refresh the page.');
        return;
    }
    
    // Set job ID
    jobIdInput.value = jobId;
    
    // Clear and populate role options based on current user role
    roleSelect.innerHTML = '<option value="">Select Role</option>';
    userSelect.innerHTML = '<option value="">Select role first</option>';
    userSelect.disabled = true;
    
    // Define assignable roles based on current role (matching the rules)
    const assignableRoles = getAssignableRoles(currentRole);
    
    if (assignableRoles.length === 0) {
        roleSelect.innerHTML = '<option value="">No assignable roles</option>';
        roleSelect.disabled = true;
    } else {
        assignableRoles.forEach(role => {
            const option = document.createElement('option');
            option.value = role;
            option.textContent = role;
            roleSelect.appendChild(option);
        });
        roleSelect.disabled = false;
    }
    
    // Update workflow info
    updateWorkflowInfo(currentRole);
    
    // Show modal
    modal.style.display = 'flex';
}

// Get assignable roles based on current role
function getAssignableRoles(currentRole) {
    const assignments = {
        'OIC': ['VO'],
        'VO': ['SS', 'OIC'], // VO can assign to SS or send completed jobs to OIC for checking
        'SS': ['FI', 'VO'], // SS can assign to FI or return to VO (completion)
        'FI': ['AS', 'PP', 'SD'],
        'AS': ['FI'], // Submit back to FI
        'PP': ['SD'], // Return to SD
        'SD': ['PP', 'SS']
    };
    
    return assignments[currentRole] || [];
}

// Update workflow information
function updateWorkflowInfo(currentRole) {
    const workflowInfo = document.getElementById('workflowInfo');
    if (!workflowInfo) return;
    
    const workflows = {
        'OIC': 'OIC → VO (Visual Officer)',
        'VO': 'VO → SS (Staff Surveyors) or VO → OIC (Send for checking)',
        'SS': 'SS → FI (Field Inspector) or SS → VO (Complete)',
        'FI': 'FI → AS/PP/SD (Field Team)',
        'AS': 'AS → FI (Submit back)',
        'PP': 'PP → SD (Pass to Scanner)',
        'SD': 'SD → PP/SS (Return or Complete)'
    };
    
    workflowInfo.innerHTML = `<p><strong>Current Step:</strong> ${workflows[currentRole] || 'Unknown workflow'}</p>`;
}

// Download attachment
function downloadAttachment(filename) {
    console.log(`Downloading attachment: ${filename}`);
    
    // Create download link
    const link = document.createElement('a');
    link.href = `uploads/${filename}`;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Export filtered jobs
function exportFilteredJobs() {
    console.log('Exporting filtered jobs');
    
    // Implementation for export functionality
    alert('Export functionality would be implemented here');
}

// Load users for assignment
function loadUsersForAssignment() {
    console.log('Loading users for assignment');
    
    const roleSelect = document.getElementById('modalAssignToRole');
    const userSelect = document.getElementById('modalAssignToUser');
    
    if (!roleSelect || !userSelect) {
        console.error('Role or user select elements not found');
        return;
    }
    
    const selectedRole = roleSelect.value;
    
    if (selectedRole) {
        userSelect.disabled = false;
        userSelect.innerHTML = '<option value="">Loading users...</option>';
        
        // Construct the correct API URL
        const apiUrl = `api/get_users_by_role.php?role=${encodeURIComponent(selectedRole)}`;
        console.log('Fetching users from:', apiUrl);
        
        // Fetch users for the selected role
        fetch(apiUrl)
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('API Response:', data);
                
                userSelect.innerHTML = '<option value="">Select User</option>';
                
                // Check if data is an array (direct response) or has error property
                if (data.error) {
                    const errorOption = document.createElement('option');
                    errorOption.value = '';
                    errorOption.textContent = data.message || `Error: ${data.error}`;
                    userSelect.appendChild(errorOption);
                    console.warn('API Error:', data);
                } else if (Array.isArray(data) && data.length > 0) {
                    // Direct array response from API
                    data.forEach(user => {
                        const option = document.createElement('option');
                        option.value = user.user_id;
                        option.textContent = `${user.name} (${user.username})`;
                        userSelect.appendChild(option);
                    });
                    console.log(`Loaded ${data.length} users for role ${selectedRole}`);
                } else if (Array.isArray(data) && data.length === 0) {
                    // Empty array response
                    const noUsersOption = document.createElement('option');
                    noUsersOption.value = '';
                    noUsersOption.textContent = `No ${selectedRole} users found`;
                    userSelect.appendChild(noUsersOption);
                    console.warn('No users found for role:', selectedRole);
                } else {
                    // Unexpected response format
                    const noUsersOption = document.createElement('option');
                    noUsersOption.value = '';
                    noUsersOption.textContent = 'Unexpected response format';
                    userSelect.appendChild(noUsersOption);
                    console.warn('Unexpected response format:', data);
                }
            })
            .catch(error => {
                console.error('Error loading users:', error);
                userSelect.innerHTML = '<option value="">Error loading users</option>';
                
                // Add retry option
                const retryOption = document.createElement('option');
                retryOption.value = '';
                retryOption.textContent = 'Click to retry...';
                userSelect.appendChild(retryOption);
                
                // Show user-friendly error
                alert('Failed to load users. Please check your connection and try again.');
            });
    } else {
        userSelect.disabled = true;
        userSelect.innerHTML = '<option value="">Select role first</option>';
    }
}

// Mark acquisition as complete
function markAcquisitionComplete(jobId) {
    if (!confirm('Are you sure you want to mark this job as "Acquisition Complete"? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('job_id', jobId);
    formData.append('pbtstatus', 'acquisition_complete');
    
    fetch('../api/update_pbtstatus.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Job marked as Acquisition Complete successfully!');
            location.reload(); // Refresh to show updated status
        } else {
            alert('Error: ' + (data.error || 'Unknown error occurred'));
        }
    })
    .catch(error => {
        console.error('Error marking acquisition complete:', error);
        alert('Error marking acquisition complete. Please try again.');
    });
}

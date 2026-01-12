// Job List Page JavaScript

// Initialize Job List Page
function initializeJobListPage() {
    console.log('Initializing Job List Page');
    
    // Initialize assignment form
    initializeAssignmentForm();
    
    // Initialize job list specific functionality
    initializeJobFilters();
    initializeJobActions();
    initializeJobModals();
    
    console.log('Job List Page initialized');
}

// Initialize assignment form submission
function initializeAssignmentForm() {
    const form = document.getElementById('assignJobForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            submitAssignment();
        });
    }
}

// Show assignment modal
function showAssignJobModal(jobId, currentRole) {
    console.log(`Showing assignment modal for job ${jobId}, current role: ${currentRole}`);
    
    const modal = document.getElementById('assignJobModal');
    const modalContent = modal.querySelector('.modal-content');
    const jobIdInput = document.getElementById('modalJobId');
    const roleSelect = document.getElementById('modalAssignToRole');
    const userSelect = document.getElementById('modalAssignToUser');
    
    if (!modal || !modalContent || !jobIdInput || !roleSelect || !userSelect) {
        console.error('Assignment modal elements not found');
        alert('Assignment modal not properly loaded. Please refresh the page.');
        return;
    }
    
    // Store current role on the modal content for later use
    modalContent.setAttribute('data-current-role', currentRole);
    
    // Set job ID
    jobIdInput.value = jobId;
    
    // Clear and populate role options based on current user role
    roleSelect.innerHTML = '<option value="">Select Role</option>';
    userSelect.innerHTML = '<option value="">Select role first</option>';
    userSelect.disabled = true;
    
    // Define assignable roles based on current role
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
    
    // Show modal
    modal.style.display = 'flex';
    
    // Initialize query return checkbox listeners
    setTimeout(() => {
        initializeQueryReturnCheckboxes();
    }, 100);
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
    
    // Special case: if current role is VO and query return button is active, add FI and SD to assignable roles
    if (currentRole === 'VO') {
        const queryReturnBtn = document.querySelector('.btn-query-return');
        if (queryReturnBtn && queryReturnBtn.classList.contains('active')) {
            return [ 'FI', 'SD']; // Include FI and SD when query return is active
        }
    }
    
    return assignments[currentRole] || [];
}

// Load users for assignment based on selected role
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
            })            .then(data => {
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
        
        // Reset role select - it will be repopulated next time the modal opens
        const roleSelect = document.getElementById('modalAssignToRole');
        if (roleSelect) {
            roleSelect.innerHTML = '<option value="">Select Role</option>';
        }
        
        // Clear remarks
        const remarksField = document.getElementById('modalRemarks');
        if (remarksField) {
            remarksField.value = '';
        }
        
        // Reset query return section (only if it exists for VO users)
        const queryReturnSection = document.getElementById('queryReturnSection');
        const queryReturnBtn = document.querySelector('.btn-query-return');
        if (queryReturnSection && queryReturnBtn) {
            queryReturnSection.style.display = 'none';
            queryReturnBtn.classList.remove('active');
            queryReturnBtn.textContent = 'Query Return';
            
            // Uncheck all query return checkboxes
            const checkboxes = queryReturnSection.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        }
    }
}

// Submit job assignment
function submitAssignment() {
    const form = document.getElementById('assignJobForm');
    const formData = new FormData(form);
    
    // Get form values for validation
    const jobId = formData.get('job_id');
    const assignToRole = formData.get('assign_to_role');
    const assignToUserId = formData.get('assign_to_user');
    const remarks = formData.get('remarks') || '';
    
    // Get query return items (only if elements exist for VO users)
    const queryReturnItems = [];
    const checkboxes = form.querySelectorAll('input[name="query_return_items[]"]:checked');
    if (checkboxes) {
        checkboxes.forEach(checkbox => {
            queryReturnItems.push(checkbox.value);
        });
    }
    
    // Prepare query info JSON if query return items are selected
    let queryInfoData = null;
    if (queryReturnItems.length > 0) {
        queryInfoData = {
            "A1": queryReturnItems.includes("Form A1") ? remarks : "",
            "B1": queryReturnItems.includes("Form B1") ? remarks : "",
            "C1": queryReturnItems.includes("Form C1") ? remarks : "",
            "L&16": queryReturnItems.includes("Form L&16") ? remarks : "",
            "L&S3": queryReturnItems.includes("L&S3") ? remarks : "",
            "MP_Pelan": queryReturnItems.includes("MP Plan") ? remarks : "",
            "query_date": new Date().toISOString().split('T')[0], // Current date in YYYY-MM-DD format
            "query_returned": ""
        };
    }
    
    console.log('Submitting assignment:', { jobId, assignToRole, assignToUserId, remarks, queryReturnItems, queryInfoData });
    
    // Validate form
    if (!jobId || !assignToRole || !assignToUserId) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Show confirmation if query return items are selected
    if (queryReturnItems.length > 0) {
        const confirmMessage = `You have selected ${queryReturnItems.length} item(s) for query return: ${queryReturnItems.join(', ')}.\n\nThe job will be assigned and the query return information will be recorded.\n\nDo you want to continue?`;
        if (!confirm(confirmMessage)) {
            return;
        }
    }
    
    // Show loading
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    submitButton.textContent = 'Assigning...';
    submitButton.disabled = true;
    
    // Prepare JSON payload to match PHP API expectations
    const payload = {
        jobId: jobId,
        assignToRole: assignToRole,
        assignToUserId: assignToUserId,
        remarks: remarks,
        queryReturnItems: queryReturnItems,
        queryInfoData: queryInfoData
    };
    
    console.log('Sending JSON payload:', payload);
    
    fetch('api/assign_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Assignment response:', data);
        
        if (data.success) {
            alert(data.message || 'Job assigned successfully!');
            closeAssignJobModal();
            
            // Refresh dashboard task count if possible
            refreshDashboardTaskCount();
            
            location.reload(); // Refresh to show updated data
        } else {
            alert('Error: ' + (data.error || 'Assignment failed'));
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

// Filter jobs based on search and filters
function filterJobs() {
    const searchTerm = document.getElementById('jobSearch')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('statusFilter')?.value.toLowerCase() || '';
    const involvementFilter = document.getElementById('involvementFilter')?.value || '';
    const pbtstatusFilter = document.getElementById('pbtstatusFilter')?.value.toLowerCase() || '';
    
    // Get button filter states
    const newFilterActive = document.getElementById('newFilterBtn')?.classList.contains('active') || false;
    const approvalFilterActive = document.getElementById('approvalFilterBtn')?.classList.contains('active') || false;
    
    const jobCards = document.querySelectorAll('.job-card');
    let visibleCount = 0;
    
    jobCards.forEach(card => {
        const jobNumber = card.querySelector('.job-number strong')?.textContent.toLowerCase() || '';
        const projectName = card.querySelector('.project-name')?.textContent.toLowerCase() || '';
        const status = card.dataset.status || '';
        const involvement = card.dataset.involvement || '';
        const pbtstatus = card.dataset.pbtstatus || 'none';
        const isNew = card.dataset.isNew === 'true';
        const assigneeName = card.dataset.assignee?.toLowerCase() || '';
        const createdByName = card.dataset.createdByName?.toLowerCase() || '';
        const needsApproval = card.dataset.needsApproval === 'true';
        
        let showCard = true;
        
        // Search filter
        if (searchTerm) {
            showCard = showCard && (
                jobNumber.includes(searchTerm) ||
                projectName.includes(searchTerm) ||
                assigneeName.includes(searchTerm) ||
                createdByName.includes(searchTerm)
            );
        }
        
        // Status filter
        if (statusFilter) {
            showCard = showCard && status === statusFilter;
        }
        
        // Involvement filter
        if (involvementFilter) {
            if (involvementFilter === 'new_tasks') {
                showCard = showCard && isNew;
            } else {
                showCard = showCard && involvement === involvementFilter;
            }
        }
        
        // PBT Status filter
        if (pbtstatusFilter) {
            showCard = showCard && pbtstatus === pbtstatusFilter;
        }
        
        // New filter button
        if (newFilterActive) {
            showCard = showCard && isNew;
        }
        
        // Approval filter button
        if (approvalFilterActive) {
            showCard = showCard && needsApproval;
        }
        
        card.style.display = showCard ? 'block' : 'none';
        if (showCard) visibleCount++;
    });
    
    // Update results count
    const resultsCount = document.getElementById('resultsCount');
    if (resultsCount) {
        resultsCount.textContent = visibleCount;
    }
}

// Clear job search
function clearJobSearch() {
    const searchInput = document.getElementById('jobSearch');
    if (searchInput) {
        searchInput.value = '';
        filterJobs();
    }
}

// Clear all filters
function clearAllFilters() {
    const searchInput = document.getElementById('jobSearch');
    const statusFilter = document.getElementById('statusFilter');
    const involvementFilter = document.getElementById('involvementFilter');
    const pbtstatusFilter = document.getElementById('pbtstatusFilter');
    const newFilterBtn = document.getElementById('newFilterBtn');
    const approvalFilterBtn = document.getElementById('approvalFilterBtn');
    
    if (searchInput) searchInput.value = '';
    if (statusFilter) statusFilter.value = '';
    if (involvementFilter) involvementFilter.value = '';
    if (pbtstatusFilter) pbtstatusFilter.value = '';
    if (newFilterBtn) newFilterBtn.classList.remove('active');
    if (approvalFilterBtn) approvalFilterBtn.classList.remove('active');
    
    filterJobs();
}

// Refresh job list
function refreshJobList() {
    location.reload();
}

// Update job progress
function updateJobProgress(jobId) {
    console.log(`Updating progress for job ${jobId}`);
    
    // Get current job status
    const jobCard = document.querySelector(`[data-job-id="${jobId}"]`);
    if (!jobCard) {
        console.error('Job card not found');
        return;
    }
    
    const currentStatus = jobCard.dataset.status;
    
    // Show status selection modal/prompt
    const newStatus = prompt('Update job status:\n- in_progress\n- completed\n- submitted\n\nEnter new status:', currentStatus);
    
    if (newStatus && newStatus !== currentStatus) {
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('job_id', jobId);
        formData.append('new_status', newStatus);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes('updated successfully')) {
                location.reload();
            } else {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data;
                const errorMsg = tempDiv.querySelector('.error-message');
                if (errorMsg) {
                    alert('Error: ' + errorMsg.textContent.replace('Error:', '').trim());
                } else {
                    alert('Status updated. Please refresh the page.');
                    location.reload();
                }
            }
        })
        .catch(error => {
            console.error('Error updating status:', error);
            alert('Error updating status. Please try again.');
        });
    }
}

// Toggle filter button
function toggleFilterButton(buttonId) {
    const button = document.getElementById(buttonId);
    if (button) {
        button.classList.toggle('active');
        filterJobs();
    }
}

// Initialize job filters
function initializeJobFilters() {
    console.log('Initializing job filters');
    
    // Add filter event listeners
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', filterJobs);
    });
    
    // Add filter button event listeners
    const newFilterBtn = document.getElementById('newFilterBtn');
    const approvalFilterBtn = document.getElementById('approvalFilterBtn');
    
    if (newFilterBtn) {
        newFilterBtn.addEventListener('click', () => toggleFilterButton('newFilterBtn'));
    }
    
    if (approvalFilterBtn) {
        approvalFilterBtn.addEventListener('click', () => toggleFilterButton('approvalFilterBtn'));
    }
}

// Initialize job actions
function initializeJobActions() {
    console.log('Initializing job actions');
    
    // Add action button event listeners
    const actionButtons = document.querySelectorAll('.job-action-btn');
    actionButtons.forEach(button => {
        button.addEventListener('click', handleJobAction);
    });
}

// Handle job actions
function handleJobAction(event) {
    const action = event.target.dataset.action;
    const jobId = event.target.dataset.jobId;
    
    console.log(`Handling job action: ${action} for job ${jobId}`);
    
    switch(action) {
        case 'view':
            viewJobDetails(jobId);
            break;
        case 'edit':
            editJob(jobId);
            break;
        case 'delete':
            deleteJob(jobId);
            break;
    }
}

// Initialize job modals
function initializeJobModals() {
    console.log('Initializing job modals');
    // Job modal initialization
}

// Job action functions
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
                
                ${data.form_count > 0 ? `
                    <div class="forms-section">
                        <h4>📄 Forms (${data.form_count})</h4>
                        <div class="forms-summary">
                            <p>Available Form Types: ${data.form_types.join(', ')}</p>
                            <button onclick="loadJobForms(${jobId})" class="btn-load-forms">
                                📋 View All Forms
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
                
                ${data.attachment_name && data.attachment_name !== 'no_file_uploaded.pdf' || (data.sj_files && data.sj_files.length > 0) ? `
                    <div class="attachment-section">
                        <h4>📎 Attachments</h4>
                        ${data.attachment_name && data.attachment_name !== 'no_file_uploaded.pdf' ? `
                            <div class="attachment-item">
                                <span>📄 ${data.attachment_name}</span>
                                <button onclick="openAttachment('uploads/jobs/oic/${data.attachment_name}')" class="btn-view">
                                     View
                                </button>
                            </div>
                        ` : ''}
                        ${data.sj_files && data.sj_files.length > 0 ? `
                            <div class="additional-attachments">
                                <h5>📋 Additional Files (${data.sj_files.length})</h5>
                                ${data.sj_files.map(file => `
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

// Load job forms
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
    
    // Fetch forms and job details (for other_attachment)
    Promise.all([
        fetch(`api/get_job_forms.php?job_id=${jobId}`),
        fetch(`api/get_job_details.php?job_id=${jobId}`)
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
        
        if (formsData.forms.length === 0) {
                container.innerHTML = `
                    <div class="no-forms">
                        <p>No forms found for this job.</p>
                    </div>
                `;
                return;
            }
            
            // Group forms by category
        const categorizedForms = groupFormsByCategory(formsData.forms);
            
            // Build categorized forms HTML
            container.innerHTML = buildCategorizedFormsHTML(categorizedForms);
        
        // Load child attachments for each lot
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
                                🔗 Open
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
                        <span class="form-date">${form.created_at_formatted}</span>
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

// Get category icon
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

// Toggle lot forms visibility
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

// Close job details modal
function closeJobDetailsModal() {
    const modal = document.getElementById('jobDetailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Download attachment
function downloadAttachment(filename) {
    console.log(`Downloading attachment: ${filename}`);
    
    // Create download link
    const link = document.createElement('a');
    link.href = `uploads/jobs/oic/${filename}`;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Open form view in new tab
function openFormView(formId) {
    if (!formId) return;
    window.open(`../pages/view_form.php?form_id=${encodeURIComponent(formId)}`, '_blank');
}

// Toggle Query Return section (VO only)
function toggleQueryReturn() {
    const queryReturnSection = document.getElementById('queryReturnSection');
    const queryReturnBtn = document.querySelector('.btn-query-return');
    
    if (!queryReturnSection || !queryReturnBtn) {
        console.warn('Query return functionality not available for this user role');
        return;
    }
    
    // Toggle visibility
    const isVisible = queryReturnSection.style.display !== 'none';
    
    if (isVisible) {
        // Hide the section
        queryReturnSection.style.display = 'none';
        queryReturnBtn.classList.remove('active');
        queryReturnBtn.textContent = 'Query Return';
        
        // Uncheck all checkboxes
        const checkboxes = queryReturnSection.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
    } else {
        // Show the section
        queryReturnSection.style.display = 'block';
        queryReturnBtn.classList.add('active');
        queryReturnBtn.textContent = 'Hide Query Return';
    }
    
    // Refresh the assignable roles in the dropdown based on query return status
    const roleSelect = document.getElementById('modalAssignToRole');
    const currentRole = document.querySelector('.modal-content').getAttribute('data-current-role');
    
    if (roleSelect && currentRole === 'VO') {
        // Get updated assignable roles
        const assignableRoles = getAssignableRoles(currentRole);
        
        // Update the dropdown options
        roleSelect.innerHTML = '<option value="">Select Role</option>';
        assignableRoles.forEach(role => {
            const option = document.createElement('option');
            option.value = role;
            option.textContent = role;
            roleSelect.appendChild(option);
        });
        
        // Reset user selection since role options changed
        const userSelect = document.getElementById('modalAssignToUser');
        if (userSelect) {
            userSelect.innerHTML = '<option value="">Select role first</option>';
            userSelect.disabled = true;
        }
    }
}

// Add visual feedback for query return selection (VO only)
function updateQueryReturnFeedback() {
    const checkboxes = document.querySelectorAll('input[name="query_return_items[]"]');
    const queryReturnBtn = document.querySelector('.btn-query-return');
    
    if (!queryReturnBtn || !checkboxes) return;
    
    let checkedCount = 0;
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            checkedCount++;
        }
    });
    
    if (checkedCount > 0) {
        queryReturnBtn.textContent = `Query Return (${checkedCount} selected)`;
        queryReturnBtn.classList.add('active');
    } else {
        queryReturnBtn.textContent = 'Query Return';
        queryReturnBtn.classList.remove('active');
    }
}

// Initialize checkbox change listeners (VO only)
function initializeQueryReturnCheckboxes() {
    const checkboxes = document.querySelectorAll('input[name="query_return_items[]"]');
    if (checkboxes && checkboxes.length > 0) {
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateQueryReturnFeedback);
        });
    }
}

// Function to refresh dashboard new task count (called from job list when assignments are made)
function refreshDashboardTaskCount() {
    // Check if we're in a main window context and can access parent dashboard functions
    if (typeof window.parent !== 'undefined' && window.parent.fetchNewTaskCount) {
        window.parent.fetchNewTaskCount();
    } else if (typeof fetchNewTaskCount === 'function') {
        fetchNewTaskCount();
    }
}
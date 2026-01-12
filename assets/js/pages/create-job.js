// Create Job Page JavaScript

// Initialize Create Job Page
function initializeCreateJobPage() {
    console.log('Initializing Create Job Page');
    
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize file upload
    initializeFileUpload();
    
    // Initialize form submission
    initializeFormSubmission();
    
    console.log('Create Job Page initialized');
}

// Initialize form validation
function initializeFormValidation() {
    console.log('Initializing form validation');
    
    const form = document.getElementById('createJobForm');
    if (form) {
        form.addEventListener('submit', handleFormSubmission);
        
        // Add field validation
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            field.addEventListener('blur', validateField);
        });
    }
    
    // Connect the Create Job button to form submission
    const createJobBtn = document.getElementById('createJobBtn');
    if (createJobBtn) {
        createJobBtn.addEventListener('click', function(event) {
            event.preventDefault();
            
            const form = document.getElementById('createJobForm');
            if (form) {
                // Trigger form submission
                const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                form.dispatchEvent(submitEvent);
            }
        });
    }
}

// Validate individual field
function validateField(event) {
    const field = event.target;
    const value = field.value.trim();
    
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'This field is required');
    } else {
        clearFieldError(field);
    }
}

// Show field error
function showFieldError(field, message) {
    clearFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
    field.classList.add('error');
}

// Clear field error
function clearFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    field.classList.remove('error');
}

// Initialize file upload
function initializeFileUpload() {
    console.log('Initializing file upload');
    
    const fileInput = document.getElementById('attachment_file');
    const uploadArea = document.getElementById('fileUploadArea');
    
    if (fileInput && uploadArea) {
        // Add event listeners
        fileInput.addEventListener('change', handleFileUpload);
        
        // Add drag and drop functionality
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('dragleave', handleDragLeave);
        uploadArea.addEventListener('drop', handleDrop);
        uploadArea.addEventListener('click', (e) => {
            // Prevent triggering file input if clicking on the input itself
            if (e.target !== fileInput) {
                fileInput.click();
            }
        });
        
        console.log('File upload initialized successfully');
    } else {
        console.error('File upload elements not found:', { fileInput, uploadArea });
    }
}

// Handle file upload
function handleFileUpload(event) {
    const file = event.target.files[0];
    console.log('File upload event triggered', { hasFile: !!file, fileName: file?.name });
    
    if (file) {
        console.log(`File selected: ${file.name}, Size: ${file.size} bytes`);
        
        // Validate file first
        if (validateFile(file)) {
            showFilePreview(file);
            updateUploadArea(true);
            console.log('File validated and preview shown');
        } else {
            // Clear the file input if validation fails
            event.target.value = '';
            updateUploadArea(false);
            console.log('File validation failed, input cleared');
        }
    } else {
        updateUploadArea(false);
        console.log('No file selected');
    }
}

// Handle drag over
function handleDragOver(event) {
    event.preventDefault();
    event.stopPropagation();
    event.currentTarget.classList.add('drag-over');
}

// Handle drag leave
function handleDragLeave(event) {
    event.preventDefault();
    event.stopPropagation();
    event.currentTarget.classList.remove('drag-over');
}

// Handle drop
function handleDrop(event) {
    event.preventDefault();
    event.stopPropagation();
    
    console.log('File drop event triggered');
    
    const uploadArea = event.currentTarget;
    uploadArea.classList.remove('drag-over');
    
    const files = event.dataTransfer.files;
    console.log(`Files dropped: ${files.length}`);
    
    if (files.length > 0) {
        const fileInput = document.getElementById('attachment_file');
        if (fileInput) {
            // Create a new FileList and assign it to the input
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            fileInput.files = dt.files;
            
            // Trigger the change event manually
            const changeEvent = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(changeEvent);
            
            console.log('File assigned to input and change event triggered');
        } else {
            console.error('File input not found for drop handling');
        }
    }
}

// Update upload area appearance
function updateUploadArea(hasFile) {
    const uploadArea = document.getElementById('fileUploadArea');
    console.log('Updating upload area appearance', { hasFile });
    
    if (uploadArea) {
        if (hasFile) {
            uploadArea.classList.add('has-file');
            console.log('Added has-file class to upload area');
        } else {
            uploadArea.classList.remove('has-file');
            console.log('Removed has-file class from upload area');
        }
    } else {
        console.error('Upload area not found for updating appearance');
    }
}

// Show file preview
function showFilePreview(file) {
    const previewContainer = document.getElementById('filePreview');
    console.log('Showing file preview', { fileName: file.name, container: !!previewContainer });
    
    if (previewContainer) {
        previewContainer.style.display = 'block';
        previewContainer.innerHTML = `
            <div class="file-item">
                <span class="file-icon">📎</span>
                <div class="file-info">
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                </div>
                <button type="button" class="remove-file" onclick="removeFile()">×</button>
            </div>
        `;
        console.log('File preview HTML updated');
    } else {
        console.error('File preview container not found');
    }
}

// Remove file
function removeFile() {
    console.log('Removing file');
    
    const fileInput = document.getElementById('attachment_file');
    const previewContainer = document.getElementById('filePreview');
    
    if (fileInput) {
        fileInput.value = '';
        console.log('File input cleared');
    } else {
        console.error('File input not found for removal');
    }
    
    if (previewContainer) {
        previewContainer.style.display = 'none';
        previewContainer.innerHTML = '';
        console.log('File preview cleared');
    } else {
        console.error('File preview container not found for removal');
    }
    
    updateUploadArea(false);
}

// Validate file
function validateFile(file) {
    console.log('Validating file:', { name: file.name, size: file.size, type: file.type });
    
    const maxSize = 10 * 1024 * 1024; // 10MB
    const allowedTypes = ['.pdf', '.doc', '.docx', '.xls', '.xlsx'];
    
    const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
    console.log('File extension:', fileExtension);
    
    if (file.size > maxSize) {
        const errorMsg = 'File size must be less than 10MB';
        console.error('File validation failed:', errorMsg);
        alert(errorMsg);
        return false;
    }
    
    if (!allowedTypes.includes(fileExtension)) {
        const errorMsg = 'Only PDF, DOC, DOCX, XLS, and XLSX files are allowed';
        console.error('File validation failed:', errorMsg);
        alert(errorMsg);
        return false;
    }
    
    console.log('File validation passed');
    return true;
}

// Initialize form submission
function initializeFormSubmission() {
    console.log('Initializing form submission');
    
    // Initialize role selection change handler
    initializeRoleSelection();
}

// Initialize role selection functionality
function initializeRoleSelection() {
    const roleSelect = document.getElementById('assign_to_role');
    if (roleSelect) {
        roleSelect.addEventListener('change', loadUsersForAssignment);
    }
}

// Load users for assignment based on selected role
function loadUsersForAssignment() {
    console.log('Loading users for assignment');
    
    const roleSelect = document.getElementById('assign_to_role');
    const userSelect = document.getElementById('assign_to_user');
    
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

// Handle form submission
function handleFormSubmission(event) {
    event.preventDefault();
    
    console.log('Handling form submission');
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validate form
    if (!validateForm(form)) {
        return;
    }
    
    // Submit form
    submitForm(formData);
}

// Validate entire form
function validateForm(form) {
    let isValid = true;
    
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'This field is required');
            isValid = false;
        }
    });
    
    return isValid;
}

// Submit form using API endpoint
function submitForm(formData) {
    console.log('Submitting form via API');
    
    // Show loading
    const submitButton = document.getElementById('createJobBtn');
    const originalText = submitButton.textContent;
    submitButton.textContent = 'Creating Job...';
    submitButton.disabled = true;
    
    // Submit to API endpoint for proper JSON response
    fetch('api/create_job.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('API response:', data);
        
        if (data.success) {
            alert(data.message || 'Job created successfully!');
            
            // Reset form completely
            resetCreateJobForm();
            
            // Optionally redirect to job list or dashboard
            setTimeout(() => {
                if (typeof loadPage === 'function') {
                    loadPage('job_list.php', 'Job List');
                } else {
                    showDashboard();
                }
            }, 1500);
        } else {
            alert('Error: ' + (data.error || 'Unknown error occurred'));
        }
    })
    .catch(error => {
        console.error('Error creating job:', error);
        alert('Error creating job. Please check your connection and try again.');
    })
    .finally(() => {
        // Reset button
        submitButton.textContent = originalText;
        submitButton.disabled = false;
    });
}

// Reset create job form
function resetCreateJobForm() {
    const form = document.getElementById('createJobForm');
    if (form) {
        form.reset();
        
        // Clear all validation errors
        const errorElements = form.querySelectorAll('.field-error');
        errorElements.forEach(error => error.remove());
        
        const errorFields = form.querySelectorAll('.error');
        errorFields.forEach(field => field.classList.remove('error'));
        
        // Reset file upload
        removeFile();
        
        // Reset user dropdown
        const userSelect = document.getElementById('assign_to_user');
        if (userSelect) {
            userSelect.disabled = true;
            userSelect.innerHTML = '<option value="">Select role first</option>';
        }
        
        console.log('Form reset completed');
    }
}

// Make functions globally available
window.resetCreateJobForm = resetCreateJobForm;
window.removeFile = removeFile;

// Initialize the page when DOM is loaded
document.addEventListener('DOMContentLoaded', initializeCreateJobPage);

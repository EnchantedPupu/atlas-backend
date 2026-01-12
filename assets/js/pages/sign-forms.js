// Sign Forms Page JavaScript Functions

// Global variable to store current survey job ID
let currentSurveyJobId = null;

// View forms for a specific survey job
function viewJobForms(surveyJobId, jobNumber) {
    console.log('Loading forms for survey job:', surveyJobId);
    
    // Store survey job ID for later use
    currentSurveyJobId = surveyJobId;
    
    document.getElementById('modalJobNumber').textContent = jobNumber;
    document.getElementById('formsModal').style.display = 'flex';
    
    // Show loading
    document.getElementById('formsContent').innerHTML = '<div class="loading">Loading forms...</div>';
    
    // Fetch forms for the survey job
    fetch('../api/get_job_forms_for_signing.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'survey_job_id=' + encodeURIComponent(surveyJobId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.grouped_forms && data.grouped_forms.length > 0) {
                displayFormsTree(data.grouped_forms);
            } else {
                displayForms(data.forms);
            }
        } else {
            document.getElementById('formsContent').innerHTML = 
                '<div class="error-message">Error loading forms: ' + (data.message || 'Unknown error') + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('formsContent').innerHTML = 
            '<div class="error-message">Error loading forms. Please try again.</div>';
    });
}

// Display forms in the modal
function displayForms(forms) {
    if (!forms || forms.length === 0) {
        document.getElementById('formsContent').innerHTML = 
            '<div class="no-forms">No forms found for this survey job.</div>';
        return;
    }
    
    let html = '<div class="forms-list">';
    
    forms.forEach(form => {
        const formData = JSON.parse(form.form_data);
        const hasFiSignature = formData.signatures && formData.signatures.fi;
        const statusClass = hasFiSignature ? 'signature-signed' : 'signature-pending';
        const statusText = hasFiSignature ? 'Signed' : 'Pending';
        
        // Extract form_id from form_data JSON, fallback to form_type
        const displayFormId = formData.form_id || formData.form || form.form_type;
        
        html += `
            <div class="form-item">
                <div class="form-details">
                    <div class="form-type">${escapeHtml(displayFormId)}</div>
                    <div class="form-meta">
                        Created: ${formatDate(form.created_at)}
                        ${hasFiSignature ? '<br>Signed: ' + formatDate(formData.signatures.fi.signed_at) : ''}
                    </div>
                </div>
                <div class="form-status">
                    <span class="signature-status ${statusClass}">${statusText}</span>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    document.getElementById('formsContent').innerHTML = html;
}

// Display forms in tree structure grouped by lot number
function displayFormsTree(groupedForms) {
    if (!groupedForms || groupedForms.length === 0) {
        document.getElementById('formsContent').innerHTML = 
            '<div class="no-forms">No forms found for this survey job.</div>';
        return;
    }
    
    let html = '<div class="forms-tree">';
    
    groupedForms.forEach((lotGroup, lotIndex) => {
        const lotId = `lot-${lotIndex}`;
        const completionPercentage = lotGroup.total_forms > 0 ? 
            Math.round((lotGroup.signed_forms / lotGroup.total_forms) * 100) : 0;
        const isComplete = lotGroup.signed_forms === lotGroup.total_forms && lotGroup.total_forms > 0;
        
        html += `
            <div class="lot-group">
                <div class="lot-header" onclick="toggleLotGroup('${lotId}')">
                    <div class="lot-info">
                        <span class="lot-toggle" id="${lotId}-toggle">▼</span>
                        <span class="lot-title">${escapeHtml(lotGroup.lot_number)}</span>
                        <span class="lot-count">(${lotGroup.total_forms} forms)</span>
                    </div>
                    <div class="lot-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${completionPercentage}%"></div>
                        </div>
                        <span class="progress-text">${lotGroup.signed_forms}/${lotGroup.total_forms} signed</span>
                        ${isComplete ? '<span class="completion-badge">✓ Complete</span>' : ''}
                    </div>
                </div>
                <div class="lot-forms" id="${lotId}-forms">
                    <div class="forms-list">
        `;
        
        lotGroup.forms.forEach(form => {
            const formData = JSON.parse(form.form_data);
            const hasFiSignature = formData.signatures && formData.signatures.fi;
            const statusClass = hasFiSignature ? 'signature-signed' : 'signature-pending';
            const statusText = hasFiSignature ? 'Signed' : 'Pending';
            
            // Extract form_id from form_data JSON, fallback to form_type
            const displayFormId = formData.form_id || formData.form || form.form_type;
            
            html += `
                <div class="form-item tree-form-item">
                    <div class="form-details">
                        <div class="form-type">${escapeHtml(displayFormId)}</div>
                        <div class="form-meta">
                            Created: ${formatDate(form.created_at)}
                            ${hasFiSignature ? '<br>Signed: ' + formatDate(formData.signatures.fi.signed_at) : ''}
                        </div>
                    </div>
                    <div class="form-status">
                        <span class="signature-status ${statusClass}">${statusText}</span>
                        ${hasFiSignature ? 
                            `<button class="btn-view-signature" onclick="viewSignature(${form.form_id})">View</button>` :
                            `<button class="btn-sign-form" onclick="signForm(${form.form_id}, '${escapeHtml(displayFormId)}', '${escapeHtml(form.surveyjob_no)}')">Sign</button>`
                        }
                    </div>
                </div>
            `;
        });
        
        html += `
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    document.getElementById('formsContent').innerHTML = html;
}

// Open sign form modal
function signForm(formId, formType, jobNumber) {
    console.log('Opening sign form modal for form:', formId);
    
    document.getElementById('signFormId').value = formId;
    document.getElementById('signFormType').textContent = formType;
    document.getElementById('signJobNumber').textContent = jobNumber;
    
    // Clear previous data
    document.getElementById('signatureData').value = '';
    const stampInput = document.getElementById('stamp_image');
    if (stampInput) {
        stampInput.value = '';
    }
    
    // Close forms modal and open sign modal
    closeFormsModal();
    
    const signModal = document.getElementById('signModal');
    signModal.style.display = 'flex';
    
    console.log('Sign modal displayed, waiting for DOM to settle...');
    
    // Wait for modal to be fully rendered before initializing signature pad
    setTimeout(() => {
        console.log('DOM settled, checking canvas availability...');
        
        const canvas = document.getElementById('signaturePad');
        if (!canvas) {
            console.error('Canvas element not found after modal display');
            return;
        }
        
        console.log('Canvas found, checking visibility...');
        const rect = canvas.getBoundingClientRect();
        console.log('Canvas rect:', rect);
        
        if (rect.width === 0 || rect.height === 0) {
            console.warn('Canvas has zero dimensions, retrying in 500ms...');
            setTimeout(() => {
                initializeSignaturePadSafely();
            }, 500);
        } else {
            console.log('Canvas has proper dimensions, initializing signature pad...');
            initializeSignaturePadSafely();
        }
    }, 100);
}

// Safe signature pad initialization with fallback
function initializeSignaturePadSafely() {
    console.log('Starting safe signature pad initialization...');
    
    const canvas = document.getElementById('signaturePad');
    if (!canvas) {
        console.error('Canvas not found during safe initialization');
        return;
    }
    
    // Force canvas to be visible and properly sized
    canvas.style.display = 'block';
    canvas.style.visibility = 'visible';
    canvas.style.pointerEvents = 'auto';
    
    console.log('Canvas made visible, attempting library load...');
    
    // Try SignaturePad library first
    checkAndLoadSignaturePad().then(() => {
        console.log('SignaturePad library ready, setting up...');
        
        setTimeout(() => {
            try {
                setupSignaturePad();
                console.log('SignaturePad library setup completed');
                
                // Verify it's working
                setTimeout(() => {
                    if (window.signaturePad && typeof window.signaturePad.clear === 'function') {
                        console.log('SignaturePad verified working');
                    } else {
                        console.warn('SignaturePad verification failed, using basic canvas');
                        setupBasicCanvas();
                    }
                }, 100);
                
            } catch (error) {
                console.error('Error setting up SignaturePad library:', error);
                console.log('Falling back to basic canvas');
                setupBasicCanvas();
            }
        }, 100);
        
    }).catch((error) => {
        console.error('Failed to load SignaturePad library:', error);
        console.log('Using basic canvas implementation directly');
        setupBasicCanvas();
    });
}

// Initialize signature pad
function initializeSignaturePad() {
    console.log('Starting signature pad initialization...');
    
    // First check if library is loaded, if not load it
    checkAndLoadSignaturePad().then(() => {
        console.log('SignaturePad library ready, initializing pad...');
        setupSignaturePad();
    }).catch((error) => {
        console.error('Failed to load SignaturePad library:', error);
        console.log('Falling back to basic canvas implementation...');
        setupBasicCanvas();
    });
}

// Setup SignaturePad library implementation
function setupSignaturePad() {
    const canvas = document.getElementById('signaturePad');
    if (!canvas) {
        console.error('Signature pad canvas not found');
        return;
    }
    
    console.log('Setting up SignaturePad library implementation...');
    
    // Clear any existing signature pad
    if (window.signaturePad) {
        try {
            if (typeof window.signaturePad.clear === 'function') {
                window.signaturePad.clear();
            }
            if (typeof window.signaturePad.off === 'function') {
                window.signaturePad.off();
            }
        } catch (e) {
            console.warn('Error cleaning up existing signature pad:', e);
        }
        window.signaturePad = null;
    }
    
    // Get the container and set proper canvas size
    const container = canvas.parentElement;
    const containerRect = container.getBoundingClientRect();
    
    // Set canvas actual size (internal resolution) - ensure minimum size
    canvas.width = Math.max(containerRect.width || 400, 400);
    canvas.height = 200;
    
    // Set CSS display size to match
    canvas.style.width = canvas.width + 'px';
    canvas.style.height = canvas.height + 'px';
    canvas.style.display = 'block';
    canvas.style.visibility = 'visible';
    canvas.style.pointerEvents = 'auto';
    
    console.log('Canvas dimensions:', canvas.width, 'x', canvas.height);
    
    // Initialize new signature pad with proper settings
    try {
        if (typeof SignaturePad === 'undefined') {
            console.error('SignaturePad library not available, falling back to basic canvas');
            setupBasicCanvas();
            return;
        }
        
        window.signaturePad = new SignaturePad(canvas, {
            backgroundColor: '#ffffff',
            penColor: '#000000',
            velocityFilterWeight: 0.7,
            minWidth: 1,
            maxWidth: 3,
            throttle: 0,
            minPointDistance: 0,
        });
        
        // Test if signature pad is working
        console.log('SignaturePad created successfully');
        
        // Clear canvas to ensure white background
        if (typeof window.signaturePad.clear === 'function') {
            window.signaturePad.clear();
        }
        
        // Add event listener for stroke end
        try {
            window.signaturePad.addEventListener('endStroke', function() {
                const dataURL = window.signaturePad.toDataURL('image/png');
                document.getElementById('signatureData').value = dataURL;
                console.log('Signature captured as base64, length:', dataURL.length);
            });
        } catch (e) {
            console.warn('Could not add endStroke event listener, using alternative method');
            
            // Alternative: Use mutation observer to detect changes
            const signatureInput = document.getElementById('signatureData');
            const captureSignature = function() {
                if (window.signaturePad && !window.signaturePad.isEmpty()) {
                    const dataURL = window.signaturePad.toDataURL('image/png');
                    signatureInput.value = dataURL;
                    console.log('Signature captured via alternative method, length:', dataURL.length);
                }
            };
            
            // Set up a periodic capture 
            const captureInterval = setInterval(captureSignature, 1000);
            
            // Also capture on form submission
            const signForm = document.getElementById('signForm');
            if (signForm) {
                signForm.addEventListener('submit', captureSignature);
            }
            
            // Store interval for cleanup
            window.signatureCaptureInterval = captureInterval;
        }
        
    } catch (error) {
        console.error('Error setting up SignaturePad library:', error);
        console.log('Falling back to basic canvas');
        setupBasicCanvas();
    }
}

// Setup basic canvas implementation
function setupBasicCanvas() {
    const canvas = document.getElementById('signaturePad');
    if (!canvas) {
        console.error('Canvas element not found in setupBasicCanvas');
        return;
    }
    
    console.log('Setting up basic canvas implementation...');
    
    // Clear any existing event listeners before initializing
    const newCanvas = canvas.cloneNode(true);
    canvas.parentNode.replaceChild(newCanvas, canvas);
    
    initializeBasicCanvas(newCanvas);
}

// Fallback basic canvas drawing functionality
function initializeBasicCanvas(canvas) {
    console.log('Initializing basic canvas drawing...');
    
    if (!canvas) {
        console.error('Invalid canvas element provided to initializeBasicCanvas');
        return;
    }
    
    // Force canvas dimensions to ensure it's properly sized
    canvas.width = 400;
    canvas.height = 200;
    
    // Set CSS dimensions to match
    canvas.style.width = '400px';
    canvas.style.height = '200px';
    
    console.log('Canvas setup - Width:', canvas.width, 'Height:', canvas.height);
    
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        console.error('Could not get 2D context from canvas');
        return;
    }
    
    // Set canvas properties for drawing
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    
    // Clear canvas and set white background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // Store drawing state in canvas object to avoid global variables
    canvas.isDrawing = false;
    canvas.lastX = 0;
    canvas.lastY = 0;
    
    // Improved coordinate calculation
    function getCanvasCoordinates(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        
        if (e.type.includes('touch')) {
            const touch = e.touches[0] || e.changedTouches[0];
            return {
                x: (touch.clientX - rect.left) * scaleX,
                y: (touch.clientY - rect.top) * scaleY
            };
        } else {
            return {
                x: (e.clientX - rect.left) * scaleX,
                y: (e.clientY - rect.top) * scaleY
            };
        }
    }
    
    function startDrawing(e) {
        e.preventDefault();
        const coords = getCanvasCoordinates(e);
        canvas.isDrawing = true;
        canvas.lastX = coords.x;
        canvas.lastY = coords.y;
        console.log('Drawing started at', coords.x, coords.y);
    }
    
    function draw(e) {
        if (!canvas.isDrawing) return;
        e.preventDefault();
        
        const coords = getCanvasCoordinates(e);
        ctx.beginPath();
        ctx.moveTo(canvas.lastX, canvas.lastY);
        ctx.lineTo(coords.x, coords.y);
        ctx.stroke();
        
        canvas.lastX = coords.x;
        canvas.lastY = coords.y;
    }
    
    function stopDrawing(e) {
        if (!canvas.isDrawing) return;
        e.preventDefault();
        canvas.isDrawing = false;
        captureSignature();
    }
    
    function captureSignature() {
        try {
            const signatureData = canvas.toDataURL('image/png');
            document.getElementById('signatureData').value = signatureData;
            console.log('Basic canvas signature captured as base64, length:', signatureData.length);
        } catch (error) {
            console.error('Failed to capture signature data:', error);
        }
    }
    
    // Add all event listeners with named functions
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    canvas.addEventListener('touchstart', startDrawing);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('touchend', stopDrawing);
    canvas.addEventListener('touchcancel', stopDrawing);
    
    // Store these functions on the canvas for future reference
    canvas.startDrawing = startDrawing;
    canvas.draw = draw;
    canvas.stopDrawing = stopDrawing;
    canvas.captureSignature = captureSignature;
    
    // Make canvas globally accessible
    window.basicCanvas = canvas;
    
    console.log('Basic canvas drawing initialized successfully');
}

// Resize signature pad to fit container
function resizeSignaturePad() {
    if (window.signaturePad) {
        const canvas = document.getElementById('signaturePad');
        const container = canvas.parentElement;
        const rect = container.getBoundingClientRect();
        
        // Store current signature data
        const signatureData = window.signaturePad.toDataURL();
        
        // Resize canvas
        canvas.width = rect.width;
        canvas.height = 200;
        
        // Reinitialize signature pad
        window.signaturePad.clear();
        
        // Restore signature if it existed
        if (signatureData && signatureData !== 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==') {
            window.signaturePad.fromDataURL(signatureData);
        }
    }
}

// Clear signature
function clearSignature() {
    console.log('Clearing signature...');
    
    if (window.signaturePad) {
        if (typeof window.signaturePad.clear === 'function') {
            window.signaturePad.clear();
            console.log('Signature cleared using SignaturePad');
        } else {
            console.log('SignaturePad clear method not available, using manual clear');
            manualClearCanvas();
        }
    } else {
        console.log('No signature pad available, attempting manual clear');
        manualClearCanvas();
    }
    
    // Always clear the hidden input
    const signatureInput = document.getElementById('signatureData');
    if (signatureInput) {
        signatureInput.value = '';
    }
}

// Manual canvas clearing function
function manualClearCanvas() {
    const canvas = document.getElementById('signaturePad');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        // Clear the canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        // Set white background
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        console.log('Canvas cleared manually');
        return true;
    }
    console.error('Canvas not found for manual clear');
    return false;
}

// View existing signature
function viewSignature(formId) {
    // This could be expanded to show signature details
    console.log('Viewing signature for form:', formId);
    alert('Signature viewing functionality can be expanded here.');
}

// Toggle lot group expansion
function toggleLotGroup(lotId) {
    const formsContainer = document.getElementById(`${lotId}-forms`);
    const toggle = document.getElementById(`${lotId}-toggle`);
    
    if (formsContainer && toggle) {
        if (formsContainer.style.display === 'none') {
            formsContainer.style.display = 'block';
            toggle.textContent = '▼';
        } else {
            formsContainer.style.display = 'none';
            toggle.textContent = '▶';
        }
    }
}

// Close forms modal
function closeFormsModal() {
    document.getElementById('formsModal').style.display = 'none';
}

// Close sign modal
function closeSignModal() {
    document.getElementById('signModal').style.display = 'none';
    
    // Clean up signature pad
    if (window.signaturePad) {
        try {
            if (typeof window.signaturePad.clear === 'function') {
                window.signaturePad.clear();
            }
            if (typeof window.signaturePad.off === 'function') {
                window.signaturePad.off(); // Remove event listeners if available
            }
        } catch (error) {
            console.warn('Error during signature pad cleanup:', error);
        }
        window.signaturePad = null;
    }
    
    // Clear the signature data input
    const signatureInput = document.getElementById('signatureData');
    if (signatureInput) {
        signatureInput.value = '';
    }
    
    // Clear the stamp image input
    const stampInput = document.getElementById('stamp_image');
    if (stampInput) {
        stampInput.value = '';
    }
    
    console.log('Sign modal closed and cleaned up');
}

// Format date string
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB') + ' ' + date.toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'});
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        if (e.target.id === 'formsModal') {
            closeFormsModal();
        } else if (e.target.id === 'signModal') {
            closeSignModal();
        }
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFormsModal();
        closeSignModal();
    }
});

// Global initialization function for dashboard
window.initializeSignFormsPage = function() {
    console.log('Initializing Sign Forms page via dashboard');
    
    // Set up form submission handler
    const signForm = document.getElementById('signForm');
    if (signForm) {
        signForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            
            const signatureData = document.getElementById('signatureData').value;
            if (!signatureData || signatureData === '') {
                alert('Please provide your signature before submitting.');
                return false;
            }
            
            // Submit form via AJAX to avoid page redirect
            submitSignForm();
        });
    }
    
    // Check and load SignaturePad library
    checkAndLoadSignaturePad();
};

// Function to submit sign form via AJAX
function submitSignForm() {
    const form = document.getElementById('signForm');
    const formData = new FormData(form);
    
    console.log('Submitting sign form via AJAX...');
    
    // Show loading state
    const submitButton = form.querySelector('.btn-sign-submit');
    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Signing...';
    
    fetch('../../../api/sign_form.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Success - show message and close modal
            alert('Form signed successfully!');
            closeSignModal();
            
            // Refresh the forms list to show updated status
            const modalJobNumber = document.getElementById('modalJobNumber').textContent;
            if (currentSurveyJobId) {
                viewJobForms(currentSurveyJobId, modalJobNumber);
            }
        } else {
            // Error - show error message
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error submitting form:', error);
        alert('Error submitting form. Please try again.');
    })
    .finally(() => {
        // Restore button state
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    });
}

// Helper function to get current survey job ID
function getCurrentSurveyJobId() {
    return currentSurveyJobId;
}

// Function to check and load SignaturePad library
function checkAndLoadSignaturePad() {
    // If SignaturePad is already available, resolve immediately
    if (typeof SignaturePad !== 'undefined') {
        console.log('SignaturePad library is already available');
        return Promise.resolve();
    }
    
    console.log('SignaturePad library not loaded, attempting to load dynamically...');
    
    return new Promise((resolve, reject) => {
        // Check if script is already being loaded
        const existingScript = document.querySelector('script[src*="signature_pad"]');
        if (existingScript) {
            console.log('SignaturePad script already exists, waiting for load...');
            
            // If script is still loading, set up handlers
            if (existingScript.onload) {
                const originalOnload = existingScript.onload;
                existingScript.onload = () => {
                    originalOnload();
                    resolve();
                };
                return;
            }
            
            // Script might have loaded but SignaturePad not initialized
            if (typeof SignaturePad === 'undefined') {
                // Wait a bit and check again
                setTimeout(() => {
                    if (typeof SignaturePad !== 'undefined') {
                        resolve();
                    } else {
                        // Script loaded but no SignaturePad, try loading again
                        loadScript();
                    }
                }, 100);
            } else {
                resolve();
            }
            return;
        }
        
        // Load new script
        function loadScript() {
            // Primary source
            const primarySrc = 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js';
            // Backup source
            const backupSrc = 'https://cdnjs.cloudflare.com/ajax/libs/signature_pad/4.0.0/signature_pad.umd.min.js';
            
            const script = document.createElement('script');
            script.async = true;
            script.src = primarySrc;
            
            script.onload = function() {
                console.log('SignaturePad library loaded from primary source');
                setTimeout(() => {
                    if (typeof SignaturePad !== 'undefined') {
                        resolve();
                    } else {
                        console.warn('SignaturePad not available after load, trying backup source');
                        loadBackup();
                    }
                }, 100);
            };
            
            script.onerror = function() {
                console.error('Failed to load SignaturePad from primary source');
                loadBackup();
            };
            
            function loadBackup() {
                const backupScript = document.createElement('script');
                backupScript.async = true;
                backupScript.src = backupSrc;
                
                backupScript.onload = function() {
                    console.log('SignaturePad library loaded from backup source');
                    setTimeout(() => {
                        if (typeof SignaturePad !== 'undefined') {
                            resolve();
                        } else {
                            reject(new Error('SignaturePad not available after backup load'));
                        }
                    }, 100);
                };
                
                backupScript.onerror = function() {
                    console.error('Failed to load SignaturePad from all sources');
                    reject(new Error('Could not load SignaturePad library'));
                };
                
                document.head.appendChild(backupScript);
            }
            
            document.head.appendChild(script);
        }
        
        // Start loading
        loadScript();
    });
}

// Note: Test functions were removed as they are no longer needed
// The signature functionality is now working correctly

// Expose all functions globally for dashboard integration
window.toggleLotGroup = toggleLotGroup;
window.initializeSignaturePad = initializeSignaturePad;
window.initializeSignaturePadSafely = initializeSignaturePadSafely;
window.setupSignaturePad = setupSignaturePad;
window.setupBasicCanvas = setupBasicCanvas;
window.checkAndLoadSignaturePad = checkAndLoadSignaturePad;
window.clearSignature = clearSignature;
window.manualClearCanvas = manualClearCanvas;
window.resizeSignaturePad = resizeSignaturePad;
window.signForm = signForm;
window.viewJobForms = viewJobForms;
window.closeFormsModal = closeFormsModal;
window.closeSignModal = closeSignModal;
window.viewSignature = viewSignature;

// Finalize the implementation with improved signature handling
(function() {
    // Ensure canvas is properly initialized
    function ensureCanvasIsReady() {
        const canvas = document.getElementById('signaturePad');
        if (!canvas) return false;
        
        // Make sure canvas is visible and interactive
        canvas.style.display = 'block';
        canvas.style.visibility = 'visible';
        canvas.style.pointerEvents = 'auto';
        
        // Set minimum size if needed
        if (canvas.width < 300 || canvas.height < 150) {
            canvas.width = 400;
            canvas.height = 200;
            canvas.style.width = '400px';
            canvas.style.height = '200px';
        }
        
        return true;
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Initializing signature functionality on DOMContentLoaded');
        
        // Preload the SignaturePad library
        checkAndLoadSignaturePad().catch(e => {
            console.warn('SignaturePad library preload failed:', e);
        });
        
        // Listen for form display
        const formsModal = document.getElementById('formsModal');
        if (formsModal) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'style' && 
                        formsModal.style.display === 'flex') {
                        console.log('Forms modal displayed, preparing signature pad');
                    }
                });
            });
            
            observer.observe(formsModal, { attributes: true });
        }
        
        // Make sure signature pad works on mobile
        const signModal = document.getElementById('signModal');
        if (signModal) {
            signModal.addEventListener('touchstart', function(e) {
                if (e.target === signModal) {
                    e.preventDefault();
                }
            }, { passive: false });
        }
        
        // Add automatic resize handler
        window.addEventListener('resize', function() {
            if (window.signaturePad) {
                resizeSignaturePad();
            }
        });
    });
})();

console.log('Sign forms JavaScript loaded successfully - production implementation');

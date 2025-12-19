// File name display
const fileInput = document.getElementById('material_file');
const fileNameDisplay = document.getElementById('file-name');

if (fileInput && fileNameDisplay) {
    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            fileNameDisplay.textContent = this.files[0].name;
        } else {
            fileNameDisplay.textContent = 'Choose a file...';
        }
    });
}

// Load subjects based on course and semester selection
const courseSelect = document.getElementById('course_id');
const semesterSelect = document.getElementById('sem_id');
const subjectSelect = document.getElementById('subject_id');

function loadSubjects() {
    const courseId = courseSelect.value;
    const semId = semesterSelect.value;
    
    // Clear current subjects
    subjectSelect.innerHTML = '<option value="">Select Subject</option>';
    
    if (!courseId || !semId) {
        return;
    }
    
    // Show loading state
    subjectSelect.innerHTML = '<option value="">Loading subjects...</option>';
    subjectSelect.disabled = true;
    
    // Fetch subjects via AJAX
    fetch(`get_subjects.php?course_id=${courseId}&sem_id=${semId}`)
        .then(response => response.json())
        .then(data => {
            subjectSelect.disabled = false;
            
            if (data.success && data.subjects && data.subjects.length > 0) {
                subjectSelect.innerHTML = '<option value="">Select Subject</option>';
                
                data.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.sub_id;
                    option.textContent = subject.sub_name;
                    subjectSelect.appendChild(option);
                });
            } else {
                subjectSelect.innerHTML = '<option value="">No subjects available</option>';
            }
        })
        .catch(error => {
            console.error('Error loading subjects:', error);
            subjectSelect.disabled = false;
            subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
        });
}

// Attach event listeners
if (courseSelect && semesterSelect && subjectSelect) {
    courseSelect.addEventListener('change', loadSubjects);
    semesterSelect.addEventListener('change', loadSubjects);
    
    // Load subjects on page load if course and semester are already selected
    if (courseSelect.value && semesterSelect.value) {
        loadSubjects();
    }
}

// Approval Modal Functions
function openApprovalModal(materialId, currentStatus, currentRemarks) {
    document.getElementById('modal_material_id').value = materialId;
    document.getElementById('modal_approval_status').value = currentStatus;
    document.getElementById('modal_remarks').value = currentRemarks;
    document.getElementById('approvalModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeApprovalModal() {
    document.getElementById('approvalModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
document.getElementById('approvalModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeApprovalModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeApprovalModal();
    }
});

// Delete confirmation
function confirmDelete(materialId) {
    if (confirm('Are you sure you want to delete this material? This action cannot be undone.')) {
        window.location.href = `study_material.php?delete=${materialId}`;
    }
}

// Notification close function
function closeNotification() {
    const overlay = document.getElementById('notificationOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        
        // Remove URL parameters after closing notification
        const url = new URL(window.location);
        url.searchParams.delete('success');
        url.searchParams.delete('deleted');
        url.searchParams.delete('updated');
        window.history.replaceState({}, document.title, url);
    }
}

// Auto-close notification after 5 seconds
const notificationOverlay = document.getElementById('notificationOverlay');
if (notificationOverlay && notificationOverlay.classList.contains('active')) {
    setTimeout(() => {
        closeNotification();
    }, 5000);
}
// Real-time Form Validation for Add Staff
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('staffForm');
    const staffTypeSelect = document.getElementById('staff_type');
    const teachingFields = document.getElementById('teaching-fields');
    const nonTeachingFields = document.getElementById('non-teaching-fields');
    const isCoordinatorCheckbox = document.getElementById('is_coordinator');
    const coordinatorCourseField = document.getElementById('coordinator-course-field');

    // Validation Functions
    const validators = {
        id_number: {
            validate: (value) => value.trim().length > 0,
            message: 'ID Number is required'
        },
full_name: {
    validate: (value) => {
        const trimmedValue = value.trim();
        // Check if name is not empty and contains no numbers
        const hasNumbers = /\d/.test(trimmedValue);
        
        if (trimmedValue.length === 0) {
            validators.full_name.message = 'Full Name is required';
            return false;
        }
        
        if (hasNumbers) {
            validators.full_name.message = 'Full Name cannot contain numbers';
            return false;
        }
        
        return true;
    },
    message: 'Full Name is required'
},
        email: {
            validate: (value) => {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(value);
            },
            message: 'Please enter a valid email address (e.g., user@example.com)'
        },
        password: {
            validate: (value) => value.length >= 6,
            message: 'Password must be at least 6 characters long'
        },
        gender: {
            validate: (value) => value !== '',
            message: 'Please select a gender'
        },
        date_of_birth: {
            validate: (value) => {
                if (!value) return false;
                const selectedDate = new Date(value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                return selectedDate <= today;
            },
            message: 'Date of birth cannot be in the future'
        },
        contact_number: {
            validate: (value) => {
                const phoneRegex = /^\d{10}$/;
                return phoneRegex.test(value.trim());
            },
            message: 'Contact number must be exactly 10 digits'
        },
        address: {
            validate: (value) => {
                return value.trim().length > 0;
            },
            message: 'Address is required'
        },
        staff_type: {
            validate: (value) => value !== '',
            message: 'Please select a staff type'
        },
        status: {
            validate: (value) => value !== '',
            message: 'Please select a status'
        },
        profile_photo: {
            validate: (input) => {
                if (!input.files || input.files.length === 0) {
                    return false;
                }
                const file = input.files[0];
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!allowedTypes.includes(file.type)) {
                    validators.profile_photo.message = 'Only JPG, PNG, or GIF images are allowed';
                    return false;
                }
                if (file.size > maxSize) {
                    validators.profile_photo.message = 'File size must be less than 5MB';
                    return false;
                }
                return true;
            },
            message: 'Please upload a valid profile photo'
        }
    };

    // Show error message
    function showError(fieldId, message) {
        const errorDiv = document.getElementById(`${fieldId}-error`);
        const field = document.getElementById(fieldId) || document.querySelector(`[name="${fieldId}"]`);
        
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.classList.add('show');
        }
        
        if (field) {
            field.classList.add('input-error');
            field.classList.remove('input-success');
        }
    }

    // Hide error message
    function hideError(fieldId) {
        const errorDiv = document.getElementById(`${fieldId}-error`);
        const field = document.getElementById(fieldId) || document.querySelector(`[name="${fieldId}"]`);
        
        if (errorDiv) {
            errorDiv.classList.remove('show');
        }
        
        if (field) {
            field.classList.remove('input-error');
            field.classList.add('input-success');
        }
    }

    // Validate single field
    function validateField(fieldId, value = null) {
        const field = document.getElementById(fieldId);
        const validator = validators[fieldId];
        
        if (!validator) return true;
        
        const fieldValue = value !== null ? value : (field ? field.value : '');
        
        // Special handling for file input
        if (fieldId === 'profile_photo') {
            const isValid = validator.validate(field);
            if (!isValid) {
                showError(fieldId, validator.message);
            } else {
                hideError(fieldId);
            }
            return isValid;
        }
        
        const isValid = validator.validate(fieldValue);
        
        if (!isValid) {
            showError(fieldId, validator.message);
        } else {
            hideError(fieldId);
        }
        
        return isValid;
    }

    // Validate teaching staff selections
    function validateTeachingSelections() {
        const staffType = staffTypeSelect.value;
        
        if (staffType !== 'Teaching') return true;  // Changed from 'teaching'
        
        let isValid = true;
        
        // Validate courses
        const courseCheckboxes = document.querySelectorAll('.course-checkbox:checked');
        if (courseCheckboxes.length === 0) {
            showError('course', 'Please select at least one course for teaching staff');
            isValid = false;
        } else {
            hideError('course');
        }
        
        // Validate semesters
        const semesterCheckboxes = document.querySelectorAll('.semester-checkbox:checked');
        if (semesterCheckboxes.length === 0) {
            showError('semester', 'Please select at least one semester for teaching staff');
            isValid = false;
        } else {
            hideError('semester');
        }
        
        return isValid;
    }

    // Validate non-teaching staff coordinator selection
    function validateNonTeachingSelections() {
        const staffType = staffTypeSelect.value;
        
        if (staffType !== 'Non Teaching') return true;  // Changed from 'non_teaching'
        
        const isCoordinator = isCoordinatorCheckbox.checked;
        
        // Only validate coordinator course if checkbox is checked
        if (isCoordinator) {
            const coordinatorCourse = document.getElementById('coordinator_course').value;
            if (!coordinatorCourse) {
                showError('coordinator_course', 'Please select a course to coordinate');
                return false;
            } else {
                hideError('coordinator_course');
            }
        }
        
        return true;  // Valid for both coordinator and non-coordinator non-teaching staff
    }

    // Add real-time validation listeners
    Object.keys(validators).forEach(fieldId => {
        const field = document.getElementById(fieldId);
        
        if (field) {
            // Validate on blur
            field.addEventListener('blur', () => {
                validateField(fieldId);
            });
            
            // Validate on input for immediate feedback
            if (field.type !== 'file') {
                field.addEventListener('input', () => {
                    // Only validate if user has already interacted
                    const errorDiv = document.getElementById(`${fieldId}-error`);
                    if (errorDiv && errorDiv.classList.contains('show')) {
                        validateField(fieldId);
                    }
                });
            }
            
            // For file input, validate on change
            if (field.type === 'file') {
                field.addEventListener('change', () => {
                    validateField(fieldId);
                });
            }
        }
    });

    // Staff type change handler
    staffTypeSelect.addEventListener('change', function() {
        const selectedType = this.value;
        
        // Hide all conditional fields first
        teachingFields.style.display = 'none';
        nonTeachingFields.style.display = 'none';
        
        // Clear previous errors
        hideError('course');
        hideError('semester');
        hideError('coordinator_course');
        
        if (selectedType === 'Teaching') {  // Changed from 'teaching'
            teachingFields.style.display = 'block';
            
            // Enable teaching checkboxes
            document.querySelectorAll('.course-checkbox, .semester-checkbox').forEach(cb => {
                cb.disabled = false;
            });
            
            // Reset non-teaching fields
            isCoordinatorCheckbox.checked = false;
            document.getElementById('coordinator_course').value = '';
            coordinatorCourseField.style.display = 'none';
            
        } else if (selectedType === 'Non Teaching') {  // Changed from 'non_teaching'
            nonTeachingFields.style.display = 'block';
            
            // Disable and uncheck teaching checkboxes
            document.querySelectorAll('.course-checkbox, .semester-checkbox').forEach(cb => {
                cb.checked = false;
                cb.disabled = true;
            });
        }
        
        // Validate staff type field
        validateField('staff_type');
    });

    // Coordinator checkbox handler
    isCoordinatorCheckbox.addEventListener('change', function() {
        const coordinatorCourseSelect = document.getElementById('coordinator_course');
        
        if (this.checked) {
            coordinatorCourseField.style.display = 'block';
            coordinatorCourseSelect.required = true;
        } else {
            coordinatorCourseField.style.display = 'none';
            coordinatorCourseSelect.required = false;
            coordinatorCourseSelect.value = '';
            hideError('coordinator_course');
        }
    });

    // Real-time validation for course checkboxes
    document.querySelectorAll('.course-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            if (staffTypeSelect.value === 'Teaching') {  // Changed from 'teaching'
                validateTeachingSelections();
            }
        });
    });

    // Real-time validation for semester checkboxes
    document.querySelectorAll('.semester-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            if (staffTypeSelect.value === 'Teaching') {  // Changed from 'teaching'
                validateTeachingSelections();
            }
        });
    });

    // Coordinator course change validation
    document.getElementById('coordinator_course').addEventListener('change', function() {
        if (isCoordinatorCheckbox.checked) {
            validateNonTeachingSelections();
        }
    });

    // Form submission handler - FIXED VERSION
    form.addEventListener('submit', function(e) {
        // Validate all fields
        let isFormValid = true;
        
        // Validate basic fields
        Object.keys(validators).forEach(fieldId => {
            if (!validateField(fieldId)) {
                isFormValid = false;
            }
        });
        
        // Validate staff type specific fields
        const staffType = staffTypeSelect.value;
        
        if (staffType === 'Teaching') {  // Changed from 'teaching'
            if (!validateTeachingSelections()) {
                isFormValid = false;
            }
        } else if (staffType === 'Non Teaching') {  // Changed from 'non_teaching'
            if (!validateNonTeachingSelections()) {
                isFormValid = false;
            }
        }
        
        // Only prevent submission if form is invalid
        if (!isFormValid) {
            e.preventDefault();  // Stop form submission only if invalid
            
            // Scroll to first error
            const firstError = document.querySelector('.error-message.show');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Show alert
            alert('Please fix all errors before submitting the form.');
        }
        // If valid, do nothing - let the form submit naturally to PHP
    });

    // Password strength indicator (optional enhancement)
    const passwordField = document.getElementById('password');
    passwordField.addEventListener('input', function() {
        const strength = calculatePasswordStrength(this.value);
        // You can add visual feedback here if desired
    });

    function calculatePasswordStrength(password) {
        let strength = 0;
        if (password.length >= 6) strength++;
        if (password.length >= 10) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z\d]/.test(password)) strength++;
        return strength;
    }

    // Contact number formatting
    const contactField = document.getElementById('contact_number');
    contactField.addEventListener('input', function() {
        // Remove non-numeric characters
        this.value = this.value.replace(/\D/g, '');
        // Limit to 10 digits
        if (this.value.length > 10) {
            this.value = this.value.slice(0, 10);
        }
    });

    // Prevent future dates in date of birth
    const dobField = document.getElementById('date_of_birth');
    const today = new Date().toISOString().split('T')[0];
    dobField.setAttribute('max', today);
});
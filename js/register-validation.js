// Register Form Validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // Validation state tracker
    const validationState = {
        full_name: false,
        id_number: false,
        gender: false,
        dob: false,
        course_id: false,
        sem_id: false,
        contact_number: false,
        address: false,
        email: false,
        password: false,
        confirm_password: false,
        profile_photo: false
    };

    // Utility function to show error
    function showError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const errorSpan = document.getElementById(`error-${fieldId}`);
        
        if (field && errorSpan) {
            field.classList.add('invalid');
            field.classList.remove('valid');
            errorSpan.textContent = message;
            errorSpan.style.display = 'block';
        }
        validationState[fieldId] = false;
    }

    // Utility function to clear error
    function clearError(fieldId) {
        const field = document.getElementById(fieldId);
        const errorSpan = document.getElementById(`error-${fieldId}`);
        
        if (field && errorSpan) {
            field.classList.remove('invalid');
            field.classList.add('valid');
            errorSpan.textContent = '';
            errorSpan.style.display = 'none';
        }
        validationState[fieldId] = true;
    }

    // Full Name Validation (letters and spaces only)
    const fullNameInput = document.getElementById('full_name');
    fullNameInput.addEventListener('input', function() {
        const value = this.value.trim();
        const namePattern = /^[a-zA-Z\s]+$/;
        
        if (value === '') {
            showError('full_name', 'Full name is required');
        } else if (!namePattern.test(value)) {
            showError('full_name', 'Full name must contain only letters and spaces');
        } else {
            clearError('full_name');
        }
    });

    fullNameInput.addEventListener('blur', function() {
        if (this.value.trim() === '') {
            showError('full_name', 'Full name is required');
        }
    });

    // Student ID Validation
    const idNumberInput = document.getElementById('id_number');
    idNumberInput.addEventListener('input', function() {
        const value = this.value.trim();
        
        if (value === '') {
            showError('id_number', 'Student ID is required');
        } else {
            clearError('id_number');
        }
    });

    idNumberInput.addEventListener('blur', function() {
        if (this.value.trim() === '') {
            showError('id_number', 'Student ID is required');
        }
    });

    // Gender Validation
    const genderSelect = document.getElementById('gender');
    genderSelect.addEventListener('change', function() {
        if (this.value === '') {
            showError('gender', 'Please select your gender');
        } else {
            clearError('gender');
        }
    });

    // Date of Birth Validation (must be at least 17 years old)
    const dobInput = document.getElementById('dob');
    dobInput.addEventListener('change', function() {
        const birthDate = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        if (this.value === '') {
            showError('dob', 'Date of birth is required');
        } else if (age < 17) {
            showError('dob', 'You must be at least 17 years old to register');
        } else {
            clearError('dob');
        }
    });

    dobInput.addEventListener('blur', function() {
        if (this.value === '') {
            showError('dob', 'Date of birth is required');
        }
    });

    // Course Validation
    const courseSelect = document.getElementById('course_id');
    courseSelect.addEventListener('change', function() {
        if (this.value === '') {
            showError('course_id', 'Please select a course');
        } else {
            clearError('course_id');
        }
    });

    // Semester Validation
    const semesterSelect = document.getElementById('sem_id');
    semesterSelect.addEventListener('change', function() {
        if (this.value === '') {
            showError('sem_id', 'Please select a semester');
        } else {
            clearError('sem_id');
        }
    });

    // Contact Number Validation (exactly 10 digits)
    const contactInput = document.getElementById('contact_number');
    contactInput.addEventListener('input', function() {
        // Allow only digits
        this.value = this.value.replace(/\D/g, '');
        
        const value = this.value;
        
        if (value === '') {
            showError('contact_number', 'Contact number is required');
        } else if (value.length < 10) {
            showError('contact_number', 'Contact number must be exactly 10 digits');
        } else if (value.length > 10) {
            showError('contact_number', 'Contact number must be exactly 10 digits');
            this.value = value.substring(0, 10);
        } else {
            clearError('contact_number');
        }
    });

    contactInput.addEventListener('blur', function() {
        if (this.value === '') {
            showError('contact_number', 'Contact number is required');
        } else if (this.value.length !== 10) {
            showError('contact_number', 'Contact number must be exactly 10 digits');
        }
    });

    // Address Validation (letters and spaces only)
    const addressInput = document.getElementById('address');
    addressInput.addEventListener('input', function() {
        const value = this.value.trim();
        const addressPattern = /^[a-zA-Z\s]+$/;
        
        if (value === '') {
            showError('address', 'Address is required');
        } else if (!addressPattern.test(value)) {
            showError('address', 'Address must contain only letters and spaces');
        } else {
            clearError('address');
        }
    });

    addressInput.addEventListener('blur', function() {
        if (this.value.trim() === '') {
            showError('address', 'Address is required');
        }
    });

    // Email Validation
    const emailInput = document.getElementById('email');
    emailInput.addEventListener('input', function() {
        const value = this.value.trim();
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (value === '') {
            showError('email', 'Email address is required');
        } else if (!emailPattern.test(value)) {
            showError('email', 'Please enter a valid email address (e.g., example@gmail.com)');
        } else {
            clearError('email');
        }
    });

    emailInput.addEventListener('blur', function() {
        if (this.value.trim() === '') {
            showError('email', 'Email address is required');
        }
    });

    // Password Validation (at least 6 characters, contains letters and numbers)
    const passwordInput = document.getElementById('password');
    const strengthDiv = document.getElementById('password-strength');
    
    passwordInput.addEventListener('input', function() {
        const value = this.value;
        const hasLetters = /[a-zA-Z]/.test(value);
        const hasNumbers = /[0-9]/.test(value);
        
        if (value === '') {
            showError('password', 'Password is required');
            strengthDiv.textContent = '';
            strengthDiv.className = 'password-strength';
        } else if (value.length < 6) {
            showError('password', 'Password must be at least 6 characters long');
            strengthDiv.textContent = 'Too short';
            strengthDiv.className = 'password-strength weak';
        } else if (!hasLetters || !hasNumbers) {
            showError('password', 'Password must contain both letters and numbers');
            strengthDiv.textContent = 'Weak';
            strengthDiv.className = 'password-strength weak';
        } else {
            clearError('password');
            if (value.length >= 10 && hasLetters && hasNumbers) {
                strengthDiv.textContent = 'Strong';
                strengthDiv.className = 'password-strength strong';
            } else {
                strengthDiv.textContent = 'Good';
                strengthDiv.className = 'password-strength medium';
            }
        }
        
        // Re-validate confirm password if it has a value
        const confirmPassword = document.getElementById('confirm_password');
        if (confirmPassword.value !== '') {
            confirmPassword.dispatchEvent(new Event('input'));
        }
    });

    passwordInput.addEventListener('blur', function() {
        if (this.value === '') {
            showError('password', 'Password is required');
        }
    });

    // Confirm Password Validation
    const confirmPasswordInput = document.getElementById('confirm_password');
    confirmPasswordInput.addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;
        
        if (confirmPassword === '') {
            showError('confirm_password', 'Please confirm your password');
        } else if (password !== confirmPassword) {
            showError('confirm_password', 'Passwords do not match');
        } else {
            clearError('confirm_password');
        }
    });

    confirmPasswordInput.addEventListener('blur', function() {
        if (this.value === '') {
            showError('confirm_password', 'Please confirm your password');
        }
    });

    // Profile Photo Validation
    const profilePhotoInput = document.getElementById('profile_photo');
    const fileNameSpan = document.getElementById('file-name');
    
    profilePhotoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if (!file) {
            showError('profile_photo', 'Profile picture is required');
            fileNameSpan.textContent = 'Choose a file';
        } else {
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!allowedTypes.includes(file.type)) {
                showError('profile_photo', 'Only JPG, JPEG, PNG, or GIF files are allowed');
                fileNameSpan.textContent = 'Choose a file';
                this.value = '';
            } else if (file.size > maxSize) {
                showError('profile_photo', 'File size must be less than 5MB');
                fileNameSpan.textContent = 'Choose a file';
                this.value = '';
            } else {
                clearError('profile_photo');
                fileNameSpan.textContent = file.name;
            }
        }
    });

    // Form submission validation
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Trigger validation on all fields
        fullNameInput.dispatchEvent(new Event('input'));
        idNumberInput.dispatchEvent(new Event('input'));
        genderSelect.dispatchEvent(new Event('change'));
        dobInput.dispatchEvent(new Event('change'));
        courseSelect.dispatchEvent(new Event('change'));
        semesterSelect.dispatchEvent(new Event('change'));
        contactInput.dispatchEvent(new Event('input'));
        addressInput.dispatchEvent(new Event('input'));
        emailInput.dispatchEvent(new Event('input'));
        passwordInput.dispatchEvent(new Event('input'));
        confirmPasswordInput.dispatchEvent(new Event('input'));
        
        // Check profile photo
        if (!profilePhotoInput.files[0]) {
            showError('profile_photo', 'Profile picture is required');
        }
        // Recheck selects and file
        if (genderSelect.value !== '') validationState.gender = true;
        if (courseSelect.value !== '') validationState.course_id = true;
        if (semesterSelect.value !== '') validationState.sem_id = true;
        if (profilePhotoInput.files[0]) validationState.profile_photo = true;

        // Check if all validations passed
        const errors = document.querySelectorAll('.invalid');
        const allValid = errors.length === 0;

        
        if (allValid) {
            // Submit the form
            this.submit();
        } else {
            // Scroll to first error
            const firstError = document.querySelector('.invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
            
            // Show alert
            alert('Please fix all errors before submitting the form.');
        }
    });

    // Prevent form submission on Enter key (except in textarea)
    form.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            return false;
        }
    });
});
<?php 
include("connect.php");

$notification = null;
$notification_type = null;
$redirect_url = null;
$redirect_delay = 3000;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $id_number = mysqli_real_escape_string($conn, trim($_POST['id_number']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $course_id = isset($_POST['course_id']) ? $_POST['course_id'] : '';
    $sem_id = isset($_POST['sem_id']) ? $_POST['sem_id'] : ''; 
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $dob = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    $contact_number = mysqli_real_escape_string($conn, trim($_POST['contact_number']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $role_id = 2;
    
    // Server-side validation
    $errors = [];
    
    // Validate full name (letters and spaces only)
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $full_name)) {
        $errors[] = "Full name must contain only letters and spaces.";
    }
    
    // Validate Student ID
    if (empty($id_number)) {
        $errors[] = "Student ID is required.";
    }
    
    // Validate gender
    if (empty($gender)) {
        $errors[] = "Please select your gender.";
    }
    
    // Validate age (must be at least 17)
    if (empty($dob)) {
        $errors[] = "Date of birth is required.";
    } else {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        if ($age < 17) {
            $errors[] = "You must be at least 17 years old to register.";
        }
    }
    
    // Validate contact number (exactly 10 digits)
    if (empty($contact_number)) {
        $errors[] = "Contact number is required.";
    } elseif (!preg_match("/^\d{10}$/", $contact_number)) {
        $errors[] = "Contact number must be exactly 10 digits.";
    }
    
    // Validate address (letters and spaces only)
    if (empty($address)) {
        $errors[] = "Address is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $address)) {
        $errors[] = "Address must contain only letters and spaces.";
    }
    
    // Validate email format
    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Validate password (at least 6 characters, contains letters and numbers)
    if (empty($password)) {
        $errors[] = "Password is required.";
    } else {
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        if (!preg_match("/[a-zA-Z]/", $password) || !preg_match("/[0-9]/", $password)) {
            $errors[] = "Password must contain both letters and numbers.";
        }
    }
    
    // Validate password match
    if (empty($confirm_password)) {
        $errors[] = "Please confirm your password.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // Validate course and semester selection
    if (empty($course_id)) {
        $errors[] = "Please select a course.";
    }
    if (empty($sem_id)) {
        $errors[] = "Please select a semester.";
    }
    
    // If there are validation errors
    if (!empty($errors)) {
        $notification = implode("<br>", $errors);
        $notification_type = "error";
    } elseif (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $notification = "Profile picture is required to complete registration.";
        $notification_type = "error";
    } else {
        $file_tmp = $_FILES['profile_photo']['tmp_name'];
        $file_name = $_FILES['profile_photo']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];

        if (!in_array($ext, $allowed)) {
            $notification = "Invalid image format. Allowed formats: JPG, JPEG, PNG, GIF.";
            $notification_type = "error";
        } else {
            $new_file_name = uniqid() . "." . $ext;
            $upload_dir = "uploads/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $upload_path = $upload_dir . $new_file_name;

            if (!move_uploaded_file($file_tmp, $upload_path)) {
                $notification = "Failed to upload profile picture. Please try again.";
                $notification_type = "error";
            } else {
                // Check for duplicate email or ID
                $check_sql = "SELECT * FROM users WHERE email='$email' OR id_number='$id_number'";
                $check_result = mysqli_query($conn, $check_sql);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $notification = "Email or ID number already registered. Please use different credentials.";
                    $notification_type = "warning";
                    // Delete uploaded file since registration failed
                    unlink($upload_path);
                } else {
                    $status = 0;
                    $insert_sql = "INSERT INTO users 
                    (full_name, id_number, email, password, role_id, gender, date_of_birth, contact_number, address, course_id, sem_id, profile_photo, status)
                    VALUES 
                    ('$full_name', '$id_number', '$email', '$password', '$role_id', '$gender', '$dob', '$contact_number', '$address','$course_id', '$sem_id', '$new_file_name', '$status')";

                    if (mysqli_query($conn, $insert_sql)) {
                        $notification = "Registration successful! Your account is pending admin approval.";
                        $notification_type = "success";
                        $redirect_url = "login.php";
                        $redirect_delay = 3500;
                    } else {
                        $notification = "An error occurred during registration: " . mysqli_error($conn);
                        $notification_type = "error";
                        // Delete uploaded file since registration failed
                        unlink($upload_path);
                    }
                }
            }
        }
    }
}

// Fetch courses and semesters for dropdown
$query = "SELECT course_id, course_name FROM course";
$result = $conn->query($query);

$semQuery = "SELECT sem_id, sem_name FROM semester";
$semResult = $conn->query($semQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register - College Management System</title>
  <link rel="stylesheet" href="css/register.css" />
  <link rel="icon" href="Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Notification Styles */
.notification-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease-in;
}

.notification-overlay.active {
    display: flex;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.notification-modal {
    background: white;
    border-radius: 12px;
    padding: 40px;
    max-width: 500px;
    width: 90%;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.4s ease-out;
    position: relative;
}

@keyframes slideUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.notification-icon {
    font-size: 60px;
    margin-bottom: 20px;
    animation: bounce 0.6s ease-out;
}

@keyframes bounce {
    0% { transform: scale(0); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notification-modal.success .notification-icon {
    color: #10b981;
}

.notification-modal.success {
    border-left: 5px solid #10b981;
}

.notification-modal.error .notification-icon {
    color: #ef4444;
}

.notification-modal.error {
    border-left: 5px solid #ef4444;
}

.notification-modal.warning .notification-icon {
    color: #f59e0b;
}

.notification-modal.warning {
    border-left: 5px solid #f59e0b;
}

.notification-title {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 10px;
    color: #1f2937;
}

.notification-message {
    font-size: 16px;
    color: #6b7280;
    margin-bottom: 30px;
    line-height: 1.6;
}

.notification-progress {
    height: 3px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 20px;
}

.notification-progress-bar {
    height: 100%;
    animation: progress linear 3s forwards;
    border-radius: 3px;
}

.notification-modal.success .notification-progress-bar {
    background: linear-gradient(90deg, #10b981, #059669);
}

.notification-modal.error .notification-progress-bar {
    background: linear-gradient(90deg, #ef4444, #dc2626);
}

.notification-modal.warning .notification-progress-bar {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

@keyframes progress {
    from { width: 100%; }
    to { width: 0%; }
}

.notification-button {
    margin-top: 20px;
    padding: 10px 30px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.notification-modal.success .notification-button {
    background: #10b981;
    color: white;
}

.notification-modal.success .notification-button:hover {
    background: #059669;
    transform: translateY(-2px);
}

.notification-modal.error .notification-button {
    background: #ef4444;
    color: white;
}

.notification-modal.error .notification-button:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

.notification-modal.warning .notification-button {
    background: #f59e0b;
    color: white;
}

.notification-modal.warning .notification-button:hover {
    background: #d97706;
    transform: translateY(-2px);
}
/* Debug - Make sure notification is visible */
.notification-overlay {
    z-index: 9999 !important;
}

.notification-modal {
    z-index: 10000 !important;
}
  </style>
</head>
<body>

<?php include("header.php"); ?>

<div class="register-container">
  <form id="registerForm" action="register.php" method="post" enctype="multipart/form-data" novalidate>
    <h2><i class="fas fa-user-plus"></i> Student Registration</h2>
    <p class="form-subtitle">Please fill in all required fields to create your account</p>

    <!-- Three-column wrapper -->
    <div class="form-wrapper">
      
      <!-- Column 1: Personal Information -->
      <div class="form-column">
        <h3 class="column-title"><i class="fas fa-user"></i> Personal Information</h3>
        
        <div class="form-group">
          <label for="full_name"><i class="fas fa-signature"></i> Full Name *</label>
          <input type="text" id="full_name" name="full_name" placeholder="Enter your full name" required />
          <span class="error-message" id="error-full_name"></span>
        </div>

        <div class="form-group">
          <label for="id_number"><i class="fas fa-id-card"></i> Student ID *</label>
          <input type="text" id="id_number" name="id_number" placeholder="e.g., STU2024001" required />
          <span class="error-message" id="error-id_number"></span>
        </div>

        <div class="form-group">
          <label for="gender"><i class="fas fa-venus-mars"></i> Gender *</label>
          <select id="gender" name="gender" required>
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Others">Others</option>
          </select>
          <span class="error-message" id="error-gender"></span>
        </div>

        <div class="form-group">
          <label for="dob"><i class="fas fa-calendar-alt"></i> Date of Birth *</label>
          <input type="date" id="dob" name="date_of_birth" required max="<?php echo date('Y-m-d'); ?>" />
          <span class="error-message" id="error-dob"></span>
        </div>
      </div>

      <!-- Column 2: Contact & Academic Information -->
      <div class="form-column">
        <h3 class="column-title"><i class="fas fa-graduation-cap"></i> Academic Information</h3>
        
        <div class="form-group">
          <label for="course_id"><i class="fas fa-book"></i> Select Course *</label>
          <select name="course_id" id="course_id" required>
            <option value="">-- Select Course --</option>
            <?php 
            if ($result && mysqli_num_rows($result) > 0) {
                mysqli_data_seek($result, 0);
                while($row = $result->fetch_assoc()): 
            ?>
              <option value="<?php echo $row['course_id']; ?>">
                <?php echo htmlspecialchars($row['course_name']); ?>
              </option>
            <?php 
                endwhile;
            }
            ?>
          </select>
          <span class="error-message" id="error-course_id"></span>
        </div>

        <div class="form-group">
          <label for="sem_id"><i class="fas fa-list-ol"></i> Select Semester *</label>
          <select name="sem_id" id="sem_id" required>
            <option value="">-- Select Semester --</option>
            <?php 
            if ($semResult && mysqli_num_rows($semResult) > 0) {
                mysqli_data_seek($semResult, 0);
                while($row = $semResult->fetch_assoc()): 
            ?>
              <option value="<?php echo $row['sem_id']; ?>">
                <?php echo htmlspecialchars($row['sem_name']); ?>
              </option>
            <?php 
                endwhile;
            }
            ?>
          </select>
          <span class="error-message" id="error-sem_id"></span>
        </div>

        <div class="form-group">
          <label for="contact_number"><i class="fas fa-phone"></i> Contact Number *</label>
          <input type="tel" id="contact_number" name="contact_number" placeholder="10 digits only" required maxlength="10" />
          <span class="error-message" id="error-contact_number"></span>
        </div>

        <div class="form-group">
          <label for="address"><i class="fas fa-map-marker-alt"></i> Address *</label>
          <input type="text" id="address" name="address" placeholder="Enter your address" required />
          <span class="error-message" id="error-address"></span>
        </div>
      </div>

      <!-- Column 3: Account Security -->
      <div class="form-column">
        <h3 class="column-title"><i class="fas fa-lock"></i> Account Security</h3>
        
        <div class="form-group">
          <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
          <input type="email" id="email" name="email" placeholder="your.email@example.com" required />
          <span class="error-message" id="error-email"></span>
        </div>

        <div class="form-group">
          <label for="password"><i class="fas fa-key"></i> Password *</label>
          <input type="password" id="password" name="password" placeholder="Min. 6 characters" required />
          <div class="password-strength" id="password-strength"></div>
          <span class="error-message" id="error-password"></span>
        </div>

        <div class="form-group">
          <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm Password *</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required />
          <span class="error-message" id="error-confirm_password"></span>
        </div>

        <div class="form-group">
          <label for="profile_photo"><i class="fas fa-camera"></i> Profile Picture *</label>
          <div class="file-input-wrapper">
            <input type="file" id="profile_photo" name="profile_photo" accept="image/*" required />
            <label for="profile_photo" class="file-label">
              <i class="fas fa-cloud-upload-alt"></i>
              <span id="file-name">Choose a file</span>
            </label>
          </div>
          <small class="field-hint">JPG, JPEG, PNG, or GIF format</small>
          <span class="error-message" id="error-profile_photo"></span>
        </div>
      </div>

    </div>

    <div class="form-actions">
      <button type="submit" name="register" id="submitBtn">
        <i class="fas fa-user-plus"></i> Register Account
      </button>
      <p class="login-link">Already have an account? <a href="login.php">Login here</a></p>
    </div>
  </form>
</div>

<!-- Notification Modal -->
<?php if ($notification): ?>
<div class="notification-overlay active" id="notificationOverlay">
    <div class="notification-modal <?php echo htmlspecialchars($notification_type); ?>">
        <div class="notification-icon">
            <?php
            switch ($notification_type) {
                case 'success':
                    echo '<i class="fas fa-check-circle"></i>';
                    break;
                case 'error':
                    echo '<i class="fas fa-times-circle"></i>';
                    break;
                case 'warning':
                    echo '<i class="fas fa-exclamation-circle"></i>';
                    break;
            }
            ?>
        </div>
        <div class="notification-title">
            <?php
            switch ($notification_type) {
                case 'success':
                    echo 'Registration Complete!';
                    break;
                case 'error':
                    echo 'Registration Failed';
                    break;
                case 'warning':
                    echo 'Registration Issue';
                    break;
            }
            ?>
        </div>
        <div class="notification-message"><?php echo $notification; ?></div>
        <div class="notification-progress">
            <div class="notification-progress-bar"></div>
        </div>
        <button class="notification-button" onclick="closeNotification()">
            <?php echo ($notification_type === 'success') ? 'Go to Login' : 'Try Again'; ?>
        </button>
    </div>
</div>

<script>
    function closeNotification() {
        const overlay = document.getElementById('notificationOverlay');
        if (overlay) {
            overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
            setTimeout(() => {
                <?php if ($redirect_url): ?>
                    window.location.href = '<?php echo $redirect_url; ?>';
                <?php else: ?>
                    overlay.classList.remove('active');
                <?php endif; ?>
            }, 300);
        }
    }

    // Auto-redirect after delay
    setTimeout(() => {
        closeNotification();
    }, <?php echo $redirect_delay; ?>);
</script>
<?php endif; ?>

<?php include("footer.php"); ?>

<script src="js/register-validation.js"></script>

</body>
</html>
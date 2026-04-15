<?php
session_start();
include("connect.php");

$notification = null;
$notification_type = null;
$redirect_url = null;
$redirect_delay = 2000;
$show_role_selector = false;
$user_data = null;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;
    $user_type = $_POST['user_type'] ?? null;

    $email = mysqli_real_escape_string($conn, $email);
    $password = mysqli_real_escape_string($conn, $password);
    $user_type = mysqli_real_escape_string($conn, $user_type);

    // Handle role selection for faculty with dual roles
    if (isset($_POST['faculty_role_choice'])) {
        $faculty_role = $_POST['faculty_role_choice'];
        $user_id = mysqli_real_escape_string($conn, $_POST['hidden_user_id']);
        $role_id = mysqli_real_escape_string($conn, $_POST['hidden_role_id']);
        
        // Fetch complete user data
        $user_sql = "SELECT * FROM `users` WHERE user_id = '$user_id'";
        $user_result = mysqli_query($conn, $user_sql);
        
        if ($user_result && mysqli_num_rows($user_result) > 0) {
            $data = mysqli_fetch_array($user_result);
            
            // Set all session variables
            $_SESSION['uid'] = $data['user_id'];
            $_SESSION['type'] = $data['role_id'];
            
            if ($faculty_role === 'teacher') {
                $_SESSION['faculty_mode'] = 'teacher';
                $redirect_url = "faculty/faculty_homepage.php";
            } else {
                $_SESSION['faculty_mode'] = 'coordinator';
                $redirect_url = "non_faculty/non_faculty_homepage.php";
            }
            
            // Handle remember me
            if (isset($_POST['remember_choice']) && $_POST['remember_choice'] === '1') {
                setcookie('user_email', $_POST['hidden_email'], time() + (7 * 24 * 60 * 60), "/");
                setcookie('user_type', $_POST['hidden_user_type'], time() + (7 * 24 * 60 * 60), "/");
            }
            
            $notification = "Welcome back! Login Successful.";
            $notification_type = "success";
        } else {
            $notification = "An error occurred. Please try again.";
            $notification_type = "error";
            $redirect_url = "login.php";
        }
    } else {
        // Normal login process
        $sql = "SELECT * FROM `users` WHERE email = '$email' AND password = '$password'";
        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_array($result);

            // Check account status first
            if (empty($data['status'])) {
                $notification = "Your account is pending verification. Please wait for approval.";
                $notification_type = "pending";
                $redirect_url = "login.php";
            } elseif (strtolower($data['status']) === 'inactive') {
                $notification = "Your account has been declined. Please contact administration.";
                $notification_type = "declined";
                $redirect_url = "login.php";
            } elseif (strtolower($data['status']) !== 'active') {
                $notification = "Your account status is not recognized. Please contact support.";
                $notification_type = "error";
                $redirect_url = "login.php";
            } else {
                // Account is active, now verify user type matches
                $role_id = $data['role_id'];
                $type_matches = false;
                
                if ($user_type === 'student' && $role_id == 2) {
                    $type_matches = true;
                    $_SESSION['uid'] = $data['user_id'];
                    $_SESSION['type'] = $data['role_id'];
                    $redirect_url = "student/student_homepage.php";
                    
                    // Handle remember me
                    if (isset($_POST['remember'])) {
                        setcookie('user_email', $email, time() + (7 * 24 * 60 * 60), "/");
                        setcookie('user_type', $user_type, time() + (7 * 24 * 60 * 60), "/");
                    } else {
                        setcookie('user_email', '', time() - 3600, "/");
                        setcookie('user_type', '', time() - 3600, "/");
                    }
                    
                    $notification = "Welcome back! Login Successful.";
                    $notification_type = "success";
                } elseif ($user_type === 'faculty' && $role_id == 3) {
                    $type_matches = true;
                    
                    // Check is_coordinator and is_teacher flags
                    $is_coordinator = isset($data['is_coordinator']) ? (int)$data['is_coordinator'] : 0;
                    $is_teacher = isset($data['is_teacher']) ? (int)$data['is_teacher'] : 0;
                    
                    if ($is_coordinator == 1 && $is_teacher == 1) {
                        // Both roles - show selection modal
                        $show_role_selector = true;
                        $user_data = $data;
                    } elseif ($is_coordinator == 1 && $is_teacher == 0) {
                        // Coordinator only
                        $_SESSION['uid'] = $data['user_id'];
                        $_SESSION['type'] = $data['role_id'];
                        $_SESSION['faculty_mode'] = 'coordinator';
                        $redirect_url = "non_faculty/non_faculty_homepage.php";
                        
                        // Handle remember me
                        if (isset($_POST['remember'])) {
                            setcookie('user_email', $email, time() + (7 * 24 * 60 * 60), "/");
                            setcookie('user_type', $user_type, time() + (7 * 24 * 60 * 60), "/");
                        } else {
                            setcookie('user_email', '', time() - 3600, "/");
                            setcookie('user_type', '', time() - 3600, "/");
                        }
                        
                        $notification = "Welcome back! Login Successful.";
                        $notification_type = "success";
                    } elseif ($is_coordinator == 0 && $is_teacher == 1) {
                        // Teacher only
                        $_SESSION['uid'] = $data['user_id'];
                        $_SESSION['type'] = $data['role_id'];
                        $_SESSION['faculty_mode'] = 'teacher';
                        $redirect_url = "faculty/faculty_homepage.php";
                        
                        // Handle remember me
                        if (isset($_POST['remember'])) {
                            setcookie('user_email', $email, time() + (7 * 24 * 60 * 60), "/");
                            setcookie('user_type', $user_type, time() + (7 * 24 * 60 * 60), "/");
                        } else {
                            setcookie('user_email', '', time() - 3600, "/");
                            setcookie('user_type', '', time() - 3600, "/");
                        }
                        
                        $notification = "Welcome back! Login Successful.";
                        $notification_type = "success";
                    } else {
                        // Neither flag is set - default to teacher
                        $_SESSION['uid'] = $data['user_id'];
                        $_SESSION['type'] = $data['role_id'];
                        $_SESSION['faculty_mode'] = 'teacher';
                        $redirect_url = "faculty/faculty_homepage.php";
                        
                        // Handle remember me
                        if (isset($_POST['remember'])) {
                            setcookie('user_email', $email, time() + (7 * 24 * 60 * 60), "/");
                            setcookie('user_type', $user_type, time() + (7 * 24 * 60 * 60), "/");
                        } else {
                            setcookie('user_email', '', time() - 3600, "/");
                            setcookie('user_type', '', time() - 3600, "/");
                        }
                        
                        $notification = "Welcome back! Login Successful.";
                        $notification_type = "success";
                    }
                } elseif ($user_type === 'admin' && $role_id == 1) {
                    $type_matches = true;
                    $_SESSION['uid'] = $data['user_id'];
                    $_SESSION['type'] = $data['role_id'];
                    $redirect_url = "admin/admin_homepage.php";
                    
                    // Handle remember me
                    if (isset($_POST['remember'])) {
                        setcookie('user_email', $email, time() + (7 * 24 * 60 * 60), "/");
                        setcookie('user_type', $user_type, time() + (7 * 24 * 60 * 60), "/");
                    } else {
                        setcookie('user_email', '', time() - 3600, "/");
                        setcookie('user_type', '', time() - 3600, "/");
                    }
                    
                    $notification = "Welcome back! Login Successful.";
                    $notification_type = "success";
                }
                
                if (!$type_matches && !$show_role_selector) {
                    $notification = "Invalid user type selected. Please select the correct user type.";
                    $notification_type = "invalid";
                    $redirect_url = "login.php";
                }
            }
        } else {
            $notification = "Invalid Email or Password. Please try again.";
            $notification_type = "invalid";
            $redirect_url = "login.php";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - College Management System</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="icon" href="Prime-College-Logo.ico" type="image/x-icon">
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
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
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
    0% {
        transform: scale(0);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

.notification-modal.success .notification-icon {
    color: #10b981;
}

.notification-modal.success {
    border-left: 5px solid #10b981;
}

.notification-modal.declined .notification-icon {
    color: #ef4444;
}

.notification-modal.declined {
    border-left: 5px solid #ef4444;
}

.notification-modal.pending .notification-icon {
    color: #f59e0b;
}

.notification-modal.pending {
    border-left: 5px solid #f59e0b;
}

.notification-modal.invalid .notification-icon {
    color: #ec4899;
}

.notification-modal.invalid {
    border-left: 5px solid #ec4899;
}

.notification-modal.error .notification-icon {
    color: #6366f1;
}

.notification-modal.error {
    border-left: 5px solid #6366f1;
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
    animation: progress linear 2s forwards;
    border-radius: 3px;
}

.notification-modal.success .notification-progress-bar {
    background: linear-gradient(90deg, #10b981, #059669);
}

.notification-modal.declined .notification-progress-bar {
    background: linear-gradient(90deg, #ef4444, #dc2626);
}

.notification-modal.pending .notification-progress-bar {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

.notification-modal.invalid .notification-progress-bar {
    background: linear-gradient(90deg, #ec4899, #be185d);
}

.notification-modal.error .notification-progress-bar {
    background: linear-gradient(90deg, #6366f1, #4f46e5);
}

@keyframes progress {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
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

.notification-modal.declined .notification-button {
    background: #ef4444;
    color: white;
}

.notification-modal.declined .notification-button:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

.notification-modal.pending .notification-button {
    background: #f59e0b;
    color: white;
}

.notification-modal.pending .notification-button:hover {
    background: #d97706;
    transform: translateY(-2px);
}

.notification-modal.invalid .notification-button {
    background: #ec4899;
    color: white;
}

.notification-modal.invalid .notification-button:hover {
    background: #be185d;
    transform: translateY(-2px);
}

.notification-modal.error .notification-button {
    background: #6366f1;
    color: white;
}

.notification-modal.error .notification-button:hover {
    background: #4f46e5;
    transform: translateY(-2px);
}

/* Role Selection Styles */
.role-selector-modal {
    background: white;
    border-radius: 12px;
    padding: 40px;
    max-width: 550px;
    width: 90%;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.4s ease-out;
}

.role-selector-title {
    font-size: 26px;
    font-weight: 700;
    margin-bottom: 15px;
    color: #1f2937;
}

.role-selector-description {
    font-size: 16px;
    color: #6b7280;
    margin-bottom: 30px;
    line-height: 1.6;
}

.role-options {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin-bottom: 20px;
}

.role-option {
    flex: 1;
    padding: 30px 20px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f9fafb;
}

.role-option:hover {
    border-color: #3b82f6;
    background: #eff6ff;
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(59, 130, 246, 0.2);
}

.role-option.selected {
    border-color: #3b82f6;
    background: #eff6ff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.role-option-icon {
    font-size: 48px;
    margin-bottom: 15px;
    color: #3b82f6;
}

.role-option-title {
    font-size: 20px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 8px;
}

.role-option-description {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.4;
}

.role-submit-button {
    padding: 12px 40px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.role-submit-button:hover {
    background: #2563eb;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
}

.role-submit-button:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
}

/* Responsive Design */
@media (max-width: 480px) {
    .login-form {
        padding: 30px 25px;
    }
    
    .notification-modal {
        padding: 30px 20px;
    }
    
    .role-options {
        flex-direction: column;
    }
    
    .role-selector-modal {
        padding: 30px 20px;
    }
}

.user-type-container {
    width: 100%;
    margin: 15px 0;
    text-align: left;
}

.user-type-label {
    display: block;
    margin-bottom: 8px;
    font-size: 15px;
    font-weight: 500;
    color: #000000;
}
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="login-container">
    <form class="login-form" method="POST" action="" id="loginForm">
        <h2>Login Form</h2>
        <?php
        $email_cookie = isset($_COOKIE['user_email']) ? $_COOKIE['user_email'] : '';
        $user_type_cookie = isset($_COOKIE['user_type']) ? $_COOKIE['user_type'] : '';
        ?>
        <input type="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($email_cookie); ?>" id="emailInput">
        <input type="password" name="password" placeholder="Password" required>
        
        <div class="user-type-container">
            <label for="user_type" class="user-type-label">I am a:</label>
            <select name="user_type" id="user_type" required>
                <option value="">Select User Type</option>
                <option value="student" <?php echo ($user_type_cookie === 'student') ? 'selected' : ''; ?>>Student</option>
                <option value="faculty" <?php echo ($user_type_cookie === 'faculty') ? 'selected' : ''; ?>>Faculty</option>
                <option value="admin" <?php echo ($user_type_cookie === 'admin') ? 'selected' : ''; ?>>Administrator</option>
            </select>
        </div>

        <div class="login-options">
            <label><input type="checkbox" name="remember" id="rememberCheckbox" <?php if ($email_cookie) echo "checked"; ?>> Remember me</label>
        </div>

        <button type="submit" name="login">Login</button>
        <p>Don't have an account? <a href="register.php" class="register-link">Register Now</a></p>
    </form>
</div>

<!-- Role Selection Modal -->
<?php if ($show_role_selector && $user_data): ?>
<div class="notification-overlay active" id="roleSelectorOverlay">
    <div class="role-selector-modal">
        <div class="role-selector-title">Choose Your Role</div>
        <div class="role-selector-description">
            You have multiple roles assigned. Please select how you'd like to proceed:
        </div>
        
        <form method="POST" action="" id="roleSelectionForm">
            <input type="hidden" name="hidden_user_id" value="<?php echo $user_data['user_id']; ?>">
            <input type="hidden" name="hidden_role_id" value="<?php echo $user_data['role_id']; ?>">
            <input type="hidden" name="hidden_email" value="<?php echo htmlspecialchars($email); ?>">
            <input type="hidden" name="hidden_user_type" value="<?php echo htmlspecialchars($user_type); ?>">
            <input type="hidden" name="remember_choice" value="<?php echo isset($_POST['remember']) ? '1' : '0'; ?>">
            <input type="hidden" name="faculty_role_choice" id="facultyRoleChoice" value="">
            
            <div class="role-options">
                <div class="role-option" onclick="selectRole('teacher')">
                    <div class="role-option-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="role-option-title">Teacher</div>
                    <div class="role-option-description">
                        Access teaching features, manage courses, and grade students
                    </div>
                </div>
                
                <div class="role-option" onclick="selectRole('coordinator')">
                    <div class="role-option-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="role-option-title">Coordinator</div>
                    <div class="role-option-description">
                        Manage program coordination, scheduling, and administrative tasks
                    </div>
                </div>
            </div>
            
            <button type="submit" class="role-submit-button" id="roleSubmitButton" disabled>
                Continue
            </button>
        </form>
    </div>
</div>

<script>
    let selectedRole = null;
    
    function selectRole(role) {
        selectedRole = role;
        document.getElementById('facultyRoleChoice').value = role;
        
        // Remove selected class from all options
        const options = document.querySelectorAll('.role-option');
        options.forEach(opt => opt.classList.remove('selected'));
        
        // Add selected class to clicked option
        event.currentTarget.classList.add('selected');
        
        // Enable submit button
        document.getElementById('roleSubmitButton').disabled = false;
    }
</script>
<?php endif; ?>

<!-- Notification Modal -->
<?php if ($notification && !$show_role_selector): ?>
<div class="notification-overlay active" id="notificationOverlay">
    <div class="notification-modal <?php echo $notification_type; ?>">
        <div class="notification-icon">
            <?php
            switch ($notification_type) {
                case 'success':
                    echo '<i class="fas fa-check-circle"></i>';
                    break;
                case 'declined':
                    echo '<i class="fas fa-times-circle"></i>';
                    break;
                case 'pending':
                    echo '<i class="fas fa-hourglass-half"></i>';
                    break;
                case 'invalid':
                    echo '<i class="fas fa-exclamation-circle"></i>';
                    break;
                case 'error':
                    echo '<i class="fas fa-info-circle"></i>';
                    break;
            }
            ?>
        </div>
        <div class="notification-title">
            <?php
            switch ($notification_type) {
                case 'success':
                    echo 'Welcome!';
                    break;
                case 'declined':
                    echo 'Access Denied';
                    break;
                case 'pending':
                    echo 'Pending Verification';
                    break;
                case 'invalid':
                    echo 'Login Failed';
                    break;
                case 'error':
                    echo 'Error';
                    break;
            }
            ?>
        </div>
        <div class="notification-message"><?php echo htmlspecialchars($notification); ?></div>
        <div class="notification-progress">
            <div class="notification-progress-bar"></div>
        </div>
        <button class="notification-button" onclick="closeNotification()">
            <?php echo ($notification_type === 'success') ? 'Continue' : 'Okay'; ?>
        </button>
    </div>
</div>

<script>
    function closeNotification() {
        const overlay = document.getElementById('notificationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            <?php if ($redirect_url): ?>
                window.location.href = '<?php echo $redirect_url; ?>';
            <?php endif; ?>
        }, 300);
    }

    // Auto-redirect after 2 seconds
    setTimeout(() => {
        closeNotification();
    }, <?php echo $redirect_delay; ?>);
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
<?php include 'lower_footer.php'; ?>
</body>
</html>
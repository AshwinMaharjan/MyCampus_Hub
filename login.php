<?php
session_start();
include("connect.php");

$notification = null;
$notification_type = null;
$redirect_url = null;
$redirect_delay = 2000; // 2 seconds delay before redirect

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];

    $email = mysqli_real_escape_string($conn, $email);
    $password = mysqli_real_escape_string($conn, $password);
    $user_type = mysqli_real_escape_string($conn, $user_type);

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
            
            // Verify the selected user type matches the database
            $type_matches = false;
            
            if ($user_type === 'student' && $role_id == 2) {
                $type_matches = true;
                $redirect_url = "student/student_homepage.php";
            } elseif ($user_type === 'faculty' && $role_id == 3) {
                // All staff members (role_id = 3) login as faculty
                // The system will determine their specific role internally
                $type_matches = true;
                $redirect_url = "faculty/faculty_homepage.php";
            } elseif ($user_type === 'admin' && $role_id == 1) {
                $type_matches = true;
                $redirect_url = "admin/admin_homepage.php";
            }
            
            if ($type_matches) {
                // Set session variables
                $_SESSION['uid'] = $data['user_id'];
                $_SESSION['type'] = $data['role_id'];

                // Handle remember me checkbox
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

/* Responsive Design */
@media (max-width: 480px) {
    .login-form {
        padding: 30px 25px;
    }
    
    .notification-modal {
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
    <form class="login-form" method="POST" action="">
        <h2>Login Form</h2>
        <?php
        $email_cookie = isset($_COOKIE['user_email']) ? $_COOKIE['user_email'] : '';
        $user_type_cookie = isset($_COOKIE['user_type']) ? $_COOKIE['user_type'] : '';
        ?>
        <input type="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($email_cookie); ?>">
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
            <label><input type="checkbox" name="remember" <?php if ($email_cookie) echo "checked"; ?>> Remember me</label>
        </div>

        <button type="submit" name="login">Login</button>
        <p>Don't have an account? <a href="register.php" class="register-link">Register Now</a></p>
    </form>
</div>

<!-- Notification Modal -->
<?php if ($notification): ?>
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
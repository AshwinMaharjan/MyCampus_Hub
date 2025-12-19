<?php
if (!isset($_SESSION['uid'])) {
  header("Location: ../login.php");
  exit();
}

$user_id = $_SESSION['uid'];

$query = "SELECT profile_photo FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$photo_file = !empty($row['profile_photo']) ? $row['profile_photo'] : 'default_profile.png';
$profile_photo = "../uploads/" . $photo_file;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Header</title>
  <link rel="stylesheet" href="../css/student_header.css">
  <link rel="stylesheet" href="../css/all.min.css">
  <style>
    /* Logout Confirmation Modal */
    .logout-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 2000;
      animation: fadeIn 0.3s ease-in;
    }

    .logout-overlay.active {
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

    .logout-modal {
      background: white;
      border-radius: 12px;
      padding: 40px;
      max-width: 450px;
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

    .logout-icon {
      font-size: 60px;
      color: #f59e0b;
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

    .logout-title {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 10px;
      color: #1f2937;
    }

    .logout-message {
      font-size: 16px;
      color: #6b7280;
      margin-bottom: 30px;
      line-height: 1.6;
    }

    .logout-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .logout-button {
      padding: 10px 30px;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      flex: 1;
      max-width: 150px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      white-space: nowrap;
    }

    .logout-button.confirm {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.2);
    }

    .logout-button.confirm:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
      background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    }

    .logout-button.cancel {
      background: #e5e7eb;
      color: #374151;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .logout-button.cancel:hover {
      background: #d1d5db;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    /* Logout Success Modal */
    .logout-success-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 2000;
      animation: fadeIn 0.3s ease-in;
    }

    .logout-success-overlay.active {
      display: flex;
    }

    .logout-success-modal {
      background: white;
      border-radius: 12px;
      padding: 40px;
      max-width: 450px;
      width: 90%;
      text-align: center;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
      animation: slideUp 0.4s ease-out;
      position: relative;
      border-left: 5px solid #10b981;
    }

    .logout-success-icon {
      font-size: 60px;
      color: #10b981;
      margin-bottom: 20px;
      animation: bounce 0.6s ease-out;
    }

    .logout-success-title {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 10px;
      color: #1f2937;
    }

    .logout-success-message {
      font-size: 16px;
      color: #6b7280;
      margin-bottom: 30px;
      line-height: 1.6;
    }

    .logout-progress {
      height: 3px;
      background: #e5e7eb;
      border-radius: 3px;
      overflow: hidden;
      margin-top: 20px;
    }

    .logout-progress-bar {
      height: 100%;
      background: linear-gradient(90deg, #10b981, #059669);
      animation: progress linear 2s forwards;
      border-radius: 3px;
    }

    @keyframes progress {
      from {
        width: 100%;
      }
      to {
        width: 0%;
      }
    }
  </style>
</head>
<body>

<header class="main-header">
  <div class="logo-container">
    <a href="homepage.php">
      <img src="../images/prime_logo.jpg" alt="Prime Logo" class="logo">
    </a>
  </div>

  <nav class="nav-icons">
    <a href="notifications.php" class="nav-icon">
      <i class="fas fa-bell"></i>
    </a>

    <div class="profile-dropdown">
      <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile" class="profile-img" id="profileIcon" />
      <div class="dropdown-menu" id="dropdownMenu">
        <a href="student_homepage.php"><i class="fas fa-gauge"></i> Dashboard</a>
        <a href="student_profile.php?user_id=<?php echo $user_id; ?>"><i class="fas fa-user-circle"></i> My Profile</a>
        <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
        <a href="study_material.php"><i class="fas fa-user-tie"></i> View Assignments</a>
        <a href="my_marks.php"><i class="fas fa-user-graduate"></i> View Grades</a>
        <a href="#" onclick="openLogoutConfirmation(event)"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </div>
  </nav>
</header>

<!-- Logout Confirmation Modal -->
<div class="logout-overlay" id="logoutConfirmationOverlay">
  <div class="logout-modal">
    <div class="logout-icon">
      <i class="fas fa-sign-out-alt"></i>
    </div>
    <div class="logout-title">Sign Out?</div>
    <div class="logout-message">Are you sure you want to sign out? You will need to login again to access this system.</div>
    <div class="logout-buttons">
      <button class="logout-button cancel" onclick="closeLogoutConfirmation()">
        <i class="fas fa-times"></i> Cancel
      </button>
      <button class="logout-button confirm" onclick="confirmLogout()">
        <i class="fas fa-check"></i> Sign Out
      </button>
    </div>
  </div>
</div>

<!-- Logout Success Modal -->
<div class="logout-success-overlay" id="logoutSuccessOverlay">
  <div class="logout-success-modal">
    <div class="logout-success-icon">
      <i class="fas fa-check-circle"></i>
    </div>
    <div class="logout-success-title">Signed Out Successfully!</div>
    <div class="logout-success-message">You have been successfully signed out. Redirecting to login page...</div>
    <div class="logout-progress">
      <div class="logout-progress-bar"></div>
    </div>
  </div>
</div>

<script src="../js/profile_dropdown.js"></script>
<script>
  function openLogoutConfirmation(event) {
    event.preventDefault();
    document.getElementById('logoutConfirmationOverlay').classList.add('active');
  }

  function closeLogoutConfirmation() {
    const overlay = document.getElementById('logoutConfirmationOverlay');
    overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
    setTimeout(() => {
      overlay.classList.remove('active');
    }, 300);
  }

  function confirmLogout() {
    // Close confirmation modal
    document.getElementById('logoutConfirmationOverlay').classList.remove('active');
    
    // Show success modal
    document.getElementById('logoutSuccessOverlay').classList.add('active');
    
    // Redirect after 2 seconds
    setTimeout(() => {
      window.location.href = 'logout.php';
    }, 2000);
  }
</script>

</body>
</html>
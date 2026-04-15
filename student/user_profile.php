<?php
include("connect.php");
session_start();
include("auth_check.php");


// Get the logged-in user's ID from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: login.php");
    exit();
}

// Fetch user data from database
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found.";
    exit();
}

// Handle default profile photo
$profile_photo = !empty($user['profile_photo']) ? $user['profile_photo'] : '../images/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Profile - <?php echo htmlspecialchars($user['full_name']); ?></title>
  <link rel="stylesheet" href="../css/student_profile.css">
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>
<body>
<?php include("header.php"); ?>
<div class="page-wrapper">
  <?php include("menu.php"); ?>
  
  <div class="profile-container">
    <div class="profile-header">
      <div class="profile-photo-section">
        <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Photo" class="profile-photo">
        <div class="status-badge <?php echo strtolower($user['status']); ?>">
          <?php echo htmlspecialchars($user['status']); ?>
        </div>
      </div>
      <div class="profile-title">
        <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
        <p class="username">@<?php echo htmlspecialchars($user['full_name']); ?></p>
      </div>
    </div>

    <div class="profile-content">
      <!-- Personal Information -->
      <div class="info-section">
        <h2 class="section-title">Personal Information</h2>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">ID Number</span>
            <span class="info-value"><?php echo htmlspecialchars($user['id_number']); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Gender</span>
            <span class="info-value"><?php echo htmlspecialchars($user['gender']); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Date of Birth</span>
            <span class="info-value"><?php echo htmlspecialchars(date('F d, Y', strtotime($user['date_of_birth']))); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Age</span>
            <span class="info-value">
              <?php 
              $dob = new DateTime($user['date_of_birth']);
              $now = new DateTime();
              echo $now->diff($dob)->y . ' years';
              ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Contact Information -->
      <div class="info-section">
        <h2 class="section-title">Contact Information</h2>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Email Address</span>
            <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Contact Number</span>
            <span class="info-value"><?php echo htmlspecialchars($user['contact_number']); ?></span>
          </div>
          <div class="info-item full-width">
            <span class="info-label">Address</span>
            <span class="info-value"><?php echo htmlspecialchars($user['address']); ?></span>
          </div>
        </div>
      </div>

      <!-- Academic Information -->
      <div class="info-section">
        <h2 class="section-title">Academic Information</h2>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Course</span>
            <span class="info-value"><?php echo htmlspecialchars($user['course_name']); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Semester</span>
            <span class="info-value"><?php echo htmlspecialchars($user['sem_name']); ?></span>
          </div>
          <?php if (!empty($user['staff_type'])): ?>
          <div class="info-item">
            <span class="info-label">Staff Type</span>
            <span class="info-value"><?php echo htmlspecialchars($user['staff_type']); ?></span>
          </div>
          <?php endif; ?>
          <?php if ($user['is_coordinator'] == 1): ?>
          <div class="info-item">
            <span class="info-label">Coordinator For</span>
            <span class="info-value"><?php echo htmlspecialchars($user['coordinator_for']); ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="action-buttons">
        <button onclick="window.location.href='edit_profile.php'" class="btn btn-primary">Edit Profile</button>
        <button onclick="window.location.href='change_password.php'" class="btn btn-secondary">Change Password</button>
      </div>
    </div>
  </div>

  <?php include("footer.php"); ?>
</div>
<?php include("lower_footer.php"); ?>
</body>
</html>
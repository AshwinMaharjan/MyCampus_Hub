<?php
session_start();
include("auth_check.php");
include("connect.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['uid'];

// If not in session, redirect to login
if (!$student_id) {
    header("Location: login.php");
    exit();
}

// Fetch user data from database
$query = "
    SELECT 
        u.*,
        c.course_name,
        s.sem_name
    FROM users u
    LEFT JOIN course c ON u.course_id = c.course_id
    LEFT JOIN semester s ON u.sem_id = s.sem_id
    WHERE u.user_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found in database.";
    exit();
}

// Handle profile photo with proper path
$profile_photo = !empty($user['profile_photo']) ? $user['profile_photo'] : 'default_profile.png';
$profile_path = "../uploads/{$profile_photo}";

if (!file_exists($profile_path)) {
    $profile_path = "../uploads/default_profile.png";
}

// Handle course and semester (set default if empty)
$course_name = !empty($user['course_name']) ? $user['course_name'] : 'Not Assigned';
$sem_name = !empty($user['sem_name']) ? $user['sem_name'] : 'Not Assigned';

// Student Privileges (this page is for students only)
$privileges = [
    [
        'icon' => '📚',
        'title' => 'View Your Subjects',
        'description' => 'Access all subjects you are enrolled in'
    ],
    [
        'icon' => '📊',
        'title' => 'View Your Marks',
        'description' => 'Check your marks and grades'
    ],
    [
        'icon' => '📅',
        'title' => 'View Your Attendance',
        'description' => 'Track your attendance records'
    ],
    [
        'icon' => '📝',
        'title' => 'Submit Leave Requests',
        'description' => 'Apply for leave and track request status'
    ],
    [
        'icon' => '📖',
        'title' => 'Access Study Materials',
        'description' => 'Download study materials for your subjects'
    ]
];

$stmt->close();
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
        <img src="<?php echo htmlspecialchars($profile_path); ?>" 
             alt="Profile Photo" 
             class="profile-photo"
             onerror="this.src='../uploads/default_profile.png'">
        <div class="status-badge <?php echo strtolower($user['status']); ?>">
          <?php echo htmlspecialchars($user['status']); ?>
        </div>
      </div>
      <div class="profile-title">
        <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
        <p class="username">ID: <?php echo htmlspecialchars($user['id_number']); ?></p>
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
            <span class="info-value"><?php echo htmlspecialchars($course_name); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Semester</span>
            <span class="info-value"><?php echo htmlspecialchars($sem_name); ?></span>
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
      <!-- Privileges Section -->
      <?php if (!empty($privileges)): ?>
      <div class="info-section">
        <h2 class="section-title">Your Privileges & Permissions</h2>
        <div class="privileges-grid">
          <?php foreach ($privileges as $privilege): ?>
          <div class="privilege-card">
            <div class="privilege-icon"><?php echo $privilege['icon']; ?></div>
            <div class="privilege-content">
              <h3 class="privilege-title"><?php echo htmlspecialchars($privilege['title']); ?></h3>
              <p class="privilege-description"><?php echo htmlspecialchars($privilege['description']); ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Action Buttons -->
      <!-- <div class="action-buttons">
        <button onclick="window.location.href='edit_profile.php'" class="btn btn-primary">Edit Profile</button>
        <button onclick="window.location.href='change_password.php'" class="btn btn-secondary">Change Password</button>
      </div>
    </div> -->
  </div>
  </div>
  </div>

  <?php include("footer.php"); ?>
</div>
<?php include("lower_footer.php"); ?>
</body>
</html>
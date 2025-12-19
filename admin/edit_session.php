<?php
session_start();
include("connect.php");
if (!isset($_SESSION['uid'])) {
  header("Location: ../login.php");
  exit();
}

$notification = null;
$notification_type = null;
$redirect_delay = 2000;

// Get session ID from URL
if (!isset($_GET['session_id']) || !is_numeric($_GET['session_id'])) {
    header("Location: manage_sessions.php");
    exit;
} 

// Fetch all semesters for dropdown
$semesterList = [];
$semResult = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_name");
if ($semResult) {
    while ($row = $semResult->fetch_assoc()) {
        $semesterList[] = $row;
    }
}

// Fetch all courses for dropdown
$courseList = [];
$courseResult = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name");
if ($courseResult) {
    while ($row = $courseResult->fetch_assoc()) {
        $courseList[] = $row;
    }
}

$session_id = intval($_GET['session_id']);

// Fetch session data
$stmt = $conn->prepare("SELECT * FROM session WHERE session_id = ?");
$stmt->bind_param("i", $session_id);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();
$stmt->close();

if (!$session) {
    header("Location: manage_sessions.php");
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $start_date = $_POST["start_date"];
  $end_date = $_POST["end_date"];
  $sem_id = intval($_POST["sem_id"]);
  $course_id = intval($_POST["course_id"]);

  if (!empty($start_date) && !empty($end_date) && $sem_id > 0 && $course_id > 0) {
      $update = $conn->prepare("UPDATE session SET start_date = ?, end_date = ?, sem_id = ?, course_id = ? WHERE session_id = ?");
      $update->bind_param("ssiii", $start_date, $end_date, $sem_id, $course_id, $session_id);
      if ($update->execute()) {
          $notification = "Session updated successfully!";
          $notification_type = "success";
          $session['start_date'] = $start_date;
          $session['end_date'] = $end_date;
          $session['sem_id'] = $sem_id;
          $session['course_id'] = $course_id;
      } else {
          $notification = "Error updating session.";
          $notification_type = "error";
      }
      $update->close();
  } else {
      $notification = "All fields including semester and course are required.";
      $notification_type = "invalid";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard - Edit Session</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/edit_session.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
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

    .notification-modal.error .notification-icon {
        color: #ef4444;
    }

    .notification-modal.error {
        border-left: 5px solid #ef4444;
    }

    .notification-modal.invalid .notification-icon {
        color: #ec4899;
    }

    .notification-modal.invalid {
        border-left: 5px solid #ec4899;
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

    .notification-modal.error .notification-progress-bar {
        background: linear-gradient(90deg, #ef4444, #dc2626);
    }

    .notification-modal.invalid .notification-progress-bar {
        background: linear-gradient(90deg, #ec4899, #be185d);
    }

    @keyframes progress {
        from {
            width: 100%;
        }
        to {
            width: 0%;
        }
    }

    .notification-buttons {
        display: flex;
        gap: 15px;
        margin-top: 20px;
        justify-content: center;
    }

    .notification-button {
        padding: 10px 30px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        flex: 1;
        max-width: 150px;
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

    .notification-modal.invalid .notification-button {
        background: #ec4899;
        color: white;
    }

    .notification-modal.invalid .notification-button:hover {
        background: #be185d;
        transform: translateY(-2px);
    }
  </style>
</head>

<body>
<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<!-- Main Content -->
<div class="form-container">
  <form method="POST">
    <h2>Edit Session</h2>

    <label>Semester</label>
    <select name="sem_id" required>
      <option value="" disabled>Select Semester</option>
      <?php foreach ($semesterList as $sem): ?>
        <option value="<?= $sem['sem_id']; ?>" <?= $sem['sem_id'] == $session['sem_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($sem['sem_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Course</label>
    <select name="course_id" required>
      <option value="" disabled>Select Course</option>
      <?php foreach ($courseList as $course): ?>
        <option value="<?= $course['course_id']; ?>" <?= $course['course_id'] == $session['course_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($course['course_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Start Date</label>
    <input type="date" name="start_date" value="<?= htmlspecialchars($session['start_date']); ?>" required />

    <label>End Date</label>
    <input type="date" name="end_date" value="<?= htmlspecialchars($session['end_date']); ?>" required />

    <button type="submit" class="btn-update">Update Session</button>
    <button type="button" class="cancel-button" onclick="window.location.href='manage_sessions.php'">Cancel</button>
  </form>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

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
                case 'error':
                    echo '<i class="fas fa-times-circle"></i>';
                    break;
                case 'invalid':
                    echo '<i class="fas fa-exclamation-circle"></i>';
                    break;
            }
            ?>
        </div>
        <div class="notification-title">
            <?php
            switch ($notification_type) {
                case 'success':
                    echo 'Success!';
                    break;
                case 'error':
                    echo 'Error';
                    break;
                case 'invalid':
                    echo 'Invalid Input';
                    break;
            }
            ?>
        </div>
        <div class="notification-message"><?php echo htmlspecialchars($notification); ?></div>
        <div class="notification-progress">
            <div class="notification-progress-bar"></div>
        </div>
        <div class="notification-buttons">
            <?php if ($notification_type === 'success'): ?>
                <button class="notification-button" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i> Back to Sessions
                </button>
            <?php else: ?>
                <button class="notification-button" onclick="closeNotification()">
                    Okay
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function closeNotification() {
        const overlay = document.getElementById('notificationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            overlay.classList.remove('active');
        }, 300);
    }

    function goBack() {
        window.location.href = 'manage_sessions.php';
    }

    // Auto-close after 2 seconds for non-success messages
    <?php if ($notification_type !== 'success'): ?>
    setTimeout(() => {
        closeNotification();
    }, <?php echo $redirect_delay; ?>);
    <?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>
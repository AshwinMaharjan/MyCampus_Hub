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

$semesterList = [];
$semesterResult = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_name");
if ($semesterResult) {
    while ($row = $semesterResult->fetch_assoc()) {
        $semesterList[] = $row;
    }
}

$courseList = [];
$courseResult = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name");
if ($courseResult) {
    while ($row = $courseResult->fetch_assoc()) {
        $courseList[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sem_id = intval($_POST["sem_id"]);
    $course_id = intval($_POST["course_id"]);
    $start_date = $_POST["start_date"];
    $end_date = $_POST["end_date"];

    if ($sem_id > 0 && !empty($start_date) && !empty($end_date)) {
        $check = $conn->prepare("SELECT * FROM session WHERE start_date = ? AND end_date = ? AND sem_id = ? AND course_id = ?");
        $check->bind_param("ssii", $start_date, $end_date, $sem_id, $course_id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $notification = "Session already exists for the selected semester.";
            $notification_type = "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO session (sem_id, course_id, start_date, end_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $sem_id, $course_id, $start_date, $end_date);
            if ($stmt->execute()) {
                $notification = "Session added successfully!";
                $notification_type = "success";
            } else {
                $notification = "Error: Could not add session.";
                $notification_type = "error";
            }
            $stmt->close();
        }
        $check->close();
    } else {
        $notification = "Please select semester and fill in both start and end dates.";
        $notification_type = "invalid";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard - Add Session</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/add_session.css" />
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

    .notification-button {
        margin-top: 20px;
        padding: 10px 30px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        width: auto;
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

  <div class="form-container">
    <form method="POST" action="">
      <h2>Add New Session</h2>

      <label>Semester</label>
      <select name="sem_id" required>
        <option value="" disabled selected>Select Semester</option>
        <?php foreach ($semesterList as $sem): ?>
          <option value="<?php echo $sem['sem_id']; ?>">
            <?php echo htmlspecialchars($sem['sem_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Course</label>
      <select name="course_id" required>
        <option value="" disabled selected>Select Course</option>
        <?php foreach ($courseList as $course): ?>
          <option value="<?php echo $course['course_id']; ?>">
            <?php echo htmlspecialchars($course['course_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Start Date</label>
      <input type="date" name="start_date" required />

      <label>End Date</label>
      <input type="date" name="end_date" required />

      <button type="submit">Add Session</button>
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
            overlay.classList.remove('active');
        }, 300);
    }

    // Auto-close after 2 seconds
    setTimeout(() => {
        closeNotification();
    }, <?php echo $redirect_delay; ?>);
</script>
<?php endif; ?>

</body>
</html>
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

// Get subject ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_subjects.php");
    exit;
}

$sub_id = intval($_GET['id']);

// Fetch semester list for dropdown
$semesterList = [];
$semesterResult = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_name");
if ($semesterResult) {
    while ($row = $semesterResult->fetch_assoc()) {
        $semesterList[] = $row;
    }
}

// Fetch existing subject data
$stmt = $conn->prepare("SELECT * FROM subject WHERE sub_id = ?");
$stmt->bind_param("i", $sub_id);
$stmt->execute();
$result = $stmt->get_result();
$subject = $result->fetch_assoc();
$stmt->close();

if (!$subject) {
    header("Location: manage_subjects.php");
    exit;
}

// Fetch staff list for dropdown (active staff with role_id=3)
$staffList = [];
$staffResult = $conn->query("SELECT user_id, full_name FROM users WHERE role_id = 3 AND status = 'active' ORDER BY full_name");
if ($staffResult) {
    while ($row = $staffResult->fetch_assoc()) {
        $staffList[] = $row;
    }
}

// Fetch course list for dropdown
$courseList = [];
$courseResult = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name");
if ($courseResult) {
    while ($row = $courseResult->fetch_assoc()) {
        $courseList[] = $row;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_sub_name = trim($_POST["sub_name"]);
    $new_role_id = intval($_POST["role_id"]);
    $new_course_id = intval($_POST["course_id"]);
    $new_sem_id = intval($_POST["sem_id"]);

    if (!empty($new_sub_name) && $new_role_id > 0 && $new_course_id > 0 && $new_sem_id > 0) {
        // Check duplicate subject name for same course excluding current subject
        $check = $conn->prepare("SELECT * FROM subject WHERE sub_name = ? AND course_id = ? AND sub_id != ?");
        $check->bind_param("sii", $new_sub_name, $new_course_id, $sub_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            $notification = "Subject with this name already exists for the selected course.";
            $notification_type = "error";
        } else {
            $update = $conn->prepare("UPDATE subject SET sub_name = ?, role_id = ?, course_id = ?, sem_id = ? WHERE sub_id = ?");
            $update->bind_param("siiii", $new_sub_name, $new_role_id, $new_course_id, $new_sem_id, $sub_id);
            if ($update->execute()) {
                $notification = "Subject updated successfully!";
                $notification_type = "success";
                $subject['sub_name'] = $new_sub_name;
                $subject['role_id'] = $new_role_id;
                $subject['course_id'] = $new_course_id;
                $subject['sem_id'] = $new_sem_id;
            } else {
                $notification = "Error updating subject.";
                $notification_type = "error";
            }
            $update->close();
        }
        $check->close();
    } else {
        $notification = "Please fill in all fields.";
        $notification_type = "invalid";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard - Edit Subject</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="../css/edit_subject.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    /* Common styles for dropdown selects */
    select {
      width: 100%;
      padding: 10px 12px;
      font-size: 16px;
      border: 1.5px solid #ccc;
      border-radius: 6px;
      background-color: #fff;
      color: #333;
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      cursor: pointer;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
      margin-bottom: 20px;
      background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg width='10' height='7' viewBox='0 0 10 7' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath fill='%23666' d='M0 0l5 7 5-7z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 12px center;
      background-size: 10px 7px;
    }

    select:focus {
      border-color: #263576;
      box-shadow: 0 0 5px rgba(38, 53, 118, 0.6);
      outline: none;
    }

    select option[disabled] {
      color: #999;
    }

    select::-ms-expand {
      display: none;
    }

    .btn-update:hover {
      background-color: #218838;
    }

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
      <div class="edit-course-container">
        <h2>Edit Subject</h2>
      </div>

      <input
        type="text"
        name="sub_name"
        value="<?php echo htmlspecialchars($subject['sub_name']); ?>"
        placeholder="Subject Name"
        required
      />

      <select name="role_id" required>
        <option value="" disabled>Select Staff</option>
        <?php foreach ($staffList as $staff): ?>
          <option value="<?php echo $staff['user_id']; ?>" <?php if ($subject['role_id'] == $staff['user_id']) echo "selected"; ?>>
            <?php echo htmlspecialchars($staff['full_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="sem_id" required>
        <option value="" disabled>Select Semester</option>
        <?php foreach ($semesterList as $sem): ?>
          <option value="<?php echo $sem['sem_id']; ?>" <?php if ($subject['sem_id'] == $sem['sem_id']) echo "selected"; ?>>
            <?php echo htmlspecialchars($sem['sem_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="course_id" required>
        <option value="" disabled>Select Course</option>
        <?php foreach ($courseList as $course): ?>
          <option value="<?php echo $course['course_id']; ?>" <?php if ($subject['course_id'] == $course['course_id']) echo "selected"; ?>>
            <?php echo htmlspecialchars($course['course_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <div class="button-row">
        <button type="submit" class="btn-update">Update Subject</button>
        <button type="button" class="cancel-button" onclick="window.location.href='manage_subjects.php'">Cancel</button>
      </div>
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
                    <i class="fas fa-arrow-left"></i> Back to Subjects
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
        window.location.href = 'manage_subjects.php';
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
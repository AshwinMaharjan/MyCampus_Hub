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

// Get staff user_id from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_staff.php");
    exit;
}

$user_id = intval($_GET['id']);

// Fetch existing staff data with course info
$stmt = $conn->prepare("SELECT users.*, course.course_name, course.course_name FROM users LEFT JOIN course ON users.course_id = course.course_id WHERE users.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();
$stmt->close();

if (!$staff) {
    header("Location: manage_staff.php");
    exit;
}

// Extract selected semesters for pre-checking in the form
$selected_sems = isset($staff['sem_name']) ? explode(",", $staff['sem_name']) : [];

// Fetch course list for dropdown
$courseList = [];
$courseResult = mysqli_query($conn, "SELECT course_id, course_name FROM course ORDER BY course_name");
if ($courseResult) {
    while ($row = mysqli_fetch_assoc($courseResult)) {
        $courseList[] = $row;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $full_name = trim($_POST['full_name']);
    $id_number = trim($_POST['id_number']);
    $gender = $_POST['gender'];
    $contact_number = trim($_POST['contact_number']);
    $address = trim($_POST['address']);
    $status = $_POST['status'];
    $staff_type = $_POST['staff_type'];
    $sem_name = isset($_POST['sem_name']) ? implode(",", $_POST['sem_name']) : '';
    $course_names = isset($_POST['course_name']) ? implode(",", $_POST['course_name']) : '';

    // Check for duplicate email or id_number excluding current user
    $check_stmt = $conn->prepare("SELECT * FROM users WHERE (id_number = ?) AND user_id != ?");
    $check_stmt->bind_param("si", $id_number, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $notification = "ID Number already exists for another staff.";
        $notification_type = "error";
    } else {
        // Update staff info
        $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, id_number = ?, gender = ?, contact_number = ?, address = ?, course_name = ?, status = ?, staff_type = ?, sem_name = ? WHERE user_id = ?");
        $update_stmt->bind_param("sssssssssi", $full_name, $id_number, $gender, $contact_number, $address, $course_names, $status, $staff_type, $sem_name, $user_id);        

        if ($update_stmt->execute()) {
            $notification = "Staff updated successfully!";
            $notification_type = "success";

            // Refresh staff data after update
            $staff['full_name'] = $full_name;
            $staff['id_number'] = $id_number;
            $staff['gender'] = $gender;
            $staff['contact_number'] = $contact_number;
            $staff['address'] = $address;
            $staff['course_name'] = $course_names;
            $staff['status'] = $status;
            $staff['staff_type'] = $staff_type;
            $staff['sem_name'] = $sem_name;

            // Also update selected_sems array
            $selected_sems = explode(",", $sem_name);
        } else {
            $notification = "Error updating staff.";
            $notification_type = "error";
        }
        $update_stmt->close();
    }
    $check_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard - Edit Staff</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="../css/edit_staff.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
  /* Form container - fixed height removed */
  .form-container {
    width: 1000px;
    padding: 40px 60px;
    background: #fff;
    margin: 20px auto;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
  }

  form > * {
    display: block;
    width: 100%;
    margin-bottom: 15px;
    font-size: 16px;
    box-sizing: border-box;
  }

  input[type="text"], input[type="email"], select {
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
  }

  input[type="text"]:focus, input[type="email"]:focus, select:focus {
    border-color: #263576;
    outline: none;
    box-shadow: 0 0 5px rgba(38, 53, 118, 0.3);
  }

  fieldset {
    border: 1px solid #ccc;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 15px;
  }

  fieldset legend {
    padding: 0 8px;
    font-weight: 600;
    color: #263576;
  }

  fieldset label {
    display: inline-block;
    margin-right: 20px;
    margin-bottom: 8px;
    cursor: pointer;
  }

  /* Button Row */
  .button-row {
    display: flex;
    gap: 15px;
    margin-top: 20px;
    justify-content: center;
  }

  button.btn-update,
  button.cancel-button {
    flex: unset;
    width: 180px;
    padding: 14px 24px;
    font-size: 18px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    text-align: center;
  }

  button.btn-update {
    background-color: #28a745;
    color: white;
  }

  button.btn-update:hover {
    background-color: #218838;
    transform: translateY(-2px);
  }

  button.cancel-button {
    background-color: #6c757d;
    color: white;
  }

  button.cancel-button:hover {
    background-color: #5a6268;
    transform: translateY(-2px);
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

  /* Responsive adjustment */
  @media (max-width: 768px) {
    .form-container {
      width: 95%;
      padding: 30px;
    }

    button.btn-update,
    button.cancel-button {
      width: 100%;
      font-size: 16px;
    }

    .button-row {
      flex-direction: column;
    }
  }
</style>
</head>

<body>
<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<!-- Main Content -->
<div class="form-container">
    <h2>Edit Staff</h2>
    <form method="POST">
      <input type="text" name="full_name" placeholder="Full Name" value="<?php echo htmlspecialchars($staff['full_name']); ?>" required />
      <input type="text" name="id_number" placeholder="ID Number" value="<?php echo htmlspecialchars($staff['id_number']); ?>" required />

      <select name="gender" required>
        <option value="" disabled <?php echo empty($staff['gender']) ? 'selected' : ''; ?>>Select Gender</option>
        <option value="Male" <?php echo ($staff['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
        <option value="Female" <?php echo ($staff['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
        <option value="Other" <?php echo ($staff['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
      </select>

      <input type="text" name="contact_number" placeholder="Contact Number" value="<?php echo htmlspecialchars($staff['contact_number']); ?>" required />
      <input type="text" name="address" placeholder="Address" value="<?php echo htmlspecialchars($staff['address']); ?>" required />

      <fieldset>
        <legend>Select Courses</legend>
        <?php
          $selected_courses = isset($staff['course_name']) ? explode(",", $staff['course_name']) : [];
          foreach ($courseList as $course) {
              $checked = in_array($course['course_name'], $selected_courses) ? 'checked' : '';
              echo '<label><input type="checkbox" name="course_name[]" value="' . htmlspecialchars($course['course_name']) . '" ' . $checked . '> ' . htmlspecialchars($course['course_name']) . '</label><br>';
          }
        ?>
      </fieldset>

      <fieldset>
        <legend>Select Semesters</legend>
        <?php
          $semesters = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
          foreach ($semesters as $sem) {
              $checked = in_array($sem, $selected_sems) ? 'checked' : '';
              echo '<label><input type="checkbox" name="sem_name[]" value="' . $sem . '" ' . $checked . '> ' . $sem . ' Semester</label><br>';
          }
        ?>
      </fieldset>

      <select name="status" required>
        <option value="" disabled <?php echo empty($staff['status']) ? 'selected' : ''; ?>>Select Status</option>
        <option value="active" <?php echo ($staff['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
        <option value="inactive" <?php echo ($staff['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
      </select>

      <select name="staff_type" required>
        <option value="" disabled <?php echo empty($staff['staff_type']) ? 'selected' : ''; ?>>Select Staff Type</option>
        <option value="Teaching" <?php echo ($staff['staff_type'] === 'Teaching') ? 'selected' : ''; ?>>Teaching</option>
        <option value="Non Teaching" <?php echo ($staff['staff_type'] === 'Non Teaching') ? 'selected' : ''; ?>>Non Teaching</option>
      </select>

      <div class="button-row">
        <button type="submit" class="btn-update">Update Staff</button>
        <button type="button" class="cancel-button" onclick="window.location.href='manage_staff.php'">Cancel</button>
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
                    <i class="fas fa-arrow-left"></i> Back to Staff
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
        window.location.href = 'manage_staff.php';
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
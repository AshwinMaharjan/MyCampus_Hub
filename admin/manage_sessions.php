<?php
session_start();
include("connect.php");
if (!isset($_SESSION['uid'])) {
  header("Location: ../login.php");
  exit();
}

$notification = null;
$notification_type = "";
$show_confirmation = false;
$delete_id_to_confirm = null;
$session_details_to_delete = null;

// Handle delete session if confirmed
if (isset($_GET['delete_id']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM session WHERE session_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $notification = "Session deleted successfully.";
        $notification_type = "success";
    } else {
        $notification = "Error deleting session.";
        $notification_type = "error";
    }
    $stmt->close();
}
// Show confirmation modal if delete_id is present without confirmation
elseif (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT s.session_id, sem.sem_name, c.course_name, s.start_date, s.end_date FROM session s 
                           LEFT JOIN semester sem ON s.sem_id = sem.sem_id 
                           LEFT JOIN course c ON s.course_id = c.course_id 
                           WHERE s.session_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result_check = $stmt->get_result();
    if ($result_check->num_rows > 0) {
        $row = $result_check->fetch_assoc();
        $show_confirmation = true;
        $delete_id_to_confirm = $delete_id;
        $session_details_to_delete = $row;
    }
    $stmt->close();
}

// Get filter values from GET or set default (0 means no filter)
$selected_sem_id = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$selected_course_id = isset($_GET['course']) ? intval($_GET['course']) : 0;

// Fetch semesters for dropdown
$semesters_result = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_id ASC");

// Fetch courses for dropdown
$courses_result = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name ASC");

// Build the base query with joins
$query = "SELECT session.*, semester.sem_name, course.course_name 
          FROM session 
          LEFT JOIN semester ON session.sem_id = semester.sem_id 
          LEFT JOIN course ON session.course_id = course.course_id";

// Add WHERE clause if filters are selected
$conditions = [];
$params = [];
$param_types = "";

if ($selected_sem_id > 0) {
    $conditions[] = "session.sem_id = ?";
    $params[] = $selected_sem_id;
    $param_types .= "i";
}
if ($selected_course_id > 0) {
    $conditions[] = "session.course_id = ?";
    $params[] = $selected_course_id;
    $param_types .= "i";
}

if (count($conditions) > 0) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY session.session_id DESC";

if (count($conditions) > 0) {
    // Prepare statement with filtering
    $stmt = $conn->prepare($query);
    if ($param_types) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // No filters, run normal query
    $result = $conn->query($query);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard - Manage Sessions</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/manage_student.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">

  <style>
    /* Filter Container Styling */
    .filter-container {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 12px 18px;
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 10px;
      margin-bottom: 25px;
      flex-wrap: wrap;
    }

    .filter-container form {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      width: 100%;
      gap: 10px;
    }

    .filter-container select {
      padding: 7px 12px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 14px;
      min-width: 150px;
    }

    .filter-container select:focus {
      border-color: #263576;
      outline: none;
      box-shadow: 0 0 5px rgba(38, 53, 118, 0.3);
    }

    .clear-filters-btn {
      padding: 7px 14px;
      font-size: 14px;
      border-radius: 4px;
      border: none;
      cursor: pointer;
      font-weight: 500;
      background: #6c757d;
      color: #fff;
    }

    .clear-filters-btn:hover {
      background: #5a6268;
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

    .notification-modal.confirm .notification-icon {
        color: #f59e0b;
    }

    .notification-modal.confirm {
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

    .notification-modal.confirm .notification-button-confirm {
        background: #ef4444;
        color: white;
    }

    .notification-modal.confirm .notification-button-confirm:hover {
        background: #dc2626;
        transform: translateY(-2px);
    }

    .notification-modal.confirm .notification-button-cancel {
        background: #6b7280;
        color: white;
    }

    .notification-modal.confirm .notification-button-cancel:hover {
        background: #4b5563;
        transform: translateY(-2px);
    }

    .confirmation-session-details {
        font-weight: 600;
        color: #1f2937;
        background: #f3f4f6;
        padding: 15px;
        border-radius: 6px;
        margin: 15px 0;
        text-align: left;
    }

    .confirmation-session-details div {
        margin: 5px 0;
        font-size: 14px;
    }

    .confirmation-session-details strong {
        color: #263576;
    }
  </style>
</head>

<body>
<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
  <h1 style="color:#263576">Manage Sessions</h1>

  <!-- Filter form -->
  <div class="filter-container">
    <form method="GET" action="manage_sessions.php">
      <select name="semester" onchange="this.form.submit()">
        <option value="0" <?= $selected_sem_id === 0 ? "selected" : ""; ?>>All Semesters</option>
        <?php 
        // Reset result pointer and re-fetch
        $semesters_result = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_id ASC");
        while ($sem = $semesters_result->fetch_assoc()): ?>
          <option value="<?= $sem['sem_id']; ?>" <?= $selected_sem_id === (int)$sem['sem_id'] ? "selected" : ""; ?>>
            <?= htmlspecialchars($sem['sem_name']); ?>
          </option>
        <?php endwhile; ?>
      </select>

      <select name="course" onchange="this.form.submit()">
        <option value="0" <?= $selected_course_id === 0 ? "selected" : ""; ?>>All Courses</option>
        <?php 
        // Reset result pointer and re-fetch
        $courses_result = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name ASC");
        while ($course = $courses_result->fetch_assoc()): ?>
          <option value="<?= $course['course_id']; ?>" <?= $selected_course_id === (int)$course['course_id'] ? "selected" : ""; ?>>
            <?= htmlspecialchars($course['course_name']); ?>
          </option>
        <?php endwhile; ?>
      </select>
    </form>
    <button type="button" class="clear-filters-btn" onclick="window.location='<?= basename($_SERVER['PHP_SELF']); ?>';">
      <i class="fas fa-times"></i> Clear Filters
    </button>
  </div>

  <?php if ($result && $result->num_rows > 0): ?>
    <table>
      <thead>
        <tr>
          <th>Session ID</th>
          <th>Semester</th>
          <th>Course</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
          <tr>
            <td><?= $row['session_id']; ?></td>
            <td><?= htmlspecialchars($row['sem_name'] ?? 'N/A'); ?></td>
            <td><?= htmlspecialchars($row['course_name'] ?? 'N/A'); ?></td>
            <td><?= htmlspecialchars($row['start_date']); ?></td>
            <td><?= htmlspecialchars($row['end_date']); ?></td>
            <td>
              <a href="edit_session.php?session_id=<?= $row['session_id']; ?>" class="btn btn-edit">
                <i class="fas fa-edit"></i> Edit
              </a>
              <a href="manage_sessions.php?delete_id=<?= $row['session_id']; ?>" class="btn btn-delete">
                <i class="fas fa-trash-alt"></i> Delete
              </a>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  <?php else: ?>
    <p style="color: red; font-weight: bold;">No sessions found for selected semester and course.</p>
  <?php endif; ?>
</div>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<!-- Confirmation Modal -->
<?php if ($show_confirmation): ?>
<div class="notification-overlay active" id="confirmationOverlay">
    <div class="notification-modal confirm">
        <div class="notification-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="notification-title">Delete Session?</div>
        <div class="notification-message">Are you sure you want to delete this session?</div>
        <div class="confirmation-session-details">
            <div><strong>Semester:</strong> <?php echo htmlspecialchars($session_details_to_delete['sem_name'] ?? 'N/A'); ?></div>
            <div><strong>Course:</strong> <?php echo htmlspecialchars($session_details_to_delete['course_name'] ?? 'N/A'); ?></div>
            <div><strong>Start Date:</strong> <?php echo htmlspecialchars($session_details_to_delete['start_date']); ?></div>
            <div><strong>End Date:</strong> <?php echo htmlspecialchars($session_details_to_delete['end_date']); ?></div>
        </div>
        <div class="notification-buttons">
            <button class="notification-button notification-button-cancel" onclick="cancelDelete()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="notification-button notification-button-confirm" onclick="confirmDelete(<?php echo $delete_id_to_confirm; ?>)">
                <i class="fas fa-check"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
    function cancelDelete() {
        const overlay = document.getElementById('confirmationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            overlay.classList.remove('active');
        }, 300);
    }

    function confirmDelete(sessionId) {
        window.location.href = 'manage_sessions.php?delete_id=' + sessionId + '&confirm=yes' + 
            '<?= $selected_sem_id !== 0 ? '&semester=' . $selected_sem_id : '' ?>' +
            '<?= $selected_course_id !== 0 ? '&course=' . $selected_course_id : '' ?>';
    }
</script>
<?php endif; ?>

<!-- Notification Modal (Success/Error) -->
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
        <button class="notification-button" onclick="closeNotification()">
            Okay
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

    setTimeout(() => {
        closeNotification();
    }, 2000);
</script>
<?php endif; ?>

</body>
</html>
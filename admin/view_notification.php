<?php
session_start();
include("connect.php"); 
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$notification = null;
$notification_type = null;
$show_confirmation = false;
$delete_id_to_confirm = null;
$message_to_delete = null;
$redirect_delay = 2000;

// Get filter parameters
$selected_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$selected_semester = isset($_GET['sem_id']) ? intval($_GET['sem_id']) : 0;
$selected_student = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_submitted = isset($_GET['search_submitted']) ? true : false;

// Handle delete notification if confirmed
if (isset($_GET['delete_id']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $notification = "Notification deleted successfully.";
        $notification_type = "success";
    } else {
        $notification = "Error deleting notification.";
        $notification_type = "error";
    }
    $stmt->close();
}
// Show confirmation modal if delete_id is present without confirmation
elseif (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT message FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result_check = $stmt->get_result();
    if ($result_check->num_rows > 0) {
        $row = $result_check->fetch_assoc();
        $show_confirmation = true;
        $delete_id_to_confirm = $delete_id;
        $message_to_delete = substr($row['message'], 0, 100) . (strlen($row['message']) > 100 ? '...' : '');
    }
    $stmt->close();
}

// Pagination variables
$records_per_page = 15;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $records_per_page;

// Build query with filters
$query = "SELECT n.id, n.message, n.user_id, n.date_sent, u.full_name, u.id_number, u.profile_photo, u.role_id,
          u.course_id, u.sem_id, c.course_name, s.sem_name
          FROM notifications n 
          JOIN users u ON n.user_id = u.user_id 
          LEFT JOIN course c ON u.course_id = c.course_id
          LEFT JOIN semester s ON u.sem_id = s.sem_id
          WHERE 1=1";

if ($search_submitted) {
    if ($selected_course > 0) {
        $query .= " AND u.course_id = $selected_course";
    }
    if ($selected_semester > 0) {
        $query .= " AND u.sem_id = $selected_semester";
    }
    if ($selected_student > 0) {
        $query .= " AND u.user_id = $selected_student";
    }
    if (!empty($date_from)) {
        $query .= " AND DATE(n.date_sent) >= '$date_from'";
    }
    if (!empty($date_to)) {
        $query .= " AND DATE(n.date_sent) <= '$date_to'";
    }
}

// Get total count
$count_query = $query;
$count_result = mysqli_query($conn, $count_query);
$total_notifications = mysqli_num_rows($count_result);
$total_pages = ceil($total_notifications / $records_per_page);

// Add order and limit
$query .= " ORDER BY n.date_sent DESC LIMIT $records_per_page OFFSET $offset";
$result = mysqli_query($conn, $query);
$notification_count = mysqli_num_rows($result);

// Get all courses for filter
$courses_result = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name ASC");

// Get all semesters for filter
$semester_result = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_name ASC");

// Get all students for filter
$staff_result = $conn->query("SELECT user_id, full_name, id_number FROM users WHERE role_id = 3 AND status = 'Active' ORDER BY full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - View Notifications</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/view_notification.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
  <div class="page-header">
    <div class="header-content">
      <h1><i class="fas fa-bell"></i> View Notifications</h1>
      <p class="header-subtitle">Manage sent notifications and messages</p>
    </div>
  </div>

  <!-- Filter Card -->
  <div class="filter-card">
    <div class="filter-header">
      <h3><i class="fas fa-filter"></i> Filter Notifications</h3>
      <button type="button" class="btn-toggle-filter" onclick="toggleFilter()">
        <i class="fas fa-chevron-down"></i>
      </button>
    </div>
    <form method="GET" action="view_notification.php" class="filter-form" id="filterForm">
      <div class="filter-row">
        <div class="filter-group">
          <label for="studentFilter"><i class="fas fa-user"></i> Student:</label>
          <select name="student_id" id="studentFilter" class="form-control">
            <option value="0">All Staff</option>
            <?php 
            $staff_result = $conn->query("SELECT user_id, full_name, id_number FROM users WHERE role_id = 3 AND status = 'Active' ORDER BY full_name ASC");
            while ($student = $staff_result->fetch_assoc()): ?>
              <option value="<?= $student['user_id'] ?>" <?= ($student['user_id'] == $selected_student) ? 'selected' : '' ?>>
                <?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['id_number']) ?>)
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="filter-group">
          <label for="courseFilter"><i class="fas fa-book"></i> Course:</label>
          <select name="course_id" id="courseFilter" class="form-control">
            <option value="0">All Courses</option>
            <?php 
            $courses_result = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name ASC");
            while ($course = $courses_result->fetch_assoc()): ?>
              <option value="<?= $course['course_id'] ?>" <?= ($course['course_id'] == $selected_course) ? 'selected' : '' ?>>
                <?= htmlspecialchars($course['course_name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="filter-group">
          <label for="semesterFilter"><i class="fas fa-calendar"></i> Semester:</label>
          <select name="sem_id" id="semesterFilter" class="form-control">
            <option value="0">All Semesters</option>
            <?php 
            $semester_result = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_name ASC");
            while ($semester = $semester_result->fetch_assoc()): ?>
              <option value="<?= $semester['sem_id'] ?>" <?= ($semester['sem_id'] == $selected_semester) ? 'selected' : '' ?>>
                <?= htmlspecialchars($semester['sem_name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>

      <div class="filter-row">
        <div class="filter-group">
          <label for="dateFrom"><i class="fas fa-calendar-alt"></i> Date From:</label>
          <input type="date" name="date_from" id="dateFrom" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
        </div>

        <div class="filter-group">
          <label for="dateTo"><i class="fas fa-calendar-alt"></i> Date To:</label>
          <input type="date" name="date_to" id="dateTo" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
        </div>

        <div class="filter-actions">
          <button type="submit" class="btn btn-primary-filter" name="search_submitted" value="1">
            <i class="fas fa-search"></i> Search
          </button>
          <button type="button" class="btn btn-secondary" onclick="clearFilters()">
            <i class="fas fa-times"></i> Clear
          </button>
        </div>
      </div>
    </form>

    <div class="notification-count">
      <span class="count-badge"><?= $total_notifications; ?></span> Notification<?= $total_notifications !== 1 ? 's' : '' ?> Found
    </div>
  </div>

  <div class="table-container">
    <table class="notifications-table">
      <thead>
        <tr>
          <th><i class="fas fa-user"></i> Profile</th>
          <th><i class="fas fa-id-card"></i> Full Name</th>
          <th><i class="fas fa-barcode"></i> ID Number</th>
          <th><i class="fas fa-book"></i> Course</th>
          <th><i class="fas fa-calendar"></i> Semester</th>
          <th><i class="fas fa-envelope"></i> Message</th>
          <th><i class="fas fa-clock"></i> Date Sent</th>
          <th><i class="fas fa-cogs"></i> Action</th>
        </tr>
      </thead>
      <tbody>
      <?php
        if ($notification_count > 0) {
          while ($row = mysqli_fetch_assoc($result)) {
              echo "<tr>";
              
              $role_id = $row['role_id'];
              $profile = !empty($row['profile_photo']) ? $row['profile_photo'] : 'default_profile.png';
              
              if ($role_id == 3) {
                  $profilePath = "images/uploads/profile_photos/$profile";
              } elseif ($role_id == 2){
                  $profilePath = "../uploads/$profile";
              } else {
                  $profilePath = "images/default_profile.png";
              }
              
              echo "<td class='profile-cell'>
                      <a href='user_profile.php?user_id={$row['user_id']}' class='profile-link'>
                        <img src='$profilePath' class='profile-pic' alt='Profile'>
                      </a>
                    </td>";
              
              echo "<td class='staff-name'>" . htmlspecialchars($row['full_name']) . "</td>";
              echo "<td class='id-number'>" . htmlspecialchars($row['id_number']) . "</td>";
              echo "<td><span class='course-badge'>" . htmlspecialchars($row['course_name'] ?? 'N/A') . "</span></td>";
              echo "<td><span class='semester-badge'>" . htmlspecialchars($row['sem_name'] ?? 'N/A') . "</span></td>";
              echo "<td class='message-cell'>" . nl2br(htmlspecialchars($row['message'])) . "</td>";
              echo "<td class='date-cell'>" . date('M d, Y h:i A', strtotime($row['date_sent'])) . "</td>";
              
              // Build URL with current filters for pagination
              $filter_params = '';
              if ($search_submitted) {
                  $filter_params .= '&search_submitted=1';
                  if ($selected_course > 0) $filter_params .= '&course_id=' . $selected_course;
                  if ($selected_semester > 0) $filter_params .= '&sem_id=' . $selected_semester;
                  if ($selected_student > 0) $filter_params .= '&student_id=' . $selected_student;
                  if (!empty($date_from)) $filter_params .= '&date_from=' . urlencode($date_from);
                  if (!empty($date_to)) $filter_params .= '&date_to=' . urlencode($date_to);
              }
              
              echo "<td class='action-cell'>
                      <a href='edit_notification.php?id={$row['id']}' class='btn btn-edit'>
                        <i class='fas fa-edit'></i> Edit
                      </a>
                      <a href='view_notification.php?delete_id={$row['id']}{$filter_params}' class='btn btn-delete'>
                        <i class='fas fa-trash'></i> Delete
                      </a>
                    </td>";
              echo "</tr>";
          }
        } else {
          echo "<tr><td colspan='8' class='no-data'><i class='fas fa-inbox'></i> No notifications found</td></tr>";
        }
      ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1 && $total_notifications > 0): ?>
  <div class="pagination-container">
    <ul class="pagination">
      <!-- Previous Button -->
      <li class="<?= $current_page === 1 ? 'disabled' : '' ?>">
        <?php if ($current_page > 1): ?>
          <a href="?page=<?= $current_page - 1 ?><?= $search_submitted ? '&search_submitted=1' : '' ?><?= $selected_course > 0 ? '&course_id=' . $selected_course : '' ?><?= $selected_semester > 0 ? '&sem_id=' . $selected_semester : '' ?><?= $selected_student > 0 ? '&student_id=' . $selected_student : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>">Previous</a>
        <?php else: ?>
          <span>Previous</span>
        <?php endif; ?>
      </li>

      <!-- Page Numbers -->
      <?php 
      $max_visible_pages = 5;
      $start_page = max(1, $current_page - floor($max_visible_pages / 2));
      $end_page = min($total_pages, $start_page + $max_visible_pages - 1);
      $start_page = max(1, $end_page - $max_visible_pages + 1);

      if ($start_page > 1):
      ?>
        <li>
          <a href="?page=1<?= $search_submitted ? '&search_submitted=1' : '' ?><?= $selected_course > 0 ? '&course_id=' . $selected_course : '' ?><?= $selected_semester > 0 ? '&sem_id=' . $selected_semester : '' ?><?= $selected_student > 0 ? '&student_id=' . $selected_student : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>">1</a>
        </li>
        <?php if ($start_page > 2): ?>
          <li><span>...</span></li>
        <?php endif; ?>
      <?php endif; ?>

      <?php for ($page = $start_page; $page <= $end_page; $page++): ?>
        <li class="<?= $page === $current_page ? 'active' : '' ?>">
          <?php if ($page === $current_page): ?>
            <span><?= $page ?></span>
          <?php else: ?>
            <a href="?page=<?= $page ?><?= $search_submitted ? '&search_submitted=1' : '' ?><?= $selected_course > 0 ? '&course_id=' . $selected_course : '' ?><?= $selected_semester > 0 ? '&sem_id=' . $selected_semester : '' ?><?= $selected_student > 0 ? '&student_id=' . $selected_student : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>">
              <?= $page ?>
            </a>
          <?php endif; ?>
        </li>
      <?php endfor; ?>

      <?php if ($end_page < $total_pages): ?>
        <?php if ($end_page < $total_pages - 1): ?>
          <li><span>...</span></li>
        <?php endif; ?>
        <li>
          <a href="?page=<?= $total_pages ?><?= $search_submitted ? '&search_submitted=1' : '' ?><?= $selected_course > 0 ? '&course_id=' . $selected_course : '' ?><?= $selected_semester > 0 ? '&sem_id=' . $selected_semester : '' ?><?= $selected_student > 0 ? '&student_id=' . $selected_student : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>">
            <?= $total_pages ?>
          </a>
        </li>
      <?php endif; ?>

      <!-- Next Button -->
      <li class="<?= $current_page === $total_pages ? 'disabled' : '' ?>">
        <?php if ($current_page < $total_pages): ?>
          <a href="?page=<?= $current_page + 1 ?><?= $search_submitted ? '&search_submitted=1' : '' ?><?= $selected_course > 0 ? '&course_id=' . $selected_course : '' ?><?= $selected_semester > 0 ? '&sem_id=' . $selected_semester : '' ?><?= $selected_student > 0 ? '&student_id=' . $selected_student : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>">Next</a>
        <?php else: ?>
          <span>Next</span>
        <?php endif; ?>
      </li>
    </ul>
    <div class="pagination-info">
      Page <?= $current_page ?> of <?= $total_pages ?>
    </div>
  </div>
  <?php endif; ?>
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
        <div class="notification-title">Delete Notification?</div>
        <div class="notification-message">Are you sure you want to delete this notification?</div>
        <div class="confirmation-message-preview">
            <strong>Message:</strong> <?php echo htmlspecialchars($message_to_delete); ?>
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
            window.location.href = 'view_notification.php<?= $search_submitted ? "?search_submitted=1" : "" ?><?= $selected_course > 0 ? "&course_id=" . $selected_course : "" ?><?= $selected_semester > 0 ? "&sem_id=" . $selected_semester : "" ?><?= $selected_student > 0 ? "&student_id=" . $selected_student : "" ?><?= !empty($date_from) ? "&date_from=" . urlencode($date_from) : "" ?><?= !empty($date_to) ? "&date_to=" . urlencode($date_to) : "" ?>';
        }, 300);
    }

    function confirmDelete(notificationId) {
        window.location.href = 'view_notification.php?delete_id=' + notificationId + '&confirm=yes<?= $search_submitted ? "&search_submitted=1" : "" ?><?= $selected_course > 0 ? "&course_id=" . $selected_course : "" ?><?= $selected_semester > 0 ? "&sem_id=" . $selected_semester : "" ?><?= $selected_student > 0 ? "&student_id=" . $selected_student : "" ?><?= !empty($date_from) ? "&date_from=" . urlencode($date_from) : "" ?><?= !empty($date_to) ? "&date_to=" . urlencode($date_to) : "" ?>';
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
    }, <?php echo $redirect_delay; ?>);
</script>
<?php endif; ?>

<script>
  function clearFilters() {
    window.location.href = 'view_notification.php';
  }

  function toggleFilter() {
    const filterForm = document.getElementById('filterForm');
    const toggleBtn = document.querySelector('.btn-toggle-filter i');
    
    if (filterForm.style.display === 'none') {
      filterForm.style.display = 'block';
      toggleBtn.classList.remove('fa-chevron-down');
      toggleBtn.classList.add('fa-chevron-up');
    } else {
      filterForm.style.display = 'none';
      toggleBtn.classList.remove('fa-chevron-up');
      toggleBtn.classList.add('fa-chevron-down');
    }
  }

  // Show filters by default if search is submitted
  <?php if ($search_submitted): ?>
  document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const toggleBtn = document.querySelector('.btn-toggle-filter i');
    filterForm.style.display = 'block';
    toggleBtn.classList.remove('fa-chevron-down');
    toggleBtn.classList.add('fa-chevron-up');
  });
  <?php endif; ?>
</script>

</body>
</html>
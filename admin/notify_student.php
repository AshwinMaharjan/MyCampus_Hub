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

$courses_result = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name ASC");
$semester_result = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_name ASC");
$selected_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$selected_semester = isset($_GET['sem_id']) ? intval($_GET['sem_id']) : 0;
$search_submitted = isset($_GET['search_submitted']) ? true : false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['message'], $_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $message = trim($_POST['message']);

    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $message);
        if ($stmt->execute()) {
            $notification = "Student notified successfully!";
            $notification_type = "success";
        } else {
            $notification = "Failed to send message.";
            $notification_type = "error";
        }
        $stmt->close();
    }
}

// Pagination variables
$records_per_page = 10;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $records_per_page;

// Build query
$query = "SELECT users.*, course.course_name, semester.sem_name
          FROM users
          LEFT JOIN course ON users.course_id = course.course_id
          LEFT JOIN semester ON users.sem_id = semester.sem_id
          WHERE users.role_id = 2 
            AND users.status = 'Active'";

if ($search_submitted) {
    if ($selected_course > 0) {
        $query .= " AND users.course_id = $selected_course";
    }
    if ($selected_semester > 0) {
        $query .= " AND users.sem_id = $selected_semester";
    }
}

// Get total count
$count_query = $query;
$count_result = mysqli_query($conn, $count_query);
$total_students = mysqli_num_rows($count_result);
$total_pages = ceil($total_students / $records_per_page);

// Add order and limit
$query .= " ORDER BY users.user_id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $records_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$students_on_page = $result->num_rows;

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - Notify Students</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/notify_student.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
  <div class="page-header">
    <div class="header-content">
      <h1><i class="fas fa-users"></i> Notify Students</h1>
      <p class="header-subtitle">Send messages and notifications to your students</p>
    </div>
  </div>

  <div class="filter-card">
    <form method="GET" action="notify_student.php" class="filter-form">
      <div class="filter-group">
        <label for="courseFilter"><i class="fas fa-book"></i> Course:</label>
        <select name="course_id" id="courseFilter" class="form-control">
          <option value="0" <?= $selected_course == 0 ? 'selected' : '' ?>>All Courses</option>
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
          <option value="0" <?= $selected_semester == 0 ? 'selected' : '' ?>>All Semesters</option>
          <?php 
          $semester_result = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_name ASC");
          while ($semester = $semester_result->fetch_assoc()): ?>
            <option value="<?= $semester['sem_id'] ?>" <?= ($semester['sem_id'] == $selected_semester) ? 'selected' : '' ?>>
              <?= htmlspecialchars($semester['sem_name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <button type="submit" class="btn btn-primary-filter" name="search_submitted" value="1">
        <i class="fas fa-search"></i> Search
      </button>
      <button type="button" class="btn btn-secondary" onclick="clearFilters()">
        <i class="fas fa-times"></i> Clear Filter
      </button>
    </form>

    <div class="student-count">
      <span class="count-badge"><?= $total_students; ?></span> Student<?= $total_students !== 1 ? 's' : '' ?> Found
    </div>
  </div>

  <div class="table-container">
    <table class="student-table">
      <thead>
        <tr>
          <th><i class="fas fa-user-circle"></i> Profile</th>
          <th><i class="fas fa-id-card"></i> ID Number</th>
          <th><i class="fas fa-user"></i> Full Name</th>
          <th><i class="fas fa-envelope"></i> Email</th>
          <th><i class="fas fa-book"></i> Course</th>
          <th><i class="fas fa-calendar"></i> Semester</th>
          <th><i class="fas fa-cogs"></i> Action</th>
        </tr>
      </thead>
      <tbody>
      <?php
        if ($students_on_page > 0) {
          while ($row = $result->fetch_assoc()) {
              echo "<tr>";
              echo "<td class='profile-cell'>
                      <a href='user_profile.php?user_id={$row['user_id']}' class='profile-link'>";
              if (!empty($row['profile_photo'])) {
                  echo "<img src='../uploads/{$row['profile_photo']}' class='profile-pic' alt='Profile' />";
              } else {
                  echo "<img src='../images/default_profile.png' class='profile-pic' alt='No Photo' />";
              }
              echo "    </a>
                    </td>";
              echo "<td class='id-number'>" . htmlspecialchars($row['id_number']) . "</td>";
              echo "<td class='student-name'>" . htmlspecialchars($row['full_name']) . "</td>";
              echo "<td class='email-cell'>" . htmlspecialchars($row['email']) . "</td>";
              echo "<td><span class='course-badge'>" . htmlspecialchars($row['course_name'] ?? 'N/A') . "</span></td>";
              echo "<td><span class='semester-badge'>" . htmlspecialchars($row['sem_name'] ?? 'N/A') . "</span></td>";
              echo "<td class='action-cell'>
                      <button onclick=\"openNotifyForm({$row['user_id']}, '" . htmlspecialchars($row['full_name']) . "')\" class='btn btn-notify' title='Send notification'>
                        <i class='fas fa-paper-plane'></i> Notify
                      </button>
                    </td>";
              echo "</tr>";
          }
        } else {
            echo "<tr><td colspan='7' class='no-data'><i class='fas fa-inbox'></i> No students found</td></tr>";
        }
      ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1 && $total_students > 0): ?>
  <div class="pagination-container">
    <ul class="pagination">
      <!-- Previous Button -->
      <li class="<?= $current_page === 1 ? 'disabled' : '' ?>">
        <?php if ($current_page > 1): ?>
          <a href="?page=<?= $current_page - 1 ?><?= $search_submitted && $selected_course > 0 ? '&course_id=' . $selected_course : '' ?><?= $search_submitted && $selected_semester > 0 ? '&sem_id=' . $selected_semester : '' ?><?= $search_submitted ? '&search_submitted=1' : '' ?>">Previous</a>
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
          <a href="?page=1<?= $search_submitted && $selected_course > 0 ? '&course_id=' . $selected_course : '' ?><?= $search_submitted && $selected_semester > 0 ? '&sem_id=' . $selected_semester : '' ?><?= $search_submitted ? '&search_submitted=1' : '' ?>">1</a>
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
            <a href="?page=<?= $page ?><?= $search_submitted && $selected_course > 0 ? '&course_id=' . $selected_course : '' ?><?= $search_submitted && $selected_semester > 0 ? '&sem_id=' . $selected_semester : '' ?><?= $search_submitted ? '&search_submitted=1' : '' ?>">
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
          <a href="?page=<?= $total_pages ?><?= $search_submitted && $selected_course > 0 ? '&course_id=' . $selected_course : '' ?><?= $search_submitted && $selected_semester > 0 ? '&sem_id=' . $selected_semester : '' ?><?= $search_submitted ? '&search_submitted=1' : '' ?>">
            <?= $total_pages ?>
          </a>
        </li>
      <?php endif; ?>

      <!-- Next Button -->
      <li class="<?= $current_page === $total_pages ? 'disabled' : '' ?>">
        <?php if ($current_page < $total_pages): ?>
          <a href="?page=<?= $current_page + 1 ?><?= $search_submitted && $selected_course > 0 ? '&course_id=' . $selected_course : '' ?><?= $search_submitted && $selected_semester > 0 ? '&sem_id=' . $selected_semester : '' ?><?= $search_submitted ? '&search_submitted=1' : '' ?>">Next</a>
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

<!-- Notify Modal -->
<div class="notify-modal" id="notifyModal">
  <div class="notify-modal-content">
    <div class="modal-header">
      <h2><i class="fas fa-envelope"></i> Send Notification</h2>
      <button type="button" class="close-btn" onclick="closeNotifyForm()">&times;</button>
    </div>
    <form method="POST" action="notify_student.php<?= $search_submitted ? '?search_submitted=1' : '' ?><?= $search_submitted && $selected_course > 0 ? '&course_id=' . $selected_course : '' ?><?= $search_submitted && $selected_semester > 0 ? '&sem_id=' . $selected_semester : '' ?>" class="notify-form-content">
      <div class="form-group">
        <label for="studentName">To:</label>
        <input type="text" id="studentName" class="form-control" readonly>
      </div>
      <div class="form-group">
        <label for="message">Message <span class="required">*</span></label>
        <textarea name="message" id="message" class="form-control" placeholder="Enter your message here..." required maxlength="500"></textarea>
        <small class="char-count"><span id="charCount">0</span>/500 characters</small>
      </div>
      <input type="hidden" name="user_id" id="notifyUserId" />
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-check"></i> Send Message
        </button>
        <button type="button" class="btn btn-secondary" onclick="closeNotifyForm()">
          <i class="fas fa-times"></i> Cancel
        </button>
      </div>
    </form>
  </div>
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
        <button class="notification-button" onclick="closeNotificationModal()">
            Okay
        </button>
    </div>
</div>

<script>
    function closeNotificationModal() {
        const overlay = document.getElementById('notificationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            overlay.classList.remove('active');
        }, 300);
    }

    setTimeout(() => {
        closeNotificationModal();
    }, <?php echo $redirect_delay; ?>);
</script>
<?php endif; ?>

<script>
  function clearFilters() {
    window.location.href = 'notify_student.php';
  }

  function openNotifyForm(userId, studentName) {
    document.getElementById('notifyUserId').value = userId;
    document.getElementById('studentName').value = studentName;
    document.getElementById('message').value = '';
    document.getElementById('charCount').textContent = '0';
    document.getElementById('notifyModal').style.display = 'flex';
  }

  function closeNotifyForm() {
    document.getElementById('notifyModal').style.display = 'none';
  }

  document.getElementById('message').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
  });

  window.addEventListener('click', function(event) {
    const modal = document.getElementById('notifyModal');
    if (event.target === modal) {
      closeNotifyForm();
    }
  });
</script>

</body>
</html>
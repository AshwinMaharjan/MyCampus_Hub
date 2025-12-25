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

// Handle individual student notification
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

// Handle bulk notification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_message'], $_POST['notification_type'])) {
    $bulk_message = trim($_POST['bulk_message']);
    $notif_type = $_POST['notification_type'];
    $bulk_course = isset($_POST['bulk_course_id']) ? intval($_POST['bulk_course_id']) : 0;
    $bulk_semester = isset($_POST['bulk_sem_id']) ? intval($_POST['bulk_sem_id']) : 0;

    if (!empty($bulk_message)) {
        // Build query to get target students
        $target_query = "SELECT user_id FROM users WHERE role_id = 2 AND status = 'Active'";
        
        if ($notif_type == 'course' && $bulk_course > 0) {
            $target_query .= " AND course_id = $bulk_course";
        } elseif ($notif_type == 'semester' && $bulk_semester > 0) {
            $target_query .= " AND sem_id = $bulk_semester";
        } elseif ($notif_type == 'course_semester' && $bulk_course > 0 && $bulk_semester > 0) {
            $target_query .= " AND course_id = $bulk_course AND sem_id = $bulk_semester";
        }
        
        $target_result = $conn->query($target_query);
        
        if ($target_result && $target_result->num_rows > 0) {
            $success_count = 0;
            $fail_count = 0;
            
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            
            while ($student = $target_result->fetch_assoc()) {
                $stmt->bind_param("is", $student['user_id'], $bulk_message);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $fail_count++;
                }
            }
            
            $stmt->close();
            
            if ($success_count > 0) {
                $notification = "Successfully notified $success_count student(s)!";
                $notification_type = "success";
                if ($fail_count > 0) {
                    $notification .= " ($fail_count failed)";
                }
            } else {
                $notification = "Failed to send notifications.";
                $notification_type = "error";
            }
        } else {
            $notification = "No students found matching the criteria.";
            $notification_type = "error";
        }
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
  <style>
    /* Add these styles to your notify_student.css file */

/* Bulk Notify Button in Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.btn-bulk-notify {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-bulk-notify:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-bulk-notify i {
  margin-right: 0.5rem;
}

/* Bulk Modal Specific Styles */
.bulk-modal {
  max-width: 600px;
}

.radio-group {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.radio-label {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  background: #f8f9fa;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.radio-label:hover {
  background: #e9ecef;
  border-color: #667eea;
}

.radio-label input[type="radio"] {
  margin-right: 0.75rem;
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #667eea;
}

.radio-label input[type="radio"]:checked + span {
  color: #667eea;
  font-weight: 600;
}

.radio-label span {
  font-size: 0.95rem;
  color: #495057;
  transition: all 0.3s ease;
}

.bulk-warning {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 8px;
  margin: 1rem 0;
}

.bulk-warning i {
  color: #ffc107;
  font-size: 1.25rem;
}

.bulk-warning span {
  color: #856404;
  font-size: 0.9rem;
  font-weight: 500;
}

/* Form Group Transitions */
#bulkCourseGroup,
#bulkSemesterGroup {
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
/* Make modals scrollable */
.notify-modal {
    display: none; /* hidden by default */
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow-y: auto; /* <-- this allows vertical scrolling */
    justify-content: center;
    align-items: center;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    padding: 1rem; /* gives space around the modal */
}

.notify-modal-content {
    background: #fff;
    border-radius: 10px;
    width: 100%;
    max-width: 600px; /* you can adjust */
    margin: 2rem auto; /* centers and adds vertical space */
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    max-height: 90vh; /* <-- prevents modal from being taller than viewport */
    overflow-y: auto; /* <-- makes modal body scrollable if content is too tall */
}

  </style>
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
    <button onclick="openBulkNotifyForm()" class="btn btn-bulk-notify">
      <i class="fas fa-bullhorn"></i> Bulk Notify
    </button>
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
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<!-- Individual Notify Modal -->
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

<!-- Bulk Notify Modal -->
<div class="notify-modal" id="bulkNotifyModal">
  <div class="notify-modal-content bulk-modal">
    <div class="modal-header">
      <h2><i class="fas fa-bullhorn"></i> Bulk Notification</h2>
      <button type="button" class="close-btn" onclick="closeBulkNotifyForm()">&times;</button>
    </div>
    <form method="POST" action="notify_student.php" class="notify-form-content" id="bulkNotifyForm">
      <div class="form-group">
        <label>Send To:</label>
        <div class="radio-group">
          <label class="radio-label">
            <input type="radio" name="notification_type" value="all" checked onchange="updateBulkTarget()">
            <span>All Students</span>
          </label>
          <label class="radio-label">
            <input type="radio" name="notification_type" value="course" onchange="updateBulkTarget()">
            <span>Specific Course</span>
          </label>
          <label class="radio-label">
            <input type="radio" name="notification_type" value="semester" onchange="updateBulkTarget()">
            <span>Specific Semester</span>
          </label>
          <label class="radio-label">
            <input type="radio" name="notification_type" value="course_semester" onchange="updateBulkTarget()">
            <span>Course & Semester</span>
          </label>
        </div>
      </div>

      <div class="form-group" id="bulkCourseGroup" style="display: none;">
        <label for="bulkCourse"><i class="fas fa-book"></i> Select Course:</label>
        <select name="bulk_course_id" id="bulkCourse" class="form-control">
          <option value="0">Select Course</option>
          <?php 
          $courses_result = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name ASC");
          while ($course = $courses_result->fetch_assoc()): ?>
            <option value="<?= $course['course_id'] ?>">
              <?= htmlspecialchars($course['course_name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group" id="bulkSemesterGroup" style="display: none;">
        <label for="bulkSemester"><i class="fas fa-calendar"></i> Select Semester:</label>
        <select name="bulk_sem_id" id="bulkSemester" class="form-control">
          <option value="0">Select Semester</option>
          <?php 
          $semester_result = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_name ASC");
          while ($semester = $semester_result->fetch_assoc()): ?>
            <option value="<?= $semester['sem_id'] ?>">
              <?= htmlspecialchars($semester['sem_name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="bulkMessage">Message <span class="required">*</span></label>
        <textarea name="bulk_message" id="bulkMessage" class="form-control" placeholder="Enter your message here..." required maxlength="500"></textarea>
        <small class="char-count"><span id="bulkCharCount">0</span>/500 characters</small>
      </div>

      <div class="bulk-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <span id="targetInfo">This will send notification to all active students.</span>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-paper-plane"></i> Send Bulk Notification
        </button>
        <button type="button" class="btn btn-secondary" onclick="closeBulkNotifyForm()">
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

  function openBulkNotifyForm() {
    document.getElementById('bulkNotifyModal').style.display = 'flex';
    updateBulkTarget();
  }

  function closeBulkNotifyForm() {
    document.getElementById('bulkNotifyModal').style.display = 'none';
    document.getElementById('bulkNotifyForm').reset();
  }

  function updateBulkTarget() {
    const notifType = document.querySelector('input[name="notification_type"]:checked').value;
    const courseGroup = document.getElementById('bulkCourseGroup');
    const semesterGroup = document.getElementById('bulkSemesterGroup');
    const targetInfo = document.getElementById('targetInfo');

    courseGroup.style.display = 'none';
    semesterGroup.style.display = 'none';

    if (notifType === 'all') {
      targetInfo.textContent = 'This will send notification to all active students.';
    } else if (notifType === 'course') {
      courseGroup.style.display = 'block';
      targetInfo.textContent = 'This will send notification to all students in the selected course.';
    } else if (notifType === 'semester') {
      semesterGroup.style.display = 'block';
      targetInfo.textContent = 'This will send notification to all students in the selected semester.';
    } else if (notifType === 'course_semester') {
      courseGroup.style.display = 'block';
      semesterGroup.style.display = 'block';
      targetInfo.textContent = 'This will send notification to all students in the selected course and semester.';
    }
  }

  document.getElementById('message').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
  });

  document.getElementById('bulkMessage').addEventListener('input', function() {
    document.getElementById('bulkCharCount').textContent = this.value.length;
  });

  window.addEventListener('click', function(event) {
    const modal = document.getElementById('notifyModal');
    const bulkModal = document.getElementById('bulkNotifyModal');
    if (event.target === modal) {
      closeNotifyForm();
    }
    if (event.target === bulkModal) {
      closeBulkNotifyForm();
    }
  });
</script>

</body>
</html>
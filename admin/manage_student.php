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
$confirmation_action = "";
$confirm_user_id = null;
$student_name_to_confirm = null;

// Handle delete action if confirmed
if (isset($_GET['delete_id']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $notification = "Student deleted successfully.";
        $notification_type = "success";
    } else {
        $notification = "Error: Could not delete student.";
        $notification_type = "error";
    }
    $stmt->close();
}
// Show confirmation modal for delete
elseif (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result_check = $stmt->get_result();
    if ($result_check->num_rows > 0) {
        $row = $result_check->fetch_assoc();
        $show_confirmation = true;
        $confirmation_action = "delete";
        $confirm_user_id = $delete_id;
        $student_name_to_confirm = $row['full_name'];
    }
    $stmt->close();
}

// Handle approve action if confirmed
if (isset($_GET['approve_id']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $user_id = intval($_GET['approve_id']);
    $stmt = $conn->prepare("UPDATE users SET status = 'Active' WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $notification = "Student approved successfully.";
        $notification_type = "success";
    } else {
        $notification = "Error approving student.";
        $notification_type = "error";
    }
    $stmt->close();
}
// Show confirmation modal for approve
elseif (isset($_GET['approve_id'])) {
    $approve_id = intval($_GET['approve_id']);
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $approve_id);
    $stmt->execute();
    $result_check = $stmt->get_result();
    if ($result_check->num_rows > 0) {
        $row = $result_check->fetch_assoc();
        $show_confirmation = true;
        $confirmation_action = "approve";
        $confirm_user_id = $approve_id;
        $student_name_to_confirm = $row['full_name'];
    }
    $stmt->close();
}

// Handle decline action if confirmed
if (isset($_GET['decline_id']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $user_id = intval($_GET['decline_id']);
    $stmt = $conn->prepare("UPDATE users SET status = 'Inactive' WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $notification = "Student declined successfully.";
        $notification_type = "success";
    } else {
        $notification = "Error declining student.";
        $notification_type = "error";
    }
    $stmt->close();
}
// Show confirmation modal for decline
elseif (isset($_GET['decline_id'])) {
    $decline_id = intval($_GET['decline_id']);
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $decline_id);
    $stmt->execute();
    $result_check = $stmt->get_result();
    if ($result_check->num_rows > 0) {
        $row = $result_check->fetch_assoc();
        $show_confirmation = true;
        $confirmation_action = "decline";
        $confirm_user_id = $decline_id;
        $student_name_to_confirm = $row['full_name'];
    }
    $stmt->close();
}

$selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$selected_course = isset($_GET['course']) ? intval($_GET['course']) : 0;
$selected_status = isset($_GET['status']) ? $_GET['status'] : '';
$search_student = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination variables
$records_per_page = 20;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $records_per_page;

$semesters_result = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_name ASC");
$courses_result = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name");

$semesters = [];
if ($semesters_result) {
    $semesters = $semesters_result->fetch_all(MYSQLI_ASSOC);
}

$courses = [];
if ($courses_result) {
    $courses = $courses_result->fetch_all(MYSQLI_ASSOC);
}

// Build the WHERE clause
$sql = "SELECT u.*, c.course_name, s.sem_name
        FROM users u
        LEFT JOIN course c ON u.course_id = c.course_id
        LEFT JOIN semester s ON u.sem_id = s.sem_id
        WHERE u.role_id = 2";

$params = [];
$types = "";

if ($selected_semester !== 0) {
    $sql .= " AND u.sem_id = ?";
    $types .= "i";
    $params[] = $selected_semester;
}

if ($selected_course !== 0) {
    $sql .= " AND u.course_id = ?";
    $types .= "i";
    $params[] = $selected_course;
}

if ($selected_status !== '') {
    if ($selected_status === 'Pending') {
        $sql .= " AND (u.status IS NULL OR u.status = '')";
    } else {
        $sql .= " AND u.status = ?";
        $types .= "s";
        $params[] = $selected_status;
    }
}

if (!empty($search_student)) {
    $sql .= " AND (u.full_name LIKE ? OR u.id_number LIKE ? OR u.email LIKE ?)";
    $types .= "sss";
    $searchTerm = "%" . $search_student . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total " . substr($sql, strpos($sql, "FROM"));
$count_stmt = $conn->prepare($count_sql);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$count_stmt->close();

// Add ORDER BY and LIMIT for pagination
$sql .= " ORDER BY u.user_id DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $records_per_page;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Student</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/manage_student.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/notification_modal.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
  <!-- Enhanced Filter Container -->
  <div class="filter-container">
    <form method="GET" action="manage_student.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; width: 100%;">
      
      <div class="filter-group">
        <label for="semesterFilter">Semester</label>
        <select id="semesterFilter" name="semester">
          <option value="0" <?= $selected_semester === 0 ? 'selected' : ''; ?>>All Semesters</option>
          <?php foreach ($semesters as $sem): ?>
            <option value="<?= $sem['sem_id']; ?>" <?= $selected_semester === (int)$sem['sem_id'] ? 'selected' : ''; ?>>
              <?= htmlspecialchars($sem['sem_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label for="courseFilter">Course</label>
        <select id="courseFilter" name="course">
          <option value="0" <?= $selected_course === 0 ? 'selected' : ''; ?>>All Courses</option>
          <?php foreach ($courses as $course): ?>
            <option value="<?= $course['course_id']; ?>" <?= $selected_course === (int)$course['course_id'] ? 'selected' : ''; ?>>
              <?= htmlspecialchars($course['course_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label for="statusFilter">Status</label>
        <select id="statusFilter" name="status">
          <option value="" <?= $selected_status === '' ? 'selected' : ''; ?>>All Status</option>
          <option value="Pending" <?= $selected_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="Active" <?= $selected_status === 'Active' ? 'selected' : ''; ?>>Active</option>
          <option value="Inactive" <?= $selected_status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
      </div>

      <div class="search-group">
        <div class="filter-group" style="flex: 1; min-width: 200px;">
          <label for="search">Search</label>
          <input type="text" name="search" id="search" placeholder="Name, ID, or Email..." value="<?= htmlspecialchars($search_student) ?>">
        </div>
        <button type="submit" class="search-btn">
          <i class="fas fa-search"></i> Search
        </button>
      </div>

      <button type="button" class="clear-filters-btn" onclick="window.location='manage_student.php';">
        <i class="fas fa-times"></i> Clear Filters
      </button>
    </form>
  </div>

  <?php if ($total_records > 0): ?>
    <div class="pagination-info">
      Showing <?= $offset + 1; ?> to <?= min($offset + $records_per_page, $total_records); ?> of <?= $total_records; ?> students
    </div>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>ID Number</th>
        <th>Profile</th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Gender</th>
        <th>Contact</th>
        <th>Semester</th>
        <th>Course</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['id_number']); ?></td>
            <td>
    <img src="<?= !empty($row['profile_photo']) ? '../uploads/' . htmlspecialchars($row['profile_photo']) : '../images/default_profile.png'; ?>" 
         alt="Profile" 
         class="profile-pic" />
</td>

            <td><?= htmlspecialchars($row['full_name']); ?></td>
            <td><?= htmlspecialchars($row['email']); ?></td>
            <td><?= htmlspecialchars($row['gender']); ?></td>
            <td><?= htmlspecialchars($row['contact_number']); ?></td>
            <td><?= htmlspecialchars($row['sem_name']); ?></td>
            <!-- <td><?= htmlspecialchars($row['address']); ?></td> -->
            <td><?= htmlspecialchars($row['course_name'] ?? 'N/A'); ?></td>
            <td>
              <?php
                if ($row['status'] === '' || $row['status'] === null) {
                  echo "<a href='manage_student.php?approve_id={$row['user_id']}' class='btn-approve'>
                          <i class='fas fa-check'></i> Approve
                        </a>
                        <a href='manage_student.php?decline_id={$row['user_id']}' class='btn-decline'>
                          <i class='fas fa-times'></i> Decline
                        </a>
                        <a href='manage_student.php?delete_id={$row['user_id']}' class='btn-decline'>
                          <i class='fas fa-trash'></i> Delete
                        </a>";
                } elseif ($row['status'] == 'Active') {
                  echo "<span class='status-approved'>Approved</span>
                  <a href='manage_student.php?delete_id={$row['user_id']}' class='btn-decline'>
                          <i class='fas fa-trash'></i> Delete </a>";
                } elseif ($row['status'] == 'Inactive') {
                  echo "<span class='status-declined'>Declined</span>;
                                    <a href='manage_student.php?delete_id={$row['user_id']}' class='btn-decline'>
                          <i class='fas fa-trash'></i> Delete </a>";
                }
              ?>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="9" style="color:red; font-weight:bold;">No students found for selected filters.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php
      // Build query string for pagination links
      $query_params = [];
      if ($selected_course !== 0) $query_params['course'] = $selected_course;
      if ($selected_semester !== 0) $query_params['semester'] = $selected_semester;
      if ($selected_status !== '') $query_params['status'] = $selected_status;
      if (!empty($search_student)) $query_params['search'] = $search_student;
      
      // Previous button
      if ($current_page > 1) {
        $query_params['page'] = $current_page - 1;
        echo '<a href="?'.http_build_query($query_params).'">Previous</a>';
      } else {
        echo '<span class="disabled">Previous</span>';
      }
      
      // Page numbers
      $range = 2;
      $start_page = max(1, $current_page - $range);
      $end_page = min($total_pages, $current_page + $range);
      
      // First page
      if ($start_page > 1) {
        $query_params['page'] = 1;
        echo '<a href="?'.http_build_query($query_params).'">1</a>';
        if ($start_page > 2) {
          echo '<span>...</span>';
        }
      }
      
      // Page numbers in range
      for ($i = $start_page; $i <= $end_page; $i++) {
        $query_params['page'] = $i;
        if ($i == $current_page) {
          echo '<span class="active">'.$i.'</span>';
        } else {
          echo '<a href="?'.http_build_query($query_params).'">'.$i.'</a>';
        }
      }
      
      // Last page
      if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
          echo '<span>...</span>';
        }
        $query_params['page'] = $total_pages;
        echo '<a href="?'.http_build_query($query_params).'">'.$total_pages.'</a>';
      }
      
      // Next button
      if ($current_page < $total_pages) {
        $query_params['page'] = $current_page + 1;
        echo '<a href="?'.http_build_query($query_params).'">Next</a>';
      } else {
        echo '<span class="disabled">Next</span>';
      }
      ?>
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
        <div class="notification-title">
            <?php 
            if ($confirmation_action === 'delete') {
                echo 'Delete Student?';
            } elseif ($confirmation_action === 'approve') {
                echo 'Approve Student?';
            } elseif ($confirmation_action === 'decline') {
                echo 'Decline Student?';
            }
            ?>
        </div>
        <div class="notification-message">
            <?php 
            if ($confirmation_action === 'delete') {
                echo 'Are you sure you want to delete this student?';
            } elseif ($confirmation_action === 'approve') {
                echo 'Are you sure you want to approve this student?';
            } elseif ($confirmation_action === 'decline') {
                echo 'Are you sure you want to decline this student?';
            }
            ?>
        </div>
        <div class="confirmation-student-name"><?php echo htmlspecialchars($student_name_to_confirm); ?></div>
        <div class="notification-buttons">
            <button class="notification-button notification-button-cancel" onclick="cancelAction()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="notification-button notification-button-confirm" onclick="confirmAction('<?php echo $confirmation_action; ?>', <?php echo $confirm_user_id; ?>)">
                <i class="fas fa-check"></i> 
                <?php 
                if ($confirmation_action === 'delete') {
                    echo 'Delete';
                } elseif ($confirmation_action === 'approve') {
                    echo 'Approve';
                } elseif ($confirmation_action === 'decline') {
                    echo 'Decline';
                }
                ?>
            </button>
        </div>
    </div>
</div>

<script>
    function cancelAction() {
        const overlay = document.getElementById('confirmationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            window.location.href = 'manage_student.php<?php 
                $params = [];
                if ($selected_course !== 0) $params[] = 'course=' . $selected_course;
                if ($selected_semester !== 0) $params[] = 'semester=' . $selected_semester;
                if ($selected_status !== '') $params[] = 'status=' . urlencode($selected_status);
                if (!empty($search_student)) $params[] = 'search=' . urlencode($search_student);
                if ($current_page > 1) $params[] = 'page=' . $current_page;
                echo !empty($params) ? '?' . implode('&', $params) : '';
            ?>';
        }, 300);
    }

    function confirmAction(action, userId) {
        let url = 'manage_student.php?' + action + '_id=' + userId + '&confirm=yes';
        <?php 
        $params = [];
        if ($selected_course !== 0) $params[] = 'course=' . $selected_course;
        if ($selected_semester !== 0) $params[] = 'semester=' . $selected_semester;
        if ($selected_status !== '') $params[] = 'status=' . urlencode($selected_status);
        if (!empty($search_student)) $params[] = 'search=' . urlencode($search_student);
        if ($current_page > 1) $params[] = 'page=' . $current_page;
        if (!empty($params)) {
            echo 'url += "&' . implode('&', $params) . '";';
        }
        ?>
        window.location.href = url;
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
            window.location.href = 'manage_student.php<?php 
                $params = [];
                if ($selected_course !== 0) $params[] = 'course=' . $selected_course;
                if ($selected_semester !== 0) $params[] = 'semester=' . $selected_semester;
                if ($selected_status !== '') $params[] = 'status=' . urlencode($selected_status);
                if (!empty($search_student)) $params[] = 'search=' . urlencode($search_student);
                if ($current_page > 1) $params[] = 'page=' . $current_page;
                echo !empty($params) ? '?' . implode('&', $params) : '';
            ?>';
        }, 300);
    }

    setTimeout(() => {
        closeNotification();
    }, 2000);
</script>
<?php endif; ?>

</body>
</html>
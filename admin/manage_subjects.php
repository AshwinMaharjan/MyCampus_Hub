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
$subject_name_to_delete = null;

// Handle delete action if confirmed
if (isset($_GET['delete_id']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM subject WHERE sub_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $notification = "Subject deleted successfully.";
        $notification_type = "success";
    } else {
        $notification = "Error: Could not delete subject.";
        $notification_type = "error";
    }
    $stmt->close();
}
// Show confirmation modal if delete_id is present without confirmation
elseif (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT sub_name FROM subject WHERE sub_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result_check = $stmt->get_result();
    if ($result_check->num_rows > 0) {
        $row = $result_check->fetch_assoc();
        $show_confirmation = true;
        $delete_id_to_confirm = $delete_id;
        $subject_name_to_delete = $row['sub_name'];
    }
    $stmt->close();
}

// Fetch Semesters and Courses for filters
$semesters_result = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_id");
$semesters = $semesters_result ? $semesters_result->fetch_all(MYSQLI_ASSOC) : [];
$courses_result = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name");
$courses = $courses_result ? $courses_result->fetch_all(MYSQLI_ASSOC) : [];

// Capture selected filters from GET request
$selectedCourse = isset($_GET['course']) ? intval($_GET['course']) : 0;
$selectedSem = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$search_subject = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination Setup
$records_per_page = 20;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $records_per_page;

// Build SQL query with filters
$sql = "SELECT 
            s.sub_id, 
            s.sub_name,
            u.full_name AS staff_name, 
            c.course_name,
            sem.sem_name 
        FROM subject s
        LEFT JOIN users u ON s.role_id = u.user_id
        LEFT JOIN course c ON s.course_id = c.course_id
        LEFT JOIN semester sem ON s.sem_id = sem.sem_id
        WHERE 1";

$params = [];
$types = "";

// Apply filters
if ($selectedSem !== 0) {
    $sql .= " AND sem.sem_id = ?";
    $types .= "i";
    $params[] = $selectedSem;
}
if ($selectedCourse !== 0) {
    $sql .= " AND c.course_id = ?";
    $types .= "i";
    $params[] = $selectedCourse;
}
if (!empty($search_subject)) {
    $sql .= " AND (s.sub_name LIKE ? OR u.full_name LIKE ?)";
    $types .= "ss";
    $searchTerm = "%" . $search_subject . "%";
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

// Add ORDER BY and LIMIT to the main query
$sql .= " ORDER BY s.sub_id DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $records_per_page;
$params[] = $offset;

// Execute main query
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
  <title>Admin Dashboard - Manage Subjects</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/my_students.css" />
  <link rel="stylesheet" href="../css/manage_courses.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
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
  flex-wrap: nowrap;
}

.filter-container form {
  display: flex;
  align-items: center;
  flex-wrap: nowrap;
  width: 100%;
  gap: 10px;
}

.filter-container select,
.filter-container input[type="text"] {
  padding: 7px 12px;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 14px;
  min-width: 150px;
}

.filter-container .search-btn,
.filter-container .clear-filters-btn {
  padding: 7px 14px;
  font-size: 14px;
  border-radius: 4px;
  border: none;
  cursor: pointer;
  font-weight: 500;
}

.search-btn {
  background: #263576;
  color: #fff;
}

.search-btn:hover {
  background: #1f2e65;
}

.clear-filters-btn {
  background: #6c757d;
  color: #fff;
}

.clear-filters-btn:hover {
  background: #5a6268;
}
    
    .pagination-container {
      margin-top: 30px;
      text-align: center;
    }

    .pagination {
      display: inline-flex;
      gap: 5px;
      list-style: none;
      margin: 0;
      padding: 0;
    }
    
    .pagination li {
      display: inline;
    }
    
    .pagination a, .pagination span {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      text-decoration: none;
      color: #263576;
    }
    
    .pagination a:hover {
      background-color: #263576;
      color: white;
    }
    
    .pagination .active span {
      background-color:  #263576;
      color: white;
      border-color:  #263576;
    }
    
    .pagination .disabled {
      opacity: 0.5;
      pointer-events: none;
    }
    
    .page-info {
      margin-top: 15px;
      font-size: 13px;
      color: #666;
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

    .confirmation-subject-name {
        font-weight: 600;
        color: #1f2937;
        background: #f3f4f6;
        padding: 10px;
        border-radius: 6px;
        margin: 15px 0;
    }
  </style>
</head>
<body>
<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="form-container">
  <h2>Manage Subjects</h2>

  <div class="filter-container">
    <form method="GET">
      <div class="filter-group">
        <select name="semester" id="semester">
          <option value="0">All Semesters</option>
          <?php foreach ($semesters as $sem): ?>
            <option value="<?= $sem['sem_id'] ?>" <?= $selectedSem == $sem['sem_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($sem['sem_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="course" id="course">
          <option value="0">All Courses</option>
          <?php foreach ($courses as $course): ?>
            <option value="<?= $course['course_id'] ?>" <?= $selectedCourse == $course['course_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($course['course_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="search" placeholder="Enter subject or staff name..." 
               value="<?= htmlspecialchars($search_subject ?? '') ?>">
        <button type="submit" class="search-btn">
          <i class="fas fa-search"></i> Search
        </button>
      </div>

      <button type="button" class="clear-filters-btn"
              onclick="window.location='<?= basename($_SERVER['PHP_SELF']); ?>';">
        <i class="fas fa-times"></i> Clear
      </button>
    </form>
  </div>

  <?php if ($result && $result->num_rows > 0): ?>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Subject Name</th>
          <th>Staff</th>
          <th>Semester</th>
          <th>Course</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($row['sub_id']); ?></td>
            <td><?php echo htmlspecialchars($row['sub_name']); ?></td>
            <td><?php echo htmlspecialchars($row['staff_name'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($row['sem_name'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($row['course_name'] ?? 'N/A'); ?></td>
            <td>
              <a href="edit_subject.php?id=<?php echo $row['sub_id']; ?>" class="btn btn-edit">
                <i class="fas fa-edit"></i> Edit
              </a>
              <a href="manage_subjects.php?delete_id=<?php echo $row['sub_id']; ?>" class="btn btn-delete">
                <i class="fas fa-trash-alt"></i> Delete
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination-container">
      <ul class="pagination">
        <!-- Previous Button -->
        <li class="<?= $current_page === 1 ? 'disabled' : '' ?>">
          <?php if ($current_page > 1): ?>
            <a href="?page=<?= $current_page - 1 ?><?= $selectedSem !== 0 ? '&semester=' . $selectedSem : '' ?><?= $selectedCourse !== 0 ? '&course=' . $selectedCourse : '' ?><?= !empty($search_subject) ? '&search=' . urlencode($search_subject) : '' ?>">Previous</a>
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
            <a href="?page=1<?= $selectedSem !== 0 ? '&semester=' . $selectedSem : '' ?><?= $selectedCourse !== 0 ? '&course=' . $selectedCourse : '' ?><?= !empty($search_subject) ? '&search=' . urlencode($search_subject) : '' ?>">1</a>
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
              <a href="?page=<?= $page ?><?= $selectedSem !== 0 ? '&semester=' . $selectedSem : '' ?><?= $selectedCourse !== 0 ? '&course=' . $selectedCourse : '' ?><?= !empty($search_subject) ? '&search=' . urlencode($search_subject) : '' ?>">
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
            <a href="?page=<?= $total_pages ?><?= $selectedSem !== 0 ? '&semester=' . $selectedSem : '' ?><?= $selectedCourse !== 0 ? '&course=' . $selectedCourse : '' ?><?= !empty($search_subject) ? '&search=' . urlencode($search_subject) : '' ?>">
              <?= $total_pages ?>
            </a>
          </li>
        <?php endif; ?>

        <!-- Next Button -->
        <li class="<?= $current_page === $total_pages ? 'disabled' : '' ?>">
          <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?= $current_page + 1 ?><?= $selectedSem !== 0 ? '&semester=' . $selectedSem : '' ?><?= $selectedCourse !== 0 ? '&course=' . $selectedCourse : '' ?><?= !empty($search_subject) ? '&search=' . urlencode($search_subject) : '' ?>">Next</a>
          <?php else: ?>
            <span>Next</span>
          <?php endif; ?>
        </li>
      </ul>
      <div class="page-info">
        Page <?= $current_page ?> of <?= $total_pages ?> (Total: <?= $total_records ?> records)
      </div>
    </div>
    <?php endif; ?>

  <?php else: ?>
    <p style="color: red; font-weight: bold;">No subjects found for selected filters.</p>
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
        <div class="notification-title">Delete Subject?</div>
        <div class="notification-message">Are you sure you want to delete this subject?</div>
        <div class="confirmation-subject-name"><?php echo htmlspecialchars($subject_name_to_delete); ?></div>
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

    function confirmDelete(subjectId) {
        window.location.href = 'manage_subjects.php?delete_id=' + subjectId + '&confirm=yes' + 
            '<?= $selectedSem !== 0 ? '&semester=' . $selectedSem : '' ?>' +
            '<?= $selectedCourse !== 0 ? '&course=' . $selectedCourse : '' ?>' +
            '<?= !empty($search_subject) ? '&search=' . urlencode($search_subject) : '' ?>' +
            '&page=<?= $current_page ?>';
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
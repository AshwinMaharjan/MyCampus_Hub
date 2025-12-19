<?php
include("connect.php");

$notification = null;
$notification_type = null;
$show_confirmation = false;
$delete_id_to_confirm = null;
$staff_name_to_delete = null;

// Handle delete staff if confirmed
if (isset($_GET['delete_id']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $delete_id = intval($_GET['delete_id']);
    
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $notification = "Staff deleted successfully.";
        $notification_type = "success";
    } else {
        $notification = "Error deleting staff.";
        $notification_type = "error";
    }
    
    $stmt->close();
}
// Show confirmation modal if delete_id is present without confirmation
elseif (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result_check = $stmt->get_result();
    if ($result_check->num_rows > 0) {
        $row = $result_check->fetch_assoc();
        $show_confirmation = true;
        $delete_id_to_confirm = $delete_id;
        $staff_name_to_delete = $row['full_name'];
    }
    $stmt->close();
}

// Get filter values
$sem_filter = isset($_GET['semester']) ? trim($_GET['semester']) : '';
$course_filter = isset($_GET['course']) ? trim($_GET['course']) : '';
$staff_type_filter = isset($_GET['staff_type']) ? trim($_GET['staff_type']) : '';
$search_filter = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch semesters for dropdown - all available semesters
$all_semesters = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
$semesters = $all_semesters;

// Fetch courses for dropdown
$courses_result = $conn->query("SELECT DISTINCT course_name FROM course ORDER BY course_name");
$courses = [];
if ($courses_result) {
    while ($row = $courses_result->fetch_assoc()) {
        $courses[] = $row['course_name'];
    }
}

// Pagination variables
$records_per_page = 10;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $records_per_page;

// Build query with filters
$query = "SELECT * FROM users WHERE role_id = 3";
$params = [];
$types = "";

if (!empty($sem_filter)) {
    $query .= " AND FIND_IN_SET(?, sem_name)";
    $params[] = $sem_filter;
    $types .= "s";
}

if (!empty($course_filter)) {
    $query .= " AND FIND_IN_SET(?, course_name)";
    $params[] = $course_filter;
    $types .= "s";
}

if (!empty($staff_type_filter)) {
    $query .= " AND staff_type = ?";
    $params[] = $staff_type_filter;
    $types .= "s";
}

if (!empty($search_filter)) {
    $query .= " AND (full_name LIKE ? OR id_number LIKE ? OR email LIKE ?)";
    $searchTerm = "%" . $search_filter . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// Get total count for pagination
$count_query = $query;
$count_stmt = $conn->prepare($count_query);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->num_rows;
$total_pages = ceil($total_records / $records_per_page);
$count_stmt->close();

// Add order, limit to main query
$query .= " ORDER BY user_id DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $records_per_page;
$params[] = $offset;

// Execute main query
$stmt = $conn->prepare($query);
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - Manage Staff</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/manage_staff.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    /* Filter Container Styling */
    .filter-container {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 15px 18px;
      margin-bottom: 25px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .filter-container form {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }

    .filter-container input[type="text"],
    .filter-container select {
      padding: 8px 12px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 14px;
      min-width: 150px;
    }

    .filter-container input[type="text"]:focus,
    .filter-container select:focus {
      border-color: #263576;
      outline: none;
      box-shadow: 0 0 5px rgba(38, 53, 118, 0.3);
    }

    .filter-container button {
      padding: 8px 16px;
      font-size: 14px;
      border-radius: 4px;
      border: none;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.3s ease;
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

    /* Pagination styles */
    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      margin: 20px 0;
      gap: 5px;
      flex-wrap: wrap;
    }
    
    .pagination a, .pagination span {
      padding: 8px 12px;
      text-decoration: none;
      border: 1px solid #263576;
      color: #263576;
      border-radius: 4px;
      transition: all 0.3s;
    }
    
    .pagination a:hover {
      background-color: #263576;
      color: white;
    }
    
    .pagination .active {
      background-color: #263576;
      color: white;
      font-weight: bold;
    }
    
    .pagination .disabled {
      color: #ccc;
      border-color: #ccc;
      cursor: not-allowed;
      pointer-events: none;
    }
    
    .pagination-info {
      text-align: center;
      margin: 10px 0;
      color: #666;
      font-size: 14px;
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

    .confirmation-staff-name {
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

  <div class="main-content">
    <!-- Filter section -->
    <div class="filter-container">
      <form method="GET" action="manage_staff.php">
        <select name="semester">
          <option value="">All Semesters</option>
          <?php foreach ($semesters as $sem): ?>
            <option value="<?php echo htmlspecialchars($sem); ?>" <?php echo $sem_filter === $sem ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($sem); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select name="course">
          <option value="">All Courses</option>
          <?php foreach ($courses as $course): ?>
            <option value="<?php echo htmlspecialchars($course); ?>" <?php echo $course_filter === $course ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($course); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select name="staff_type">
          <option value="">All Staff Types</option>
          <option value="teaching" <?php echo $staff_type_filter === 'teaching' ? 'selected' : ''; ?>>Teaching</option>
          <option value="non_teaching" <?php echo $staff_type_filter === 'non_teaching' ? 'selected' : ''; ?>>Non-Teaching</option>
        </select>

        <input type="text" name="search" placeholder="Search by name, ID, or email..." value="<?php echo htmlspecialchars($search_filter); ?>">

        <button type="submit" class="search-btn">
          <i class="fas fa-search"></i> Search
        </button>
        <button type="button" class="clear-filters-btn" onclick="window.location='manage_staff.php';">
          <i class="fas fa-times"></i> Clear
        </button>
      </form>
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>ID Number</th>
          <th>Profile</th>
          <th>Full Name</th>
          <th>Contact</th>
          <th>Address</th>
          <th>Course</th>
          <th>Semester</th>
          <th>Staff Type</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
          if ($result && $result->num_rows > 0) {
              $count = $total_records - $offset;
              while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $count-- . "</td>";
                echo "<td>" . htmlspecialchars($row['id_number']) . "</td>";

                echo "<td>";
                if (!empty($row['profile_photo'])) {
                    echo "<img src='images/uploads/profile_photos/" . $row['profile_photo'] . "' class='profile-pic' alt='Profile' />";
                } else {
                    echo "<img src='images/default_profile.png' class='profile-pic' alt='No Photo' />";
                }
                echo "</td>";

                echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
                echo "<td>" . htmlspecialchars($row['address']) . "</td>";

                echo "<td>" . (!empty($row['course_name']) ? nl2br(htmlspecialchars(str_replace(",", ", ", $row['course_name']))) : 'N/A') . "</td>";

                echo "<td>" . (!empty($row['sem_name']) ? htmlspecialchars($row['sem_name']) : 'N/A') . "</td>";

                echo "<td>" . (!empty($row['staff_type']) ? htmlspecialchars($row['staff_type']) : 'N/A') . "</td>";

                echo "<td>";
                echo "<a href='edit_staff.php?id=" . $row['user_id'] . "' class='btn btn-edit'>
                        <i class='fas fa-edit'></i> Edit
                      </a> ";
                echo "<a href='manage_staff.php?delete_id=" . $row['user_id'] . "' class='btn btn-delete'>
                        <i class='fas fa-trash-alt'></i> Delete
                      </a>";
                echo "</td>";

                echo "</tr>";
              }
          } else {
              echo "<tr><td colspan='10' style='color:red; font-weight:bold; text-align:center;'>No staff members found.</td></tr>";
          }
        ?>
      </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php
        if ($current_page > 1) {
          $params_str = '';
          if (!empty($sem_filter)) $params_str .= '&semester=' . urlencode($sem_filter);
          if (!empty($course_filter)) $params_str .= '&course=' . urlencode($course_filter);
          if (!empty($staff_type_filter)) $params_str .= '&staff_type=' . urlencode($staff_type_filter);
          if (!empty($search_filter)) $params_str .= '&search=' . urlencode($search_filter);
          echo '<a href="?page=' . ($current_page - 1) . $params_str . '">Previous</a>';
        } else {
          echo '<span class="disabled">Previous</span>';
        }
        
        $range = 2;
        $start_page = max(1, $current_page - $range);
        $end_page = min($total_pages, $current_page + $range);
        
        if ($start_page > 1) {
          $params_str = '';
          if (!empty($sem_filter)) $params_str .= '&semester=' . urlencode($sem_filter);
          if (!empty($course_filter)) $params_str .= '&course=' . urlencode($course_filter);
          if (!empty($staff_type_filter)) $params_str .= '&staff_type=' . urlencode($staff_type_filter);
          if (!empty($search_filter)) $params_str .= '&search=' . urlencode($search_filter);
          echo '<a href="?page=1' . $params_str . '">1</a>';
          if ($start_page > 2) {
            echo '<span>...</span>';
          }
        }
        
        for ($i = $start_page; $i <= $end_page; $i++) {
          $params_str = '';
          if (!empty($sem_filter)) $params_str .= '&semester=' . urlencode($sem_filter);
          if (!empty($course_filter)) $params_str .= '&course=' . urlencode($course_filter);
          if (!empty($staff_type_filter)) $params_str .= '&staff_type=' . urlencode($staff_type_filter);
          if (!empty($search_filter)) $params_str .= '&search=' . urlencode($search_filter);
          
          if ($i == $current_page) {
            echo '<span class="active">'.$i.'</span>';
          } else {
            echo '<a href="?page='.$i . $params_str .'">'.$i.'</a>';
          }
        }
        
        if ($end_page < $total_pages) {
          if ($end_page < $total_pages - 1) {
            echo '<span>...</span>';
          }
          $params_str = '';
          if (!empty($sem_filter)) $params_str .= '&semester=' . urlencode($sem_filter);
          if (!empty($course_filter)) $params_str .= '&course=' . urlencode($course_filter);
          if (!empty($staff_type_filter)) $params_str .= '&staff_type=' . urlencode($staff_type_filter);
          if (!empty($search_filter)) $params_str .= '&search=' . urlencode($search_filter);
          echo '<a href="?page='.$total_pages . $params_str .'">'.$total_pages.'</a>';
        }
        
        if ($current_page < $total_pages) {
          $params_str = '';
          if (!empty($sem_filter)) $params_str .= '&semester=' . urlencode($sem_filter);
          if (!empty($course_filter)) $params_str .= '&course=' . urlencode($course_filter);
          if (!empty($staff_type_filter)) $params_str .= '&staff_type=' . urlencode($staff_type_filter);
          if (!empty($search_filter)) $params_str .= '&search=' . urlencode($search_filter);
          echo '<a href="?page=' . ($current_page + 1) . $params_str . '">Next</a>';
        } else {
          echo '<span class="disabled">Next</span>';
        }
        ?>
      </div>
    <?php endif; ?>

    <?php if ($total_records > 0): ?>
      <div class="pagination-info">
        Showing <?= $offset + 1; ?> to <?= min($offset + $records_per_page, $total_records); ?> of <?= $total_records; ?> staff members
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
        <div class="notification-title">Delete Staff?</div>
        <div class="notification-message">Are you sure you want to delete this staff member?</div>
        <div class="confirmation-staff-name"><?php echo htmlspecialchars($staff_name_to_delete); ?></div>
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

    function confirmDelete(staffId) {
        window.location.href = 'manage_staff.php?delete_id=' + staffId + '&confirm=yes' +
            '<?= !empty($sem_filter) ? '&semester=' . urlencode($sem_filter) : '' ?>' +
            '<?= !empty($course_filter) ? '&course=' . urlencode($course_filter) : '' ?>' +
            '<?= !empty($staff_type_filter) ? '&staff_type=' . urlencode($staff_type_filter) : '' ?>' +
            '<?= !empty($search_filter) ? '&search=' . urlencode($search_filter) : '' ?>' +
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
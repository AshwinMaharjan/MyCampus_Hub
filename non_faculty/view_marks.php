<?php
session_start();
include("connect.php");
include("auth_check.php");
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$staff_id = $_SESSION['uid'];

// Check if a specific subject is selected
$selectedSubjectId = isset($_GET['sub_id']) ? intval($_GET['sub_id']) : 0;

// Get subject details if a subject is selected
$subjectInfo = null;
if ($selectedSubjectId !== 0) {
    $subjectQuery = "SELECT sub_id, sub_name, course_id, sem_id 
                 FROM subject 
                 WHERE sub_id = ?";
    $stmt = $conn->prepare($subjectQuery);
    $stmt->bind_param("i", $selectedSubjectId);
    $stmt->execute();
    $subjectResult = $stmt->get_result();
    $subjectInfo = $subjectResult->fetch_assoc();
    $stmt->close();
}

// Notification and Confirmation System
$notification = null;
$notification_type = "";
$show_confirmation = false;
$delete_id_to_confirm = null;
$student_name_to_delete = null;

$query = "SELECT coordinator_for FROM users WHERE user_id = ? AND is_coordinator = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$stmt->bind_result($coordinator_course);
$stmt->fetch();
$stmt->close();

if (!$coordinator_course) {
    die("You are not assigned to coordinate any course.");
}
// Handle delete action if confirmed
if (isset($_GET['delete_id']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $delete_id = intval($_GET['delete_id']);
    
    $checkStmt = $conn->prepare("SELECT entered_by_staff FROM marks WHERE marks_id = ?");
    $checkStmt->bind_param("i", $delete_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $markData = $checkResult->fetch_assoc();
        
        if ($markData['entered_by_staff'] == $staff_id) {
            $stmt = $conn->prepare("DELETE FROM marks WHERE marks_id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $notification = "Marks deleted successfully!";
                $notification_type = "success";
            } else {
                $notification = "Error deleting marks: " . $stmt->error;
                $notification_type = "error";
            }
            $stmt->close();
        } else {
            $notification = "You can only delete marks that you entered.";
            $notification_type = "error";
        }
    } else {
        $notification = "Marks record not found.";
        $notification_type = "error";
    }
    $checkStmt->close();
}
// Show confirmation modal for delete
elseif (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT u.full_name, m.entered_by_staff FROM marks m 
                           JOIN users u ON m.user_id = u.user_id 
                           WHERE m.marks_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result_check = $stmt->get_result();
    if ($result_check->num_rows > 0) {
        $row = $result_check->fetch_assoc();
        
        // Check if staff owns this mark
        if ($row['entered_by_staff'] == $staff_id) {
            $show_confirmation = true;
            $delete_id_to_confirm = $delete_id;
            $student_name_to_delete = $row['full_name'];
        } else {
            $notification = "You can only delete marks that you entered.";
            $notification_type = "error";
        }
    }
    $stmt->close();
}

$subject_filter = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$search_student = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_exam_type = isset($_GET['exam_type']) ? intval($_GET['exam_type']) : 0;

$subjectQuery = "SELECT sub_id, sub_name 
                 FROM subject 
                 WHERE course_id = ? 
                 ORDER BY sub_name";

$subjectStmt = $conn->prepare($subjectQuery);
$subjectStmt->bind_param("i", $coordinator_course);
$subjectStmt->execute();
$subjects = $subjectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$subjectStmt->close();


$semesterQuery = "SELECT sem_id, sem_name FROM semester ORDER BY sem_id";
$semesterResult = $conn->query($semesterQuery);
$semesters = [];
if ($semesterResult) {
    $semesters = $semesterResult->fetch_all(MYSQLI_ASSOC);
}

$courseQuery = "SELECT course_id, course_name FROM course ORDER BY course_name";
$courseResult = $conn->query($courseQuery);
$courses = [];
if ($courseResult) {
    $courses = $courseResult->fetch_all(MYSQLI_ASSOC);
}

$examTypesQuery = "SELECT exam_type_id, exam_name FROM exam_types WHERE is_active = 1 ORDER BY display_order";
$examTypesResult = $conn->query($examTypesQuery);
$examTypes = [];
if ($examTypesResult) {
    $examTypes = $examTypesResult->fetch_all(MYSQLI_ASSOC);
}

$selectedSubject = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$selectedSemester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$selectedCourse = isset($_GET['course']) ? intval($_GET['course']) : 0;

// Force subject filter when coming from My Subjects page
if ($selectedSubjectId > 0) {
    $selectedSubject = $selectedSubjectId;
}

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Calculate Statistics for current view
function calculateStats($conn, $whereClause, $paramTypes, $params) {
    $statsQuery = "SELECT 
        COUNT(m.marks_id) as total_students,
        ROUND(AVG(m.obtained_marks), 2) as avg_marks,
        MAX(m.obtained_marks) as highest_marks,
        MIN(m.obtained_marks) as lowest_marks,
        ROUND(AVG(m.percentage), 2) as avg_percentage,
        SUM(CASE WHEN m.percentage >= 40 THEN 1 ELSE 0 END) as pass_count,
        SUM(CASE WHEN m.percentage < 40 THEN 1 ELSE 0 END) as fail_count
    FROM marks m 
    LEFT JOIN users u ON m.user_id = u.user_id 
    LEFT JOIN subject s ON m.sub_id = s.sub_id 
    LEFT JOIN semester sem ON m.sem_id = sem.sem_id
    LEFT JOIN course c ON m.course_id = c.course_id
    LEFT JOIN exam_types et ON m.exam_type_id = et.exam_type_id
    WHERE $whereClause";
    
    $stmt = $conn->prepare($statsQuery);
    if (!empty($paramTypes)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    return $stats;
}

// Build conditions
$whereConditions = ["m.course_id = ?"];
$params = [$coordinator_course];
$paramTypes = "i";
// If viewing a specific subject, filter by it
if ($selectedSubjectId > 0 && $subjectInfo) {
    $whereConditions[] = "m.sub_id = ?";
    $params[] = $selectedSubjectId;
    $paramTypes .= "i";
} else {
    // Use regular filters only when not viewing specific subject
    if ($selectedSubject > 0) {
        $whereConditions[] = "m.sub_id = ?";
        $params[] = $selectedSubject;
        $paramTypes .= "i";
    }

    if ($selectedSemester > 0) {
        $whereConditions[] = "m.sem_id = ?";
        $params[] = $selectedSemester;
        $paramTypes .= "i";
    }

    if ($selectedCourse > 0) {
        $whereConditions[] = "m.course_id = ?";
        $params[] = $selectedCourse;
        $paramTypes .= "i";
    }

    if ($selected_exam_type > 0) {
        $whereConditions[] = "m.exam_type_id = ?";
        $params[] = $selected_exam_type;
        $paramTypes .= "i";
    }

    if (!empty($search_student)) {
        $whereConditions[] = "(u.full_name LIKE ? OR u.id_number LIKE ?)";
        $searchTerm = "%" . $search_student . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= "ss";
    }
}

$whereClause = implode(" AND ", $whereConditions);

// Get statistics with the same filters
$stats = calculateStats($conn, $whereClause, $paramTypes, $params);

// Calculate pass rate
$passRate = $stats['total_students'] > 0 
    ? round(($stats['pass_count'] / $stats['total_students']) * 100, 2)
    : 0;

// Get total records for pagination
$countQuery = "SELECT COUNT(*) as total FROM marks m 
    LEFT JOIN users u ON m.user_id = u.user_id 
    LEFT JOIN subject s ON m.sub_id = s.sub_id 
    LEFT JOIN semester sem ON m.sem_id = sem.sem_id
    LEFT JOIN course c ON m.course_id = c.course_id
    LEFT JOIN exam_types et ON m.exam_type_id = et.exam_type_id
    WHERE $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param($paramTypes, ...$params);
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$total_pages = ceil($totalRecords / $records_per_page);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>View Entered Marks</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/notify_staff.css" />
  <link rel="stylesheet" href="../css/view_marks.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>

<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
  <?php if ($selectedSubjectId !== 0 && $subjectInfo): ?>
    <!-- Back button when viewing specific subject -->
    <a href="view_subjects.php" class="back-btn">
      <i class="fas fa-arrow-left"></i>
      <span>Back to My Subjects</span>
    </a>

    <!-- Subject Information Banner -->
    <div class="subject-info-banner">
      <h2>
        <i class="fas fa-chart-line"></i>
        Marks for <?= htmlspecialchars($subjectInfo['sub_name']) ?>
      </h2>
    </div>
  <?php endif; ?>

  <!-- Statistics Section -->
  <?php if ($stats['total_students'] > 0): ?>
  <div class="stats-container">
    <div class="stat-card average">
      <div class="stat-label"><i class="fas fa-chart-bar"></i> Average Marks</div>
      <div class="stat-value"><?= $stats['avg_marks'] ?></div>
      <div class="stat-unit">out of 100</div>
    </div>

    <div class="stat-card highest">
      <div class="stat-label"><i class="fas fa-arrow-up"></i> Highest Marks</div>
      <div class="stat-value"><?= $stats['highest_marks'] ?></div>
      <div class="stat-unit">in class</div>
    </div>

    <div class="stat-card lowest">
      <div class="stat-label"><i class="fas fa-arrow-down"></i> Lowest Marks</div>
      <div class="stat-value"><?= $stats['lowest_marks'] ?></div>
      <div class="stat-unit">minimum in class</div>
    </div>

    <div class="stat-card pass-rate">
      <div class="stat-label"><i class="fas fa-check-circle"></i> Pass Rate</div>
      <div class="stat-value"><?= $passRate ?>%</div>
      <div class="stat-unit"><?= $stats['pass_count'] ?> passed, <?= $stats['fail_count'] ?> failed</div>
    </div>
  </div>
  <?php endif; ?>

<?php if (true): ?>
<div class="filter-container">
  <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; width: 100%;">
    
    <div class="filter-group">
      <label>Subject</label>
      <select name="subject" id="subject">
        <option value="0">All Subjects</option>
        <?php foreach ($subjects as $subject): ?>
          <option value="<?= $subject['sub_id'] ?>" <?= $selectedSubject == $subject['sub_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($subject['sub_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group">
      <label>Semester</label>
      <select name="semester" id="semester">
        <option value="0">All Semesters</option>
        <?php foreach ($semesters as $sem): ?>
          <option value="<?= $sem['sem_id'] ?>" <?= $selectedSemester == $sem['sem_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($sem['sem_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group">
      <label>Exam Type</label>
      <select name="exam_type" id="exam_type">
        <option value="0">All Exam Types</option>
        <?php foreach ($examTypes as $examType): ?>
          <option value="<?= $examType['exam_type_id'] ?>" <?= $selected_exam_type == $examType['exam_type_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($examType['exam_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="search-group">
      <div class="filter-group" style="flex: 1; min-width: 200px;">
        <label>Search Student</label>
        <input type="text" name="search" id="search" placeholder="Name or ID number..." value="<?= htmlspecialchars($search_student) ?>">
      </div>
      <button type="submit" class="search-btn">
        <i class="fas fa-search"></i> Search
      </button>
    </div>

    <button type="button" class="clear-filters-btn" onclick="window.location='view_marks.php';">
      <i class="fas fa-times"></i> Clear
    </button>

  </form>
</div>
<?php endif; ?>

  <!-- Pagination Info -->
  <?php if ($totalRecords > 0): ?>
  <div class="pagination-info">
    Showing <?= $offset + 1 ?> to <?= min($offset + $records_per_page, $totalRecords) ?> of <?= $totalRecords ?> entries
  </div>
  <?php endif; ?>

  <?php
$query = "
SELECT 
    m.marks_id,
    m.obtained_marks,
    m.percentage,
    m.grade,
    m.remarks,
    u.full_name,
    u.id_number,
    c.course_name,
    sem.sem_name,
    s.sub_name,
    et.exam_name
FROM marks m
JOIN users u ON m.user_id = u.user_id
JOIN course c ON m.course_id = c.course_id
JOIN semester sem ON m.sem_id = sem.sem_id
JOIN subject s ON m.sub_id = s.sub_id
JOIN exam_types et ON m.exam_type_id = et.exam_type_id
WHERE $whereClause
ORDER BY u.full_name ASC
LIMIT ? OFFSET ?
";

$params[] = $records_per_page;
$params[] = $offset;
$paramTypes .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
  
  <table>
    <thead>
      <tr>
        <th>ID Number</th>
        <th>Student Name</th>
        <th>Course</th>
        <th>Semester</th>
        <th>Subject</th>
        <th>Exam Type</th>
        <th>Obtained</th>
        <th>Percentage</th>
        <th>Grade</th>
        <th>Remarks</th>
      </tr>
    </thead>
    <tbody>
    <?php
      if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          echo "<tr>";
          echo "<td>" . htmlspecialchars($row['id_number'] ?? 'N/A') . "</td>";
          echo "<td>" . htmlspecialchars($row['full_name'] ?? 'N/A') . "</td>";
          echo "<td>" . htmlspecialchars($row['course_name'] ?? 'N/A') . "</td>";
          echo "<td>" . htmlspecialchars($row['sem_name'] ?? 'N/A') . "</td>";
          echo "<td>" . htmlspecialchars($row['sub_name'] ?? 'N/A') . "</td>";
          echo "<td>" . htmlspecialchars($row['exam_name'] ?? 'N/A') . "</td>";
          echo "<td>" . htmlspecialchars($row['obtained_marks'] ?? 'N/A') . "</td>";
          echo "<td>" . htmlspecialchars($row['percentage'] ?? 'N/A') . "%</td>";
          
          $grade = $row['grade'] ?? 'N/A';
          $gradeClass = 'grade-' . str_replace('+', '-plus', $grade);
          echo "<td><span class='grade-badge $gradeClass'>" . htmlspecialchars($grade) . "</span></td>";
          
          echo "<td style='text-align:left;'>" . htmlspecialchars($row['remarks'] ?? '') . "</td>";
          
          // Build query string for maintaining filters in action links
          $queryParams = [];
          if ($selectedSubjectId > 0) $queryParams['sub_id'] = $selectedSubjectId;
          if ($selectedSubject > 0) $queryParams['subject'] = $selectedSubject;
          if ($selectedSemester > 0) $queryParams['semester'] = $selectedSemester;
          if ($selectedCourse > 0) $queryParams['course'] = $selectedCourse;
          if ($selected_exam_type > 0) $queryParams['exam_type'] = $selected_exam_type;
          if (!empty($search_student)) $queryParams['search'] = $search_student;
          if ($current_page > 1) $queryParams['page'] = $current_page;
          
          $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
          echo "</tr>";
        }
      } else {
        $colspan = 11;
        $message = $selectedSubjectId > 0 && $subjectInfo 
            ? "No marks found for this subject. Enter marks for students first." 
            : "No marks found. Try adjusting your filters or enter some marks first.";
        echo "<tr><td colspan='$colspan' style='text-align:center; padding:20px; color:#666;'>$message</td></tr>";
      }
      
      $stmt->close();
    ?>
    </tbody>
  </table>

  <!-- Pagination Controls -->
  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php
    // Build query string for pagination links
    $query_params = [];
    if ($selectedSubjectId > 0) $query_params['sub_id'] = $selectedSubjectId;
    if ($selectedSubject > 0) $query_params['subject'] = $selectedSubject;
    if ($selectedSemester > 0) $query_params['semester'] = $selectedSemester;
    if ($selectedCourse > 0) $query_params['course'] = $selectedCourse;
    if ($selected_exam_type > 0) $query_params['exam_type'] = $selected_exam_type;
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
        <div class="notification-title">Delete Marks?</div>
        <div class="notification-message">Are you sure you want to delete marks for this student?</div>
        <div class="confirmation-student-name"><?php echo htmlspecialchars($student_name_to_delete); ?></div>
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
            window.location.href = 'view_marks.php<?php 
                $params = [];
                if ($selectedSubjectId > 0) $params[] = 'sub_id=' . $selectedSubjectId;
                if ($selectedSubject > 0) $params[] = 'subject=' . $selectedSubject;
                if ($selectedSemester > 0) $params[] = 'semester=' . $selectedSemester;
                if ($selectedCourse > 0) $params[] = 'course=' . $selectedCourse;
                if ($selected_exam_type > 0) $params[] = 'exam_type=' . $selected_exam_type;
                if (!empty($search_student)) $params[] = 'search=' . urlencode($search_student);
                if ($current_page > 1) $params[] = 'page=' . $current_page;
                echo !empty($params) ? '?' . implode('&', $params) : '';
            ?>';
        }, 300);
    }

    function confirmDelete(marksId) {
        let url = 'view_marks.php?delete_id=' + marksId + '&confirm=yes';
        <?php 
        $params = [];
        if ($selectedSubjectId > 0) $params[] = 'sub_id=' . $selectedSubjectId;
        if ($selectedSubject > 0) $params[] = 'subject=' . $selectedSubject;
        if ($selectedSemester > 0) $params[] = 'semester=' . $selectedSemester;
        if ($selectedCourse > 0) $params[] = 'course=' . $selectedCourse;
        if ($selected_exam_type > 0) $params[] = 'exam_type=' . $selected_exam_type;
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
        <button class="notification-button" onclick="closeNotification()">Okay</button>
    </div>
</div>

<script>
    function closeNotification() {
        const overlay = document.getElementById('notificationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            window.location.href = 'view_marks.php<?php 
                $params = [];
                if ($selectedSubjectId > 0) $params[] = 'sub_id=' . $selectedSubjectId;
                if ($selectedSubject > 0) $params[] = 'subject=' . $selectedSubject;
                if ($selectedSemester > 0) $params[] = 'semester=' . $selectedSemester;
                if ($selectedCourse > 0) $params[] = 'course=' . $selectedCourse;
                if ($selected_exam_type > 0) $params[] = 'exam_type=' . $selected_exam_type;
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
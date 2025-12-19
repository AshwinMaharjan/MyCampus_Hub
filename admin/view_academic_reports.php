<?php
include("connect.php");

// Initialize notification variables
$notification = null;
$notification_type = null;

// Handle delete operation
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $stmt = $conn->prepare("DELETE FROM marks WHERE marks_id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $notification = "Academic record deleted successfully.";
        $notification_type = "success";
    } else {
        $notification = "Error deleting record: " . $stmt->error;
        $notification_type = "error";
    }
    
    $stmt->close();
}

// Pagination variables
$records_per_page = 10;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $records_per_page;

// Filters
$sem_filter = isset($_GET['sem_filter']) ? intval($_GET['sem_filter']) : '';
$course_filter = isset($_GET['course_filter']) ? intval($_GET['course_filter']) : '';
$exam_type_filter = isset($_GET['exam_type_filter']) ? intval($_GET['exam_type_filter']) : '';
$staff_filter = isset($_GET['staff_filter']) ? intval($_GET['staff_filter']) : '';
$student_filter = isset($_GET['student_filter']) ? mysqli_real_escape_string($conn, trim($_GET['student_filter'])) : '';

// Build query with filters
$where_clauses = array();
if (!empty($sem_filter)) {
    $where_clauses[] = "m.sem_id = $sem_filter";
}
if (!empty($course_filter)) {
    $where_clauses[] = "m.course_id = $course_filter";
}
if (!empty($exam_type_filter)) {
    $where_clauses[] = "m.exam_type_id = $exam_type_filter";
}
if (!empty($staff_filter)) {
    $where_clauses[] = "m.entered_by_staff = $staff_filter";
}
if (!empty($student_filter)) {
    $where_clauses[] = "(u.full_name LIKE '%$student_filter%' OR u.id_number LIKE '%$student_filter%' OR u.email LIKE '%$student_filter%')";
}

$where_query = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM marks m
                JOIN users u ON m.user_id = u.user_id
                JOIN subject sub ON m.sub_id = sub.sub_id
                JOIN semester sem ON m.sem_id = sem.sem_id
                JOIN course c ON m.course_id = c.course_id
                JOIN exam_types et ON m.exam_type_id = et.exam_type_id
                $where_query";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get paginated records
$query = "SELECT m.*, u.full_name, u.id_number, u.email, sub.sub_name, sem.sem_name, 
          c.course_name, et.exam_name, sf.full_name as staff_name
          FROM marks m
          JOIN users u ON m.user_id = u.user_id
          JOIN subject sub ON m.sub_id = sub.sub_id
          JOIN semester sem ON m.sem_id = sem.sem_id
          JOIN course c ON m.course_id = c.course_id
          JOIN exam_types et ON m.exam_type_id = et.exam_type_id
          LEFT JOIN users sf ON m.entered_by_staff = sf.user_id
          $where_query
          ORDER BY m.marks_id DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $records_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Calculate stats based on filters
$stats_query = "SELECT 
                COUNT(DISTINCT m.user_id) as total_students,
                ROUND(AVG(m.percentage), 2) as avg_percentage,
                MAX(m.percentage) as highest_percentage,
                MIN(m.percentage) as lowest_percentage,
                SUM(CASE WHEN m.grade IN ('A', 'A+') THEN 1 ELSE 0 END) as grade_a_count,
                SUM(CASE WHEN m.grade IN ('B', 'B+') THEN 1 ELSE 0 END) as grade_b_count,
                SUM(CASE WHEN m.grade IN ('C', 'C+') THEN 1 ELSE 0 END) as grade_c_count,
                SUM(CASE WHEN m.grade IN ('D', 'D+') THEN 1 ELSE 0 END) as grade_d_count,
                SUM(CASE WHEN m.grade = 'F' THEN 1 ELSE 0 END) as grade_f_count,
                SUM(CASE WHEN m.percentage >= 75 THEN 1 ELSE 0 END) as passed_count,
                SUM(CASE WHEN m.percentage BETWEEN 51 AND 74 THEN 1 ELSE 0 END) as average_count,
                SUM(CASE WHEN m.percentage <= 50 THEN 1 ELSE 0 END) as failed_count
                FROM marks m
                JOIN users u ON m.user_id = u.user_id
                JOIN subject sub ON m.sub_id = sub.sub_id
                JOIN semester sem ON m.sem_id = sem.sem_id
                JOIN course c ON m.course_id = c.course_id
                JOIN exam_types et ON m.exam_type_id = et.exam_type_id
                $where_query";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get top performing subject
$top_subject_query = "SELECT sub.sub_name, ROUND(AVG(m.percentage), 2) as avg_percentage
                      FROM marks m
                      JOIN subject sub ON m.sub_id = sub.sub_id
                      JOIN users u ON m.user_id = u.user_id
                      JOIN semester sem ON m.sem_id = sem.sem_id
                      JOIN course c ON m.course_id = c.course_id
                      JOIN exam_types et ON m.exam_type_id = et.exam_type_id
                      $where_query
                      GROUP BY m.sub_id
                      ORDER BY avg_percentage DESC
                      LIMIT 1";

$top_subject_result = mysqli_query($conn, $top_subject_query);
$top_subject = mysqli_fetch_assoc($top_subject_result);

// Get lowest performing subject
$lowest_subject_query = "SELECT sub.sub_name, ROUND(AVG(m.percentage), 2) as avg_percentage
                         FROM marks m
                         JOIN subject sub ON m.sub_id = sub.sub_id
                         JOIN users u ON m.user_id = u.user_id
                         JOIN semester sem ON m.sem_id = sem.sem_id
                         JOIN course c ON m.course_id = c.course_id
                         JOIN exam_types et ON m.exam_type_id = et.exam_type_id
                         $where_query
                         GROUP BY m.sub_id
                         ORDER BY avg_percentage ASC
                         LIMIT 1";

$lowest_subject_result = mysqli_query($conn, $lowest_subject_query);
$lowest_subject = mysqli_fetch_assoc($lowest_subject_result);

// Fetch filter options
$sem_query = "SELECT sem_id, sem_name FROM semester ORDER BY sem_name";
$sem_result = mysqli_query($conn, $sem_query);

$course_query = "SELECT course_id, course_name FROM course ORDER BY course_name";
$course_result = mysqli_query($conn, $course_query);

$exam_type_query = "SELECT exam_type_id, exam_name FROM exam_types ORDER BY exam_name";
$exam_type_result = mysqli_query($conn, $exam_type_query);

// Fetch staff options
$staff_query = "SELECT user_id, full_name FROM users WHERE role_id = 3 ORDER BY full_name";
$staff_result = mysqli_query($conn, $staff_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Academic Reports</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/manage_staff.css" />
  <link rel="stylesheet" href="../css/view_academic_reports.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

  <div class="main-content">
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="sem_filter">Semester</label>
                    <select name="sem_filter" id="sem_filter">
                        <option value="">All Semesters</option>
                        <?php 
                        $sem_result = mysqli_query($conn, $sem_query);
                        while($row = $sem_result->fetch_assoc()): ?>
                            <option value="<?php echo $row['sem_id']; ?>" <?php echo ($sem_filter == $row['sem_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['sem_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="course_filter">Course</label>
                    <select name="course_filter" id="course_filter">
                        <option value="">All Courses</option>
                        <?php 
                        $course_result = mysqli_query($conn, $course_query);
                        while($row = $course_result->fetch_assoc()): ?>
                            <option value="<?php echo $row['course_id']; ?>" <?php echo ($course_filter == $row['course_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['course_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="exam_type_filter">Exam Type</label>
                    <select name="exam_type_filter" id="exam_type_filter">
                        <option value="">All Exam Types</option>
                        <?php 
                        $exam_type_result = mysqli_query($conn, $exam_type_query);
                        while($row = $exam_type_result->fetch_assoc()): ?>
                            <option value="<?php echo $row['exam_type_id']; ?>" <?php echo ($exam_type_filter == $row['exam_type_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['exam_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="staff_filter">Staff</label>
                    <select name="staff_filter" id="staff_filter">
                        <option value="">All Staff</option>
                        <?php 
                        $staff_result = mysqli_query($conn, $staff_query);
                        while($row = $staff_result->fetch_assoc()): ?>
                            <option value="<?php echo $row['user_id']; ?>" <?php echo ($staff_filter == $row['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($row['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="student_filter">Search Student (Name/ID/Email)</label>
                    <input type="text" name="student_filter" id="student_filter" placeholder="Enter student name, ID or email" value="<?php echo htmlspecialchars($student_filter); ?>">
                </div>
            </div>

            <div class="filter-buttons">
                <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                <button type="button" class="btn-reset" onclick="window.location='?'"><i class="fas fa-redo"></i> Clear Filter</button>
            </div>
        </form>
    </div>

    <!-- Stats Section -->
    <?php if ($total_records > 0): ?>
    <div class="stats-section">
                <div class="stat-card average-percentage">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-label">Overall Average Percentage</div>
            <div class="stat-value">
        <?php echo isset($stats['avg_percentage']) ? number_format($stats['avg_percentage'], 2) . '%' : 'N/A'; ?>
        </div>
        </div>

        <div class="stat-card highest">
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-label">Highest Percentage</div>
            <div class="stat-value"><?php echo $stats['highest_percentage']; ?>%</div>
        </div>

        <div class="stat-card lowest">
            <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
            <div class="stat-label">Lowest Percentage</div>
            <div class="stat-value"><?php echo $stats['lowest_percentage']; ?>%</div>
        </div>

        <div class="stat-card passed">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Passed (≥75%)</div>
            <div class="stat-value"><?php echo $stats['passed_count']; ?></div>
        </div>

        <div class="stat-card average">
            <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-label">Average (51-74%)</div>
            <div class="stat-value"><?php echo $stats['average_count']; ?></div>
        </div>

        <div class="stat-card failed">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-label">Failed (≤50%)</div>
            <div class="stat-value"><?php echo $stats['failed_count']; ?></div>
        </div>
        <div class="stat-card top-subject">
            <div class="stat-icon"><i class="fas fa-star"></i></div>
            <div class="stat-label">Top Performing Subject</div>
            <div class="stat-value"><?php echo ($top_subject) ? htmlspecialchars($top_subject['sub_name']) : 'N/A'; ?></div>
            <?php if ($top_subject): ?>
                <div class="stat-subvalue"><?php echo $top_subject['avg_percentage']; ?>% avg</div>
            <?php endif; ?>
        </div>

        <div class="stat-card lowest-subject">
            <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
            <div class="stat-label">Lowest Performing Subject</div>
            <div class="stat-value"><?php echo ($lowest_subject) ? htmlspecialchars($lowest_subject['sub_name']) : 'N/A'; ?></div>
            <?php if ($lowest_subject): ?>
                <div class="stat-subvalue"><?php echo $lowest_subject['avg_percentage']; ?>% avg</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Grade Distribution -->
    <div class="grade-distribution">
        <div class="grade-item grade-a">
            <span class="grade-count"><?php echo $stats['grade_a_count']; ?></span>
            <span class="grade-name">Grade A</span>
        </div>
        <div class="grade-item grade-b">
            <span class="grade-count"><?php echo $stats['grade_b_count']; ?></span>
            <span class="grade-name">Grade B</span>
        </div>
        <div class="grade-item grade-c">
            <span class="grade-count"><?php echo $stats['grade_c_count']; ?></span>
            <span class="grade-name">Grade C</span>
        </div>
        <div class="grade-item grade-d">
            <span class="grade-count"><?php echo $stats['grade_d_count']; ?></span>
            <span class="grade-name">Grade D</span>
        </div>
        <div class="grade-item grade-f">
            <span class="grade-count"><?php echo $stats['grade_f_count']; ?></span>
            <span class="grade-name">Grade F</span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($total_records > 0): ?>
      <div class="pagination-info">
        <i class="fas fa-info-circle"></i> Showing <?= $offset + 1; ?> to <?= min($offset + $records_per_page, $total_records); ?> of <?= $total_records; ?> academic records
      </div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Student Name</th>
          <th>ID Number</th>
          <th>Subject</th>
          <th>Semester</th>
          <th>Course</th>
          <th>Exam Type</th>
          <th>Obtained Marks</th>
          <th>Percentage</th>
          <th>Grade</th>
          <th>Remarks</th>
          <th>Entered By</th>
        </tr>
      </thead>
      <tbody>
        <?php
          if ($result && $result->num_rows > 0) {
              $count = $offset + 1;
              while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $count++ . "</td>";
                echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['id_number']) . "</td>";
                echo "<td>" . htmlspecialchars($row['sub_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['sem_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['exam_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['obtained_marks']) . " / " . htmlspecialchars($row['full_marks']) . "</td>";
                
                // Percentage with badge
                $percentage = $row['percentage'];
                $percentage_class = 'percentage-low';
                if ($percentage >= 75) $percentage_class = 'percentage-high';
                elseif ($percentage >= 50) $percentage_class = 'percentage-medium';
                echo "<td><span class='percentage-badge $percentage_class'>" . number_format($percentage, 2) . "%</span></td>";
                
                // Grade with badge - normalize A+ to A, B+ to B, etc.
                $grade = $row['grade'];
                $normalized_grade = rtrim($grade, '+');
                $grade_class = "grade-" . $normalized_grade;
                echo "<td><span class='grade-badge $grade_class'>" . htmlspecialchars($grade) . "</span></td>";
                
                echo "<td>" . (!empty($row['remarks']) ? htmlspecialchars($row['remarks']) : 'N/A') . "</td>";
                echo "<td>" . (!empty($row['staff_name']) ? htmlspecialchars($row['staff_name']) : 'N/A') . "</td>";
                
                echo "</tr>";
              }
          } else {
              echo "<tr><td colspan='12' style='color:red; font-weight:bold; text-align:center;'>No academic records found.</td></tr>";
          }
        ?>
      </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php
        // Build query string for pagination links
        $query_params = [];
        if (!empty($sem_filter)) $query_params['sem_filter'] = $sem_filter;
        if (!empty($course_filter)) $query_params['course_filter'] = $course_filter;
        if (!empty($exam_type_filter)) $query_params['exam_type_filter'] = $exam_type_filter;
        if (!empty($staff_filter)) $query_params['staff_filter'] = $staff_filter;
        if (!empty($student_filter)) $query_params['student_filter'] = $student_filter;
        
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

<!-- Notification Modal -->
<?php if ($notification): ?>
<div class="notification-overlay active" id="notificationOverlay">
    <div class="notification-modal <?php echo $notification_type; ?>">
        <div class="notification-icon">
            <?php
            if ($notification_type === 'success') {
                echo '<i class="fas fa-check-circle"></i>';
            } else {
                echo '<i class="fas fa-times-circle"></i>';
            }
            ?>
        </div>
        <div class="notification-title">
            <?php echo ($notification_type === 'success') ? 'Success!' : 'Error'; ?>
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
            window.location.href = 'academic_reports.php<?php 
                $params = [];
                if (!empty($sem_filter)) $params[] = 'sem_filter=' . $sem_filter;
                if (!empty($course_filter)) $params[] = 'course_filter=' . $course_filter;
                if (!empty($exam_type_filter)) $params[] = 'exam_type_filter=' . $exam_type_filter;
                if (!empty($staff_filter)) $params[] = 'staff_filter=' . $staff_filter;
                if (!empty($student_filter)) $params[] = 'student_filter=' . urlencode($student_filter);
                if ($current_page > 1) $params[] = 'page=' . $current_page;
                echo !empty($params) ? '?' . implode('&', $params) : '';
            ?>';
        }, 300);
    }

    // Auto-close after 3 seconds
    setTimeout(() => {
        closeNotification();
    }, 3000);
</script>
<?php endif; ?>

</body>
</html>
<?php
session_start();
include("auth_check.php");
include("connect.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$staff_id = $_SESSION['uid'];

// Get filter values
$filter_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_semester = isset($_GET['sem_id']) ? intval($_GET['sem_id']) : 0;
$filter_subject = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get subjects taught by this staff
$subjectQuery = "SELECT sub_id, sub_name, course_id, sem_id FROM subject WHERE role_id = ? ORDER BY sub_name";
$subjectStmt = $conn->prepare($subjectQuery);
$subjectStmt->bind_param("i", $staff_id);
$subjectStmt->execute();
$subjects = $subjectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$subjectStmt->close();

// Get all courses
$courseQuery = "SELECT course_id, course_name FROM course ORDER BY course_name";
$courseResult = $conn->query($courseQuery);
$courses = [];
if ($courseResult) {
    $courses = $courseResult->fetch_all(MYSQLI_ASSOC);
}

// Get all semesters
$semesterQuery = "SELECT sem_id, sem_name FROM semester ORDER BY sem_id";
$semesterResult = $conn->query($semesterQuery);
$semesters = [];
if ($semesterResult) {
    $semesters = $semesterResult->fetch_all(MYSQLI_ASSOC);
}

// Build report query
$reportQuery = "
    SELECT 
        u.user_id,
        u.id_number,
        u.full_name,
        c.course_name,
        s.sem_name,
        sub.sub_name,
        COUNT(a.attendance_id) AS total_classes,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
    FROM attendance a
    INNER JOIN users u ON a.user_id = u.user_id
    INNER JOIN course c ON a.course_id = c.course_id
    INNER JOIN semester s ON a.sem_id = s.sem_id
    INNER JOIN subject sub ON a.sub_id = sub.sub_id
    WHERE sub.role_id = ?
";

$params = [$staff_id];
$types = "i";

if ($filter_course > 0) {
    $reportQuery .= " AND a.course_id = ?";
    $params[] = $filter_course;
    $types .= "i";
}

if ($filter_semester > 0) {
    $reportQuery .= " AND a.sem_id = ?";
    $params[] = $filter_semester;
    $types .= "i";
}

if ($filter_subject > 0) {
    $reportQuery .= " AND a.sub_id = ?";
    $params[] = $filter_subject;
    $types .= "i";
}

if (!empty($filter_date_from)) {
    $reportQuery .= " AND a.attendance_date >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if (!empty($filter_date_to)) {
    $reportQuery .= " AND a.attendance_date <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

$reportQuery .= " GROUP BY u.user_id, a.sub_id ORDER BY sub.sub_name, u.full_name ASC";

$reportStmt = $conn->prepare($reportQuery);
if ($reportStmt) {
    $reportStmt->bind_param($types, ...$params);
    $reportStmt->execute();
    $reportResult = $reportStmt->get_result();
    $reportData = $reportResult->fetch_all(MYSQLI_ASSOC);
    $reportStmt->close();
} else {
    $reportData = [];
}

// Calculate overall statistics
$total_students = 0;
$total_classes_sum = 0;
$total_present = 0;
$total_absent = 0;
$total_late = 0;
$student_percentages = [];

foreach ($reportData as $row) {
    $total_students++;
    $total_classes_sum += $row['total_classes'];
    $total_present += $row['present_count'];
    $total_absent += $row['absent_count'];
    $total_late += $row['late_count'];
    
    $percentage = $row['total_classes'] > 0 
        ? round((($row['present_count'] + $row['late_count']) / $row['total_classes']) * 100, 2)
        : 0;
    
    $student_percentages[] = [
        'name' => $row['full_name'],
        'percentage' => $percentage
    ];
}

$overall_percentage = $total_classes_sum > 0 
    ? round((($total_present + $total_late) / $total_classes_sum) * 100, 2)
    : 0;

$average_attendance = $total_students > 0 
    ? round(array_sum(array_column($student_percentages, 'percentage')) / $total_students, 2)
    : 0;

// Get top 3 best attendance
usort($student_percentages, function($a, $b) {
    return $b['percentage'] <=> $a['percentage'];
});
$best_students = array_slice($student_percentages, 0, 3);

// Get top 3 worst attendance
$worst_students = array_slice(array_reverse($student_percentages), 0, 3);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance Report</title>
<link rel="stylesheet" href="../css/all.min.css" />
<link rel="stylesheet" href="../css/admin_menu.css" />
<link rel="stylesheet" href="../css/attendance_report.css" />
<link rel="stylesheet" href="../css/view_attendance.css" />
<link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-chart-bar"></i>
            Attendance Report Summary
        </h1>
    </div>

<!-- Filter Container -->
<div class="filter-container">
    <form method="GET" class="filter-form">
        <!-- Course Filter -->
        <div class="filter-group">
            <label>Course</label>
            <select name="course_id" id="course">
                <option value="0">All Courses</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= $course['course_id'] ?>" <?= $filter_course == $course['course_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($course['course_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Semester Filter -->
        <div class="filter-group">
            <label>Semester</label>
            <select name="sem_id" id="semester">
                <option value="0">All Semesters</option>
                <?php foreach ($semesters as $semester): ?>
                    <option value="<?= $semester['sem_id'] ?>" <?= $filter_semester == $semester['sem_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($semester['sem_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Subject Filter -->
        <div class="filter-group">
            <label>Subject</label>
            <select name="subject_id" id="subject">
                <option value="0">All Subjects</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= $subject['sub_id'] ?>" <?= $filter_subject == $subject['sub_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($subject['sub_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Date Filters -->
        <div class="filter-group">
            <label>Date From</label>
            <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
        </div>

        <div class="filter-group">
            <label>Date To</label>
            <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
        </div>

        <!-- Filter Buttons -->
        <div class="filter-buttons">
            <button type="submit" class="btn-primary">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <button type="button" class="btn-danger" onclick="window.location='attendance_report.php';">
                <i class="fas fa-times"></i> Clear
            </button>

            <!-- Export Button -->
<button type="button" 
        id="exportBtn"
        class="btn-export">
    <i class="fas fa-file-csv"></i> Export CSV
</button>
        </div>
    </form>
</div>

    <?php if (!empty($reportData)): ?>
    <!-- Statistics Section -->
    <div class="stats-section">
        <div class="stats-grid">
            <!-- Total Students -->
            <div class="stat-card total">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Students</div>
                    <div class="stat-value"><?= $total_students ?></div>
                </div>
            </div>

            <!-- Total Present -->
            <div class="stat-card present">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Present</div>
                    <div class="stat-value"><?= $total_present ?></div>
                </div>
            </div>

            <!-- Total Absent -->
            <div class="stat-card absent">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Absent</div>
                    <div class="stat-value"><?= $total_absent ?></div>
                </div>
            </div>

            <!-- Total Late -->
            <div class="stat-card late">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Late</div>
                    <div class="stat-value"><?= $total_late ?></div>
                </div>
            </div>

            <!-- Average Attendance -->
            <div class="stat-card average">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Average Attendance</div>
                    <div class="stat-value"><?= $average_attendance ?>%</div>
                </div>
            </div>
        </div> <!-- stats-grid -->
    </div> <!-- stats-section -->

    <!-- Top Performers -->
    <div class="top-performers">
        <div class="performer-card best">
            <div class="performer-header">
                <i class="fas fa-trophy"></i>
                <div class="performer-title">Best Attendance</div>
            </div>
            <div class="performer-list">
                <?php if (!empty($best_students)): ?>
                    <?php foreach ($best_students as $student): ?>
                    <div class="performer-item">
                        <div class="performer-name"><?= htmlspecialchars($student['name']) ?></div>
                        <div class="performer-percentage"><?= $student['percentage'] ?>%</div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">No data available</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="performer-card needs-attention">
            <div class="performer-header">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="performer-title">Needs Attention</div>
            </div>
            <div class="performer-list">
                <?php if (!empty($worst_students)): ?>
                    <?php foreach ($worst_students as $student): ?>
                    <div class="performer-item">
                        <div class="performer-name"><?= htmlspecialchars($student['name']) ?></div>
                        <div class="performer-percentage"><?= $student['percentage'] ?>%</div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">No data available</div>
                <?php endif; ?>
            </div>
        </div>
    </div> <!-- top-performers -->

    <!-- Table Container -->
    <div class="table-container">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-table"></i>
                Detailed Report
            </div>
            <div class="search-box">
                <input type="text" class="search-input" id="searchInput" placeholder="Search by student name or ID...">
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Semester</th>
                        <th>Subject</th>
                        <th>Total Classes</th>
                        <th>Present</th>
                        <th>Late</th>
                        <th>Absent</th>
                        <th>Attendance %</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <?php foreach ($reportData as $i => $row): 
                        $percentage = $row['total_classes'] > 0 
                            ? round((($row['present_count'] + $row['late_count']) / $row['total_classes']) * 100, 1)
                            : 0;
                        $badge_class = $percentage >= 90 ? 'excellent' : ($percentage >= 75 ? 'good' : ($percentage >= 60 ? 'average' : 'poor'));
                    ?>
                    <tr class="report-row" 
                        data-student-name="<?= strtolower($row['full_name']) ?>" 
                        data-student-id="<?= strtolower($row['id_number']) ?>">
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($row['id_number']) ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['course_name']) ?></td>
                        <td><?= htmlspecialchars($row['sem_name']) ?></td>
                        <td><?= htmlspecialchars($row['sub_name']) ?></td>
                        <td><?= $row['total_classes'] ?></td>
                        <td class="present"><?= $row['present_count'] ?></td>
                        <td class="late"><?= $row['late_count'] ?></td>
                        <td class="absent"><?= $row['absent_count'] ?></td>
                        <td class="percentage">
                            <span class="percentage-badge <?= $badge_class ?>"><?= $percentage ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div> <!-- table-container -->

    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No Attendance Records Found</h3>
        <p>There are no attendance records matching your filter criteria. Try adjusting your filters or ensure attendance has been recorded.</p>
    </div>
    <?php endif; ?>
</div> <!-- main-content -->
</div> <!-- main-content -->

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");

    // Search functionality
    if (searchInput) {
        searchInput.addEventListener("input", function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.report-row');

            rows.forEach(row => {
                const studentName = row.dataset.studentName;
                const studentId = row.dataset.studentId;

                if (studentName.includes(searchTerm) || studentId.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<!-- Export Confirmation Modal -->
<div class="delete-overlay" id="exportOverlay">
    <div class="delete-modal">
        <div class="delete-icon" style="color: #4CAF50;">
            <i class="fas fa-file-download"></i>
        </div>
        <div class="delete-title">Export Attendance Report</div>
        <div class="delete-message">
            Are you sure you want to export the filtered attendance records to CSV?
        </div>
        <div class="delete-buttons">
            <button class="delete-yes-btn" id="exportYesBtn" style="background-color: #4CAF50;">
                <i class="fas fa-file-csv"></i> Yes, Export
            </button>
            <button class="delete-no-btn" id="exportNoBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");
    const exportBtn = document.getElementById("exportBtn");
    const exportOverlay = document.getElementById("exportOverlay");
    const exportYesBtn = document.getElementById("exportYesBtn");
    const exportNoBtn = document.getElementById("exportNoBtn");

    // Search functionality
    if (searchInput) {
        searchInput.addEventListener("input", function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.report-row');

            rows.forEach(row => {
                const studentName = row.dataset.studentName;
                const studentId = row.dataset.studentId;

                if (studentName.includes(searchTerm) || studentId.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Export functionality
    if (exportBtn) {
        // Show export confirmation modal
        exportBtn.addEventListener("click", (e) => {
            e.preventDefault();
            exportOverlay.classList.add("active");
        });

        // Confirm export
        exportYesBtn.addEventListener("click", () => {
            exportOverlay.classList.remove("active");
            
            // Build export URL with current filters
            const urlParams = new URLSearchParams({
                course_id: '<?= $filter_course ?>',
                sem_id: '<?= $filter_semester ?>',
                subject_id: '<?= $filter_subject ?>',
                date_from: '<?= htmlspecialchars($filter_date_from) ?>',
                date_to: '<?= htmlspecialchars($filter_date_to) ?>'
            });
            
            // Navigate to export page
            window.location.href = 'export_attendance.php?' + urlParams.toString();
        });

        // Cancel export
        exportNoBtn.addEventListener("click", () => {
            exportOverlay.classList.remove("active");
        });

        // Close modal if clicking outside
        exportOverlay.addEventListener("click", (e) => {
            if (e.target === exportOverlay) {
                exportOverlay.classList.remove("active");
            }
        });
    }
});
</script>
</body>
</html>
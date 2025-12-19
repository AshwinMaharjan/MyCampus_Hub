<?php
session_start();
include("auth_check.php");
include("connect.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['uid'];

// Get filter values
$filter_subject = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$filter_date = isset($_GET['attendance_date']) ? $_GET['attendance_date'] : '';
$filter_month = isset($_GET['attendance_month']) ? $_GET['attendance_month'] : '';

// Get student's course and semester
$studentQuery = "SELECT course_id, sem_id, full_name, id_number FROM users WHERE user_id = ?";
$studentStmt = $conn->prepare($studentQuery);
$studentStmt->bind_param("i", $student_id);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();
$studentData = $studentResult->fetch_assoc();
$studentStmt->close();

$student_course = $studentData['course_id'];
$student_semester = $studentData['sem_id'];
$student_name = $studentData['full_name'];
$student_id_number = $studentData['id_number'];

// Get subjects for the student's course and semester
$subjectQuery = "SELECT sub_id, sub_name FROM subject WHERE course_id = ? AND sem_id = ? ORDER BY sub_name";
$subjectStmt = $conn->prepare($subjectQuery);
$subjectStmt->bind_param("ii", $student_course, $student_semester);
$subjectStmt->execute();
$subjects = $subjectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$subjectStmt->close();

// Build attendance query
$attendanceQuery = "
    SELECT 
        a.attendance_id,
        a.attendance_date,
        a.status,
        a.remarks,
        a.created_at,
        s.sub_name,
        CONCAT(u.full_name) as marked_by
    FROM attendance a
    JOIN subject s ON a.sub_id = s.sub_id
    LEFT JOIN users u ON a.attendance_done_by = u.user_id
    WHERE a.user_id = ?
";

$params = [$student_id];
$types = "i";

if ($filter_subject > 0) {
    $attendanceQuery .= " AND a.sub_id = ?";
    $params[] = $filter_subject;
    $types .= "i";
}

if (!empty($filter_date)) {
    $attendanceQuery .= " AND a.attendance_date = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if (!empty($filter_month)) {
    $attendanceQuery .= " AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?";
    $params[] = $filter_month;
    $types .= "s";
}

$attendanceQuery .= " ORDER BY a.attendance_date DESC, a.created_at DESC";

// Get all attendance records for statistics (before pagination)
$statsStmt = $conn->prepare($attendanceQuery);
$statsStmt->bind_param($types, ...$params);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$allRecords = $statsResult->fetch_all(MYSQLI_ASSOC);
$statsStmt->close();

// Calculate statistics
$total_records = count($allRecords);
$present_count = 0;
$absent_count = 0;
$late_count = 0;

foreach ($allRecords as $record) {
    switch ($record['status']) {
        case 'Present':
            $present_count++;
            break;
        case 'Absent':
            $absent_count++;
            break;
        case 'Late':
            $late_count++;
            break;
    }
}

$attendance_percentage = $total_records > 0 
    ? round((($present_count + $late_count) / $total_records) * 100, 2) 
    : 0;

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Add pagination to query
$attendanceQueryWithLimit = $attendanceQuery . " LIMIT $limit OFFSET $offset";

$attendanceStmt = $conn->prepare($attendanceQueryWithLimit);
$attendanceStmt->bind_param($types, ...$params);
$attendanceStmt->execute();
$attendanceResult = $attendanceStmt->get_result();
$attendanceRecords = $attendanceResult->fetch_all(MYSQLI_ASSOC);
$attendanceStmt->close();

$totalPages = ceil($total_records / $limit);
$startRecord = ($page - 1) * $limit + 1;
$endRecord = min($startRecord + count($attendanceRecords) - 1, $total_records);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>My Attendance</title>
<link rel="stylesheet" href="../css/all.min.css" />
<link rel="stylesheet" href="../css/student_menu.css" />
<link rel="stylesheet" href="../css/student_view_attendance.css" />
<link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>
<body>

<?php include("header.php"); ?>
<div class="page-wrapper"> 
<?php include("menu.php"); ?>

<!-- Page Header -->
<div class="main-content">
<div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clipboard-list"></i>
                My Attendance Records
            </h1>
            <p class="student-info">
                <strong><?= htmlspecialchars($student_name) ?></strong> 
                (<?= htmlspecialchars($student_id_number) ?>)
            </p>
        </div>
    </div>

    <!-- Filter Container -->
    <div class="filter-container">
        <form method="GET" id="filterForm" class="filter-form">
            <div class="filter-group">
                <label>Subject</label>
                <select name="subject_id" id="subject">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= $subject['sub_id'] ?>" <?= $filter_subject == $subject['sub_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subject['sub_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Date</label>
                <input 
    type="month" 
    name="attendance_month" 
    value="<?= htmlspecialchars($filter_month) ?>"
    max="<?= date('Y-m') ?>"
>
            </div>

            <button type="submit" class="btn-success">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <button type="button" class="btn-danger" id="clearFiltersBtn">
                <i class="fas fa-times"></i> Clear Filters
            </button>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Classes</div>
                <div class="stat-value"><?= $total_records ?></div>
            </div>
        </div>

        <div class="stat-card present">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Present</div>
                <div class="stat-value"><?= $present_count ?></div>
            </div>
        </div>

        <div class="stat-card absent">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Absent</div>
                <div class="stat-value"><?= $absent_count ?></div>
            </div>
        </div>

        <div class="stat-card late">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Late</div>
                <div class="stat-value"><?= $late_count ?></div>
            </div>
        </div>

        <div class="stat-card percentage">
            <div class="stat-icon">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Attendance Rate</div>
                <div class="stat-value"><?= $attendance_percentage ?>%</div>
            </div>
        </div>
    </div>

    <!-- Table Container -->
    <div class="table-container">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-table"></i>
                Attendance Details
            </div>
            <div class="search-box">
                <input type="text" class="search-input" id="searchInput" placeholder="Search by subject">
            </div>
        </div>

        <?php if (count($attendanceRecords) > 0): ?>
        <div style="overflow-x: auto;">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Marked By</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody">
                    <?php foreach ($attendanceRecords as $record): ?>
                    <tr class="attendance-row" 
                        data-subject="<?= strtolower($record['sub_name']) ?>" 
                        data-date="<?= $record['attendance_date'] ?>">
                        <td><?= date('d M Y', strtotime($record['attendance_date'])) ?></td>
                        <td><?= htmlspecialchars($record['sub_name']) ?></td>
                        <td>
                            <span class="status-badge <?= strtolower($record['status']) ?>">
                                <?= htmlspecialchars($record['status']) ?>
                            </span>
                        </td>
                        <td><?= !empty($record['remarks']) ? htmlspecialchars($record['remarks']) : '-' ?></td>
                        <td><?= htmlspecialchars($record['marked_by']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_records > 0): ?>
            <div class="pagination-info">
                Showing <?= $startRecord ?>–<?= $endRecord ?> of <?= $total_records ?> records
            </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; Prev</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?= $i == $page 
                        ? "<span class='active'>$i</span>" 
                        : "<a href='?".http_build_query(array_merge($_GET, ['page'=>$i]))."'>$i</a>" ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &raquo;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No Attendance Records Found</h3>
            <p>There are no attendance records matching your criteria.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");
    const clearFiltersBtn = document.getElementById("clearFiltersBtn");

    // Search functionality
    if (searchInput) {
        searchInput.addEventListener("input", function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.attendance-row');

            rows.forEach(row => {
                const subject = row.dataset.subject;
                const date = row.dataset.date;

                if (subject.includes(searchTerm) || date.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Clear filters
    clearFiltersBtn.addEventListener("click", () => {
        window.location.href = 'student_view_attendance.php';
    });
});
</script>

</body>
</html>
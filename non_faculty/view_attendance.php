<?php
session_start();
include("auth_check.php");
include("connect.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$staff_id = $_SESSION['uid'];
$message = '';
$notification_type = null;
$redirect_url = null;
$redirect_delay = 2000;

// Get user's course information (non-faculty assigned to a course)
$userQuery = "SELECT coordinator_for, course_name FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $staff_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userData = $userResult->fetch_assoc();
$userStmt->close();

if (!$userData || !$userData['coordinator_for']) {
    $message = "No course assigned to your account. Please contact administration.";
    $notification_type = "error";
}

$userCourseId = $userData['coordinator_for'] ?? null;
$userCourseName = $userData['course_name'] ?? null;
// Get filter values
$filter_semester = isset($_GET['sem_id']) ? intval($_GET['sem_id']) : 0;
$filter_subject = 0;

if (isset($_GET['sub_id'])) {
    $filter_subject = intval($_GET['sub_id']);
} elseif (isset($_GET['subject_id'])) {
    $filter_subject = intval($_GET['subject_id']);
}

$filter_date = isset($_GET['attendance_date']) ? $_GET['attendance_date'] : '';
$filter_month = isset($_GET['attendance_month']) ? $_GET['attendance_month'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Get all semesters
$semesterQuery = "SELECT sem_id, sem_name FROM semester ORDER BY sem_id";
$semesterResult = $conn->query($semesterQuery);
$semesters = [];
if ($semesterResult) {
    $semesters = $semesterResult->fetch_all(MYSQLI_ASSOC);
}

// Get subjects for the coordinator's course
$subjects = [];
if ($userCourseId) {
    $subjectQuery = "SELECT sub_id, sub_name, course_id, sem_id FROM subject WHERE course_id = ? ORDER BY sem_id, sub_name";
    $subjectStmt = $conn->prepare($subjectQuery);
    $subjectStmt->bind_param("i", $userCourseId);
    $subjectStmt->execute();
    $subjects = $subjectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $subjectStmt->close();
}

// Build attendance query - only for coordinator's course
$attendanceRecords = [];
$totalRecords = 0;

$avgPercentage = 0;

if ($userCourseId) {

    $avgQuery = "
        SELECT 
            u.user_id,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
            COUNT(*) AS total_count
        FROM attendance a
        JOIN users u ON a.user_id = u.user_id
        WHERE a.course_id = ?
        GROUP BY u.user_id
    ";

    $stmt = $conn->prepare($avgQuery);
    $stmt->bind_param("i", $userCourseId);
    $stmt->execute();
    $avgResult = $stmt->get_result();

    $sumPercentages = 0;
    $studentCount = 0;

    while ($row = $avgResult->fetch_assoc()) {
        if ($row['total_count'] > 0) {
            $percentage = (($row['present_count'] + $row['late_count']) / $row['total_count']) * 100;
            $sumPercentages += $percentage;
            $studentCount++;
        }
    }

    $avgPercentage = $studentCount > 0 
        ? round($sumPercentages / $studentCount, 2)
        : 0;
}

if ($userCourseId) {
    $attendanceQuery = "
        SELECT 
            a.attendance_id,
            a.attendance_date,
            a.status,
            a.remarks,
            a.created_at,
            u.user_id,
            u.id_number,
            u.full_name as student_name,
            s.sub_name,
            c.course_name,
            sem.sem_name,
            staff.full_name as marked_by
        FROM attendance a
        JOIN users u ON a.user_id = u.user_id
        JOIN subject s ON a.sub_id = s.sub_id
        JOIN course c ON a.course_id = c.course_id
        JOIN semester sem ON a.sem_id = sem.sem_id
        LEFT JOIN users staff ON a.attendance_done_by = staff.user_id
        WHERE a.course_id = ?
    ";

    $params = [$userCourseId];
    $types = "i";

    if ($filter_semester > 0) {
        $attendanceQuery .= " AND a.sem_id = ?";
        $params[] = $filter_semester;
        $types .= "i";
    }

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
    
    if (!empty($filter_status)) {
        $attendanceQuery .= " AND a.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }

    $attendanceQuery .= " ORDER BY a.attendance_date DESC, a.created_at DESC";

    // Pagination setup
    $limit = 10;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;

    // Count total records first
    $countQuery = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(*) as total FROM', $attendanceQuery);
    $countQuery = preg_replace('/ORDER BY.*/', '', $countQuery);
    
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRecords = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    // Get paginated records
    $attendanceQueryWithLimit = $attendanceQuery . " LIMIT $limit OFFSET $offset";
    $attendanceStmt = $conn->prepare($attendanceQueryWithLimit);
    $attendanceStmt->bind_param($types, ...$params);
    $attendanceStmt->execute();
    $attendanceResult = $attendanceStmt->get_result();
    $attendanceRecords = $attendanceResult->fetch_all(MYSQLI_ASSOC);
    $attendanceStmt->close();
}

$totalPages = $limit > 0 ? ceil($totalRecords / $limit) : 0;
$startRecord = $totalRecords > 0 ? ($page - 1) * $limit + 1 : 0;
$endRecord = min($startRecord + count($attendanceRecords) - 1, $totalRecords);

// Calculate statistics
$present_count = 0;
$absent_count = 0;
$late_count = 0;

foreach ($attendanceRecords as $record) {
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

$attendance_percentage = $totalRecords > 0 
    ? round((($present_count + $late_count) / $totalRecords) * 100, 2) 
    : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Attendance - Coordinator</title>
<link rel="stylesheet" href="../css/all.min.css" />
<link rel="stylesheet" href="../css/admin_menu.css" />
<link rel="stylesheet" href="../css/view_attendance.css" />
<link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
    <?php if (!$userCourseId): ?>
        <div class="error-message" style="background: #fff3cd; border: 2px solid #ffc107; color: #856404; padding: 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 2em;"></i>
            <div>
                <strong>Error:</strong> <?= htmlspecialchars($message) ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-clipboard-list" style="color: white;"></i>
                Course Attendance Records
            </h1>
            <div style="margin-top: 15px; display: flex; align-items: center; gap: 10px; font-size: 1.05em;">
                <i class="fas fa-graduation-cap"></i>
                <strong>Course:</strong> <?= htmlspecialchars($userCourseName) ?>
            </div>
        </div>

        <!-- Filter Container -->
        <div class="filter-container">
            <form method="GET" id="filterForm" class="filter-form">
                <div class="filter-group">
                    <label>Semester</label>
                    <select name="sem_id" id="semester">
                        <option value="">All Semesters</option>
                        <?php foreach ($semesters as $semester): ?>
                            <option value="<?= $semester['sem_id'] ?>" <?= $filter_semester == $semester['sem_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($semester['sem_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

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
                    <label>Month</label>
                    <input type="month" name="attendance_month" value="<?= htmlspecialchars($filter_month) ?>">
                </div>

                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" id="status">
                        <option value="">All Status</option>
                        <option value="Present" <?= $filter_status === 'Present' ? 'selected' : '' ?>>Present</option>
                        <option value="Absent" <?= $filter_status === 'Absent' ? 'selected' : '' ?>>Absent</option>
                        <option value="Late" <?= $filter_status === 'Late' ? 'selected' : '' ?>>Late</option>
                    </select>
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
            <div class="stat-card average">
    <div class="stat-icon">
        <i class="fas fa-percentage"></i>
    </div>
    <div class="stat-content">
        <div class="stat-label">Avg Attendance %</div>
        <div class="stat-value"><?= $avgPercentage ?>%</div>
    </div>
</div>

            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Records</div>
                    <div class="stat-value"><?= $totalRecords ?></div>
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
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-table"></i>
                    Attendance Details
                </div>
                <div class="search-box">
                    <input type="text" class="search-input" id="searchInput" placeholder="Search by student name or ID...">
                </div>
            </div>

            <?php if (count($attendanceRecords) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Semester</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Marked By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTableBody">
                        <?php foreach ($attendanceRecords as $index => $record): ?>
                        <tr class="attendance-row" 
                            data-id="<?= $record['attendance_id'] ?>"
                            data-student-name="<?= strtolower($record['student_name']) ?>" 
                            data-student-id="<?= strtolower($record['id_number']) ?>">
                            <td><?= date('d M Y', strtotime($record['attendance_date'])) ?></td>
                            <td><?= htmlspecialchars($record['id_number']) ?></td>
                            <td><?= htmlspecialchars($record['student_name']) ?></td>
                            <td><?= htmlspecialchars($record['sem_name']) ?></td>
                            <td><?= htmlspecialchars($record['sub_name']) ?></td>
                            <td>
                                <span class="status-badge <?= strtolower($record['status']) ?>">
                                    <?= htmlspecialchars($record['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($record['marked_by'] ?? 'N/A') ?></td>
                            <td><?= !empty($record['remarks']) ? htmlspecialchars($record['remarks']) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalRecords > 0): ?>
                <div class="pagination-info">
                    Showing <?= $startRecord ?>–<?= $endRecord ?> of <?= $totalRecords ?> records
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
                <p>There are no attendance records for <?= htmlspecialchars($userCourseName) ?> matching the selected criteria.</p>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</div>

<!-- Result Notification Modal -->
<?php if ($message && $notification_type): ?>
<div class="result-overlay active" id="resultOverlay">
    <div class="result-modal <?php echo $notification_type; ?>">
        <div class="result-icon">
            <?php
            switch ($notification_type) {
                case 'success':
                    echo '<i class="fas fa-check-circle"></i>';
                    break;
                case 'error':
                    echo '<i class="fas fa-times-circle"></i>';
                    break;
                case 'warning':
                    echo '<i class="fas fa-exclamation-triangle"></i>';
                    break;
            }
            ?>
        </div>
        <div class="result-title">
            <?php
            switch ($notification_type) {
                case 'success':
                    echo 'Success!';
                    break;
                case 'error':
                    echo 'Error';
                    break;
                case 'warning':
                    echo 'Warning';
                    break;
            }
            ?>
        </div>
        <div class="result-message"><?php echo htmlspecialchars($message); ?></div>
        <button class="result-button" onclick="closeResultNotification()">Okay</button>
    </div>
</div>

<script>
    function closeResultNotification() {
        const overlay = document.getElementById('resultOverlay');
        overlay.classList.remove('active');
    }
</script>
<?php endif; ?>

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

    // Clear filters
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener("click", () => {
            window.location.href = 'view_attendance.php';
        });
    }
});
</script>

</body>
</html>
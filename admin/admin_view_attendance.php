<?php
session_start();
include("auth_check.php");
include("connect.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$message = '';
$notification_type = null;

// Handle delete request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_attendance'])) {
    $attendance_id = intval($_POST['attendance_id']);
    
    if ($attendance_id > 0) {
        $deleteStmt = $conn->prepare("DELETE FROM attendance WHERE attendance_id = ?");
        if ($deleteStmt) {
            $deleteStmt->bind_param("i", $attendance_id);
            
            if ($deleteStmt->execute()) {
                if ($deleteStmt->affected_rows > 0) {
                    $message = "Attendance record deleted successfully!";
                    $notification_type = "success";
                } else {
                    $message = "No record found.";
                    $notification_type = "error";
                }
            } else {
                $message = "Error deleting record: " . $deleteStmt->error;
                $notification_type = "error";
            }
            $deleteStmt->close();
        } else {
            $message = "Database error: " . $conn->error;
            $notification_type = "error";
        }
    } else {
        $message = "Invalid attendance ID.";
        $notification_type = "error";
    }
}

// Get filter values
$filter_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_semester = isset($_GET['sem_id']) ? intval($_GET['sem_id']) : 0;
$filter_subject = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$filter_faculty = isset($_GET['faculty_id']) ? intval($_GET['faculty_id']) : 0;
$filter_student = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$filter_date = isset($_GET['attendance_date']) ? $_GET['attendance_date'] : '';
$filter_month = isset($_GET['attendance_month']) ? $_GET['attendance_month'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

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

// Get all subjects
$subjectQuery = "SELECT sub_id, sub_name, course_id, sem_id FROM subject ORDER BY sub_name";
$subjectResult = $conn->query($subjectQuery);
$subjects = [];
if ($subjectResult) {
    $subjects = $subjectResult->fetch_all(MYSQLI_ASSOC);
}

// Get all faculty members
$facultyQuery = "SELECT user_id, full_name FROM users WHERE role_id = 3 ORDER BY full_name";
$facultyResult = $conn->query($facultyQuery);
$faculties = [];
if ($facultyResult) {
    $faculties = $facultyResult->fetch_all(MYSQLI_ASSOC);
}

// Get all students
$studentQuery = "SELECT user_id, full_name, id_number, course_id, sem_id FROM users WHERE role_id = 2 ORDER BY full_name";
$studentResult = $conn->query($studentQuery);
$students = [];
if ($studentResult) {
    $students = $studentResult->fetch_all(MYSQLI_ASSOC);
}

// Build attendance query
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
        f.full_name as faculty_name
    FROM attendance a
    JOIN users u ON a.user_id = u.user_id
    JOIN subject s ON a.sub_id = s.sub_id
    JOIN course c ON a.course_id = c.course_id
    JOIN semester sem ON a.sem_id = sem.sem_id
    LEFT JOIN users f ON a.attendance_done_by = f.user_id
    WHERE 1=1
";

$params = [];
$types = "";

if ($filter_course > 0) {
    $attendanceQuery .= " AND a.course_id = ?";
    $params[] = $filter_course;
    $types .= "i";
}

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

if ($filter_faculty > 0) {
    $attendanceQuery .= " AND a.attendance_done_by = ?";
    $params[] = $filter_faculty;
    $types .= "i";
}

if ($filter_student > 0) {
    $attendanceQuery .= " AND a.user_id = ?";
    $params[] = $filter_student;
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

// Get all records for statistics
if (!empty($types)) {
    $statsStmt = $conn->prepare($attendanceQuery);
    $statsStmt->bind_param($types, ...$params);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $allRecords = $statsResult->fetch_all(MYSQLI_ASSOC);
    $statsStmt->close();
} else {
    $statsResult = $conn->query($attendanceQuery);
    $allRecords = $statsResult->fetch_all(MYSQLI_ASSOC);
}

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
$limit = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Add pagination to query
$attendanceQueryWithLimit = $attendanceQuery . " LIMIT $limit OFFSET $offset";

if (!empty($types)) {
    $attendanceStmt = $conn->prepare($attendanceQueryWithLimit);
    $attendanceStmt->bind_param($types, ...$params);
    $attendanceStmt->execute();
    $attendanceResult = $attendanceStmt->get_result();
    $attendanceRecords = $attendanceResult->fetch_all(MYSQLI_ASSOC);
    $attendanceStmt->close();
} else {
    $attendanceResult = $conn->query($attendanceQueryWithLimit);
    $attendanceRecords = $attendanceResult->fetch_all(MYSQLI_ASSOC);
}

$totalPages = ceil($total_records / $limit);
$startRecord = ($page - 1) * $limit + 1;
$endRecord = min($startRecord + count($attendanceRecords) - 1, $total_records);

// Get unique student count
$uniqueStudents = array_unique(array_column($allRecords, 'user_id'));
$unique_student_count = count($uniqueStudents);

// Get unique faculty count
$uniqueFaculty = array_unique(array_column($allRecords, 'attendance_done_by'));
$unique_faculty_count = count(array_filter($uniqueFaculty));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Admin - View All Attendance</title>
<link rel="stylesheet" href="../css/all.min.css" />
<link rel="stylesheet" href="../css/admin_menu.css" />
<link rel="stylesheet" href="../css/admin_view_attendance.css" />
<link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                All Attendance Records
            </h1>
            <p class="page-subtitle">Complete attendance overview across all courses, semesters, and students</p>
        </div>
        <div class="action-buttons">
            <button class="btn-export" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
        </div>
    </div>

    <!-- Filter Container -->
    <div class="filter-container">
        <div class="filter-header">
            <i class="fas fa-filter"></i>
            <span>Advanced Filters</span>
        </div>
        <form method="GET" id="filterForm" class="filter-form">
            <div class="filter-group">
                <label>Course</label>
                <select name="course_id" id="course">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['course_id'] ?>" <?= $filter_course == $course['course_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

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
                        <option value="<?= $subject['sub_id'] ?>" 
                                data-course="<?= $subject['course_id'] ?>"
                                data-semester="<?= $subject['sem_id'] ?>"
                                <?= $filter_subject == $subject['sub_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subject['sub_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Faculty</label>
                <select name="faculty_id" id="faculty">
                    <option value="">All Faculty</option>
                    <?php foreach ($faculties as $faculty): ?>
                        <option value="<?= $faculty['user_id'] ?>" <?= $filter_faculty == $faculty['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($faculty['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Student</label>
                <select name="student_id" id="student">
                    <option value="">All Students</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student['user_id'] ?>" 
                                data-course="<?= $student['course_id'] ?>"
                                data-semester="<?= $student['sem_id'] ?>"
                                <?= $filter_student == $student['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['id_number']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Date</label>
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

            <div class="filter-buttons">
                <button type="submit" class="btn-success">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
                <button type="button" class="btn-danger" id="clearFiltersBtn">
                    <i class="fas fa-times"></i> Clear All
                </button>
            </div>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Records</div>
                <div class="stat-value"><?= number_format($total_records) ?></div>
                <div class="stat-trend">
                    <i class="fas fa-info-circle"></i>
                    All attendance entries
                </div>
            </div>
        </div>

        <div class="stat-card present">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Present</div>
                <div class="stat-value"><?= number_format($present_count) ?></div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i>
                    <?= $total_records > 0 ? round(($present_count / $total_records) * 100, 1) : 0 ?>% of total
                </div>
            </div>
        </div>

        <div class="stat-card absent">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Absent</div>
                <div class="stat-value"><?= number_format($absent_count) ?></div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-down"></i>
                    <?= $total_records > 0 ? round(($absent_count / $total_records) * 100, 1) : 0 ?>% of total
                </div>
            </div>
        </div>

        <div class="stat-card late">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Late</div>
                <div class="stat-value"><?= number_format($late_count) ?></div>
                <div class="stat-trend">
                    <i class="fas fa-equals"></i>
                    <?= $total_records > 0 ? round(($late_count / $total_records) * 100, 1) : 0 ?>% of total
                </div>
            </div>
        </div>

        <div class="stat-card percentage">
            <div class="stat-icon">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Attendance Rate</div>
                <div class="stat-value"><?= $attendance_percentage ?>%</div>
                <div class="stat-trend">
                    <i class="fas fa-chart-line"></i>
                    Overall performance
                </div>
            </div>
        </div>
    </div>

    <!-- Table Container -->
    <div class="table-container">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-table"></i>
                Detailed Attendance Records
            </div>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Search by student name, ID, or subject...">
            </div>
        </div>

        <?php if (count($attendanceRecords) > 0): ?>
        <div class="table-wrapper">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Semester</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Faculty</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody">
                    <?php foreach ($attendanceRecords as $record): ?>
                    <tr class="attendance-row" 
                        data-student-name="<?= strtolower($record['student_name']) ?>" 
                        data-student-id="<?= strtolower($record['id_number']) ?>"
                        data-subject="<?= strtolower($record['sub_name']) ?>">
                        <td><?= date('d M Y', strtotime($record['attendance_date'])) ?></td>
                        <td><span class="student-id"><?= htmlspecialchars($record['id_number']) ?></span></td>
                        <td><?= htmlspecialchars($record['student_name']) ?></td>
                        <td><?= htmlspecialchars($record['course_name']) ?></td>
                        <td><?= htmlspecialchars($record['sem_name']) ?></td>
                        <td><?= htmlspecialchars($record['sub_name']) ?></td>
                        <td>
                            <span class="status-badge <?= strtolower($record['status']) ?>">
                                <i class="fas fa-<?= $record['status'] === 'Present' ? 'check' : ($record['status'] === 'Absent' ? 'times' : 'clock') ?>"></i>
                                <?= htmlspecialchars($record['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($record['faculty_name']) ?></td>
                        <td><?= !empty($record['remarks']) ? htmlspecialchars($record['remarks']) : '-' ?></td>
                        <td>
                            <div class="action-buttons-group">
                                <button class="action-btn edit" 
                                        onclick="window.location.href='edit_attendance.php?attendance_id=<?= $record['attendance_id'] ?>'"
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete" 
                                        onclick="confirmDelete(<?= $record['attendance_id'] ?>, '<?= htmlspecialchars($record['student_name']) ?>')"
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_records > 0): ?>
        <div class="pagination-info">
            Showing <?= $startRecord ?>–<?= $endRecord ?> of <?= number_format($total_records) ?> records
        </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination-btn">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php else: ?>
                <span class="pagination-btn disabled">
                    <i class="fas fa-chevron-left"></i> Previous
                </span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            
            if ($start > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="pagination-btn">1</a>
                <?php if ($start > 2): ?>
                    <span class="pagination-dots">...</span>
                <?php endif;
            endif;

            for ($i = $start; $i <= $end; $i++): ?>
                <?= $i == $page 
                    ? "<span class='pagination-btn active'>$i</span>" 
                    : "<a href='?".http_build_query(array_merge($_GET, ['page'=>$i]))."' class='pagination-btn'>$i</a>" ?>
            <?php endfor;

            if ($end < $totalPages):
                if ($end < $totalPages - 1): ?>
                    <span class="pagination-dots">...</span>
                <?php endif; ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" class="pagination-btn"><?= $totalPages ?></a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pagination-btn">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="pagination-btn disabled">
                    Next <i class="fas fa-chevron-right"></i>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No Attendance Records Found</h3>
            <p>There are no attendance records matching your filter criteria.</p>
            <button class="btn-primary" onclick="document.getElementById('clearFiltersBtn').click()">
                <i class="fas fa-redo"></i> Clear Filters
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="delete-overlay" id="deleteOverlay">
    <div class="delete-modal">
        <div class="delete-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="delete-title">Delete Attendance Record</div>
        <div class="delete-message">
            Are you sure you want to delete the attendance record for <strong id="deleteStudentName"></strong>?
            <br><small>This action cannot be undone.</small>
        </div>
        <div class="delete-buttons">
            <button class="delete-yes-btn" id="deleteYesBtn">
                <i class="fas fa-trash"></i> Yes, Delete
            </button>
            <button class="delete-no-btn" id="deleteNoBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>
</div>
</div>

<!-- Result Notification Modal -->
<?php if ($message): ?>
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
            }
            ?>
        </div>
        <div class="result-title">
            <?php echo $notification_type === 'success' ? 'Success!' : 'Error'; ?>
        </div>
        <div class="result-message"><?php echo htmlspecialchars($message); ?></div>
        <div class="result-progress">
            <div class="result-progress-bar"></div>
        </div>
        <button class="result-button" onclick="closeResultNotification()">Okay</button>
    </div>
</div>

<script>
    function closeResultNotification() {
        document.getElementById('resultOverlay').style.animation = 'fadeOut 0.3s ease-in';
        setTimeout(() => {
            window.location.reload();
        }, 300);
    }

    setTimeout(closeResultNotification, 2000);
</script>
<?php endif; ?>

<!-- Hidden form for deletion -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="delete_attendance" value="1">
    <input type="hidden" name="attendance_id" id="deleteAttendanceId">
</form>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");
    const clearFiltersBtn = document.getElementById("clearFiltersBtn");
    const deleteOverlay = document.getElementById("deleteOverlay");
    const deleteYesBtn = document.getElementById("deleteYesBtn");
    const deleteNoBtn = document.getElementById("deleteNoBtn");
    const deleteForm = document.getElementById("deleteForm");
    const deleteAttendanceId = document.getElementById("deleteAttendanceId");
    
    const courseSelect = document.getElementById("course");
    const semesterSelect = document.getElementById("semester");
    const subjectSelect = document.getElementById("subject");
    const studentSelect = document.getElementById("student");

    let currentDeleteId = null;

    // Search functionality
    if (searchInput) {
        searchInput.addEventListener("input", function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.attendance-row');

            rows.forEach(row => {
                const studentName = row.dataset.studentName;
                const studentId = row.dataset.studentId;
                const subject = row.dataset.subject;

                if (studentName.includes(searchTerm) || 
                    studentId.includes(searchTerm) || 
                    subject.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Clear filters
    clearFiltersBtn.addEventListener("click", () => {
        window.location.href = 'admin_view_attendance.php';
    });

    // Filter cascade - Update subjects based on course/semester
    function updateSubjectOptions() {
        const courseId = courseSelect.value;
        const semesterId = semesterSelect.value;
        const subjectOptions = subjectSelect.querySelectorAll('option');

        subjectOptions.forEach(option => {
            if (option.value === '') {
                option.style.display = '';
                return;
            }

            const optionCourse = option.dataset.course;
            const optionSemester = option.dataset.semester;

            if ((courseId && optionCourse !== courseId) || 
                (semesterId && optionSemester !== semesterId)) {
                option.style.display = 'none';
            } else {
                option.style.display = '';
            }
        });
    }

    // Filter cascade - Update students based on course/semester
    function updateStudentOptions() {
        const courseId = courseSelect.value;
        const semesterId = semesterSelect.value;
        const studentOptions = studentSelect.querySelectorAll('option');

        studentOptions.forEach(option => {
            if (option.value === '') {
                option.style.display = '';
                return;
            }

            const optionCourse = option.dataset.course;
            const optionSemester = option.dataset.semester;

            if ((courseId && optionCourse !== courseId) || 
                (semesterId && optionSemester !== semesterId)) {
                option.style.display = 'none';
            } else {
                option.style.display = '';
            }
        });
    }

    courseSelect.addEventListener('change', () => {
        updateSubjectOptions();
        updateStudentOptions();
    });

    semesterSelect.addEventListener('change', () => {
        updateSubjectOptions();
        updateStudentOptions();
    });

    // Initialize filters on page load
    updateSubjectOptions();
    updateStudentOptions();

    // Delete confirmation
    window.confirmDelete = function(attendanceId, studentName) {
        currentDeleteId = attendanceId;
        document.getElementById('deleteStudentName').textContent = studentName;
        deleteOverlay.classList.add('active');
    };

    // Delete Yes button
    deleteYesBtn.addEventListener("click", () => {
        if (currentDeleteId) {
            deleteAttendanceId.value = currentDeleteId;
            deleteForm.submit();
        }
    });

    // Delete No button
    deleteNoBtn.addEventListener("click", () => {
        deleteOverlay.classList.remove('active');
        currentDeleteId = null;
    });

    // Close modal when clicking outside
    deleteOverlay.addEventListener("click", (e) => {
        if (e.target === deleteOverlay) {
            deleteOverlay.classList.remove('active');
            currentDeleteId = null;
        }
    });
});

// Export to Excel function
function exportToExcel() {
    const table = document.querySelector('.attendance-table');
    const rows = Array.from(table.querySelectorAll('tbody tr:not([style*="display: none"])'));
    
    if (rows.length === 0) {
        alert('No records to export');
        return;
    }

    let csv = 'Date,Student ID,Student Name,Course,Semester,Subject,Status,Faculty,Remarks\n';
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td');
        const rowData = [
            cols[0].textContent,
            cols[1].textContent,
            cols[2].textContent,
            cols[3].textContent,
            cols[4].textContent,
            cols[5].textContent,
            cols[6].textContent.trim(),
            cols[7].textContent,
            cols[8].textContent
        ];
        csv += rowData.map(cell => `"${cell}"`).join(',') + '\n';
    });

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'attendance_report_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

</body>
</html>
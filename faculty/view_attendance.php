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

// Handle delete request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_attendance'])) {
    $attendance_id = intval($_POST['attendance_id']);
    
    if ($attendance_id > 0) {
        $deleteStmt = $conn->prepare("DELETE FROM attendance WHERE attendance_id = ? AND attendance_done_by = ?");
        if ($deleteStmt) {
            $deleteStmt->bind_param("ii", $attendance_id, $staff_id);
            
            if ($deleteStmt->execute()) {
                if ($deleteStmt->affected_rows > 0) {
                    $message = "Attendance record deleted successfully!";
                    $notification_type = "success";
                } else {
                    $message = "No record found or you don't have permission to delete this record.";
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
$filter_date = isset($_GET['attendance_date']) ? $_GET['attendance_date'] : '';
$filter_month = isset($_GET['attendance_month']) ? $_GET['attendance_month'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

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
        sem.sem_name
    FROM attendance a
    JOIN users u ON a.user_id = u.user_id
    JOIN subject s ON a.sub_id = s.sub_id
    JOIN course c ON a.course_id = c.course_id
    JOIN semester sem ON a.sem_id = sem.sem_id
    WHERE a.attendance_done_by = ?
";

$params = [$staff_id];
$types = "i";

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

$attendanceStmt = $conn->prepare($attendanceQuery);
if ($attendanceStmt) {
    $attendanceStmt->bind_param($types, ...$params);
    $attendanceStmt->execute();
    $attendanceResult = $attendanceStmt->get_result();
    $attendanceRecords = $attendanceResult->fetch_all(MYSQLI_ASSOC);
    $attendanceStmt->close();
} else {
    $attendanceRecords = [];
}

// Calculate statistics
$total_records = count($attendanceRecords);
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

$attendance_percentage = $total_records > 0 
    ? round((($present_count + $late_count) / $total_records) * 100, 2) 
    : 0;
// Pagination setup
// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Append pagination to the query BEFORE executing
$attendanceQueryWithLimit = $attendanceQuery . " LIMIT $limit OFFSET $offset";

$attendanceStmt = $conn->prepare($attendanceQueryWithLimit);
$attendanceStmt->bind_param($types, ...$params);
$attendanceStmt->execute();
$attendanceResult = $attendanceStmt->get_result();
$attendanceRecords = $attendanceResult->fetch_all(MYSQLI_ASSOC);
$attendanceStmt->close();

// Count total records (for pagination info)
$countSql = preg_replace('/ORDER BY.*/', '', $attendanceQuery);
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = $countResult->num_rows;
$countStmt->close();

$totalPages = ceil($totalRecords / $limit);
// Record range display
$startRecord = ($page - 1) * $limit + 1;
$endRecord = min($startRecord + count($attendanceRecords) - 1, $totalRecords);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>View Attendance</title>
<link rel="stylesheet" href="../css/all.min.css" />
<link rel="stylesheet" href="../css/admin_menu.css" />
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
            <i class="fas fa-clipboard-list" style="color: black"></i>
            Attendance Records
        </h1>
        <div class="action-buttons">
            <a href="attendance.php" class="btn-primary">
                <i class="fas fa-plus"></i> Mark Attendance
            </a>
        </div>
    </div>

    <!-- Filter Container -->
    <div class="filter-container">
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
                        <option value="<?= $subject['sub_id'] ?>" <?= $filter_subject == $subject['sub_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subject['sub_name']) ?>
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

            <button type="submit" class="btn-success">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <button type="button" class="btn-danger" id="clearFiltersBtn">
                <i class="fas fa-times"></i> Clear Filters
            </button>
        </form>
    </div>

    <!-- Statistics Cards -->
    <!-- <div class="stats-container">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Records</div>
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
    </div>
 -->
    <!-- Table Container -->
    <div class="table-container">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-table"></i>
                Attendance Details
            </div>
            <div class="search-box">
                <input type="text" class="search-input" id="searchInput" placeholder=" Search by student name or ID...">
            </div>
        </div>

        <?php if (count($attendanceRecords) > 0): ?>
        <div style="overflow-x: auto;">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <!-- <th>S.N.</th> -->
                        <th>Date</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Semester</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody">
                    <?php foreach ($attendanceRecords as $index => $record): 
                        $snStart = $totalRecords - (($page - 1) * $limit);?>
                    <tr class="attendance-row" 
                        data-id="<?= $record['attendance_id'] ?>"
                        data-student-name="<?= strtolower($record['student_name']) ?>" 
                        data-student-id="<?= strtolower($record['id_number']) ?>">
                        <!-- <td><?= $snStart - $index ?></td> -->
                        <td><?= date('d M Y', strtotime($record['attendance_date'])) ?></td>
                        <td><?= htmlspecialchars($record['id_number']) ?></td>
                        <td><?= htmlspecialchars($record['student_name']) ?></td>
                        <td><?= htmlspecialchars($record['course_name']) ?></td>
                        <td><?= htmlspecialchars($record['sem_name']) ?></td>
                        <td><?= htmlspecialchars($record['sub_name']) ?></td>
                        <td>
                            <span class="status-badge <?= strtolower($record['status']) ?>">
                                <?= htmlspecialchars($record['status']) ?>
                            </span>
                        </td>
                        <td><?= !empty($record['remarks']) ? htmlspecialchars($record['remarks']) : '-' ?></td>
                        <td>
                            <button class="action-btn delete" onclick="confirmDelete(<?= $record['attendance_id'] ?>, '<?= htmlspecialchars($record['student_name']) ?>')">
    <i class="fas fa-trash"></i>
</button>

                            <button class="action-btn edit" onclick="window.location.href='edit_attendance.php?attendance_id=<?= $record['attendance_id'] ?>'">
    <i class="fas fa-edit"></i>
</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
    <?php if ($totalRecords > 0): ?>
  <div class="pagination-info">
    Showing <?= $startRecord ?>–<?= $endRecord ?> of <?= $totalRecords ?> students
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
            <a href="attendance.php" class="btn-primary" style="margin-top: 15px;">
                <i class="fas fa-plus"></i> Mark Attendance
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Delete Confirmation Modal -->
<div class="delete-overlay" id="deleteOverlay">
    <div class="delete-modal">
        <div class="delete-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="delete-title">Delete Attendance Record</div>
        <div class="delete-message" id="deleteMessage">
            Are you sure you want to delete the attendance record for <strong id="deleteStudentName"></strong>?
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

<div class="result-overlay" id="resultOverlay">
    <div class="result-modal" id="resultModal">
        <div class="result-icon" id="resultIcon"></div>
        <div class="result-title" id="resultTitle"></div>
        <div class="result-message" id="resultMessage"></div>
        <button class="result-button" onclick="closeResultNotification()">Okay</button>
    </div>
</div>

<!-- Hidden form for deletion -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="delete_attendance" value="1">
    <input type="hidden" name="attendance_id" id="deleteAttendanceId">
</form>

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
        <div class="result-progress">
            <div class="result-progress-bar"></div>
        </div>
        <button class="result-button" onclick="closeResultNotification()">
            <?php echo ($notification_type === 'success') ? 'Continue' : 'Okay'; ?>
        </button>
    </div>
</div>

<script>
    function closeResultNotification() {
        const overlay = document.getElementById('resultOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            <?php if ($redirect_url): ?>
                window.location.href = '<?php echo $redirect_url; ?>';
            <?php else: ?>
                overlay.classList.remove('active');
                window.location.reload();
            <?php endif; ?>
        }, 300);
    }

    setTimeout(() => {
        closeResultNotification();
    }, <?php echo $redirect_delay; ?>);
</script>
<?php endif; ?>
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

    let currentDeleteId = null;

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

    // Show clear filters button if any filter is active
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.toString()) {
        clearFiltersBtn.style.display = 'inline-flex';
    }

    // Clear filters
    clearFiltersBtn.addEventListener("click", () => {
        window.location.href = 'view_attendance.php';
    });

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
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const deleteOverlay = document.getElementById("deleteOverlay");
    const deleteYesBtn = document.getElementById("deleteYesBtn");
    const deleteNoBtn = document.getElementById("deleteNoBtn");
    const deleteStudentName = document.getElementById("deleteStudentName");

    const resultOverlay = document.getElementById("resultOverlay");
    const resultModal = document.getElementById("resultModal");
    const resultIcon = document.getElementById("resultIcon");
    const resultTitle = document.getElementById("resultTitle");
    const resultMessage = document.getElementById("resultMessage");

    let currentDeleteId = null;
    let currentDeleteRow = null;

    // Show delete confirmation modal
    window.confirmDelete = function(attendanceId, studentName) {
        currentDeleteId = attendanceId;
        deleteStudentName.textContent = studentName;
        currentDeleteRow = document.querySelector(`.attendance-row[data-id='${attendanceId}']`);
        deleteOverlay.classList.add('active');
    };

    // Cancel deletion
    deleteNoBtn.addEventListener("click", () => {
        deleteOverlay.classList.remove('active');
        currentDeleteId = null;
    });

    deleteOverlay.addEventListener("click", (e) => {
        if(e.target === deleteOverlay){
            deleteOverlay.classList.remove('active');
            currentDeleteId = null;
        }
    });

    // Delete record via AJAX
    deleteYesBtn.addEventListener("click", () => {
        if(!currentDeleteId) return;

        fetch('delete_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `attendance_id=${currentDeleteId}`
        })
        .then(res => res.json())
        .then(data => {
            deleteOverlay.classList.remove('active');

            if(data.success){
                // Remove row from table
                if(currentDeleteRow) currentDeleteRow.remove();
                showResultNotification('Success!', data.message, 'success');
            } else {
                showResultNotification('Error', data.message, 'error');
            }

            currentDeleteId = null;
            currentDeleteRow = null;
        })
        .catch(err => {
            deleteOverlay.classList.remove('active');
            showResultNotification('Error', 'Something went wrong. Try again!', 'error');
            currentDeleteId = null;
            currentDeleteRow = null;
        });
    });

    // Show result notification
    function showResultNotification(title, message, type){
        resultTitle.textContent = title;
        resultMessage.textContent = message;

        resultModal.className = 'result-modal ' + type; // add class 'success' or 'error'

        switch(type){
            case 'success': resultIcon.innerHTML = '<i class="fas fa-check-circle"></i>'; break;
            case 'error': resultIcon.innerHTML = '<i class="fas fa-times-circle"></i>'; break;
            default: resultIcon.innerHTML = '<i class="fas fa-info-circle"></i>';
        }

        resultOverlay.classList.add('active');
    }

    window.closeResultNotification = function(){
        resultOverlay.classList.remove('active');
    };
});
</script>

</body>
</html>
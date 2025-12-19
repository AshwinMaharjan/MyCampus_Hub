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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_attendance'])) {
    $course_id = intval($_POST['course_id']);
    $sem_id = intval($_POST['sem_id']);
    $subject_id = intval($_POST['subject_id']);
    $attendance_date = $_POST['attendance_date'];
    $student_ids = $_POST['student_ids'] ?? [];
    $attendance_status = $_POST['attendance_status'] ?? [];
    $remarks = $_POST['remarks'] ?? [];

    // Validate inputs
    if ($course_id <= 0 || $sem_id <= 0 || $subject_id <= 0) {
        $message = "Invalid course, semester, or subject selection.";
        $notification_type = "error";
    } elseif (empty($attendance_date)) {
        $message = "Please select attendance date";
        $notification_type = "error";
    } elseif (empty($student_ids)) {
        $message = "No students found to mark attendance.";
        $notification_type = "error";
    } elseif (strtotime($attendance_date) > strtotime(date('Y-m-d'))) {
        // Future date check
        $message = "Future dates are not allowed for attendance!";
        $notification_type = "error";
    } else {
        // All validations passed, check for duplicate attendance
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE sub_id = ? AND course_id = ? AND sem_id = ? AND attendance_date = ?");
        if (!$checkStmt) {
            $message = "Database error: " . $conn->error;
            $notification_type = "error";
        } else {
            $checkStmt->bind_param("iiis", $subject_id, $course_id, $sem_id, $attendance_date);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkRow = $checkResult->fetch_assoc();
            $count = $checkRow['count'];
            $checkStmt->close();

            if ($count > 0) {
                $message = "Attendance for this subject, date, and session has already been recorded.";
                $notification_type = "warning";
            } else {
                // No duplicates, proceed to insert attendance
                $stmt = $conn->prepare("
                    INSERT INTO attendance (
                        user_id, sub_id, course_id, sem_id, attendance_date, 
                        status, remarks, attendance_done_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                if (!$stmt) {
                    $message = "Database error: " . $conn->error;
                    $notification_type = "error";
                } else {
                    $success_count = 0;
                    $error_count = 0;

                    foreach ($student_ids as $student_id) {
                        $status = $attendance_status[$student_id] ?? 'Present';
                        $remark = !empty($remarks[$student_id]) ? trim($remarks[$student_id]) : null;

                        $stmt->bind_param(
                            "iiiisssi", 
                            $student_id, 
                            $subject_id, 
                            $course_id, 
                            $sem_id,
                            $attendance_date, 
                            $status, 
                            $remark, 
                            $staff_id
                        );

                        if ($stmt->execute()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }

                    $stmt->close();

                    if ($error_count === 0) {
                        $message = "Attendance saved successfully for {$success_count} students!";
                        $notification_type = "success";
                        $redirect_url = "view_attendance.php";
                    } else {
                        $message = "Attendance saved for {$success_count} students. Failed for {$error_count} students.";
                        $notification_type = "warning";
                    }
                }
            }
        }
    }
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Mark Attendance</title>
<link rel="stylesheet" href="../css/all.min.css" />
<link rel="stylesheet" href="../css/admin_menu.css" />
<link rel="stylesheet" href="../css/attendance.css" />
<link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
    <!-- Filter Section -->
    <div class="filter-container">
        <form method="GET" id="filterForm" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; width: 100%;">
            
            <div class="filter-group">
                <label>Course</label>
                <select name="course_id" id="course" required>
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= htmlspecialchars($course['course_id']) ?>">
                            <?= htmlspecialchars($course['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Semester</label>
                <select name="sem_id" id="semester" required disabled>
                    <option value="">Select Semester</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Subject</label>
                <select name="subject_id" id="subject" required disabled>
                    <option value="">Select Subject</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Date</label>
                <input type="date" name="attendance_date" id="attendance_date" required 
                       value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
            </div>

            <button type="button" class="search-btn" id="loadStudentsBtn" disabled>
                <i class="fas fa-users"></i> Load Students
            </button>

        </form>
    </div>

    <!-- Summary Section -->
    <div class="summary-section" id="summarySection">
        <div class="summary-label" style="font-size: 16px; margin-bottom: 10px;">Attendance Summary</div>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Students</div>
                <div class="summary-value" id="totalStudents">0</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Present</div>
                <div class="summary-value" id="presentCount">0</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Absent</div>
                <div class="summary-value" id="absentCount">0</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Late</div>
                <div class="summary-value" id="lateCount">0</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Attendance %</div>
                <div class="summary-value" id="attendancePercentage">0%</div>
            </div>
        </div>
    </div>

    <!-- Students Section -->
    <div class="students-section" id="studentsSection">
        <form method="POST" id="attendanceForm">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-users"></i> Students List
                </div>
                <label class="mark-all-present">
                    <input type="checkbox" id="markAllPresent">
                    Mark All Present
                </label>
            </div>

            <div class="search-group" style="margin-bottom: 15px;">
                <div class="filter-group" style="flex: 1;">
                    <input type="text" id="searchStudent" placeholder="🔍 Search by ID or Name...">
                </div>
            </div>

            <input type="hidden" name="course_id" id="hidden_course_id">
            <input type="hidden" name="sem_id" id="hidden_sem_id">
            <input type="hidden" name="subject_id" id="hidden_subject_id">
            <input type="hidden" name="attendance_date" id="hidden_attendance_date">

            <div id="studentsTableContainer">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Loading students...</p>
                </div>
            </div>

            <button type="button" name="save_attendance" class="search-btn" id="saveBtn" style="display: none; margin-top: 20px;">
                <i class="fas fa-save"></i> Save Attendance
            </button>
        </form>
    </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-modal">
        <div class="confirm-icon">
            <i class="fas fa-question-circle"></i>
        </div>
        <div class="confirm-title">Confirm Attendance Submission</div>
        <div class="confirm-message" id="confirmMessage">
            Are you sure you want to save attendance for <strong id="confirmStudentCount">0</strong> students?
        </div>
        <div class="confirm-buttons">
            <button class="confirm-yes-btn" id="confirmYesBtn">
                <i class="fas fa-check"></i> Yes, Save
            </button>
            <button class="confirm-no-btn" id="confirmNoBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
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
    const courseSelect = document.getElementById("course");
    const semesterSelect = document.getElementById("semester");
    const subjectSelect = document.getElementById("subject");
    const dateInput = document.getElementById("attendance_date");
    const loadStudentsBtn = document.getElementById("loadStudentsBtn");
    const studentsSection = document.getElementById("studentsSection");
    const summarySection = document.getElementById("summarySection");
    const studentsTableContainer = document.getElementById("studentsTableContainer");
    const markAllPresentCheckbox = document.getElementById("markAllPresent");
    const searchInput = document.getElementById("searchStudent");
    const saveBtn = document.getElementById("saveBtn");
    const attendanceForm = document.getElementById("attendanceForm");

    // Confirmation modal elements
    const confirmOverlay = document.getElementById("confirmOverlay");
    const confirmYesBtn = document.getElementById("confirmYesBtn");
    const confirmNoBtn = document.getElementById("confirmNoBtn");
    const confirmStudentCount = document.getElementById("confirmStudentCount");

    // Store all subjects data
    const allSubjects = <?php echo json_encode($subjects); ?>;
    const allSemesters = <?php echo json_encode($semesters); ?>;

    // Check if all required fields are filled
    function checkFormValidity() {
        const allFilled = courseSelect.value && semesterSelect.value && subjectSelect.value && dateInput.value;
        loadStudentsBtn.disabled = !allFilled;
    }

    // Load semesters when course is selected
    courseSelect.addEventListener("change", function() {
        const courseId = parseInt(this.value);
        semesterSelect.innerHTML = '<option value="">Select Semester</option>';
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';
        semesterSelect.disabled = true;
        subjectSelect.disabled = true;
        
        if (courseId) {
            // Get unique semesters for this course from subjects
            const uniqueSemesters = new Set();
            allSubjects.forEach(subject => {
                if (parseInt(subject.course_id) === courseId) {
                    uniqueSemesters.add(parseInt(subject.sem_id));
                }
            });

            if (uniqueSemesters.size > 0) {
                // Add semesters to dropdown
                allSemesters.forEach(sem => {
                    if (uniqueSemesters.has(parseInt(sem.sem_id))) {
                        const opt = document.createElement("option");
                        opt.value = sem.sem_id;
                        opt.textContent = sem.sem_name;
                        semesterSelect.appendChild(opt);
                    }
                });
                semesterSelect.disabled = false;
            }
        }
        checkFormValidity();
    });

    // Load subjects when semester is selected
    semesterSelect.addEventListener("change", function() {
        const courseId = parseInt(courseSelect.value);
        const semId = parseInt(this.value);
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';
        subjectSelect.disabled = true;
        
        if (courseId && semId) {
            // Filter subjects by course and semester
            const filteredSubjects = allSubjects.filter(subject => 
                parseInt(subject.course_id) === courseId && 
                parseInt(subject.sem_id) === semId
            );

            if (filteredSubjects.length > 0) {
                filteredSubjects.forEach(subject => {
                    const opt = document.createElement("option");
                    opt.value = subject.sub_id;
                    opt.textContent = subject.sub_name;
                    subjectSelect.appendChild(opt);
                });
                subjectSelect.disabled = false;
            }
        }
        checkFormValidity();
    });

    // Enable load button when all fields are filled
    [subjectSelect, dateInput].forEach(element => {
        element.addEventListener("change", checkFormValidity);
    });

    // Load students when button is clicked
    loadStudentsBtn.addEventListener("click", function() {
        const courseId = courseSelect.value;
        const semId = semesterSelect.value;
        const subjectId = subjectSelect.value;
        const date = dateInput.value;

        if (!courseId || !semId || !subjectId || !date) {
            alert("Please fill all required fields!");
            return;
        }

        // Set hidden fields
        document.getElementById("hidden_course_id").value = courseId;
        document.getElementById("hidden_sem_id").value = semId;
        document.getElementById("hidden_subject_id").value = subjectId;
        document.getElementById("hidden_attendance_date").value = date;

        // Show students section with loading
        studentsSection.classList.add("active");
        studentsTableContainer.innerHTML = `
            <div class="loading">
                <i class="fas fa-spinner"></i>
                <p>Loading students...</p>
            </div>
        `;
        saveBtn.style.display = 'none';

        // Fetch students
        fetch('fetch_students_for_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `course_id=${courseId}&sem_id=${semId}&subject_id=${subjectId}&date=${date}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                studentsTableContainer.innerHTML = `
                    <div class="no-students">
                        <i class="fas fa-exclamation-circle" style="font-size: 50px; color: #999;"></i>
                        <p>${data.error}</p>
                    </div>
                `;
                summarySection.classList.remove("active");
                return;
            }

            if (data.duplicate) {
                studentsTableContainer.innerHTML = `
                    <div class="no-students">
                        <i class="fas fa-info-circle" style="font-size: 50px; color: #f59e0b;"></i>
                        <p style="color: #f59e0b; font-weight: 600;">${data.message}</p>
                    </div>
                `;
                summarySection.classList.remove("active");
                return;
            }

            if (data.students && data.students.length > 0) {
                renderStudentsTable(data.students);
                updateSummary();
                summarySection.classList.add("active");
                saveBtn.style.display = 'inline-flex';
            } else {
                studentsTableContainer.innerHTML = `
                    <div class="no-students">
                        <i class="fas fa-user-slash" style="font-size: 50px; color: #999;"></i>
                        <p>No students found for this course and semester.</p>
                    </div>
                `;
                summarySection.classList.remove("active");
            }
        })
        .catch(err => {
            console.error('Error:', err);
            studentsTableContainer.innerHTML = `
                <div class="no-students">
                    <i class="fas fa-exclamation-triangle" style="font-size: 50px; color: #f44336;"></i>
                    <p style="color: #f44336;">Error loading students. Please try again.</p>
                </div>
            `;
            summarySection.classList.remove("active");
        });
    });

    // Render students table
    function renderStudentsTable(students) {
        let tableHTML = `
            <table>
                <thead>
                    <tr>
                        <th>S.N.</th>
                        <th>ID Number</th>
                        <th>Student Name</th>
                        <th>Attendance Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
        `;

        students.forEach((student, index) => {
            tableHTML += `
                <tr class="student-row" data-student-name="${student.full_name.toLowerCase()}" data-student-id="${student.id_number.toLowerCase()}">
                    <td>${index + 1}</td>
                    <td>${student.id_number}</td>
                    <td>${student.full_name}</td>
                    <td>
                        <input type="hidden" name="student_ids[]" value="${student.user_id}">
                        <div class="radio-group">
                            <div class="radio-option present">
                                <input type="radio" name="attendance_status[${student.user_id}]" 
                                       value="Present" id="present_${student.user_id}" 
                                       class="attendance-radio" checked>
                                <label for="present_${student.user_id}">Present</label>
                            </div>
                            <div class="radio-option absent">
                                <input type="radio" name="attendance_status[${student.user_id}]" 
                                       value="Absent" id="absent_${student.user_id}" 
                                       class="attendance-radio">
                                <label for="absent_${student.user_id}">Absent</label>
                            </div>
                            <div class="radio-option late">
                                <input type="radio" name="attendance_status[${student.user_id}]" 
                                       value="Late" id="late_${student.user_id}" 
                                       class="attendance-radio">
                                <label for="late_${student.user_id}">Late</label>
                            </div>
                        </div>
                    </td>
                    <td>
                        <input type="text" name="remarks[${student.user_id}]" 
                               class="remarks-input" placeholder="Optional remarks...">
                    </td>
                </tr>
            `;
        });

        tableHTML += `
                </tbody>
            </table>
        `;

        studentsTableContainer.innerHTML = tableHTML;

        // Add event listeners to all radio buttons
        document.querySelectorAll('.attendance-radio').forEach(radio => {
            radio.addEventListener('change', updateSummary);
        });
    }

    // Mark all present checkbox handler
    markAllPresentCheckbox.addEventListener("change", function() {
        const presentRadios = document.querySelectorAll('input[type="radio"][value="Present"]');
        presentRadios.forEach(radio => {
            radio.checked = this.checked;
        });
        updateSummary();
    });

    // Search functionality
    searchInput.addEventListener("input", function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('.student-row');

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

    // Update summary statistics
    function updateSummary() {
        const allRadios = document.querySelectorAll('.attendance-radio');
        const studentIds = new Set();
        
        let presentCount = 0;
        let absentCount = 0;
        let lateCount = 0;

        allRadios.forEach(radio => {
            if (radio.checked) {
                studentIds.add(radio.name);
                
                if (radio.value === 'Present') {
                    presentCount++;
                } else if (radio.value === 'Absent') {
                    absentCount++;
                } else if (radio.value === 'Late') {
                    lateCount++;
                }
            }
        });

        const totalStudents = studentIds.size;
        const attendancePercentage = totalStudents > 0
    ? ((presentCount * 100 + lateCount * 50) / totalStudents).toFixed(2)
    : 0;
        document.getElementById('totalStudents').textContent = totalStudents;
        document.getElementById('presentCount').textContent = presentCount;
        document.getElementById('absentCount').textContent = absentCount;
        document.getElementById('lateCount').textContent = lateCount;
        document.getElementById('attendancePercentage').textContent = attendancePercentage + '%';
    }

    // Save button click - Show confirmation modal
    saveBtn.addEventListener("click", function(e) {
        e.preventDefault();
        
        const totalStudents = document.getElementById('totalStudents').textContent;
        
        if (totalStudents === '0') {
            alert('No students to save attendance for!');
            return;
        }

        // Update confirmation message
        confirmStudentCount.textContent = totalStudents;
        
        // Show confirmation modal
        confirmOverlay.classList.add('active');
    });

    // Confirm Yes button - Submit form
    confirmYesBtn.addEventListener("click", function() {
        confirmOverlay.classList.remove('active');
        
        // Create a hidden input for save_attendance
        const saveInput = document.createElement('input');
        saveInput.type = 'hidden';
        saveInput.name = 'save_attendance';
        saveInput.value = '1';
        attendanceForm.appendChild(saveInput);
        
        // Submit the form
        attendanceForm.submit();
    });

    // Confirm No button - Close modal
    confirmNoBtn.addEventListener("click", function() {
        confirmOverlay.classList.remove('active');
    });

    // Close modal when clicking outside
    confirmOverlay.addEventListener("click", function(e) {
        if (e.target === confirmOverlay) {
            confirmOverlay.classList.remove('active');
        }
    });
});

loadStudentsBtn.addEventListener("click", function() {
    const courseId = courseSelect.value;
    const semId = semesterSelect.value;
    const subjectId = subjectSelect.value;
    const date = dateInput.value;

    if (!courseId || !semId || !subjectId || !date) {
        alert("Please fill all required fields!");
        return;
    }

    // Check for future date
    const selectedDate = new Date(date);
    const today = new Date();
    today.setHours(0,0,0,0);
    if (selectedDate > today) {
        alert("Future dates are not allowed for attendance!");
        return;
    }

    // Proceed with hidden fields & fetch...
});

</script>
</body>
</html>
<?php
session_start();
include("auth_check.php");
include("connect.php");

$staff_id = $_SESSION['uid'];
$message = '';
$notification_type = null;
$redirect_url = null;
$redirect_delay = 2000;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id = intval($_POST['student_id']);
    $subject_id = intval($_POST['subject_id']);
    $id_number = $_POST['id_number'];
    $full_marks = floatval($_POST['full_marks']);
    $obtained = floatval($_POST['obtained_marks']);
    $remarks = isset($_POST['remarks']) && !empty($_POST['remarks']) ? trim($_POST['remarks']) : '';
    $percentage = round(($obtained / $full_marks) * 100, 2);
    $grade = $_POST['grade'];
    $exam_type_id = intval($_POST['exam_type_id']);
    $session_id = 1;
    $course_id = intval($_POST['course_id']);
    $sem_id = intval($_POST['sem_id']);
    $exam_date = date('Y-m-d');

    // Validate inputs
    if ($student_id <= 0 || $subject_id <= 0 || $exam_type_id <= 0) {
        $message = "Invalid student, subject, or exam type selection.";
        $notification_type = "error";
        $redirect_url = "enter_marks.php";
    } elseif ($full_marks <= 0 || $obtained < 0 || $obtained > $full_marks) {
        $message = "Invalid marks. Obtained marks cannot exceed full marks.";
        $notification_type = "error";
        $redirect_url = "enter_marks.php";
    } else {
        // Prevent duplicate entry for same student, subject, exam type, and semester
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM marks WHERE user_id = ? AND sub_id = ? AND exam_type_id = ? AND sem_id = ?");
        
        if (!$checkStmt) {
            $message = "Database error: " . $conn->error;
            $notification_type = "error";
            $redirect_url = "enter_marks.php";
        } else {
            $checkStmt->bind_param("iiii", $student_id, $subject_id, $exam_type_id, $sem_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkRow = $checkResult->fetch_assoc();
            $count = $checkRow['count'];
            $checkStmt->close();

            if ($count > 0) {
                $message = "Marks for this student, subject, and exam type already exist.";
                $notification_type = "warning";
                $redirect_url = "enter_marks.php";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO marks (
                        user_id, id_number, sub_id, sem_id, course_id, session_id,
                        full_marks, obtained_marks, remarks, entered_by_staff,
                        percentage, grade, exam_type_id, exam_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if (!$stmt) {
                    $message = "Database error: " . $conn->error;
                    $notification_type = "error";
                    $redirect_url = "enter_marks.php";
                } else {
                    $stmt->bind_param(
                        "isiiiiidssssis",
                        $student_id,
                        $id_number,
                        $subject_id,
                        $sem_id,
                        $course_id,
                        $session_id,
                        $full_marks,
                        $obtained,
                        $remarks,
                        $staff_id,
                        $percentage,
                        $grade,
                        $exam_type_id,
                        $exam_date
                    );

                    if ($stmt->execute()) {
                        $message = "Marks inserted successfully!";
                        $notification_type = "success";
                        $redirect_url = "view_marks.php";
                    } else {
                        $message = "Failed to insert marks: " . $stmt->error;
                        $notification_type = "error";
                        $redirect_url = "enter_marks.php";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Get subjects taught by this staff
$stmt = $conn->prepare("SELECT sub_id, sub_name FROM subject WHERE role_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $subjects = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Enter Marks</title>
<link rel="stylesheet" href="../css/admin_menu.css" />
<link rel="stylesheet" href="../css/enter_marks.css" />
<link rel="icon" href="../Prime-College-Logo.ico" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .error { border: 2px solid #e74c3c !important; background-color: #fdf2f2 !important; }
    .error-message {
        color: #e74c3c;
        font-size: 0.85em;
        margin-top: 5px;
        padding: 5px;
        background-color: #fdf2f2;
        border: 1px solid #e74c3c;
        border-radius: 4px;
        display: none;
    }
    .error-message.show {
        display: block;
    }
    #obtained_marks.invalid {
        border: 2px solid #e74c3c !important;
        background-color: #fdf2f2 !important;
    }
    button[type="submit"]:disabled {
        background-color: #cccccc !important;
        cursor: not-allowed !important;
        opacity: 0.6;
    }

    /* Notification Styles - Copied from login.php */
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

    .notification-modal.warning .notification-icon {
        color: #f59e0b;
    }

    .notification-modal.warning {
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

    .notification-modal.warning .notification-progress-bar {
        background: linear-gradient(90deg, #f59e0b, #d97706);
    }

    @keyframes progress {
        from {
            width: 100%;
        }
        to {
            width: 0%;
        }
    }

    .notification-button {
        margin-top: 20px;
        padding: 10px 30px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
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

    .notification-modal.warning .notification-button {
        background: #f59e0b;
        color: white;
    }

    .notification-modal.warning .notification-button:hover {
        background: #d97706;
        transform: translateY(-2px);
    }
</style>
</head>
<body>
<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
    <h2>Enter Marks</h2>
    <form method="POST" id="marksForm">
        <label for="subject">Subject:</label>
        <select name="subject_id" id="subject" required>
            <option value="">Select Subject</option>
            <?php foreach ($subjects as $sub): ?>
                <option value="<?= htmlspecialchars($sub['sub_id']) ?>"><?= htmlspecialchars($sub['sub_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="exam_type">Exam Type:</label>
        <select name="exam_type_id" id="exam_type" required>
            <option value="">Select Exam Type</option>
        </select>

        <label for="id_number">Student ID:</label>
        <select name="id_number" id="id_number" required>
            <option value="">Select Student</option>
        </select>

        <input type="hidden" name="student_id" id="student_id" />

        <label>Course:</label>
        <input type="text" id="course_name" readonly />
        <input type="hidden" name="course_id" id="course_id" />

        <label>Semester:</label>
        <input type="text" id="semester_name" readonly />
        <input type="hidden" name="sem_id" id="sem_id" />

        <label>Full Marks:</label>
        <input type="number" name="full_marks" id="full_marks" readonly required />

        <label>Obtained Marks:</label>
        <input type="number" name="obtained_marks" id="obtained_marks" min="0" required />
        <div id="marks_error" class="error-message"></div>

        <label>Percentage:</label>
        <input type="text" name="percentage" id="percentage" readonly />

        <label>Grade:</label>
        <input type="text" name="grade" id="grade" readonly />

        <label>Remarks:</label>
        <input type="text" name="remarks" id="remarks" />

        <button type="submit">Submit Marks</button>
    </form>
</div>
</div>
</div>

<!-- Notification Modal -->
<?php if ($message): ?>
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
                case 'warning':
                    echo '<i class="fas fa-exclamation-triangle"></i>';
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
                case 'warning':
                    echo 'Warning';
                    break;
            }
            ?>
        </div>
        <div class="notification-message"><?php echo htmlspecialchars($message); ?></div>
        <div class="notification-progress">
            <div class="notification-progress-bar"></div>
        </div>
        <button class="notification-button" onclick="closeNotification()">
            <?php echo ($notification_type === 'success') ? 'Continue' : 'Okay'; ?>
        </button>
    </div>
</div>

<script>
    function closeNotification() {
        const overlay = document.getElementById('notificationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            <?php if ($redirect_url): ?>
                window.location.href = '<?php echo $redirect_url; ?>';
            <?php endif; ?>
        }, 300);
    }

    // Auto-redirect after 2 seconds
    setTimeout(() => {
        closeNotification();
    }, <?php echo $redirect_delay; ?>);
</script>
<?php endif; ?>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const subjectSelect = document.getElementById("subject");
    const examTypeSelect = document.getElementById("exam_type");
    const idNumberSelect = document.getElementById("id_number");
    const fullMarksInput = document.getElementById("full_marks");
    const obtainedInput = document.getElementById("obtained_marks");
    const percentageInput = document.getElementById("percentage");
    const gradeInput = document.getElementById("grade");
    const marksForm = document.getElementById("marksForm");
    const submitBtn = marksForm.querySelector('button[type="submit"]');
    const marksErrorDiv = document.getElementById("marks_error");

    // Validate obtained marks in real-time
    function validateObtainedMarks() {
        const fullMarks = parseFloat(fullMarksInput.value) || 0;
        const obtained = parseFloat(obtainedInput.value) || 0;
        let isValid = true;
        let errorMsg = '';

        if (obtained < 0) {
            isValid = false;
            errorMsg = 'Obtained marks cannot be negative.';
        } else if (fullMarks > 0 && obtained > fullMarks) {
            isValid = false;
            errorMsg = `Obtained marks cannot exceed full marks (${fullMarks}).`;
        }

        if (isValid) {
            obtainedInput.classList.remove('invalid');
            marksErrorDiv.classList.remove('show');
            marksErrorDiv.textContent = '';
        } else {
            obtainedInput.classList.add('invalid');
            marksErrorDiv.classList.add('show');
            marksErrorDiv.textContent = errorMsg;
        }

        updateSubmitButton();
        return isValid;
    }

    // Update submit button state
    function updateSubmitButton() {
        const hasError = marksErrorDiv.classList.contains('show');
        const studentSelected = document.getElementById("student_id").value;
        const examTypeSelected = examTypeSelect.value;
        
        if (hasError || !studentSelected || !examTypeSelected) {
            submitBtn.disabled = true;
        } else {
            submitBtn.disabled = false;
        }
    }

    // Fetch exam types for subject
    subjectSelect.addEventListener("change", function() {
        const subjectId = this.value;
        examTypeSelect.innerHTML = '<option value="">Select Exam Type</option>';
        idNumberSelect.innerHTML = '<option value="">Select Student</option>';
        fullMarksInput.value = "";
        percentageInput.value = "";
        gradeInput.value = "";
        obtainedInput.value = "";
        marksErrorDiv.classList.remove('show');
        obtainedInput.classList.remove('invalid');

        if (subjectId) {
            fetch(`fetch_exam_types.php?sub_id=${subjectId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error fetching exam types:', data.error);
                        return;
                    }
                    data.forEach(exam => {
                        const opt = document.createElement("option");
                        opt.value = exam.exam_type_id;
                        opt.textContent = exam.exam_name;
                        opt.dataset.fullMarks = exam.full_marks;
                        examTypeSelect.appendChild(opt);
                    });
                })
                .catch(err => console.error('Error fetching exam types:', err));

            // Fetch students for this subject
            const examTypeId = examTypeSelect.value;
            let body = 'subject_id=' + encodeURIComponent(subjectId);
            if (examTypeId) {
                body += '&exam_type_id=' + encodeURIComponent(examTypeId);
            }
            
            fetch(`fetch_students.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body
            })
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error fetching students:', data.error);
                        return;
                    }
                    data.forEach(std => {
                        const opt = document.createElement("option");
                        opt.value = std.id_number;
                        opt.textContent = `${std.id_number} - ${std.full_name}`;
                        opt.dataset.studentId = std.user_id;
                        opt.dataset.courseName = std.course_name;
                        opt.dataset.semId = std.sem_id;
                        opt.dataset.courseId = std.course_id;
                        idNumberSelect.appendChild(opt);
                    });
                })
                .catch(err => console.error('Error fetching students:', err));
        }
        updateSubmitButton();
    });

    // Reload students when exam type changes
    examTypeSelect.addEventListener('change', function() {
        const subjectId = subjectSelect.value;
        if (subjectId) {
            const examTypeId = this.value;
            let body = 'subject_id=' + encodeURIComponent(subjectId);
            if (examTypeId) {
                body += '&exam_type_id=' + encodeURIComponent(examTypeId);
            }
            
            fetch(`fetch_students.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body
            })
                .then(res => res.json())
                .then(data => {
                    idNumberSelect.innerHTML = '<option value="">Select Student</option>';
                    if (data.error) {
                        console.error('Error fetching students:', data.error);
                        return;
                    }
                    data.forEach(std => {
                        const opt = document.createElement("option");
                        opt.value = std.id_number;
                        opt.textContent = `${std.id_number} - ${std.full_name}`;
                        opt.dataset.studentId = std.user_id;
                        opt.dataset.courseName = std.course_name;
                        opt.dataset.semId = std.sem_id;
                        opt.dataset.courseId = std.course_id;
                        idNumberSelect.appendChild(opt);
                    });
                })
                .catch(err => console.error('Error fetching students:', err));
        }
        percentageInput.value = "";
        gradeInput.value = "";
        obtainedInput.value = "";
        marksErrorDiv.classList.remove('show');
        obtainedInput.classList.remove('invalid');
        updateSubmitButton();
    });

    // Auto fill full marks when exam type selected
    examTypeSelect.addEventListener("change", function() {
        const selected = this.options[this.selectedIndex];
        fullMarksInput.value = selected.dataset.fullMarks || '';
        percentageInput.value = "";
        gradeInput.value = "";
        obtainedInput.value = "";
        marksErrorDiv.classList.remove('show');
        obtainedInput.classList.remove('invalid');
    });

    // Auto fill student details
    idNumberSelect.addEventListener("change", function() {
        const selected = this.options[this.selectedIndex];
        document.getElementById("student_id").value = selected.dataset.studentId || '';
        document.getElementById("course_name").value = selected.dataset.courseName || '';
        document.getElementById("semester_name").value = selected.dataset.semId || '';
        document.getElementById("course_id").value = selected.dataset.courseId || '';
        document.getElementById("sem_id").value = selected.dataset.semId || '';
        percentageInput.value = "";
        gradeInput.value = "";
        obtainedInput.value = "";
        marksErrorDiv.classList.remove('show');
        obtainedInput.classList.remove('invalid');
        updateSubmitButton();
    });

    // Real-time validation for obtained marks
    obtainedInput.addEventListener("input", function() {
        validateObtainedMarks();
        calculateGrade();
    });

    obtainedInput.addEventListener("blur", function() {
        validateObtainedMarks();
    });

    // Calculate grade when marks are valid
    function calculateGrade() {
        const obtained = parseFloat(obtainedInput.value) || 0;
        const full = parseFloat(fullMarksInput.value) || 0;
        
        if (full > 0 && obtained >= 0 && obtained <= full) {
            const percent = (obtained / full) * 100;
            percentageInput.value = percent.toFixed(2);
            gradeInput.value = getGrade(percent);
        } else {
            percentageInput.value = "";
            gradeInput.value = "";
        }
    }

    function getGrade(percentage) {
        if (percentage >= 90) return "A+";
        if (percentage >= 80) return "A";
        if (percentage >= 70) return "B+";
        if (percentage >= 60) return "B";
        if (percentage >= 50) return "C+";
        if (percentage >= 40) return "C";
        return "F";
    }

    // Form submission validation
    marksForm.addEventListener("submit", function(e) {
        if (!validateObtainedMarks()) {
            e.preventDefault();
            return false;
        }
    });

    // Initialize button state
    updateSubmitButton();
});
</script>
</body>
</html>
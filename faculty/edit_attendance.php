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

// Get attendance ID from URL
$attendance_id = isset($_GET['attendance_id']) ? intval($_GET['attendance_id']) : 0;

if ($attendance_id <= 0) {
    header("Location: view_attendance.php");
    exit();
}

// Fetch attendance record
$fetchQuery = "
    SELECT 
        a.attendance_id,
        a.user_id,
        a.sub_id,
        a.course_id,
        a.sem_id,
        a.attendance_date,
        a.status,
        a.remarks,
        a.attendance_done_by,
        u.id_number,
        u.full_name,
        s.sub_name,
        c.course_name,
        sem.sem_name
    FROM attendance a
    JOIN users u ON a.user_id = u.user_id
    JOIN subject s ON a.sub_id = s.sub_id
    JOIN course c ON a.course_id = c.course_id
    JOIN semester sem ON a.sem_id = sem.sem_id
    WHERE a.attendance_id = ? AND a.attendance_done_by = ?
";

$fetchStmt = $conn->prepare($fetchQuery);
if (!$fetchStmt) {
    die("Database error: " . $conn->error);
}

$fetchStmt->bind_param("ii", $attendance_id, $staff_id);
$fetchStmt->execute();
$result = $fetchStmt->get_result();

if ($result->num_rows === 0) {
    $fetchStmt->close();
    header("Location: view_attendance.php");
    exit();
}

$attendance = $result->fetch_assoc();
$fetchStmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_attendance'])) {
    $new_status = $_POST['status'] ?? $attendance['status'];
    $new_remarks = !empty($_POST['remarks']) ? trim($_POST['remarks']) : null;

    // Validate status
    $valid_statuses = ['Present', 'Absent', 'Late'];
    if (!in_array($new_status, $valid_statuses)) {
        $message = "Invalid attendance status selected.";
        $notification_type = "error";
    } else {
        // Update attendance record
        $updateStmt = $conn->prepare("
            UPDATE attendance 
            SET status = ?, remarks = ?, created_at = NOW() 
            WHERE attendance_id = ? AND attendance_done_by = ?
        ");

        if (!$updateStmt) {
            $message = "Database error: " . $conn->error;
            $notification_type = "error";
        } else {
            $updateStmt->bind_param("ssii", $new_status, $new_remarks, $attendance_id, $staff_id);

            if ($updateStmt->execute()) {
                if ($updateStmt->affected_rows > 0) {
                    $message = "Attendance record updated successfully!";
                    $notification_type = "success";
                    $redirect_url = "view_attendance.php";
                    
                    // Update local data to show changes
                    $attendance['status'] = $new_status;
                    $attendance['remarks'] = $new_remarks;
                } else {
                    $message = "No changes were made to the attendance record.";
                    $notification_type = "warning";
                }
            } else {
                $message = "Error updating record: " . $updateStmt->error;
                $notification_type = "error";
            }
            $updateStmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Edit Attendance</title>
<link rel="stylesheet" href="../css/all.min.css" />
<link rel="stylesheet" href="../css/admin_menu.css" />
<link rel="stylesheet" href="../css/edit_attendance.css" />
<link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-edit"></i>
            Edit Attendance Record
        </h1>
        <a href="view_attendance.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Records
        </a>
    </div>

    <!-- Form Container -->
    <div class="form-container">
        
        <!-- Student Information Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-user"></i>
                Student Information
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Student ID</div>
                    <div class="info-value"><?= htmlspecialchars($attendance['id_number']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Student Name</div>
                    <div class="info-value"><?= htmlspecialchars($attendance['full_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Attendance Date</div>
                    <div class="info-value"><?= date('d F Y', strtotime($attendance['attendance_date'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Course Information Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-book"></i>
                Course Information
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Course</div>
                    <div class="info-value"><?= htmlspecialchars($attendance['course_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Semester</div>
                    <div class="info-value"><?= htmlspecialchars($attendance['sem_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Subject</div>
                    <div class="info-value"><?= htmlspecialchars($attendance['sub_name']) ?></div>
                </div>
            </div>
        </div>

        <!-- Edit Attendance Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-pen"></i>
                Edit Attendance Details
            </div>

            <form method="POST" id="editForm">
                <!-- Attendance Status -->
                <div class="form-group">
                    <label>
                        Attendance Status <span class="required">*</span>
                    </label>
                    <div class="radio-group">
                        <div class="radio-option present">
                            <input type="radio" name="status" value="Present" 
                                   id="status_present" <?= $attendance['status'] === 'Present' ? 'checked' : '' ?>>
                            <label for="status_present">Present</label>
                        </div>
                        <div class="radio-option absent">
                            <input type="radio" name="status" value="Absent" 
                                   id="status_absent" <?= $attendance['status'] === 'Absent' ? 'checked' : '' ?>>
                            <label for="status_absent">Absent</label>
                        </div>
                        <div class="radio-option late">
                            <input type="radio" name="status" value="Late" 
                                   id="status_late" <?= $attendance['status'] === 'Late' ? 'checked' : '' ?>>
                            <label for="status_late">Late</label>
                        </div>
                    </div>
                </div>

                <!-- Remarks -->
                <div class="form-group">
                    <label for="remarks">Remarks (Optional)</label>
                    <textarea name="remarks" id="remarks" 
                              placeholder="Add any additional notes or comments about this attendance record..."><?= htmlspecialchars($attendance['remarks'] ?? '') ?></textarea>
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i> You can add notes like reason for absence, late arrival time, etc.
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="view_attendance.php" class="btn-secondary" style="text-decoration: none">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="button" class="btn-primary" id="updateBtn">
                        <i class="fas fa-save"></i> Update Attendance
                    </button>
                </div>
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
        <div class="confirm-title">Confirm Update</div>
        <div class="confirm-message">
            Are you sure you want to update this attendance record for <strong><?= htmlspecialchars($attendance['full_name']) ?></strong>?
        </div>
        <div class="confirm-buttons">
            <button class="confirm-yes-btn" id="confirmYesBtn">
                <i class="fas fa-check"></i> Yes, Update
            </button>
            <button class="confirm-no-btn" id="confirmNoBtn">
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
    const updateBtn = document.getElementById("updateBtn");
    const confirmOverlay = document.getElementById("confirmOverlay");
    const confirmYesBtn = document.getElementById("confirmYesBtn");
    const confirmNoBtn = document.getElementById("confirmNoBtn");
    const editForm = document.getElementById("editForm");

    // Update button click - Show confirmation modal
    updateBtn.addEventListener("click", (e) => {
        e.preventDefault();
        confirmOverlay.classList.add('active');
    });

    // Confirm Yes button - Submit form
    confirmYesBtn.addEventListener("click", () => {
        confirmOverlay.classList.remove('active');
        
        // Create a hidden input for update_attendance
        const updateInput = document.createElement('input');
        updateInput.type = 'hidden';
        updateInput.name = 'update_attendance';
        updateInput.value = '1';
        editForm.appendChild(updateInput);
        
        // Submit the form
        editForm.submit();
    });

    // Confirm No button - Close modal
    confirmNoBtn.addEventListener("click", () => {
        confirmOverlay.classList.remove('active');
    });

    // Close modal when clicking outside
    confirmOverlay.addEventListener("click", (e) => {
        if (e.target === confirmOverlay) {
            confirmOverlay.classList.remove('active');
        }
    });

    // Prevent accidental navigation
    let formChanged = false;
    const formInputs = editForm.querySelectorAll('input, textarea');
    
    formInputs.forEach(input => {
        input.addEventListener('change', () => {
            formChanged = true;
        });
    });

    window.addEventListener('beforeunload', (e) => {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Don't show warning after form submission
    editForm.addEventListener('submit', () => {
        formChanged = false;
    });
});
</script>
</body>
</html>
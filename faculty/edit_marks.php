<?php
session_start();
include("connect.php");
include ("auth_check.php");
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$staff_id = $_SESSION['uid'];
$message = '';
$error = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view_marks.php?msg=error");
    exit();
}

$marks_id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT m.*, u.full_name, u.id_number, s.sub_name
    FROM marks m
    JOIN users u ON m.user_id = u.user_id
    JOIN subject s ON m.sub_id = s.sub_id
    WHERE m.marks_id = ? AND m.entered_by_staff = ?
");
$stmt->bind_param("ii", $marks_id, $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: view_marks.php?msg=unauthorized");
    exit();
}

$marks_data = $result->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $obtained_marks = floatval($_POST['obtained_marks']);
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    $full_marks = floatval($marks_data['full_marks']); 
    
    if ($obtained_marks < 0) {
        $error = "Obtained marks cannot be negative.";
    } elseif ($obtained_marks > $full_marks) {
        $error = "Obtained marks cannot exceed full marks (" . $full_marks . ").";
    } else {
        $percentage = round(($obtained_marks / $full_marks) * 100, 2);
        
        $grade = '';
        if ($percentage >= 90) $grade = "A+";
        elseif ($percentage >= 80) $grade = "A";
        elseif ($percentage >= 70) $grade = "B+";
        elseif ($percentage >= 60) $grade = "B";
        elseif ($percentage >= 50) $grade = "C+";
        elseif ($percentage >= 40) $grade = "C";
        else $grade = "F";
        
        $updateStmt = $conn->prepare("
            UPDATE marks 
            SET obtained_marks = ?, percentage = ?, grade = ?, remarks = ?
            WHERE marks_id = ? AND entered_by_staff = ?
        ");
        $updateStmt->bind_param("ddssii", $obtained_marks, $percentage, $grade, $remarks, $marks_id, $staff_id);
        
        if ($updateStmt->execute()) {
            $stmt = $conn->prepare("
                SELECT m.*, u.full_name, u.id_number, s.sub_name
                FROM marks m
                JOIN users u ON m.user_id = u.user_id
                JOIN subject s ON m.sub_id = s.sub_id
                WHERE m.marks_id = ? AND m.entered_by_staff = ?
            ");
            $stmt->bind_param("ii", $marks_id, $staff_id);
            $stmt->execute();
            $marks_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $message = "Marks updated successfully!";
        } else {
            $error = "Failed to update marks: " . $updateStmt->error;
        }
        $updateStmt->close();
    }
}

$showToast = false;
$toastMessage = "";
$toastStatus = "";

if (!empty($message)) {
    $showToast = true;
    $toastMessage = $message;
    $toastStatus = "success";
} elseif (!empty($error)) {
    $showToast = true;
    $toastMessage = $error;
    $toastStatus = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Edit Marks</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/edit_marks.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" />
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
  <a href="view_marks.php" class="back-btn">
    <i class="fas fa-arrow-left"></i> Back to View Marks
  </a>
  
  <h2>Edit Marks</h2>

  <div class="student-info">
    <h3>Student & Subject Information</h3>
    <div class="info-grid">
      <div class="info-item">
        <div class="info-label">Student Name:</div>
        <div class="info-value"><?= htmlspecialchars($marks_data['full_name']) ?></div>
      </div>
      <div class="info-item">
        <div class="info-label">Student ID:</div>
        <div class="info-value"><?= htmlspecialchars($marks_data['id_number']) ?></div>
      </div>
      <div class="info-item">
        <div class="info-label">Subject:</div>
        <div class="info-value"><?= htmlspecialchars($marks_data['sub_name']) ?></div>
      </div>
      <div class="info-item">
        <div class="info-label">Full Marks:</div>
        <div class="info-value"><?= htmlspecialchars($marks_data['full_marks']) ?></div>
      </div>
      <div class="info-item">
        <div class="info-label">Current Grade:</div>
        <div class="info-value grade-<?= str_replace('+', '-plus', $marks_data['grade']) ?>">
          <?= htmlspecialchars($marks_data['grade']) ?>
        </div>
      </div>
      <div class="info-item">
        <div class="info-label">Current Percentage:</div>
        <div class="info-value"><?= htmlspecialchars($marks_data['percentage']) ?>%</div>
      </div>
    </div>
  </div>
  <form method="POST" id="editMarksForm">
    <label>Full Marks:</label>
    <input type="number" value="<?= htmlspecialchars($marks_data['full_marks']) ?>" readonly />

    <label>Obtained Marks: <span style="color: red;">*</span></label>
    <input type="number" 
           name="obtained_marks" 
           id="obtained_marks" 
           value="<?= htmlspecialchars($marks_data['obtained_marks']) ?>" 
           min="0" 
           max="<?= htmlspecialchars($marks_data['full_marks']) ?>"
           step="0.01"
           required />

    <div id="grade_preview" class="grade-preview">
      Current: <?= htmlspecialchars($marks_data['percentage']) ?>% - Grade: <?= htmlspecialchars($marks_data['grade']) ?>
    </div>

    <label>Remarks:</label>
    <textarea name="remarks" id="remarks" rows="3" placeholder="Enter any remarks (optional)"><?= htmlspecialchars($marks_data['remarks']) ?></textarea>

    <div style="display: flex; gap: 10px; margin-top: 20px;">
      <button type="submit" style="background: #28a745;">
        <i class="fas fa-save"></i> Update Marks
      </button>
      <a href="view_marks.php" class="back-btn" style="margin: 0; display: inline-block; text-align: center; padding: 12px 20px;">
        <i class="fas fa-times"></i> Cancel
      </a>
    </div>
  </form>
</div>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<div id="toast"></div>

<!-- Confirmation & Notification Scripts -->
<script>
/* ================== Confirmation Overlay ================== */
function showConfirmation(message, onConfirm) {
    let overlay = document.createElement('div');
    overlay.className = 'notification-overlay active';
    overlay.innerHTML = `
        <div class="notification-modal confirm">
            <div class="notification-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="notification-title">Confirm Action</div>
            <div class="notification-message">${message}</div>
            <div class="notification-buttons">
                <button class="notification-button notification-button-cancel">Cancel</button>
                <button class="notification-button notification-button-confirm">Yes</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    overlay.querySelector('.notification-button-cancel').onclick = () => {
        overlay.remove();
    };

    overlay.querySelector('.notification-button-confirm').onclick = () => {
        overlay.remove();
        onConfirm();
    };
}

/* Example usage:
showConfirmation("Are you sure you want to delete?", function() {
    window.location.href = "delete.php?id=123";
});
*/

/* ================== Notification Overlay ================== */
function showNotification(message, type = 'success', autoClose = true) {
    let overlay = document.createElement('div');
    overlay.className = 'notification-overlay active';
    overlay.innerHTML = `
        <div class="notification-modal ${type}">
            <div class="notification-icon">
                ${type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>'}
            </div>
            <div class="notification-title">${type === 'success' ? 'Success!' : 'Error'}</div>
            <div class="notification-message">${message}</div>
            <div class="notification-progress">
                <div class="notification-progress-bar"></div>
            </div>
            <button class="notification-button">Okay</button>
        </div>
    `;
    document.body.appendChild(overlay);

    const modal = overlay.querySelector('.notification-modal');
    const progressBar = overlay.querySelector('.notification-progress-bar');

    overlay.querySelector('.notification-button').onclick = () => {
        closeNotification();
    };

    function closeNotification() {
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => overlay.remove(), 300);
    }

    if (autoClose) {
        let width = 0;
        const interval = setInterval(() => {
            width += 1;
            progressBar.style.width = width + '%';
            if (width >= 100) {
                clearInterval(interval);
                closeNotification();
            }
        }, 20);
    }
}

/* Example usage:
showNotification("Marks updated successfully!", "success");
showNotification("Failed to update marks.", "error");
*/
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("editMarksForm");
    const confirmOverlay = document.getElementById("confirmToast");
    const btnYes = document.getElementById("confirmYes");
    const btnNo = document.getElementById("confirmNo");

    // Intercept form submit
    form.addEventListener("submit", function(e) {
        e.preventDefault(); // Stop immediate submit
        confirmOverlay.style.display = "flex"; // Show confirmation overlay
    });

    // On confirmation
    btnYes.addEventListener("click", function() {
        confirmOverlay.style.display = "none";
        form.submit(); // Actually submit the form
    });

    // On cancellation
    btnNo.addEventListener("click", function() {
        confirmOverlay.style.display = "none"; // Hide overlay
    });

    // Show toast notification if PHP sets a message
    <?php if ($showToast): ?>
        showNotification("<?= addslashes($toastMessage) ?>", "<?= $toastStatus ?>");
    <?php endif; ?>
});

/* ================== Notification Overlay ================== */
function showNotification(message, type = 'success', autoClose = true) {
    let overlay = document.createElement('div');
    overlay.className = 'notification-overlay active';
    overlay.innerHTML = `
        <div class="notification-modal ${type}">
            <div class="notification-icon">
                ${type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>'}
            </div>
            <div class="notification-title">${type === 'success' ? 'Success!' : 'Error'}</div>
            <div class="notification-message">${message}</div>
            <div class="notification-progress">
                <div class="notification-progress-bar"></div>
            </div>
            <button class="notification-button">Okay</button>
        </div>
    `;
    document.body.appendChild(overlay);

    const modal = overlay.querySelector('.notification-modal');
    const progressBar = overlay.querySelector('.notification-progress-bar');

    overlay.querySelector('.notification-button').onclick = () => {
        overlay.remove();
    };

    if (autoClose) {
        let width = 0;
        const interval = setInterval(() => {
            width += 1;
            progressBar.style.width = width + '%';
            if (width >= 100) {
                clearInterval(interval);
                overlay.remove();
            }
        }, 20);
    }
}
</script>
<div id="confirmToast" class="confirm-toast">
  <div class="confirm-content">
    <p>Are you sure you want to update these marks?</p>
    <div class="confirm-buttons">
      <button id="confirmYes" class="btn-yes">Yes</button>
      <button id="confirmNo" class="btn-no">No</button>
    </div>
  </div>
</div>

</body>
</html>
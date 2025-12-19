<?php
session_start();
include("connect.php");
include("auth_check.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = intval($_SESSION['uid']);

// Verify user is a student
$stmt = $conn->prepare("SELECT role_id, course_id, sem_id, full_name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData || $userData['role_id'] != 2) {
    die("Access denied. Students only.");
}

$student_course_id = $userData['course_id'];
$student_sem_id = $userData['sem_id'];
$student_name = $userData['full_name'];

// Get coordinator
$stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE is_coordinator = 1 AND coordinator_for = ? AND role_id = 3");
$stmt->bind_param("i", $student_course_id);
$stmt->execute();
$coordinator = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$coordinator) {
    $coordinator_message = "No coordinator assigned to your course yet.";
}

// Notification
$notification = null;
$notification_type = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    $errors = [];

    if (empty($leave_type)) $errors[] = "Please select leave type.";
    if (empty($start_date) || empty($end_date)) $errors[] = "Provide both start and end dates.";
    if (strtotime($end_date) < strtotime($start_date)) $errors[] = "End date cannot be before start date.";
    if (empty($reason) || strlen($reason) < 10) $errors[] = "Reason must be at least 10 characters.";

    if (empty($errors)) {
        $coordinator_id = $coordinator ? $coordinator['user_id'] : null;
        $stmt = $conn->prepare("INSERT INTO student_leave_requests 
            (student_id, course_id, sem_id, leave_type, start_date, end_date, reason, coordinator_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissssi", $student_id, $student_course_id, $student_sem_id, $leave_type, $start_date, $end_date, $reason, $coordinator_id);
        if ($stmt->execute()) {
            $notification = "Leave request submitted successfully!";
            $notification_type = "success";
        } else {
            $notification = "Error submitting leave request: " . $stmt->error;
            $notification_type = "error";
        }
        $stmt->close();
    } else {
        $notification = implode("<br>", $errors);
        $notification_type = "error";
    }
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT status FROM student_leave_requests WHERE leave_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $delete_id, $student_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        if ($row['status'] === 'Pending') {
            $stmt = $conn->prepare("DELETE FROM student_leave_requests WHERE leave_id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $notification = "Leave request cancelled successfully!";
                $notification_type = "success";
            } else {
                $notification = "Error cancelling request.";
                $notification_type = "error";
            }
            $stmt->close();
        } else {
            $notification = "Cannot delete processed request.";
            $notification_type = "error";
        }
    } else {
        $notification = "Request not found.";
        $notification_type = "error";
    }
}

// Fetch leave requests
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';
$where = "lr.student_id = $student_id";
if ($status_filter !== 'All') {
    $status_safe = $conn->real_escape_string($status_filter);
    $where .= " AND lr.status = '$status_safe'";  // Add lr. prefix
}

// Pagination
$records_per_page = 10;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $records_per_page;

// Total records
$totalRecords = $conn->query("SELECT COUNT(*) AS total FROM student_leave_requests lr WHERE $where")->fetch_assoc()['total'];
$total_pages = ceil($totalRecords / $records_per_page);

// Fetch leave requests
$query = "SELECT lr.*, u.full_name AS coordinator_name 
          FROM student_leave_requests lr
          LEFT JOIN users u ON lr.coordinator_id = u.user_id
          WHERE $where
          ORDER BY lr.requested_at DESC
          LIMIT $records_per_page OFFSET $offset";
$result = $conn->query($query);
if (!$result) die("Query error: " . $conn->error);

// Statistics
$stats = $conn->query("SELECT 
    COUNT(*) AS total_requests,
    SUM(CASE WHEN lr.status='Pending' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN lr.status='Approved' THEN 1 ELSE 0 END) AS approved_count,
    SUM(CASE WHEN lr.status='Rejected' THEN 1 ELSE 0 END) AS rejected_count
    FROM student_leave_requests lr WHERE lr.student_id = $student_id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Leave Request</title>
    <link rel="stylesheet" href="../css/all.min.css" />
    <link rel="stylesheet" href="../css/admin_menu.css" />
    <link rel="stylesheet" href="../css/student_leave_requests.css" />
    <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>
<body>

    <?php include("header.php"); ?>
<div class="page-wrapper">
    <?php include("menu.php"); ?>
<div class="main-content">
    <!-- <div class="page-header">
        <h1><i class="fas fa-file-alt"></i> Leave Request System</h1>
        <p>Submit and manage your leave requests</p>
    </div> -->

    <!-- Statistics -->
    <?php if ($stats['total_requests'] > 0): ?>
    <div class="stats-container">
        <div class="stat-card total">
            <div class="stat-label"><i class="fas fa-list"></i> Total Requests</div>
            <div class="stat-value"><?= $stats['total_requests'] ?></div>
        </div>
        <div class="stat-card pending">
            <div class="stat-label"><i class="fas fa-clock"></i> Pending</div>
            <div class="stat-value"><?= $stats['pending_count'] ?></div>
        </div>
        <div class="stat-card approved">
            <div class="stat-label"><i class="fas fa-check-circle"></i> Approved</div>
            <div class="stat-value"><?= $stats['approved_count'] ?></div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-label"><i class="fas fa-times-circle"></i> Rejected</div>
            <div class="stat-value"><?= $stats['rejected_count'] ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Leave Request Form -->
    <div class="form-container">
        <div class="form-title">
            <i class="fas fa-paper-plane"></i>
            Submit New Leave Request
        </div>

        <?php if (isset($coordinator)): ?>
        <div class="coordinator-info">
            <i class="fas fa-user-tie"></i>
            <strong>Your Coordinator:</strong> <?= htmlspecialchars($coordinator['full_name']) ?>
        </div>
        <?php elseif (isset($coordinator_message)): ?>
        <div class="coordinator-info" style="background: #fef3c7; border-color: #f59e0b;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Notice:</strong> <?= htmlspecialchars($coordinator_message) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label>Leave Type <span class="required">*</span></label>
                    <select name="leave_type" required>
                        <option value="">Select Leave Type</option>
                        <option value="Sick">Sick Leave</option>
                        <option value="Casual">Casual Leave</option>
                        <option value="Personal">Personal Leave</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Start Date <span class="required">*</span></label>
                    <input type="date" name="start_date" required min="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label>End Date <span class="required">*</span></label>
                    <input type="date" name="end_date" required min="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group full-width">
                    <label>Reason <span class="required">*</span></label>
                    <textarea name="reason" required placeholder="Please provide a detailed reason for your leave request (minimum 10 characters)..."></textarea>
                </div>
            </div>

            <button type="submit" name="submit_leave" class="submit-btn">
                <i class="fas fa-paper-plane"></i>
                Submit Leave Request
            </button>
<button type="button" class="view-btn" onclick="window.location.href='view_requests.php'">
    <i class="fas fa-eye"></i>
    View Leave Request
</button>

        </form>
    </div>

    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
        $query_params = ['status' => $status_filter];
        
        if ($current_page > 1) {
            $query_params['page'] = $current_page - 1;
            echo '<a href="?'.http_build_query($query_params).'">Previous</a>';
        } else {
            echo '<span class="disabled">Previous</span>';
        }
        
        for ($i = 1; $i <= $total_pages; $i++) {
            $query_params['page'] = $i;
            if ($i == $current_page) {
                echo '<span class="active">'.$i.'</span>';
            } else {
                echo '<a href="?'.http_build_query($query_params).'">'.$i.'</a>';
            }
        }
        
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

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<!-- Notification Modal -->
<?php if ($notification): ?>
<div class="notification-overlay" id="notificationOverlay">
    <div class="notification-modal <?= $notification_type ?>">
        <div class="notification-icon">
            <?php if ($notification_type === 'success'): ?>
                <i class="fas fa-check-circle"></i>
            <?php else: ?>
                <i class="fas fa-times-circle"></i>
            <?php endif; ?>
        </div>
        <div class="notification-title">
            <?= $notification_type === 'success' ? 'Success!' : 'Error' ?>
        </div>
        <div class="notification-message">
            <?= $notification ?>
        </div>
        <button class="notification-button" onclick="closeNotification()">Okay</button>
    </div>
</div>

<script>
    function closeNotification() {
        const overlay = document.getElementById('notificationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            window.location.href = 'student_leave_requests.php?status=<?= $status_filter ?>';
        }, 300);
    }

    setTimeout(() => {
        closeNotification();
    }, 3000);
</script>
<?php endif; ?>

<script>
    // Auto-update end date when start date changes
    document.querySelector('input[name="start_date"]').addEventListener('change', function() {
        const endDateInput = document.querySelector('input[name="end_date"]');
        endDateInput.min = this.value;
        if (endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = this.value;
        }
    });

    // Character counter for reason textarea
    const reasonTextarea = document.querySelector('textarea[name="reason"]');
    if (reasonTextarea) {
        const counter = document.createElement('div');
        counter.style.cssText = 'font-size: 12px; color: #666; margin-top: 5px; text-align: right;';
        reasonTextarea.parentElement.appendChild(counter);

        function updateCounter() {
            const length = reasonTextarea.value.length;
            counter.textContent = `${length} characters (minimum 10)`;
            if (length < 10) {
                counter.style.color = '#ef4444';
            } else {
                counter.style.color = '#10b981';
            }
        }

        reasonTextarea.addEventListener('input', updateCounter);
        updateCounter();
    }
</script>

</body>
</html>

<?php
$conn->close();
?>
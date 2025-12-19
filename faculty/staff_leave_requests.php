<?php
session_start();
include("connect.php");
include("auth_check.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$staff_id = $_SESSION['uid'];

// Verify user is non-faculty staff
$userQuery = "SELECT role_id, full_name FROM users WHERE user_id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$userResult = $stmt->get_result();
$userData = $userResult->fetch_assoc();
$stmt->close();

if (!$userData || $userData['role_id'] != 3) {
    die("Access denied. Faculty staff only.");
}

$staff_name = $userData['full_name'];

// Get admin for approvals (assuming role_id = 1 is admin)
$adminQuery = "SELECT user_id, full_name FROM users 
               WHERE role_id = 1 
               ORDER BY user_id ASC 
               LIMIT 1";
$adminResult = $conn->query($adminQuery);
$admin = $adminResult->fetch_assoc();

if (!$admin) {
    $admin_message = "No administrator found in the system.";
}

// Notification system
$notification = null;
$notification_type = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    
    // Validation
    $errors = [];
    
    if (empty($leave_type)) {
        $errors[] = "Please select leave type.";
    }
    
    if (empty($start_date) || empty($end_date)) {
        $errors[] = "Please provide both start and end dates.";
    }
    
    if (strtotime($end_date) < strtotime($start_date)) {
        $errors[] = "End date cannot be before start date.";
    }
    
    if (empty($reason) || strlen($reason) < 10) {
        $errors[] = "Please provide a detailed reason (at least 10 characters).";
    }
    
    if (empty($errors)) {
        $admin_id = $admin ? $admin['user_id'] : null;
        
        $insertQuery = "INSERT INTO staff_leave_requests 
                       (staff_id, leave_type, start_date, end_date, reason, admin_id) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("issssi", $staff_id, $leave_type, $start_date, $end_date, $reason, $admin_id);
        
        if ($insertStmt->execute()) {
            $notification = "Leave request submitted successfully!";
            $notification_type = "success";
        } else {
            $notification = "Error submitting leave request: " . $insertStmt->error;
            $notification_type = "error";
        }
        $insertStmt->close();
    } else {
        $notification = implode("<br>", $errors);
        $notification_type = "error";
    }
}

// Handle delete request
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Check if the request belongs to this staff and is still pending
    $checkQuery = "SELECT status FROM staff_leave_requests WHERE leave_id = ? AND staff_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $delete_id, $staff_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $row = $checkResult->fetch_assoc();
        if ($row['status'] === 'Pending') {
            $deleteStmt = $conn->prepare("DELETE FROM staff_leave_requests WHERE leave_id = ?");
            $deleteStmt->bind_param("i", $delete_id);
            if ($deleteStmt->execute()) {
                $notification = "Leave request cancelled successfully!";
                $notification_type = "success";
            } else {
                $notification = "Error cancelling request.";
                $notification_type = "error";
            }
            $deleteStmt->close();
        } else {
            $notification = "Cannot delete a request that has been processed.";
            $notification_type = "error";
        }
    } else {
        $notification = "Request not found.";
        $notification_type = "error";
    }
    $checkStmt->close();
}

// Fetch leave requests
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';

$whereConditions = ["staff_id = ?"];
$params = [$staff_id];
$paramTypes = "i";

if ($status_filter !== 'All') {
    $whereConditions[] = "status = ?";
    $params[] = $status_filter;
    $paramTypes .= "s";
}

$whereClause = implode(" AND ", $whereConditions);

// Pagination
$records_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get total records
$countQuery = "SELECT COUNT(*) as total FROM staff_leave_requests WHERE $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param($paramTypes, ...$params);
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$total_pages = ceil($totalRecords / $records_per_page);

// Fetch requests
$query = "SELECT lr.*, u.full_name as admin_name 
          FROM staff_leave_requests lr
          LEFT JOIN users u ON lr.admin_id = u.user_id
          WHERE $whereClause
          ORDER BY lr.requested_at DESC
          LIMIT ? OFFSET ?";

$params[] = $records_per_page;
$params[] = $offset;
$paramTypes .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Calculate statistics
$statsQuery = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM staff_leave_requests 
    WHERE staff_id = ?";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $staff_id);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();
$statsStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Leave Request</title>
    <link rel="stylesheet" href="../css/all.min.css" />
    <link rel="stylesheet" href="../css/admin_menu.css" />
    <link rel="stylesheet" href="../css/student_leave_requests.css" />
    <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
    <style>
.page-wrapper,
.main-content,
.form-container {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

body, html {
    overflow-x: hidden;
}

    </style>
</head>
<body>


    <?php include("header.php"); ?>
<div class="page-wrapper">
    <?php include("menu.php"); ?>
<div class="main-content">

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

        <?php if (isset($admin)): ?>
        <div class="coordinator-info">
            <i class="fas fa-user-shield"></i>
            <strong>Approving Authority:</strong> <?= htmlspecialchars($admin['full_name']) ?> (Administrator)
        </div>
        <?php elseif (isset($admin_message)): ?>
        <div class="coordinator-info" style="background: #fef3c7; border-color: #f59e0b;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Notice:</strong> <?= htmlspecialchars($admin_message) ?>
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
                        <option value="Annual">Annual Leave</option>
                        <option value="Emergency">Emergency Leave</option>
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
            window.location.href = 'staff_leave_requests.php?status=<?= $status_filter ?>';
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
$stmt->close();
$conn->close();
?>
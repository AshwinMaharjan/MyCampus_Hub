<?php
session_start();
include("connect.php");
include("auth_check.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$admin_id = intval($_SESSION['uid']);

// Verify user is a super admin (role_id = 1)
$stmt = $conn->prepare("SELECT role_id, full_name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData || $userData['role_id'] != 1) {
    die("Access denied. Super Admin only.");
}

$admin_name = $userData['full_name'];

// Notification
$notification = null;
$notification_type = "";

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $leave_id = intval($_POST['leave_id']);
    $action = $_POST['action'];
    $remarks = trim($_POST['remarks'] ?? '');
    
    if ($action === 'approve' || $action === 'reject') {
        $status = ($action === 'approve') ? 'Approved' : 'Rejected';
        
        $stmt = $conn->prepare("UPDATE staff_leave_requests SET status = ?, admin_id = ?, admin_remarks = ?, processed_at = NOW() WHERE leave_id = ?");
        $stmt->bind_param("sisi", $status, $admin_id, $remarks, $leave_id);
        
        if ($stmt->execute()) {
            $notification = "Leave request " . strtolower($status) . " successfully!";
            $notification_type = "success";
        } else {
            $notification = "Error updating leave request.";
            $notification_type = "error";
        }
        $stmt->close();
    }
}

// Filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';
$leave_type_filter = isset($_GET['leave_type']) ? $_GET['leave_type'] : 'All';
$staff_type_filter = isset($_GET['staff_type']) ? $_GET['staff_type'] : 'All';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$records_per_page = 10;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $records_per_page;

// Build WHERE clause
$where_conditions = ["1=1"];
$params = [];
$types = "";

if ($status_filter !== 'All') {
    $where_conditions[] = "slr.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($leave_type_filter !== 'All') {
    $where_conditions[] = "slr.leave_type = ?";
    $params[] = $leave_type_filter;
    $types .= "s";
}

if ($staff_type_filter !== 'All') {
    if ($staff_type_filter === 'Teaching') {
        $where_conditions[] = "u.role_id = 2";
    } elseif ($staff_type_filter === 'Non Teaching') {
        $where_conditions[] = "u.role_id = 3";
    }
}

if (!empty($search_query)) {
    $where_conditions[] = "(u.full_name LIKE ? OR slr.reason LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$where_clause = implode(" AND ", $where_conditions);

// Build main query
$main_query = "SELECT 
    slr.leave_id,
    slr.staff_id,
    u.full_name AS staff_name,
    u.email AS staff_email,
    u.role_id,
    CASE 
        WHEN u.role_id = 2 THEN 'Teaching'
        WHEN u.role_id = 3 THEN 'Non Teaching'
        ELSE 'Unknown'
    END AS staff_type,
    slr.leave_type,
    slr.start_date,
    slr.end_date,
    slr.reason,
    slr.status,
    slr.admin_id,
    slr.admin_remarks,
    slr.requested_at,
    slr.processed_at,
    admin.full_name AS processed_by
FROM staff_leave_requests slr
LEFT JOIN users u ON slr.staff_id = u.user_id
LEFT JOIN users admin ON slr.admin_id = admin.user_id
WHERE $where_clause";

// Count total records
$count_query = "SELECT COUNT(*) AS total FROM staff_leave_requests slr 
                LEFT JOIN users u ON slr.staff_id = u.user_id 
                WHERE $where_clause";

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $totalRecords = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $totalRecords = $conn->query($count_query)->fetch_assoc()['total'];
}

$total_pages = ceil($totalRecords / $records_per_page);

// Fetch leave requests with pagination
$query = "$main_query ORDER BY slr.requested_at DESC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Statistics
$stats_query = "SELECT 
    COUNT(*) AS total_requests,
SUM(CASE WHEN slr.status='Pending' THEN 1 ELSE 0 END) AS pending_count,
SUM(CASE WHEN slr.status='Approved' THEN 1 ELSE 0 END) AS approved_count,
SUM(CASE WHEN slr.status='Rejected' THEN 1 ELSE 0 END) AS rejected_count,
    SUM(CASE WHEN u.role_id = 2 THEN 1 ELSE 0 END) AS teaching_count,
    SUM(CASE WHEN u.role_id = 3 THEN 1 ELSE 0 END) AS non_teaching_count
FROM staff_leave_requests slr
LEFT JOIN users u ON slr.staff_id = u.user_id";

$stats = $conn->query($stats_query)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff Leave Requests - Super Admin</title>
    <link rel="stylesheet" href="../css/all.min.css" />
    <link rel="stylesheet" href="../css/admin_menu.css" />
    <link rel="stylesheet" href="../css/student_leave_requests.css" />
    <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
    <style>
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .approve-btn, .reject-btn, .view-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .approve-btn {
            background: #10b981;
            color: white;
        }
        
        .approve-btn:hover {
            background: #059669;
        }
        
        .reject-btn {
            background: #ef4444;
            color: white;
        }
        
        .reject-btn:hover {
            background: #dc2626;
        }
        
        .view-btn {
            background: #3b82f6;
            color: white;
        }
        
        .view-btn:hover {
            background: #2563eb;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            animation: fadeIn 0.3s;
        }
        
        .modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .close-btn:hover {
            background: #f3f4f6;
            color: #1f2937;
        }
        
        .detail-row {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #1f2937;
            padding: 8px;
            background: #f9fafb;
            border-radius: 4px;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .modal-actions button {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .filters-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #4b5563;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .filter-btn.clear {
            padding: 8px 16px;
            background: #fa2727ff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .filter-btn:hover {
            background: #2563eb;
        }
        
        .filter-btn.clear:hover {
            background: #d60101ff;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .staff-type-badge {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .staff-teaching {
            background: #dbeafe;
            color: #1e40af;
        }

        .staff-non-teaching {
            background: #fce7f3;
            color: #9f1239;
        }

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

        <h2 style="color: #1f2937; margin-bottom: 20px;">
            <i class="fas fa-users-cog"></i> Manage Staff Leave Requests
        </h2>

        <!-- Statistics -->
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

        <!-- Filters -->
        <div class="filters-container">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Staff Type</label>
                        <select name="staff_type">
                            <option value="All" <?= $staff_type_filter === 'All' ? 'selected' : '' ?>>All Staff</option>
                            <option value="Teaching" <?= $staff_type_filter === 'Teaching' ? 'selected' : '' ?>>Teaching Staff</option>
                            <option value="Non Teaching" <?= $staff_type_filter === 'Non Teaching' ? 'selected' : '' ?>>Non Teaching Staff</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="All" <?= $status_filter === 'All' ? 'selected' : '' ?>>All Status</option>
                            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Leave Type</label>
                        <select name="leave_type">
                            <option value="All" <?= $leave_type_filter === 'All' ? 'selected' : '' ?>>All Types</option>
                            <option value="Sick" <?= $leave_type_filter === 'Sick' ? 'selected' : '' ?>>Sick Leave</option>
                            <option value="Casual" <?= $leave_type_filter === 'Casual' ? 'selected' : '' ?>>Casual Leave</option>
                            <option value="Personal" <?= $leave_type_filter === 'Personal' ? 'selected' : '' ?>>Personal Leave</option>
                            <option value="Other" <?= $leave_type_filter === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Search Staff</label>
                        <input type="text" name="search" placeholder="Staff name or reason..." value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                    
                    <div class="filter-actions" style="display: flex; gap: 10px;">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="super_admin_leave_requests.php" class="filter-btn clear">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Leave Requests Table -->
        <div class="table-container">
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>Staff Name</th>
                        <th>Staff Type</th>
                        <th>Leave Type</th>
                        <th>Duration</th>
                        <th>Requested On</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['staff_name']) ?></td>
                                <td>
                                    <span class="staff-type-badge staff-<?= strtolower(str_replace(' ', '-', $row['staff_type'])) ?>">
                                        <?= $row['staff_type'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['leave_type']) ?></td>
                                <td>
                                    <?= date('M d, Y', strtotime($row['start_date'])) ?><br>
                                    <small>to</small><br>
                                    <?= date('M d, Y', strtotime($row['end_date'])) ?>
                                </td>
                                <td><?= date('M d, Y H:i', strtotime($row['requested_at'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($row['status']) ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="view-btn" onclick='viewRequest(<?= json_encode($row) ?>)'>
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <button class="approve-btn" onclick="openActionModal(<?= $row['leave_id'] ?>, 'approve')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="reject-btn" onclick="openActionModal(<?= $row['leave_id'] ?>, 'reject')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px;"></i><br>
                                No leave requests found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $query_params = [
                'status' => $status_filter,
                'leave_type' => $leave_type_filter,
                'staff_type' => $staff_type_filter,
                'search' => $search_query
            ];
            
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

<!-- View Details Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Leave Request Details</h3>
            <button class="close-btn" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div id="modalBody"></div>
    </div>
</div>

<!-- Action Modal (Approve/Reject) -->
<div class="modal-overlay" id="actionModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="actionModalTitle">Process Leave Request</h3>
            <button class="close-btn" onclick="closeModal('actionModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="leave_id" id="actionLeaveId">
            <input type="hidden" name="action" id="actionType">
            
            <div class="detail-row">
                <div class="detail-label">Remarks (Optional)</div>
                <textarea name="remarks" rows="4" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;" placeholder="Add any remarks or comments..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="closeModal('actionModal')" style="background: #e5e7eb; color: #1f2937;">
                    Cancel
                </button>
                <button type="submit" id="actionSubmitBtn" style="background: #10b981; color: white;">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>

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
            window.location.href = 'staff_leave_requests.php';
        }, 300);
    }

    setTimeout(() => {
        closeNotification();
    }, 3000);
</script>
<?php endif; ?>

<script>
    function viewRequest(data) {
        const modal = document.getElementById('viewModal');
        const modalBody = document.getElementById('modalBody');
        
        const startDate = new Date(data.start_date);
        const endDate = new Date(data.end_date);
        const daysDiff = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
        
        modalBody.innerHTML = `
            <div class="detail-row">
                <div class="detail-label">Request ID</div>
                <div class="detail-value">#${data.leave_id}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Staff Name</div>
                <div class="detail-value">${data.staff_name}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Email</div>
                <div class="detail-value">${data.staff_email || 'N/A'}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Staff Type</div>
                <div class="detail-value">
                    <span class="staff-type-badge staff-${data.staff_type.toLowerCase().replace(' ', '-')}">${data.staff_type}</span>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Leave Type</div>
                <div class="detail-value">${data.leave_type}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Duration</div>
                <div class="detail-value">
                    ${new Date(data.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                    to
                    ${new Date(data.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                    <br><strong>(${daysDiff} day${daysDiff > 1 ? 's' : ''})</strong>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reason</div>
                <div class="detail-value">${data.reason}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value">
                    <span class="status-badge status-${data.status.toLowerCase()}">${data.status}</span>
                </div>
            </div>
            ${data.admin_remarks ? `
            <div class="detail-row">
                <div class="detail-label">Admin Remarks</div>
                <div class="detail-value">${data.admin_remarks}</div>
            </div>
            ` : ''}
            ${data.processed_by ? `
            <div class="detail-row">
                <div class="detail-label">Processed By</div>
                <div class="detail-value">${data.processed_by}</div>
            </div>
            ` : ''}
            <div class="detail-row">
                <div class="detail-label">Requested On</div>
                <div class="detail-value">${new Date(data.requested_at).toLocaleString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</div>
            </div>
            ${data.processed_at ? `
            <div class="detail-row">
                <div class="detail-label">Processed On</div>
                <div class="detail-value">${new Date(data.processed_at).toLocaleString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</div>
            </div>
            ` : ''}
        `;
        
        modal.style.display = 'block';
    }
    
    function openActionModal(leaveId, action) {
        const modal = document.getElementById('actionModal');
        const title = document.getElementById('actionModalTitle');
        const submitBtn = document.getElementById('actionSubmitBtn');
        
        document.getElementById('actionLeaveId').value = leaveId;
        document.getElementById('actionType').value = action;
        
        if (action === 'approve') {
            title.textContent = 'Approve Leave Request';
            submitBtn.textContent = 'Approve Request';
            submitBtn.style.background = '#10b981';
        } else {
            title.textContent = 'Reject Leave Request';
            submitBtn.textContent = 'Reject Request';
            submitBtn.style.background = '#ef4444';
        }
        
        modal.style.display = 'block';
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    }
</script>

</body>
</html>

<?php
$conn->close();
?>
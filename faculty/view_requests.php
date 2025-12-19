<?php
session_start();
include("connect.php");
include("auth_check.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$staff_id = intval($_SESSION['uid']);

// Verify user is non-faculty staff
$stmt = $conn->prepare("SELECT role_id, full_name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData || $userData['role_id'] != 3) {
    die("Access denied. Non-faculty staff only.");
}

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';
$where = "lr.staff_id = $staff_id";
if ($status_filter !== 'All') {
    $status_safe = $conn->real_escape_string($status_filter);
    $where .= " AND lr.status = '$status_safe'";
}

// Pagination
$records_per_page = 10;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $records_per_page;

// Total records
$totalRecords = $conn->query("SELECT COUNT(*) AS total FROM staff_leave_requests lr WHERE $where")->fetch_assoc()['total'];
$total_pages = ceil($totalRecords / $records_per_page);

// Fetch leave requests
$query = "SELECT lr.*, u.full_name AS admin_name 
          FROM staff_leave_requests lr
          LEFT JOIN users u ON lr.admin_id = u.user_id
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
    FROM staff_leave_requests lr WHERE lr.staff_id = $staff_id")->fetch_assoc();

$cancelledLeaveId = null;

if (isset($_GET['delete_id']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $delete_id = intval($_GET['delete_id']);

    // Verify that it belongs to this staff and is pending
    $checkStmt = $conn->prepare("SELECT * FROM staff_leave_requests WHERE leave_id = ? AND staff_id = ? AND status='Pending'");
    $checkStmt->bind_param("ii", $delete_id, $staff_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $delStmt = $conn->prepare("DELETE FROM staff_leave_requests WHERE leave_id = ?");
        $delStmt->bind_param("i", $delete_id);
        if ($delStmt->execute()) {
            $cancelledLeaveId = $delete_id;
        }
        $delStmt->close();
    }
    $checkStmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Leave Requests</title>
    <link rel="stylesheet" href="../css/all.min.css" />
    <link rel="stylesheet" href="../css/admin_menu.css" />
    <link rel="stylesheet" href="../css/student_leave_requests.css" />
    <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
    <style>
        /* Submit New Request inline with filter */
        .filter-container form {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 15px;
            width: 100%;
        }
        .filter-group { display: flex; align-items: center; gap: 10px; }
        .submit-new-btn {
            padding: 10px 20px; background: #667eea; color: #fff;
            border-radius: 6px; text-decoration: none; font-weight: 600;
            display: inline-flex; align-items: center; gap: 5px; transition: 0.3s;
        }
        .submit-new-btn:hover { background: #764ba2; }
        .action-btn.delete-btn { color: #ef4444; text-decoration: none; font-weight: 600; display: inline-flex; gap: 5px; }
        .action-btn.delete-btn:hover { text-decoration: none; color: #dc2626; cursor: pointer; }
        /* Confirmation Modal */
        .confirmation-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index:1000; }
        .confirmation-overlay.active { display: flex; }
        .confirmation-modal { background:#fff; padding:30px; border-radius:10px; max-width:400px; width:90%; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.2); }
        .confirmation-modal h3 { margin-bottom:15px; }
        .confirmation-modal p { margin-bottom:25px; }
        .confirmation-modal .btn { padding:8px 20px; border:none; border-radius:6px; font-weight:600; cursor:pointer; margin:0 10px; }
        .btn-confirm { background:#ef4444; color:#fff; }
        .btn-confirm:hover { background:#dc2626; }
        .btn-cancel { background:#6b7280; color:#fff; }
        .btn-cancel:hover { background:#4b5563; }
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

        <!-- Filter -->
        <div class="filter-container">
            <form method="GET">
                <div class="filter-group">
                    <label>Filter by Status</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="All" <?= $status_filter === 'All' ? 'selected' : '' ?>>All Requests</option>
                        <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <a href="staff_leave_requests.php" class="submit-new-btn">
                    <i class="fas fa-plus"></i> Submit New Request
                </a>
            </form>
        </div>

        <!-- Leave Requests Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Duration</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Admin</th>
                        <th>Remarks</th>
                        <th>Requested On</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                            $start = new DateTime($row['start_date']);
                            $end = new DateTime($row['end_date']);
                            $interval = $start->diff($end);
                            $days = $interval->days + 1;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['leave_type']) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($row['start_date'])) ?> - <?= date('M d, Y', strtotime($row['end_date'])) ?></td>
                                <td><?= $days ?> day<?= $days > 1 ? 's' : '' ?></td>
                                <td style="max-width:200px;"><?= htmlspecialchars(substr($row['reason'],0,80)) ?><?= strlen($row['reason'])>80?'...':'' ?></td>
                                <td><span class="status-badge status-<?= strtolower($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                <td><?= $row['admin_name'] ? htmlspecialchars($row['admin_name']) : 'N/A' ?></td>
                                <td><?= $row['admin_remarks'] ? htmlspecialchars($row['admin_remarks']) : '-' ?></td>
                                <td><?= date('M d, Y', strtotime($row['requested_at'])) ?></td>
                                <td>
                                    <?php if ($row['status']==='Pending'): ?>
                                        <a href="javascript:void(0);" class="action-btn delete-btn" onclick="showCancelModal(<?= $row['leave_id'] ?>)">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#999;font-size:12px;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-data">
                                <i class="fas fa-inbox"></i><div>No leave requests found.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $query_params = ['status'=>$status_filter];
            if ($current_page>1){$query_params['page']=$current_page-1; echo '<a href="?'.http_build_query($query_params).'">Previous</a>';}
            else echo '<span class="disabled">Previous</span>';
            for($i=1;$i<=$total_pages;$i++){ $query_params['page']=$i;
                echo $i==$current_page?'<span class="active">'.$i.'</span>':'<a href="?'.http_build_query($query_params).'">'.$i.'</a>';
            }
            if($current_page<$total_pages){$query_params['page']=$current_page+1; echo '<a href="?'.http_build_query($query_params).'">Next</a>';}
            else echo '<span class="disabled">Next</span>';
            ?>
        </div>
        <?php endif; ?>

    </div>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<div class="confirmation-overlay" id="cancelModal">
    <div class="confirmation-modal" id="cancelModalContent">
        <h3 id="modalTitle">Cancel Leave Request?</h3>
        <p id="modalText">Are you sure you want to cancel this leave request?</p>
        <button class="btn btn-cancel" id="modalNoBtn">No</button>
        <button class="btn btn-confirm" id="modalYesBtn">Yes, Cancel</button>
    </div>
</div>

<script>
let cancelId = null;
function showCancelModal(leaveId){ cancelId=leaveId; document.getElementById('cancelModal').classList.add('active'); document.getElementById('modalTitle').textContent='Cancel Leave Request?'; document.getElementById('modalText').textContent='Are you sure you want to cancel this leave request?'; document.getElementById('modalNoBtn').style.display='inline-block'; document.getElementById('modalYesBtn').style.display='inline-block'; }
document.getElementById('modalNoBtn').addEventListener('click',()=>{document.getElementById('cancelModal').classList.remove('active');});
document.getElementById('modalYesBtn').addEventListener('click',()=>{if(cancelId){window.location.href="?delete_id="+cancelId+"&confirm=yes";}});

<?php if($cancelledLeaveId): ?>
document.addEventListener('DOMContentLoaded',()=>{
    const overlay=document.getElementById('cancelModal'); overlay.classList.add('active');
    document.getElementById('modalTitle').textContent='Leave Request Cancelled';
    document.getElementById('modalText').textContent='The leave request has been successfully cancelled.';
    document.getElementById('modalNoBtn').style.display='none';
    document.getElementById('modalYesBtn').textContent='OK';
    document.getElementById('modalYesBtn').onclick=function(){overlay.classList.remove('active'); window.location.href="<?= $_SERVER['PHP_SELF'] ?>";};
});
<?php endif; ?>
</script>

</body>
</html>

<?php $conn->close(); ?>

<?php
session_start();
include("connect.php");

$notification = $_SESSION['notification'] ?? '';
$notification_type = $_SESSION['notification_type'] ?? '';
unset($_SESSION['notification'], $_SESSION['notification_type']);

// Check if logged in and is super admin
if(!isset($_SESSION['uid'])){
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['uid'];

// Verify super admin (role_id = 1)
$stmt = $conn->prepare("SELECT role_id, full_name FROM users WHERE user_id = ?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if(!$user || $user['role_id'] != 1){
    header("Location: ../login.php");
    exit();
}

// Handle approval/rejection override
if($_SERVER["REQUEST_METHOD"]=="POST" && isset($_POST['update_status'])){
    $material_id = intval($_POST['material_id']);
    $new_status = $_POST['approval_status'];
    $remarks = trim($_POST['remarks']);

    $update_stmt = $conn->prepare("UPDATE study_material SET approval_status=?, approved_by=?, approval_date=NOW(), remarks=?, last_modified=NOW() WHERE material_id=?");

    if(!$update_stmt){
        $_SESSION['notification'] = "Error preparing statement: ".$conn->error;
        $_SESSION['notification_type'] = "error";
    } else {
        $update_stmt->bind_param("sisi",$new_status,$user_id,$remarks,$material_id);

        if($update_stmt->execute()){
            $_SESSION['notification'] = "Material status updated successfully!";
            $_SESSION['notification_type'] = "success";
        } else {
            $_SESSION['notification'] = "Error updating status: ".$update_stmt->error;
            $_SESSION['notification_type'] = "error";
        }

        $update_stmt->close();
    }

    header("Location: study_material.php");
    exit();
}

// Handle delete
if(isset($_GET['delete']) && is_numeric($_GET['delete'])){
    $material_id = intval($_GET['delete']);

    $check = $conn->prepare("SELECT file_path FROM study_material WHERE material_id=?");
    if(!$check){
        $_SESSION['notification'] = "Error preparing statement: ".$conn->error;
        $_SESSION['notification_type'] = "error";
    } else {
        $check->bind_param("i",$material_id);
        $check->execute();
        $result = $check->get_result();

        if($result->num_rows > 0){
            $material = $result->fetch_assoc();
            $file_to_delete = dirname(__DIR__)."/".$material['file_path'];
            if(file_exists($file_to_delete)) unlink($file_to_delete);

            $delete_stmt = $conn->prepare("DELETE FROM study_material WHERE material_id=?");
            if(!$delete_stmt){
                $_SESSION['notification'] = "Error preparing delete: ".$conn->error;
                $_SESSION['notification_type'] = "error";
            } else {
                $delete_stmt->bind_param("i",$material_id);
                if($delete_stmt->execute()){
                    $_SESSION['notification'] = "Material deleted successfully!";
                    $_SESSION['notification_type'] = "success";
                } else {
                    $_SESSION['notification'] = "Error deleting material: ".$delete_stmt->error;
                    $_SESSION['notification_type'] = "error";
                }
                $delete_stmt->close();
            }
        } else {
            $_SESSION['notification'] = "Material not found.";
            $_SESSION['notification_type'] = "error";
        }
        $check->close();
    }

    header("Location: study_material.php");
    exit();
}

// Filters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_subject = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$filter_uploader = isset($_GET['uploader']) ? $_GET['uploader'] : 'all';
$filter_course = isset($_GET['course']) ? intval($_GET['course']) : 0;
$filter_sem = isset($_GET['sem']) ? intval($_GET['sem']) : 0;

// Build query
$query = "
SELECT sm.*, s.sub_name, c.course_name, sem.sem_name,
       u.full_name as uploader_name, u.email as uploader_email, u.role_id as uploader_role,
       approver.full_name as approver_name
FROM study_material sm
JOIN subject s ON sm.subject_id = s.sub_id
JOIN course c ON sm.course_id = c.course_id
JOIN semester sem ON sm.sem_id = sem.sem_id
JOIN users u ON sm.user_id = u.user_id
LEFT JOIN users approver ON sm.approved_by = approver.user_id
WHERE 1=1
";

$params = [];
$types = "";

if($filter_status!='all'){
    $query.=" AND sm.approval_status=?";
    $params[]=$filter_status;
    $types.="s";
}

if($filter_subject>0){
    $query.=" AND sm.subject_id=?";
    $params[]=$filter_subject;
    $types.="i";
}

if($filter_course>0){
    $query.=" AND sm.course_id=?";
    $params[]=$filter_course;
    $types.="i";
}

if($filter_sem>0){
    $query.=" AND sm.sem_id=?";
    $params[]=$filter_sem;
    $types.="i";
}

if($filter_uploader=='faculty'){
    $query.=" AND u.role_id=3";
}elseif($filter_uploader=='student'){
    $query.=" AND u.role_id=2";
}

$query.=" ORDER BY sm.upload_date DESC";

$stmt = $conn->prepare($query);
if(count($params)>0){
    $stmt->bind_param($types,...$params);
}
$stmt->execute();
$materials_result = $stmt->get_result();

// Get courses, semesters, subjects for filters
$courses = $conn->query("SELECT * FROM course ORDER BY course_name");
$sems = $conn->query("SELECT * FROM semester ORDER BY sem_name");
$subjects = $conn->query("SELECT * FROM subject ORDER BY sub_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Study Materials - Admin</title>
<link rel="stylesheet" href="../css/all.min.css">
<link rel="stylesheet" href="../css/faculty_study_material.css">
<link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
<style>
    .page-wrapper, .main-content, .form-container { width: 100%; max-width: 100%; box-sizing: border-box; } 
    body, html { overflow-x: hidden; }
    
    /* Enhanced Notification Styles */
    .notification {
        position: fixed;
        top: 80px;
        right: 20px;
        padding: 16px 24px;
        border-radius: 8px;
        color: #fff;
        font-weight: 500;
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease-out;
        min-width: 300px;
        font-size: 15px;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    
    .notification.success { 
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border-left: 4px solid #1e7e34;
    }
    
    .notification.error { 
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        border-left: 4px solid #bd2130;
    }

    .notification .notification-icon {
        font-size: 20px;
    }

    .notification .close-btn {
        background: transparent;
        border: none;
        color: #fff;
        font-size: 20px;
        cursor: pointer;
        padding: 0;
        margin-left: auto;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .notification .close-btn:hover {
        background: rgba(255,255,255,0.2);
    }

    /* Enhanced Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out;
    }

    .modal-overlay.active {
        display: flex;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background: #fff;
        border-radius: 12px;
        padding: 0;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 2px solid #e9ecef;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px 12px 0 0;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 20px;
        font-weight: 600;
        color: white;
    }

    .modal-header h3 i {
        margin-right: 8px;
    }

    .modal-close {
        background: transparent;
        border: none;
        color: white;
        font-size: 28px;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .modal-close:hover {
        background: rgba(255,255,255,0.2);
    }
    .btn-clear {
    background: #dc3545;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 2px;
    transition: background 0.2s, transform 0.2s;
}

.btn-clear:hover {
    background: #c82333;
    transform: translateY(-1px);
}


    .modal-content > p {
        padding: 24px;
        margin: 0;
        font-size: 18px;
        color: black;
        line-height: 1.6;
        text-align: center;
    }

    .modal-content form {
        padding: 24px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #343a40;
        font-size: 14px;
    }

    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .modal-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        padding: 20px 24px;
        border-top: 2px solid #e9ecef;
        background: #f8f9fa;
        border-radius: 0 0 12px 12px;
    }

    .modal-actions button {
        padding: 10px 24px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #5568d3 0%, #63408a 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    /* Delete Modal Specific Styles */
    #deleteModal .modal-header {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    }
</style>
</head>
<body>
    <?php if($notification): ?>
    <div class="notification <?= $notification_type ?>">
        <span class="notification-icon">
            <i class="fas <?= $notification_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        </span>
        <span><?= htmlspecialchars($notification) ?></span>
        <button class="close-btn" onclick="this.parentElement.style.display='none';">&times;</button>
    </div>
    <script>
        setTimeout(function() {
            var notif = document.querySelector('.notification');
            if(notif) {
                notif.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(function() {
                    notif.style.display = 'none';
                }, 300);
            }
        }, 5000);
    </script>
    <?php endif; ?>

<?php include("header.php"); ?>
<div class="page-wrapper">
<?php include("menu.php"); ?>

<div class="main-container">
    <!-- Filters -->
    <div class="filter-section">
        <div class="section-header"><h2><i class="fas fa-filter"></i> Filter Materials</h2></div>
        <form method="GET" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label>Course</label>
                    <select name="course" onchange="this.form.submit()">
                        <option value="0">All Courses</option>
                        <?php while($c=$courses->fetch_assoc()): ?>
                        <option value="<?= $c['course_id'] ?>" <?= $filter_course==$c['course_id']?'selected':'' ?>><?= htmlspecialchars($c['course_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Semester</label>
                    <select name="sem" onchange="this.form.submit()">
                        <option value="0">All Semesters</option>
                        <?php while($s=$sems->fetch_assoc()): ?>
                        <option value="<?= $s['sem_id'] ?>" <?= $filter_sem==$s['sem_id']?'selected':'' ?>><?= htmlspecialchars($s['sem_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <select name="subject" onchange="this.form.submit()">
                        <option value="0">All Subjects</option>
                        <?php while($s=$subjects->fetch_assoc()): ?>
                        <option value="<?= $s['sub_id'] ?>" <?= $filter_subject==$s['sub_id']?'selected':'' ?>><?= htmlspecialchars($s['sub_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Uploader</label>
                    <select name="uploader" onchange="this.form.submit()">
                        <option value="all" <?= $filter_uploader=='all'?'selected':'' ?>>All</option>
                        <option value="faculty" <?= $filter_uploader=='faculty'?'selected':'' ?>>Faculty</option>
                        <option value="student" <?= $filter_uploader=='student'?'selected':'' ?>>Students</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="all" <?= $filter_status=='all'?'selected':'' ?>>All</option>
                        <option value="pending" <?= $filter_status=='pending'?'selected':'' ?>>Pending</option>
                        <option value="approved" <?= $filter_status=='approved'?'selected':'' ?>>Approved</option>
                        <option value="rejected" <?= $filter_status=='rejected'?'selected':'' ?>>Rejected</option>
                    </select>
                </div>
                <!-- Clear Filters button -->
    <div class="form-group" style="align-self: flex-end; margin-left: 12px;">
        <button type="button" class="btn-clear" onclick="window.location.href='study_material.php'">
        <i class="fas fa-times"></i> Clear Filters
        </button>
            </div>
        </form>
    </div>

    <!-- Materials Section -->
    <div class="materials-section">
        <div class="section-header">
            <h2><i class="fas fa-folder-open"></i> Study Materials</h2>
            <span class="material-count"><?= $materials_result->num_rows ?> materials</span>
        </div>

        <?php if($materials_result->num_rows>0): ?>
        <div class="materials-grid">
            <?php while($m=$materials_result->fetch_assoc()): ?>
            <div class="material-card status-<?= $m['approval_status'] ?>">
                <div class="material-header">
                    <div class="material-icon">
                        <?php
                        $icon_map = ['pdf'=>'fa-file-pdf','doc'=>'fa-file-word','docx'=>'fa-file-word','ppt'=>'fa-file-powerpoint','pptx'=>'fa-file-powerpoint','txt'=>'fa-file-alt','jpg'=>'fa-file-image','jpeg'=>'fa-file-image','png'=>'fa-file-image','zip'=>'fa-file-archive','rar'=>'fa-file-archive'];
                        $icon = $icon_map[$m['file_extension']]??'fa-file';
                        ?>
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                    <div class="material-type-badge <?= $m['material_type'] ?>"><?= ucwords(str_replace('_',' ',$m['material_type'])) ?></div>
                </div>
                <div class="material-content">
                    <h3 class="material-title"><?= htmlspecialchars($m['material_title']) ?></h3>
                    <div class="material-meta">
                        <div class="meta-item"><i class="fas fa-user"></i> <?= htmlspecialchars($m['uploader_name']) ?> (<?= $m['uploader_role']==3?'Faculty':'Student' ?>)</div>
                        <div class="meta-item"><i class="fas fa-book"></i> <?= htmlspecialchars($m['sub_name']) ?></div>
                        <div class="meta-item"><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($m['course_name']) ?> - <?= htmlspecialchars($m['sem_name']) ?></div>
                        <div class="meta-item"><i class="fas fa-calendar"></i> <?= date('M d, Y',strtotime($m['upload_date'])) ?></div>
                        <div class="meta-item"><i class="fas fa-hdd"></i> <?= number_format($m['file_size']/1024,2) ?> KB</div>
                    </div>
                    <?php if($m['material_description']): ?><p class="material-description"><?= htmlspecialchars($m['material_description']) ?></p><?php endif; ?>
                    <div class="approval-status status-<?= $m['approval_status'] ?>"><i class="fas <?= $m['approval_status']=='approved'?'fa-check-circle':($m['approval_status']=='pending'?'fa-clock':'fa-times-circle') ?>"></i> <?= ucfirst($m['approval_status']) ?> <?php if($m['approver_name']): ?><span class="approver">by <?= htmlspecialchars($m['approver_name']) ?></span><?php endif; ?></div>
                </div>
                <div class="material-actions">
                    <a href="../<?= $m['file_path'] ?>" target="_blank" class="btn-action btn-view" title="View"><i class="fas fa-eye"></i></a>
                    <a href="../<?= $m['file_path'] ?>" download="<?= htmlspecialchars($m['file_name']) ?>" class="btn-action btn-download" title="Download"><i class="fas fa-download"></i></a>
                    <button onclick="openApprovalModal(<?= $m['material_id'] ?>,'<?= $m['approval_status'] ?>','<?= htmlspecialchars(addslashes($m['remarks']??'')) ?>')" class="btn-action btn-edit" title="Update Status"><i class="fas fa-edit"></i></button>
                    <button onclick="openDeleteModal(<?= $m['material_id'] ?>)" class="btn-action btn-delete" title="Delete">
                    <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-folder-open"></i><h3>No Materials Found</h3><p>No materials match your current filter criteria.</p></div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
</div>

<!-- Delete Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <p>Are you sure you want to delete this material? This action cannot be undone.</p>
        <div class="modal-actions">
            <button class="btn-secondary" onclick="closeDeleteModal()"><i class="fas fa-times"></i>Cancel</button>
            <button class="btn-danger" id="confirmDeleteBtn"><i class="fas fa-trash"></i> Delete</button>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal-overlay" id="approvalModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-tasks"></i> Update Material Status</h3>
            <button class="modal-close" onclick="closeApprovalModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="material_id" id="modal_material_id" />
            <div class="form-group">
                <label>Status</label>
                <select name="approval_status" id="modal_approval_status" required>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" id="modal_remarks" rows="4" placeholder="Enter remarks (optional)"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeApprovalModal()">Cancel</button>
                <button type="submit" name="update_status" class="btn-primary"><i class="fas fa-save"></i> Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeleteModal(materialId) {
    const modal = document.getElementById('deleteModal');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    
    modal.classList.add('active');
    
    confirmBtn.onclick = function() {
        window.location.href = 'study_material.php?delete=' + materialId;
    };
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('active');
}

function openApprovalModal(materialId, currentStatus, remarks) {
    const modal = document.getElementById('approvalModal');
    document.getElementById('modal_material_id').value = materialId;
    document.getElementById('modal_approval_status').value = currentStatus;
    document.getElementById('modal_remarks').value = remarks;
    
    modal.classList.add('active');
}

function closeApprovalModal() {
    const modal = document.getElementById('approvalModal');
    modal.classList.remove('active');
}

// Close modals when clicking outside
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Close modals with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});
</script>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>
<script>
function clearFilters() {
    const form = document.querySelector('.filter-form');

    // Reset all selects
    form.querySelectorAll('select').forEach(sel => {
        if(sel.name === 'course' || sel.name === 'sem' || sel.name === 'subject') sel.value = '0';
        else sel.value = 'all';
    });

    // Submit the form
    form.submit();
}
</script>

</body>
</html>
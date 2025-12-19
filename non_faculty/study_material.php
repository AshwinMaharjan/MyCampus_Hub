<?php
session_start();
include("connect.php");

// Check if user is logged in and is coordinator
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['uid'];
$notification = null;
$notification_type = null;

// Verify user is coordinator
$user_check = $conn->prepare("SELECT role_id, full_name, is_coordinator, coordinator_for FROM users WHERE user_id = ?");
$user_check->bind_param("i", $user_id);
$user_check->execute();
$user_result = $user_check->get_result();
$user_data = $user_result->fetch_assoc();

if (!$user_data || $user_data['role_id'] != 3 || $user_data['is_coordinator'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Coordinator's assigned course(s)
$coordinator_course_id = intval($user_data['coordinator_for']);

// Filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_subject = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$filter_uploader = isset($_GET['uploader']) ? $_GET['uploader'] : 'all'; // all, faculty, student

// Build query for materials
$materials_query = "
    SELECT sm.*, s.sub_name, c.course_name, sem.sem_name, 
           u.full_name as uploader_name, u.email as uploader_email, u.role_id as uploader_role,
           approver.full_name as approver_name
    FROM study_material sm
    JOIN subject s ON sm.subject_id = s.sub_id
    JOIN course c ON sm.course_id = c.course_id
    JOIN semester sem ON sm.sem_id = sem.sem_id
    JOIN users u ON sm.user_id = u.user_id
    LEFT JOIN users approver ON sm.approved_by = approver.user_id
    WHERE sm.course_id = ?
";

$params = [$coordinator_course_id];
$types = "i";

if ($filter_status != 'all') {
    $materials_query .= " AND sm.approval_status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_subject > 0) {
    $materials_query .= " AND sm.subject_id = ?";
    $params[] = $filter_subject;
    $types .= "i";
}

if ($filter_uploader == 'faculty') {
    $materials_query .= " AND u.role_id = 3";
} elseif ($filter_uploader == 'student') {
    $materials_query .= " AND u.role_id = 2";
}

$materials_query .= " ORDER BY sm.upload_date DESC";

$materials_stmt = $conn->prepare($materials_query);
$materials_stmt->bind_param($types, ...$params);
$materials_stmt->execute();
$materials_result = $materials_stmt->get_result();

// Get subjects for dropdown (all subjects in coordinator's course)
$subjects_query = $conn->prepare("SELECT sub_id, sub_name FROM subject WHERE course_id = ? ORDER BY sub_name");
$subjects_query->bind_param("i", $coordinator_course_id);
$subjects_query->execute();
$subjects_result = $subjects_query->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Study Materials - Coordinator</title>
<link rel="stylesheet" href="../css/all.min.css">
<link rel="stylesheet" href="../css/faculty_study_material.css">
<link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
<style>
.page-wrapper, .main-content, .form-container { width: 100%; max-width: 100%; box-sizing: border-box; } body, html { overflow-x: hidden; }
</style>
</head>
<body>
<?php include("header.php"); ?>
<div class="page-wrapper">
<?php include("menu.php"); ?>

<div class="main-container">
    <div class="filter-section">
        <div class="section-header">
            <h2><i class="fas fa-filter"></i> Filter Materials</h2>
        </div>
        <form method="GET" action="" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="filter_status">Status</label>
                    <select name="status" id="filter_status" onchange="this.form.submit()">
                        <option value="all" <?= $filter_status=='all'?'selected':'' ?>>All Status</option>
                        <option value="pending" <?= $filter_status=='pending'?'selected':'' ?>>Pending</option>
                        <option value="approved" <?= $filter_status=='approved'?'selected':'' ?>>Approved</option>
                        <option value="rejected" <?= $filter_status=='rejected'?'selected':'' ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filter_subject">Subject</label>
                    <select name="subject" id="filter_subject" onchange="this.form.submit()">
                        <option value="0">All Subjects</option>
                        <?php while($subject=$subjects_result->fetch_assoc()): ?>
                        <option value="<?= $subject['sub_id'] ?>" <?= $filter_subject==$subject['sub_id']?'selected':'' ?>>
                            <?= htmlspecialchars($subject['sub_name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter_uploader">Uploaded By</label>
                    <select name="uploader" id="filter_uploader" onchange="this.form.submit()">
                        <option value="all" <?= $filter_uploader=='all'?'selected':'' ?>>All</option>
                        <option value="faculty" <?= $filter_uploader=='faculty'?'selected':'' ?>>Faculty</option>
                        <option value="student" <?= $filter_uploader=='student'?'selected':'' ?>>Students</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- Materials Section -->
    <div class="materials-section">
        <div class="section-header">
            <h2><i class="fas fa-folder-open"></i> Study Materials</h2>
            <span class="material-count"><?= $materials_result->num_rows ?> materials</span>
        </div>

        <?php if ($materials_result->num_rows > 0): ?>
        <div class="materials-grid">
            <?php while ($material = $materials_result->fetch_assoc()): ?>
            <div class="material-card status-<?= $material['approval_status'] ?>">
                <div class="material-header">
                    <div class="material-icon">
                        <?php
                        $icon_map = [
                            'pdf'=>'fa-file-pdf', 'doc'=>'fa-file-word', 'docx'=>'fa-file-word',
                            'ppt'=>'fa-file-powerpoint', 'pptx'=>'fa-file-powerpoint', 'txt'=>'fa-file-alt',
                            'jpg'=>'fa-file-image', 'jpeg'=>'fa-file-image', 'png'=>'fa-file-image',
                            'zip'=>'fa-file-archive', 'rar'=>'fa-file-archive'
                        ];
                        $icon = $icon_map[$material['file_extension']] ?? 'fa-file';
                        ?>
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                    <div class="material-type-badge <?= $material['material_type'] ?>">
                        <?= ucwords(str_replace('_',' ',$material['material_type'])) ?>
                    </div>
                </div>

                <div class="material-content">
                    <h3 class="material-title"><?= htmlspecialchars($material['material_title']) ?></h3>
                    <div class="material-meta">
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span><?= htmlspecialchars($material['uploader_name']) ?> (<?= $material['uploader_role']==3?'Faculty':'Student' ?>)</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-book"></i>
                            <span><?= htmlspecialchars($material['sub_name']) ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-graduation-cap"></i>
                            <span><?= htmlspecialchars($material['course_name']) ?> - <?= htmlspecialchars($material['sem_name']) ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?= date('M d, Y', strtotime($material['upload_date'])) ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-hdd"></i>
                            <span><?= number_format($material['file_size']/1024,2) ?> KB</span>
                        </div>
                    </div>

                    <?php if($material['material_description']): ?>
                    <p class="material-description"><?= htmlspecialchars($material['material_description']) ?></p>
                    <?php endif; ?>

                    <div class="approval-status status-<?= $material['approval_status'] ?>">
                        <i class="fas <?= $material['approval_status']=='approved'?'fa-check-circle':($material['approval_status']=='pending'?'fa-clock':'fa-times-circle') ?>"></i>
                        <?= ucfirst($material['approval_status']) ?>
                        <?php if($material['approver_name']): ?>
                            <span class="approver">by <?= htmlspecialchars($material['approver_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="material-actions">
                    <a href="../<?= $material['file_path'] ?>" target="_blank" class="btn-action btn-view" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="../<?= $material['file_path'] ?>" download="<?= htmlspecialchars($material['file_name']) ?>" class="btn-action btn-download" title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h3>No Materials Found</h3>
            <p>No materials match your current filter criteria.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>
<script src="../js/non_faculty_study_material.js"></script>
</body>
</html>

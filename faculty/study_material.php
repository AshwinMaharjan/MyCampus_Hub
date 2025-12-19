<?php
session_start();
include("connect.php");

// Check if user is logged in and is faculty
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['uid'];
$notification = null;
$notification_type = null;

// Verify user is faculty (role_id = 3)
$user_check = $conn->prepare("SELECT role_id, full_name FROM users WHERE user_id = ?");
$user_check->bind_param("i", $user_id);
$user_check->execute();
$user_result = $user_check->get_result();
$user_data = $user_result->fetch_assoc();

if (!$user_data || $user_data['role_id'] != 3) {
    header("Location: ../login.php");
    exit();
}

// Handle faculty file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_material'])) {
    $course_id = intval($_POST['course_id']);
    $sem_id = intval($_POST['sem_id']);
    $subject_id = intval($_POST['subject_id']);
    $material_title = trim($_POST['material_title']);
    $material_description = trim($_POST['material_description']);
    $material_type = $_POST['material_type'];

    if (empty($course_id) || empty($sem_id) || empty($subject_id) || empty($material_title) || empty($material_type)) {
        $notification = "Please fill all required fields.";
        $notification_type = "error";
    } elseif (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
        $notification = "Please select a file to upload.";
        $notification_type = "error";
    } else {
        // Verify faculty teaches this subject
        $verify_subject = $conn->prepare("SELECT sub_id FROM subject WHERE role_id = ? AND sub_id = ?");
        $verify_subject->bind_param("ii", $user_id, $subject_id);
        $verify_subject->execute();
        $verify_result = $verify_subject->get_result();
        
        if ($verify_result->num_rows == 0) {
            $notification = "You are not assigned to teach this subject.";
            $notification_type = "error";
        } else {
            $file = $_FILES['material_file'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_extensions = ['pdf','doc','docx','ppt','pptx','txt','jpg','jpeg','png','zip','rar'];

            if (!in_array($file_ext, $allowed_extensions)) {
                $notification = "Invalid file type. Allowed: " . implode(', ', $allowed_extensions);
                $notification_type = "error";
            } elseif ($file_size > 10485760) { // 10MB
                $notification = "File too large. Maximum size is 10MB.";
                $notification_type = "error";
            } else {
                $upload_dir = dirname(__DIR__) . "/uploads/study_materials/";
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $new_file_name = uniqid() . '_' . time() . "." . $file_ext;
                $full_path = $upload_dir . $new_file_name;
                $db_path = "uploads/study_materials/" . $new_file_name;

                if (move_uploaded_file($file_tmp, $full_path)) {
                    // Faculty uploads are auto-approved
                    $stmt = $conn->prepare("INSERT INTO study_material 
                        (user_id, course_id, sem_id, subject_id, material_title, material_description, 
                        material_type, file_path, file_name, file_size, file_extension, approval_status, approved_by, approval_date, upload_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), NOW())");

                    if (!$stmt) {
                        $notification = "Database error: " . $conn->error;
                        $notification_type = "error";
                        unlink($full_path);
                    } else {
                        $stmt->bind_param(
                            "iiiisssssisi",
                            $user_id, $course_id, $sem_id, $subject_id,
                            $material_title, $material_description,
                            $material_type, $db_path,
                            $file_name, $file_size, $file_ext, $user_id
                        );

                        if ($stmt->execute()) {
                            header("Location: study_material.php?success=1");
                            exit();
                        } else {
                            $notification = "Database error: " . $stmt->error;
                            $notification_type = "error";
                            unlink($full_path);
                        }
                        $stmt->close();
                    }
                } else {
                    $notification = "Failed to upload file.";
                    $notification_type = "error";
                }
            }
        }
        $verify_subject->close();
    }
}

// Handle approval/rejection of student submissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $material_id = intval($_POST['material_id']);
    $new_status = $_POST['approval_status'];
    $remarks = trim($_POST['remarks']);

    // Verify faculty has permission to approve this material (teaches the subject)
    $verify = $conn->prepare("
        SELECT sm.material_id 
        FROM study_material sm
        JOIN subject s ON sm.subject_id = s.sub_id
        WHERE sm.material_id = ? AND s.role_id = ?
    ");
    $verify->bind_param("ii", $material_id, $user_id);
    $verify->execute();
    $verify_result = $verify->get_result();
    
    if ($verify_result->num_rows > 0) {
        $update_stmt = $conn->prepare("UPDATE study_material 
            SET approval_status = ?, approved_by = ?, approval_date = NOW(), remarks = ?, last_modified = NOW()
            WHERE material_id = ?");
        
        $update_stmt->bind_param("sisi", $new_status, $user_id, $remarks, $material_id);
        
        if ($update_stmt->execute()) {
            header("Location: study_material.php?updated=1");
            exit();
        } else {
            $notification = "Error updating status: " . $update_stmt->error;
            $notification_type = "error";
        }
        $update_stmt->close();
    } else {
        $notification = "You don't have permission to update this material.";
        $notification_type = "error";
    }
    $verify->close();
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $material_id = intval($_GET['delete']);
    
    // Verify faculty has permission (either uploaded by them or they teach the subject)
    $check = $conn->prepare("
        SELECT sm.file_path, sm.user_id 
        FROM study_material sm
        LEFT JOIN subject s ON sm.subject_id = s.sub_id AND s.role_id = ?
        WHERE sm.material_id = ? AND (sm.user_id = ? OR s.role_id IS NOT NULL)
    ");
    $check->bind_param("iii", $user_id, $material_id, $user_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $material = $result->fetch_assoc();
        $file_to_delete = dirname(__DIR__) . "/" . $material['file_path'];
        
        if (file_exists($file_to_delete)) {
            unlink($file_to_delete);
        }
        
        $delete_stmt = $conn->prepare("DELETE FROM study_material WHERE material_id = ?");
        $delete_stmt->bind_param("i", $material_id);
        
        if ($delete_stmt->execute()) {
            header("Location: study_material.php?deleted=1");
            exit();
        } else {
            $notification = "Error deleting material: " . $delete_stmt->error;
            $notification_type = "error";
        }
        $delete_stmt->close();
    } else {
        $notification = "You don't have permission to delete this material.";
        $notification_type = "error";
    }
    $check->close();
}

// Check for success messages
if (isset($_GET['success'])) {
    $notification = "Material uploaded successfully!";
    $notification_type = "success";
}
if (isset($_GET['updated'])) {
    $notification = "Status updated successfully!";
    $notification_type = "success";
}
if (isset($_GET['deleted'])) {
    $notification = "Material deleted successfully!";
    $notification_type = "success";
}

// Get faculty's assigned subjects
$assigned_subjects_query = $conn->prepare("
    SELECT s.sub_id, s.sub_name, c.course_name, sem.sem_name, c.course_id, sem.sem_id
    FROM subject s
    JOIN course c ON s.course_id = c.course_id
    JOIN semester sem ON s.sem_id = sem.sem_id
    WHERE s.role_id = ?
    ORDER BY c.course_name, sem.sem_name, s.sub_name
");
$assigned_subjects_query->bind_param("i", $user_id);
$assigned_subjects_query->execute();
$assigned_subjects_result = $assigned_subjects_query->get_result();

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_subject = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$filter_uploader = isset($_GET['uploader']) ? $_GET['uploader'] : 'all'; // all, faculty, student

// Build query for materials (both faculty uploads and student submissions)
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
    WHERE s.role_id = ?
";

$params = [$user_id];
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

// Get courses for dropdown (only courses the faculty teaches)
$courses_query = $conn->prepare("
    SELECT DISTINCT c.course_id, c.course_name 
    FROM course c
    JOIN subject s ON c.course_id = s.course_id
    WHERE s.role_id = ?
    ORDER BY c.course_name
");
$courses_query->bind_param("i", $user_id);
$courses_query->execute();
$courses_result = $courses_query->get_result();

// Get semesters for dropdown (only semesters the faculty teaches)
$semesters_query = $conn->prepare("
    SELECT DISTINCT sem.sem_id, sem.sem_name 
    FROM semester sem
    JOIN subject s ON sem.sem_id = s.sem_id
    WHERE s.role_id = ?
    ORDER BY sem.sem_name
");
$semesters_query->bind_param("i", $user_id);
$semesters_query->execute();
$semesters_result = $semesters_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Study Materials - Faculty</title>
    <link rel="stylesheet" href="../css/all.min.css" />
    <link rel="stylesheet" href="../css/faculty_study_material.css" />
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
    <!-- Upload Section -->
    <div class="upload-section">
        <div class="section-header">
            <h2><i class="fas fa-cloud-upload-alt"></i> Upload Study Material</h2>
        </div>
        <form method="POST" action="" enctype="multipart/form-data" class="upload-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="course_id">Course <span class="required">*</span></label>
                    <select name="course_id" id="course_id" required>
                        <option value="">Select Course</option>
                        <?php while ($course = $courses_result->fetch_assoc()): ?>
                        <option value="<?php echo $course['course_id']; ?>">
                            <?php echo htmlspecialchars($course['course_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="sem_id">Semester <span class="required">*</span></label>
                    <select name="sem_id" id="sem_id" required>
                        <option value="">Select Semester</option>
                        <?php while ($semester = $semesters_result->fetch_assoc()): ?>
                        <option value="<?php echo $semester['sem_id']; ?>">
                            <?php echo htmlspecialchars($semester['sem_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="subject_id">Subject <span class="required">*</span></label>
                    <select name="subject_id" id="subject_id" required>
                        <option value="">Select Subject</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="material_type">Material Type <span class="required">*</span></label>
                    <select name="material_type" id="material_type" required>
                        <option value="">Select Type</option>
                        <option value="notes">Notes</option>
                        <option value="assignment">Assignment</option>
                        <option value="previous_paper">Previous Paper</option>
                        <option value="reference_book">Reference Book</option>
                        <option value="presentation">Presentation</option>
                        <option value="lab_manual">Lab Manual</option>
                        <option value="syllabus">Syllabus</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="material_title">Title <span class="required">*</span></label>
                <input type="text" name="material_title" id="material_title" placeholder="Enter material title" required />
            </div>

            <div class="form-group">
                <label for="material_description">Description</label>
                <textarea name="material_description" id="material_description" rows="3" placeholder="Enter material description (optional)"></textarea>
            </div>

            <div class="form-group">
                <label for="material_file">Upload File <span class="required">*</span></label>
                <div class="file-upload-wrapper">
                    <input type="file" name="material_file" id="material_file" required accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.zip,.rar" />
                    <label for="material_file" class="file-upload-label">
                        <i class="fas fa-paperclip"></i>
                        <span id="file-name">Choose a file...</span>
                    </label>
                </div>
                <small class="file-info">Max file size: 10MB. Allowed formats: PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, PNG, ZIP, RAR</small>
            </div>

            <button type="submit" name="upload_material" class="btn-upload">
                <i class="fas fa-upload"></i> Upload Material
            </button>
        </form>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="section-header">
            <h2><i class="fas fa-filter"></i> Filter Materials</h2>
        </div>
        <form method="GET" action="" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="filter_status">Status</label>
                    <select name="status" id="filter_status" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filter_subject">Subject</label>
                    <select name="subject" id="filter_subject" onchange="this.form.submit()">
                        <option value="0">All Subjects</option>
                        <?php 
                        $assigned_subjects_result->data_seek(0);
                        while ($subject = $assigned_subjects_result->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $subject['sub_id']; ?>" <?php echo $filter_subject == $subject['sub_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['sub_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter_uploader">Uploaded By</label>
                    <select name="uploader" id="filter_uploader" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_uploader == 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="faculty" <?php echo $filter_uploader == 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                        <option value="student" <?php echo $filter_uploader == 'student' ? 'selected' : ''; ?>>Students</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- Materials Section -->
    <div class="materials-section">
        <div class="section-header">
            <h2><i class="fas fa-folder-open"></i> Study Materials</h2>
            <span class="material-count"><?php echo $materials_result->num_rows; ?> materials</span>
        </div>

        <?php if ($materials_result->num_rows > 0): ?>
        <div class="materials-grid">
            <?php while ($material = $materials_result->fetch_assoc()): ?>
            <div class="material-card status-<?php echo $material['approval_status']; ?>">
                <div class="material-header">
                    <div class="material-icon">
                        <?php
                        $icon_map = [
                            'pdf' => 'fa-file-pdf',
                            'doc' => 'fa-file-word',
                            'docx' => 'fa-file-word',
                            'ppt' => 'fa-file-powerpoint',
                            'pptx' => 'fa-file-powerpoint',
                            'txt' => 'fa-file-alt',
                            'jpg' => 'fa-file-image',
                            'jpeg' => 'fa-file-image',
                            'png' => 'fa-file-image',
                            'zip' => 'fa-file-archive',
                            'rar' => 'fa-file-archive'
                        ];
                        $icon = $icon_map[$material['file_extension']] ?? 'fa-file';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="material-type-badge <?php echo $material['material_type']; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $material['material_type'])); ?>
                    </div>
                </div>

                <div class="material-content">
                    <h3 class="material-title"><?php echo htmlspecialchars($material['material_title']); ?></h3>
                    
                    <div class="material-meta">
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($material['uploader_name']); ?> 
                                (<?php echo $material['uploader_role'] == 3 ? 'Faculty' : 'Student'; ?>)
                            </span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-book"></i>
                            <span><?php echo htmlspecialchars($material['sub_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-graduation-cap"></i>
                            <span><?php echo htmlspecialchars($material['course_name']); ?> - <?php echo htmlspecialchars($material['sem_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('M d, Y', strtotime($material['upload_date'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-hdd"></i>
                            <span><?php echo number_format($material['file_size'] / 1024, 2); ?> KB</span>
                        </div>
                    </div>

                    <?php if ($material['material_description']): ?>
                    <p class="material-description"><?php echo htmlspecialchars($material['material_description']); ?></p>
                    <?php endif; ?>

                    <div class="approval-status status-<?php echo $material['approval_status']; ?>">
                        <i class="fas <?php echo $material['approval_status'] == 'approved' ? 'fa-check-circle' : ($material['approval_status'] == 'pending' ? 'fa-clock' : 'fa-times-circle'); ?>"></i>
                        <?php echo ucfirst($material['approval_status']); ?>
                        <?php if ($material['approver_name']): ?>
                            <span class="approver">by <?php echo htmlspecialchars($material['approver_name']); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($material['remarks']): ?>
                    <div class="remarks-box">
                        <strong>Remarks:</strong>
                        <p><?php echo htmlspecialchars($material['remarks']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="material-actions">
                    <a href="../<?php echo $material['file_path']; ?>" target="_blank" class="btn-action btn-view" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
<a href="../<?php echo $material['file_path']; ?>" download="<?php echo htmlspecialchars($material['file_name']); ?>" 
   class="btn-action btn-download" title="Download">
    <i class="fas fa-download"></i>
</a>
                    <?php if ($material['uploader_role'] == 2): // Only allow approval for student submissions ?>
                    <button onclick="openApprovalModal(<?php echo $material['material_id']; ?>, '<?php echo $material['approval_status']; ?>', '<?php echo htmlspecialchars(addslashes($material['remarks'] ?? '')); ?>')" 
                            class="btn-action btn-edit" title="Update Status">
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php endif; ?>
                    <button onclick="confirmDelete(<?php echo $material['material_id']; ?>)" class="btn-action btn-delete" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
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
                <label for="modal_approval_status">Status <span class="required">*</span></label>
                <select name="approval_status" id="modal_approval_status" required>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <div class="form-group">
                <label for="modal_remarks">Remarks</label>
                <textarea name="remarks" id="modal_remarks" rows="4" placeholder="Enter your remarks (optional)"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeApprovalModal()">Cancel</button>
                <button type="submit" name="update_status" class="btn-primary">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Notification Modal -->
<?php if ($notification): ?>
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
            }
            ?>
        </div>
        <div class="notification-title">
            <?php echo ($notification_type === 'success') ? 'Success!' : 'Error'; ?>
        </div>
        <div class="notification-message"><?php echo htmlspecialchars($notification); ?></div>
        <div class="notification-progress">
            <div class="notification-progress-bar"></div>
        </div>
        <button class="notification-button" onclick="closeNotification()">
            <?php echo ($notification_type === 'success') ? 'Continue' : 'Okay'; ?>
        </button>
    </div>
</div>
<?php endif; ?>

<script src="../js/faculty_study_material.js"></script>

</body>
</html>
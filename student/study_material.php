<?php
session_start();
include("connect.php");

if (!isset($_SESSION['uid'])) {
  header("Location: ../login.php");
  exit();
}

$user_id = $_SESSION['uid'];
$notification = null;
$notification_type = null;

// Get user details
$user_query = $conn->prepare("
    SELECT 
        u.course_id, 
        c.course_name, 
        u.sem_id, 
        s.sem_name
    FROM users u
    LEFT JOIN course c ON u.course_id = c.course_id
    LEFT JOIN semester s ON u.sem_id = s.sem_id
    WHERE u.user_id = ?
");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

$user_course_id = $user_data['course_id'];
$user_course_name = $user_data['course_name'];

$user_sem_id = $user_data['sem_id'];
$user_sem_name = $user_data['sem_name'];


// Handle file upload - FIXED VERSION
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_material'])) {

    $course_id = intval($_POST['course_id']);
    $sem_id = intval($_POST['sem_id']);
    $subject_id = intval($_POST['subject_id']);
    $material_title = trim($_POST['material_title']);
    $material_description = trim($_POST['material_description']);
    $material_type = $_POST['material_type'];

    // Validate required fields
    if (empty($course_id) || empty($sem_id) || empty($subject_id) || empty($material_title) || empty($material_type)) {
        $notification = "Please fill all required fields.";
        $notification_type = "error";
    } elseif (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        $error_code = $_FILES['material_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $notification = "Upload error: " . ($error_messages[$error_code] ?? "Unknown error");
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
            // Use absolute path for filesystem operations
            $upload_dir = dirname(__DIR__) . "/uploads/study_materials/";
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $notification = "Failed to create upload directory.";
                    $notification_type = "error";
                    goto end_upload;
                }
            }

            // Check if directory is writable
            if (!is_writable($upload_dir)) {
                $notification = "Upload directory is not writable. Check permissions.";
                $notification_type = "error";
                goto end_upload;
            }

            $new_file_name = uniqid() . '_' . time() . "." . $file_ext;
            $full_path = $upload_dir . $new_file_name;
            
            // Path to store in database (relative)
            $db_path = "uploads/study_materials/" . $new_file_name;

            if (move_uploaded_file($file_tmp, $full_path)) {
                
                $stmt = $conn->prepare("INSERT INTO study_material 
                    (user_id, course_id, sem_id, subject_id, material_title, material_description, 
                    material_type, file_path, file_name, file_size, file_extension)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                if (!$stmt) {
                    $notification = "Database prepare error: " . $conn->error;
                    $notification_type = "error";
                    unlink($full_path);
                } else {
                    $stmt->bind_param(
                        "iiiisssssis",
                        $user_id, $course_id, $sem_id, $subject_id,
                        $material_title, $material_description,
                        $material_type, $db_path,
                        $file_name, $file_size, $file_ext
                    );

                    if ($stmt->execute()) {
                        $notification = "Material uploaded successfully!";
                        $notification_type = "success";
                        
                        // Redirect to avoid form resubmission
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
                $notification = "Failed to move uploaded file. Check server permissions.";
                $notification_type = "error";
            }
        }
    }
    
    end_upload:
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $material_id = intval($_GET['delete']);
    
    $check = $conn->prepare("SELECT file_path FROM study_material WHERE material_id = ? AND user_id = ?");
    $check->bind_param("ii", $material_id, $user_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $material = $result->fetch_assoc();
        
        // Build absolute path for deletion
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
            $notification = "Error: Could not delete material.";
            $notification_type = "error";
        }
        $delete_stmt->close();
    } else {
        $notification = "Material not found or you don't have permission to delete it.";
        $notification_type = "error";
    }
    $check->close();
}

// Check for success messages from redirect
if (isset($_GET['success'])) {
    $notification = "Material uploaded successfully!";
    $notification_type = "success";
}
if (isset($_GET['deleted'])) {
    $notification = "Material deleted successfully!";
    $notification_type = "success";
}

// Fetch user's materials
// $materials_query = $conn->prepare("
//     SELECT sm.*, s.sub_name, c.course_name, sem.sem_name 
//     FROM study_material sm
//     LEFT JOIN subject s ON sm.subject_id = s.sub_id
//     LEFT JOIN course c ON sm.course_id = c.course_id
//     LEFT JOIN semester sem ON sm.sem_id = sem.sem_id
//     LEFT JOIN users u ON sm.user_id = u.user_id
//     WHERE (sm.user_id = ? OR (u.role_id = 3 AND sm.course_id = ? AND sm.sem_id = ?))
//     ORDER BY sm.upload_date DESC
// ");
// $materials_query->bind_param("iii", $user_id, $user_course_id, $user_sem_id);
// $materials_query->execute();
// $materials_result = $materials_query->get_result();
// Your materials
$my_materials_query = $conn->prepare("
    SELECT sm.*, s.sub_name, c.course_name, sem.sem_name 
    FROM study_material sm
    LEFT JOIN subject s ON sm.subject_id = s.sub_id
    LEFT JOIN course c ON sm.course_id = c.course_id
    LEFT JOIN semester sem ON sm.sem_id = sem.sem_id
    WHERE sm.user_id = ?
    ORDER BY sm.upload_date DESC
");
$my_materials_query->bind_param("i", $user_id);
$my_materials_query->execute();
$my_materials_result = $my_materials_query->get_result();

// Teacher uploaded materials for your course and semester
$teacher_materials_query = $conn->prepare("
    SELECT sm.*, s.sub_name, c.course_name, sem.sem_name 
    FROM study_material sm
    LEFT JOIN subject s ON sm.subject_id = s.sub_id
    LEFT JOIN course c ON sm.course_id = c.course_id
    LEFT JOIN semester sem ON sm.sem_id = sem.sem_id
    LEFT JOIN users u ON sm.user_id = u.user_id
    WHERE u.role_id = 3 AND sm.course_id = ? AND sm.sem_id = ? 
    ORDER BY sm.upload_date DESC
");
$teacher_materials_query->bind_param("ii", $user_course_id, $user_sem_id);
$teacher_materials_query->execute();
$teacher_materials_result = $teacher_materials_query->get_result();


// Get courses for dropdown
$courses_query = $conn->query("SELECT * FROM course ORDER BY course_name");

// Get semesters for dropdown
$semesters_query = $conn->query("SELECT * FROM semester ORDER BY sem_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Study Materials</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/study_material.css" />
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
    <label>Course <span class="required">*</span></label>
    <input type="text" value="<?php echo htmlspecialchars($user_course_name); ?>" disabled>
    <input type="hidden" name="course_id" id="course_id" value="<?php echo $user_course_id; ?>">
</div>

<div class="form-group">
    <label>Semester <span class="required">*</span></label>
    <input type="text" value="<?php echo htmlspecialchars($user_sem_name); ?>" disabled>
    <input type="hidden" name="sem_id" id="sem_id" value="<?php echo $user_sem_id; ?>">
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

    <!-- Materials List Section -->
<div class="materials-section">
    <!-- My Uploaded Materials -->
    <div class="section-header">
        <h2><i class="fas fa-folder-open"></i> My Uploaded Materials</h2>
    </div>

    <?php if ($my_materials_result->num_rows > 0): ?>
    <div class="materials-grid">
        <?php while ($material = $my_materials_result->fetch_assoc()): ?>
        <div class="material-card">
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
                </div>
            </div>

            <div class="material-actions">
                <a href="../<?php echo $material['file_path']; ?>" download="<?php echo htmlspecialchars($material['file_name']); ?>" class="btn-action btn-download" title="Download">
                    <i class="fas fa-download"></i>
                </a>
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
        <h3>No Materials Uploaded Yet</h3>
        <p>Start by uploading your first study material above!</p>
    </div>
    <?php endif; ?>
</div>

<!-- Teacher Uploaded Materials -->
<div class="materials-section">
    <div class="section-header">
        <h2><i class="fas fa-chalkboard-teacher"></i> Teacher Uploaded Materials</h2>
    </div>

    <?php if ($teacher_materials_result->num_rows > 0): ?>
    <div class="materials-grid">
        <?php while ($material = $teacher_materials_result->fetch_assoc()): ?>
        <div class="material-card">
            <div class="material-header">
                <div class="material-icon">
                    <?php
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
                </div>
            </div>

            <div class="material-actions">
                <a href="../<?php echo $material['file_path']; ?>" download="<?php echo htmlspecialchars($material['file_name']); ?>" class="btn-action btn-download" title="Download">
                    <i class="fas fa-download"></i>
                </a>
                <!-- No delete button for teacher uploaded files -->
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-chalkboard-teacher"></i>
        <h3>No Materials from Teachers Yet</h3>
        <p>Materials uploaded by your teachers will appear here.</p>
    </div>
    <?php endif; ?>
</div>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

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
                case 'invalid':
                    echo '<i class="fas fa-exclamation-circle"></i>';
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
                case 'invalid':
                    echo 'Invalid Input';
                    break;
            }
            ?>
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

<script src="../js/study_material.js"></script>

</body>
</html>
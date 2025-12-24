<?php
session_start();
include("auth_check.php");
include("connect.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$notification = null;
$notification_type = null;
$redirect_delay = 2000;
$redirect_url = null;

if (isset($_POST['add_staff_btn'])) {
    $id       = trim($_POST['id_number']);
    $name     = trim($_POST['full_name']);
    $email    = trim($_POST['email']);
    $pass     = $_POST['password'];
    $gender   = $_POST['gender'];
    $dob      = $_POST['date_of_birth'];
    $phone    = trim($_POST['contact_number']);
    $address  = trim($_POST['address']);
    $status   = $_POST['status'];
    $role     = 3; // Staff role
    
    // Check which responsibilities are selected
    $assign_teaching = isset($_POST['assign_teaching']) ? 1 : 0;
    $assign_coordinator = isset($_POST['assign_coordinator']) ? 1 : 0;

    // Validate that at least one responsibility is selected
    if (!$assign_teaching && !$assign_coordinator) {
        $notification = "Please assign at least one responsibility (Teaching or Coordinator).";
        $notification_type = "error";
    }
    
    // Check for existing email or ID
    if (empty($notification)) {
        $check = $conn->prepare("SELECT * FROM users WHERE email=? OR id_number=?");
        $check->bind_param("ss", $email, $id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $notification = "This email or ID is already used.";
            $notification_type = "error";
        }
        $check->close();
    }

    // Handle profile photo upload
    if (empty($notification)) {
        $photo = '';
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $ext_allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $photo_name = $_FILES['profile_photo']['name'];
            $tmp = $_FILES['profile_photo']['tmp_name'];
            $ext = strtolower(pathinfo($photo_name, PATHINFO_EXTENSION));

            if (in_array($ext, $ext_allowed)) {
                $new_name = uniqid('profile_', true) . '.' . $ext;
                $folder = 'images/uploads/profile_photos/';
                
                if (!is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }
                
                $path = $folder . $new_name;

                if (move_uploaded_file($tmp, $path)) {
                    $photo = $new_name;
                } else {
                    $notification = "Photo upload failed.";
                    $notification_type = "error";
                }
            } else {
                $notification = "Only JPG, PNG, or GIF images allowed.";
                $notification_type = "error";
            }
        } else {
            $notification = "Please upload profile photo.";
            $notification_type = "error";
        }
    }

    // Process the form if no errors
    if (empty($notification)) {
        $conn->begin_transaction();

        try {
            // Get course_name and sem_name (store ALL selected as comma-separated)
            $course_name_for_user = null;
            $sem_name_for_user = null;
            
            // NEW: Initialize is_coordinator and coordinator_for
            $is_coordinator = 0;
            $coordinator_for = 0;
            
            if ($assign_teaching) {
                // Validate teaching fields
                if (empty($_POST['course_name']) || empty($_POST['sem_name'])) {
                    throw new Exception("Please select courses and semesters for teaching assignment.");
                }

                // Get ALL selected courses and semesters
                $selectedCourses = $_POST['course_name'];
                $selectedSemesters = $_POST['sem_name'];
                
                // Store ALL selected course names as comma-separated string
                $course_name_for_user = implode(', ', $selectedCourses);
                
                // Store ALL selected semester names as comma-separated string
                $sem_name_for_user = implode(', ', $selectedSemesters);
                
            } elseif ($assign_coordinator) {
                // If only coordinator, get course_name from coordinator selection
                $course_name_for_user = $_POST['coordinator_course'];
                
                // sem_name remains NULL for coordinator-only staff
            }

            // NEW: Set is_coordinator and coordinator_for based on coordinator assignment
            if ($assign_coordinator) {
                $is_coordinator = 1;
                
                if (!empty($_POST['coordinator_course'])) {
                    $coordinator_course_name = $_POST['coordinator_course'];
                    
                    // Get course_id for coordinator_for column
                    $coordCourseQuery = $conn->prepare("SELECT course_id FROM course WHERE course_name = ?");
                    $coordCourseQuery->bind_param("s", $coordinator_course_name);
                    $coordCourseQuery->execute();
                    $coordCourseQuery->bind_result($coord_course_id);
                    if ($coordCourseQuery->fetch()) {
                        $coordinator_for = $coord_course_id;
                    }
                    $coordCourseQuery->close();
                }
            }

            // Insert basic user information WITH course_name, sem_name, is_coordinator, and coordinator_for
            $insert = $conn->prepare("
                INSERT INTO users 
                (full_name, id_number, email, password, role_id, gender, date_of_birth, 
                 contact_number, address, profile_photo, status, course_name, sem_name, is_coordinator, coordinator_for) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$insert) {
                throw new Exception("Database error: " . $conn->error);
            }

            $insert->bind_param(
                "ssssississsssii",
                $name,
                $id,
                $email,
                $pass,
                $role,
                $gender,
                $dob,
                $phone,
                $address,
                $photo,
                $status,
                $course_name_for_user,
                $sem_name_for_user,
                $is_coordinator,
                $coordinator_for
            );

            if (!$insert->execute()) {
                throw new Exception("Error inserting user: " . $insert->error);
            }

            $user_id = $conn->insert_id;
            $insert->close();

            // Handle Teaching Assignment
            if ($assign_teaching) {
                $selectedCourses = $_POST['course_name'];
                $selectedSemesters = $_POST['sem_name'];

                // Insert into staff_teaching_assignments table
                $insertTeaching = $conn->prepare("
                    INSERT INTO staff_teaching_assignments 
                    (staff_id, course_id, sem_id, sub_id) 
                    VALUES (?, ?, ?, ?)
                ");

                if (!$insertTeaching) {
                    throw new Exception("Database error on teaching assignments: " . $conn->error);
                }

                foreach ($selectedCourses as $courseName) {
                    // Get course_id
                    $courseQuery = $conn->prepare("SELECT course_id FROM course WHERE course_name = ?");
                    $courseQuery->bind_param("s", $courseName);
                    $courseQuery->execute();
                    $courseQuery->bind_result($course_id);
                    if (!$courseQuery->fetch()) {
                        $courseQuery->close();
                        continue;
                    }
                    $courseQuery->close();

                    foreach ($selectedSemesters as $semName) {
                        // Get sem_id
                        $semQuery = $conn->prepare("SELECT sem_id FROM semester WHERE sem_name = ?");
                        $semQuery->bind_param("s", $semName);
                        $semQuery->execute();
                        $semQuery->bind_result($sem_id);
                        if (!$semQuery->fetch()) {
                            $semQuery->close();
                            continue;
                        }
                        $semQuery->close();

                        // Insert the teaching assignment
                        $sub_id = null; // Set to null or get from form if applicable
                        $insertTeaching->bind_param("iiii", $user_id, $course_id, $sem_id, $sub_id);
                        
                        if (!$insertTeaching->execute()) {
                            throw new Exception("Error inserting teaching assignment: " . $insertTeaching->error);
                        }
                    }
                }
                $insertTeaching->close();
            }

            // Handle Coordinator Assignment
            if ($assign_coordinator) {
                if (empty($_POST['coordinator_course'])) {
                    throw new Exception("Please select a course for coordinator assignment.");
                }

                $coordinator_course_name = $_POST['coordinator_course'];
                
                // Get course ID
                $courseQuery = $conn->prepare("SELECT course_id FROM course WHERE course_name = ?");
                $courseQuery->bind_param("s", $coordinator_course_name);
                $courseQuery->execute();
                $courseQuery->bind_result($coordinator_for_table);
                if (!$courseQuery->fetch()) {
                    throw new Exception("Selected coordinator course not found.");
                }
                $courseQuery->close();

                // Insert into coordinators table
                $insertCoordinator = $conn->prepare("
                    INSERT INTO coordinators 
                    (user_id, coordinator_for, full_name, email, contact_number, status) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                if (!$insertCoordinator) {
                    throw new Exception("Database error on coordinators table: " . $conn->error);
                }

                $insertCoordinator->bind_param(
                    "iissss",
                    $user_id,
                    $coordinator_for_table,
                    $name,
                    $email,
                    $phone,
                    $status
                );

                if (!$insertCoordinator->execute()) {
                    throw new Exception("Error inserting coordinator: " . $insertCoordinator->error);
                }

                $insertCoordinator->close();
            }

            $conn->commit();

            $notification = "Staff member created successfully! Responsibilities have been assigned as selected.";
            $notification_type = "success";
            $redirect_url = "manage_staff.php";

        } catch (Exception $e) {
            $conn->rollback();
            $notification = "Error: " . $e->getMessage();
            $notification_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard - Add Staff</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/add_staff.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    .error-message {
      color: #dc3545;
      font-size: 13px;
      margin-top: 5px;
      display: none;
      padding: 6px 10px;
      background-color: #fee;
      border-radius: 4px;
      border-left: 3px solid #dc3545;
    }

    .error-message.show {
      display: block;
      animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .input-error {
      border-color: #dc3545 !important;
      background-color: #fff5f5 !important;
    }

    .input-success {
      border-color: #28a745 !important;
      background-color: #f0fff4 !important;
    }

    #teaching-fields,
    #coordinator-fields {
      display: none;
      margin-top: 20px;
    }

    .notification-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        animation: fadeIn 0.3s ease-in;
    }

    .notification-overlay.active {
        display: flex;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .notification-modal {
        background: white;
        border-radius: 12px;
        padding: 40px;
        max-width: 500px;
        width: 90%;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.4s ease-out;
        position: relative;
    }

    @keyframes slideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .notification-icon {
        font-size: 60px;
        margin-bottom: 20px;
        animation: bounce 0.6s ease-out;
    }

    @keyframes bounce {
        0% {
            transform: scale(0);
        }
        50% {
            transform: scale(1.1);
        }
        100% {
            transform: scale(1);
        }
    }

    .notification-modal.success .notification-icon {
        color: #10b981;
    }

    .notification-modal.success {
        border-left: 5px solid #10b981;
    }

    .notification-modal.error .notification-icon {
        color: #ef4444;
    }

    .notification-modal.error {
        border-left: 5px solid #ef4444;
    }

    .notification-title {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 10px;
        color: #1f2937;
    }

    .notification-message {
        font-size: 16px;
        color: #6b7280;
        margin-bottom: 30px;
        line-height: 1.6;
    }

    .notification-progress {
        height: 3px;
        background: #e5e7eb;
        border-radius: 3px;
        overflow: hidden;
        margin-top: 20px;
    }

    .notification-progress-bar {
        height: 100%;
        animation: progress linear 2s forwards;
        border-radius: 3px;
    }

    .notification-modal.success .notification-progress-bar {
        background: linear-gradient(90deg, #10b981, #059669);
    }

    .notification-modal.error .notification-progress-bar {
        background: linear-gradient(90deg, #ef4444, #dc2626);
    }

    @keyframes progress {
        from {
            width: 100%;
        }
        to {
            width: 0%;
        }
    }

    .notification-button {
        margin-top: 20px;
        padding: 10px 30px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        width: auto;
    }

    .notification-modal.success .notification-button {
        background: #10b981;
        color: white;
    }

    .notification-modal.success .notification-button:hover {
        background: #059669;
        transform: translateY(-2px);
    }

    .notification-modal.error .notification-button {
        background: #ef4444;
        color: white;
    }

    .notification-modal.error .notification-button:hover {
        background: #dc2626;
        transform: translateY(-2px);
    }

    .instruction-box {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px 25px;
      border-radius: 12px;
      margin-bottom: 25px;
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .instruction-box h3 {
      margin: 0 0 10px 0;
      font-size: 20px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .instruction-box p {
      margin: 0;
      font-size: 15px;
      line-height: 1.6;
      opacity: 0.95;
    }

    .helper-text {
      background: #f0f9ff;
      border-left: 4px solid #3b82f6;
      padding: 12px 15px;
      margin: 10px 0;
      border-radius: 6px;
      font-size: 14px;
      color: #1e40af;
      line-height: 1.5;
    }

    .info-tooltip {
      background: #fef3c7;
      border: 1px solid #f59e0b;
      padding: 12px 15px;
      border-radius: 8px;
      margin: 15px 0;
      display: flex;
      align-items: flex-start;
      gap: 10px;
      font-size: 13px;
      color: #92400e;
      line-height: 1.5;
    }

    .info-tooltip i {
      color: #f59e0b;
      margin-top: 2px;
      font-size: 16px;
    }

    .section-header {
      background: #f3f4f6;
      padding: 15px 20px;
      border-radius: 8px;
      margin: 20px 0 15px 0;
      border-left: 4px solid #6366f1;
    }

    .section-header h4 {
      margin: 0 0 5px 0;
      color: #374151;
      font-size: 16px;
      font-weight: 700;
    }

    .section-header p {
      margin: 0;
      color: #6b7280;
      font-size: 13px;
    }

    .responsibility-checkboxes {
      background: #f9fafb;
      padding: 20px;
      border-radius: 8px;
      border: 2px solid #e5e7eb;
      margin: 20px 0;
    }

    .responsibility-checkboxes h4 {
      margin: 0 0 15px 0;
      color: #374151;
      font-size: 16px;
      font-weight: 700;
    }

    .responsibility-option {
      margin: 12px 0;
      padding: 15px 18px;
      background: white;
      border-radius: 8px;
      border: 2px solid #d1d5db;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .responsibility-option:hover {
      border-color: #6366f1;
      background: #f9fafb;
    }

    .responsibility-option.selected {
      border-color: #6366f1;
      background: #eef2ff;
    }

    .responsibility-option label {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 600;
      cursor: pointer;
      color: #374151;
      font-size: 15px;
      margin: 0;
      padding: 0;
    }

    .responsibility-option input[type="checkbox"] {
      width: 20px;
      height: 20px;
      cursor: pointer;
      accent-color: #6366f1;
    }

    .responsibility-option .description {
      font-size: 13px;
      color: #6b7280;
      font-weight: normal;
      margin-top: 5px;
      margin-left: 32px;
    }

    #coordinator-course-field {
      margin-top: 15px;
    }
  </style>
</head>

<body>
<?php include("header.php"); ?>
<?php include("menu.php"); ?>

  <div class="form-container">
    <?php
    $courseList = [];
    $courseQuery = "SELECT course_name FROM course ORDER BY course_name";
    $courseResult = mysqli_query($conn, $courseQuery);
    
    if ($courseResult) {
        while ($row = mysqli_fetch_assoc($courseResult)) {
            $courseList[] = $row;
        }
    }
    ?>
    <form method="POST" action="" enctype="multipart/form-data" id="staffForm" novalidate>
      <h2>Add Staff</h2>

      <div>
        <input type="text" name="id_number" id="id_number" placeholder="Enter ID Number" required />
        <div class="error-message" id="id_number-error"></div>
      </div>

      <div>
        <input type="text" name="full_name" id="full_name" placeholder="Enter Full Name" required />
        <div class="error-message" id="full_name-error"></div>
      </div>

      <div>
        <input type="email" name="email" id="email" placeholder="Enter Email" required />
        <div class="error-message" id="email-error"></div>
      </div>

      <div>
        <input type="password" name="password" id="password" placeholder="Enter Password" required />
        <div class="error-message" id="password-error"></div>
      </div>

      <div>
        <select name="gender" id="gender" required>
          <option value="" disabled selected>Select Gender</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
        <div class="error-message" id="gender-error"></div>
      </div>

      <div>
        <input type="date" name="date_of_birth" id="date_of_birth" placeholder="Select Date of Birth" required />
        <div class="error-message" id="date_of_birth-error"></div>
      </div>

      <div>
        <input type="text" name="contact_number" id="contact_number" placeholder="Enter Contact Number" required />
        <div class="error-message" id="contact_number-error"></div>
      </div>

      <div>
        <input type="text" name="address" id="address" placeholder="Enter Address" required />
        <div class="error-message" id="address-error"></div>
      </div>

      <div>
        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required />
        <div class="error-message" id="profile_photo-error"></div>
      </div>

      <div>
        <select name="status" id="status" required>
          <option value="" disabled selected>Select Status</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
        <div class="error-message" id="status-error"></div>
      </div>

      <!-- Responsibility Selection -->
      <div class="responsibility-checkboxes">
        <h4><i class="fas fa-tasks"></i> Assign Responsibilities</h4>

        <div class="responsibility-option" id="teaching-option">
          <label>
            <input type="checkbox" name="assign_teaching" id="assign_teaching">
            <span><i class="fas fa-chalkboard-teacher"></i> Assign Teaching Responsibilities</span>
          </label>
          <div class="description">Enable if the staff member will teach subjects to students.</div>
        </div>

        <div class="responsibility-option" id="coordinator-option">
          <label>
            <input type="checkbox" name="assign_coordinator" id="assign_coordinator">
            <span><i class="fas fa-user-tie"></i> Assign as Course Coordinator</span>
          </label>
          <div class="description">Enable if the staff member will manage or coordinate a course.</div>
        </div>
      </div>

      <!-- Teaching Fields -->
      <div id="teaching-fields">
        <fieldset id="course-group">
          <legend>Select Courses</legend>
          <?php foreach ($courseList as $course): ?>
            <label>
              <input type="checkbox" name="course_name[]" class="course-checkbox" value="<?php echo $course['course_name']; ?>">
              <?php echo htmlspecialchars($course['course_name']); ?>
            </label><br>
          <?php endforeach; ?>
        </fieldset>
        <div class="error-message" id="course-error"></div>

        <fieldset id="semester-group">
          <legend>Select Semesters</legend>
          <?php
            $semesters = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
            foreach ($semesters as $sem) {
                echo '<label><input type="checkbox" name="sem_name[]" class="semester-checkbox" value="' . $sem . '"> ' . $sem . ' Semester</label><br>';
            }
          ?>
        </fieldset>
        <div class="error-message" id="semester-error"></div>
      </div>

      <!-- Coordinator Fields -->
      <div id="coordinator-fields">
       <div id="coordinator-course-field">
          <select name="coordinator_course" id="coordinator_course">
            <option value="" disabled selected>Select Course to Coordinate</option>
            <?php foreach ($courseList as $course): ?>
              <option value="<?php echo $course['course_name']; ?>">
                <?php echo htmlspecialchars($course['course_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="error-message" id="coordinator_course-error"></div>
        </div>
      </div>

      <button type="submit" name="add_staff_btn">Add Staff</button>
    </form>
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
            }
            ?>
        </div>
        <div class="notification-title">
            <?php
            switch ($notification_type) {
                case 'success':
                    echo 'Staff Created Successfully';
                    break;
                case 'error':
                    echo 'Error';
                    break;
            }
            ?>
        </div>
        <div class="notification-message"><?php echo htmlspecialchars($notification); ?></div>
        <?php if ($notification_type === 'success'): ?>
        <div class="helper-text" style="margin: 15px 0; text-align: left;">
          <strong>System Access:</strong> This staff member has been granted system access based on the selected responsibilities. These assignments can be modified at any time from the staff management page.
        </div>
        <?php endif; ?>
        <div class="notification-progress">
            <div class="notification-progress-bar"></div>
        </div>
        <button class="notification-button" onclick="closeNotification()">
            <?php echo ($notification_type === 'success') ? 'Continue' : 'Okay'; ?>
        </button>
    </div>
</div>

<script>
    function closeNotification() {
        const overlay = document.getElementById('notificationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            <?php if ($redirect_url && $notification_type === 'success'): ?>
                window.location.href = '<?php echo $redirect_url; ?>';
            <?php else: ?>
                overlay.classList.remove('active');
            <?php endif; ?>
        }, 300);
    }

    setTimeout(() => {
        closeNotification();
    }, <?php echo $redirect_delay; ?>);
</script>
<?php endif; ?>

<script>
// Toggle responsibility fields
document.addEventListener('DOMContentLoaded', function() {
    const teachingCheckbox = document.getElementById('assign_teaching');
    const coordinatorCheckbox = document.getElementById('assign_coordinator');
    const teachingFields = document.getElementById('teaching-fields');
    const coordinatorFields = document.getElementById('coordinator-fields');
    const teachingOption = document.getElementById('teaching-option');
    const coordinatorOption = document.getElementById('coordinator-option');

    teachingCheckbox.addEventListener('change', function() {
        if (this.checked) {
            teachingFields.style.display = 'block';
            teachingOption.classList.add('selected');
        } else {
            teachingFields.style.display = 'none';
            teachingOption.classList.remove('selected');
        }
    });

    coordinatorCheckbox.addEventListener('change', function() {
        if (this.checked) {
            coordinatorFields.style.display = 'block';
            coordinatorOption.classList.add('selected');
        } else {
            coordinatorFields.style.display = 'none';
            coordinatorOption.classList.remove('selected');
        }
    });
});
</script>

<script src="../js/staff_validation.js"></script>

</body>
</html>
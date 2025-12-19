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
    $role     = 3;
    $staff_type = $_POST['staff_type'];
    
    // Initialize variables
    $semesters = '';
    $course_name = '';
    $course_id_str = '';
    $sem_id_str = '';
    $is_coordinator = 0;
    $coordinator_for = null;

    // Handle Teaching Staff
    if ($staff_type === 'Teaching') {
        // Get selected semesters
        $semesters = isset($_POST['sem_name']) ? implode(",", $_POST['sem_name']) : '';
        
        // Get semester IDs
        $semIds = [];
        if (!empty($_POST['sem_name'])) {
            foreach ($_POST['sem_name'] as $semName) {
                $semQuery = $conn->prepare("SELECT sem_id FROM semester WHERE sem_name = ?");
                $semQuery->bind_param("s", $semName);
                $semQuery->execute();
                $semQuery->bind_result($sid);
                if ($semQuery->fetch()) {
                    $semIds[] = $sid;
                }
                $semQuery->close();
            }
        }
        $sem_id_str = implode(",", $semIds);

        // Get selected courses
        $course_name = isset($_POST['course_name']) ? implode(",", $_POST['course_name']) : '';
        $courseIds = [];

        if (!empty($_POST['course_name'])) {
            $selectedCourses = $_POST['course_name'];
            foreach ($selectedCourses as $courseName) {
                $courseQuery = $conn->prepare("SELECT course_id FROM course WHERE course_name = ?");
                $courseQuery->bind_param("s", $courseName);
                $courseQuery->execute();
                $courseQuery->bind_result($cid);
                if ($courseQuery->fetch()) {
                    $courseIds[] = $cid;
                }
                $courseQuery->close();
            }
        }
        $course_id_str = implode(",", $courseIds);
    }
    // Handle Non-Teaching Staff (Coordinator)
// Handle Non-Teaching Staff (Coordinator)
elseif ($staff_type === 'Non Teaching') {
    $is_coordinator = isset($_POST['is_coordinator']) ? 1 : 0;    
    if ($is_coordinator && isset($_POST['coordinator_course']) && !empty($_POST['coordinator_course'])) {
        $coordinator_course_name = $_POST['coordinator_course'];
        
        // Get course ID for coordinator
        $courseQuery = $conn->prepare("SELECT course_id FROM course WHERE course_name = ?");
        $courseQuery->bind_param("s", $coordinator_course_name);
        $courseQuery->execute();
        $courseQuery->bind_result($coord_course_id);
        if ($courseQuery->fetch()) {
            $coordinator_for = $coord_course_id;
            $course_id_str = (string)$coord_course_id;
            $course_name = $coordinator_course_name;
        }
        $courseQuery->close();
    }
    // If not a coordinator, leave course fields empty (already initialized above)
}
    // Check for existing email or ID
    $check = $conn->prepare("SELECT * FROM users WHERE email=? OR id_number=?");
    $check->bind_param("ss", $email, $id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $notification = "This email or ID is already used.";
        $notification_type = "error";
    } else {
        $photo = '';
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $ext_allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $photo_name = $_FILES['profile_photo']['name'];
            $tmp = $_FILES['profile_photo']['tmp_name'];
            $ext = strtolower(pathinfo($photo_name, PATHINFO_EXTENSION));

            if (in_array($ext, $ext_allowed)) {
                $new_name = uniqid('profile_', true) . '.' . $ext;
                $folder = 'images/uploads/profile_photos/';
                
                // Create directory if it doesn't exist
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

if (empty($notification)) {

    // Ensure coordinator_for is integer and nullable
    $coordinator_for_val = !empty($coordinator_for) ? (int)$coordinator_for : NULL;

    // Prepare the insert statement
    $insert = $conn->prepare("
        INSERT INTO users 
        (full_name, id_number, email, password, role_id, gender, date_of_birth, contact_number, address, 
         course_id, course_name, sem_id, sem_name, profile_photo, status, staff_type, is_coordinator, coordinator_for) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$insert) {
        $notification = "Database error: " . $conn->error;
        $notification_type = "error";
    } else {

        // Bind parameters with correct types
        // FIXED: Changed 5th parameter from 's' to 'i' for role_id
        $insert->bind_param(
            "ssssississssssssii",  // Fixed: role is now 'i' (was 's')
            $name,
            $id,
            $email,
            $pass,
            $role,              // This is an integer
            $gender,
            $dob,
            $phone,
            $address,
            $course_id_str,
            $course_name,
            $sem_id_str,
            $semesters,
            $photo,
            $status,
            $staff_type,
            $is_coordinator,
            $coordinator_for_val
        );

        // Execute and handle result
        
        if ($insert->execute()) {
            $notification = "Staff added successfully!";
            $notification_type = "success";
            $redirect_url = "manage_staff.php";
        } else {
            $notification = "Error: Could not add staff. " . $insert->error;
            $notification_type = "error";
        }

        $insert->close();
    }
}

    $check->close();
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

    /* Hide/Show fields based on staff type */
    #teaching-fields,
    #non-teaching-fields {
      display: none;
    }

    /* Notification Styles */
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

    .coordinator-checkbox {
      margin: 15px 0;
      padding: 18px 20px;
      background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
      border-radius: 8px;
      border: 2px solid #2196f3;
    }

    .coordinator-checkbox label {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 600;
      cursor: pointer;
      color: #1565c0;
      font-size: 16px;
      margin: 0;
      padding: 0;
    }

    .coordinator-checkbox input[type="checkbox"] {
      width: 22px;
      height: 22px;
      cursor: pointer;
      accent-color: #1976d2;
    }

    #coordinator-course-field {
      display: none;
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

      <!-- Staff Type Selection -->
      <div>
        <select name="staff_type" id="staff_type" required>
          <option value="" disabled selected>Select Staff Type</option>
          <option value="Teaching">Teaching Staff</option>
          <option value="Non Teaching">Non-Teaching Staff</option>
        </select>
        <div class="error-message" id="staff_type-error"></div>
      </div>

      <!-- Teaching Staff Fields -->
      <div id="teaching-fields">
        <fieldset id="course-group">
          <legend>Select Courses (Teaching)</legend>
          <?php foreach ($courseList as $course): ?>
            <label>
              <input type="checkbox" name="course_name[]" class="course-checkbox" value="<?php echo $course['course_name']; ?>">
              <?php echo htmlspecialchars($course['course_name']); ?>
            </label><br>
          <?php endforeach; ?>
        </fieldset>
        <div class="error-message" id="course-error"></div>

        <fieldset id="semester-group">
          <legend>Select Semesters (Teaching)</legend>
          <?php
            $semesters = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
            foreach ($semesters as $sem) {
                echo '<label><input type="checkbox" name="sem_name[]" class="semester-checkbox" value="' . $sem . '"> ' . $sem . ' Semester</label><br>';
            }
          ?>
        </fieldset>
        <div class="error-message" id="semester-error"></div>
      </div>

      <!-- Non-Teaching Staff Fields -->
      <div id="non-teaching-fields">
        <div class="coordinator-checkbox">
          <label>
            <input type="checkbox" name="is_coordinator" id="is_coordinator">
            <span>Assign as Course Coordinator</span>
          </label>
        </div>

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

      <div>
        <!-- <label for="profile_photo">Profile Photo:</label> -->
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
                    echo 'Success!';
                    break;
                case 'error':
                    echo 'Error';
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

    // Auto-close after 2 seconds
    setTimeout(() => {
        closeNotification();
    }, <?php echo $redirect_delay; ?>);
</script>
<?php endif; ?>

<script src="../js/staff_validation.js"></script>

</body>
</html>
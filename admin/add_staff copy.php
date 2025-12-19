<?php
session_start();
include("connect.php");
if (!isset($_SESSION['uid'])) {
  header("Location: ../login.php");
  exit();
}

if (isset($_POST['add_staff_btn'])) {
    $id_number = trim($_POST['id_number']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $gender = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    $contact_number = trim($_POST['contact_number']);
    $address = trim($_POST['address']);
    $status = $_POST['status'];
    $course_id = $_POST['course_id'];
    $role_id = 3;
    $check_stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR id_number = ?");
    $check_stmt->bind_param("ss", $email, $id_number);
    $check_stmt->execute();
    $result_check = $check_stmt->get_result();

    if ($result_check->num_rows > 0) {
        $toastMessage = "Staff with this email or ID number already exists.";
        $toastStatus = "error";
    } else {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $allowed_ext = ['jpg','jpeg','png','gif'];
            $file_name = $_FILES['profile_photo']['name'];
            $file_tmp = $_FILES['profile_photo']['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_ext)) {
                $new_file_name = uniqid('profile_', true) . '.' . $file_ext;
                $upload_dir = 'images/uploads/profile_photos/';
                $upload_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $profile_photo = $new_file_name;
                } else {
                    $toastMessage = "Error uploading profile photo.";
                    $toastStatus = "error";
                }
            } else {
                $toastMessage = "Invalid file type for profile photo.";
                $toastStatus = "error";
            }
        } else {
            $toastMessage = "Profile photo is required.";
            $toastStatus = "error";
        }

        if (empty($toastMessage)) {
          $sem_name = isset($_POST['sem_name']) ? implode(",", $_POST['sem_name']) : '';
      
          $stmt = $conn->prepare("INSERT INTO users (full_name, id_number, email, password, role_id, gender, date_of_birth, contact_number, address, course_id, profile_photo, status, sem_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
          $stmt->bind_param("ssssissssssss", $full_name, $id_number, $email, $password, $role_id, $gender, $date_of_birth, $contact_number, $address, $course_id, $profile_photo, $status, $sem_name);
            if ($stmt->execute()) {
                $toastMessage = "Staff added successfully.";
                $toastStatus = "success";
            } else {
                $toastMessage = "Error adding staff: " . $stmt->error;
                $toastStatus = "error";
            }
            $stmt->close();
        }
    }
    $check_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/add_staff.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <style>
  .staff-error {
    color: red;
    font-size: 0.85em;
    margin-top: 4px;
  }
  #semester-error {
  margin-top: 6px;
  color: red;
  font-size: 0.95em;
  font-weight: 500;
}
</style>

</head>

<body>
<?php include("header.php"); ?>

<div class="page-wrapper">
  <div class="sidebar">
    <div class="sidebar-header">
      <h2>Admin Panel</h2>
    </div>
    <ul class="sidebar-menu">
      <li><a href="homepage.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>

      <li class="dropdown">
        <a href="#" class="dropdown-toggle"><i class="fas fa-book"></i> Course <i class="fas fa-caret-down right"></i></a>
        <ul class="dropdown-content">
          <li><a href="add_course.php">Add Course</a></li>
          <li><a href="manage_courses.php">Manage Course</a></li>
        </ul>
      </li>

      <li class="dropdown">
        <a href="#" class="dropdown-toggle"><i class="fas fa-book-open"></i> Subject <i class="fas fa-caret-down right"></i></a>
        <ul class="dropdown-content">
          <li><a href="add_subject.php">Add Subject</a></li>
          <li><a href="manage_subjects.php">Manage Subject</a></li>
        </ul>
      </li>

      <li class="dropdown">
        <a href="#" class="dropdown-toggle"><i class="fas fa-calendar-alt"></i> Session <i class="fas fa-caret-down right"></i></a>
        <ul class="dropdown-content">
          <li><a href="add_session.php">Add Session</a></li>
          <li><a href="manage_sessions.php">Manage Session</a></li>
        </ul>
      </li>

      <li><a href="add_staff.php"><i class="fas fa-user-plus"></i> Add Staff</a></li>
      <li><a href="manage_staff.php"><i class="fas fa-users-cog"></i> Manage Staff</a></li>
      <li><a href="manage_student.php"><i class="fas fa-user-friends"></i> Manage Student</a></li>
      <li><a href="notify_staff.php"><i class="fas fa-bell"></i> Notify Staff</a></li>
      <li><a href="notify_student.php"><i class="fas fa-bell"></i> Notify Student</a></li>
      <li><a href="view_attendance.php"><i class="fas fa-calendar-check"></i> View Attendance</a></li>
      <li><a href="student_feedback.php"><i class="fas fa-comment-dots"></i> Student Feedback</a></li>
      <li><a href="staff_feedback.php"><i class="fas fa-comments"></i> Staff Feedback</a></li>
      <li><a href="student_leave.php"><i class="fas fa-user-clock"></i> Student Leave</a></li>
      <li><a href="staff_leave.php"><i class="fas fa-user-clock"></i> Staff Leave</a></li>
      <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </div>

  <div class="form-container">
    <?php
    $courseList = [];
    $courseQuery = "SELECT course_id, course_name FROM course ORDER BY course_name";
    $courseResult = mysqli_query($conn, $courseQuery);
    
    if ($courseResult) {
        while ($row = mysqli_fetch_assoc($courseResult)) {
            $courseList[] = $row;
        }
    }
    ?>
  <form method="POST" action="" enctype="multipart/form-data">
    <h2>Add Staff</h2>

    <input type="text" name="id_number" placeholder="Enter ID Number" required />

    <input type="text" name="full_name" placeholder="Enter Full Name" required />

    <input type="email" name="email" placeholder="Enter Email" required />

    <input type="password" name="password" placeholder="Enter Password" required />

    <select name="gender" required>
      <option value="" disabled selected>Select Gender</option>
      <option value="Male">Male</option>
      <option value="Female">Female</option>
      <option value="Other">Other</option>
    </select>

    <input type="date" name="date_of_birth" placeholder="Select Date of Birth" required />
    <input type="text" name="contact_number" placeholder="Enter Contact Number" required />
    <input type="text" name="address" placeholder="Enter Address" required />
    <select name="course_id" required>
  <option value="" disabled selected>Select Course</option>
  <?php foreach ($courseList as $course): ?>
    <option value="<?php echo $course['course_id']; ?>">
      <?php echo htmlspecialchars($course['course_name']); ?>
    </option>
    
  <?php endforeach; ?>
</select>
<fieldset id="semester-group">
  <legend>Select Semesters</legend>
  <?php
    $semesters = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
    foreach ($semesters as $sem) {
        echo '<label><input type="checkbox" name="sem_name[]" value="' . $sem . '"> ' . $sem . ' Semester</label><br>';
    }
  ?>
</fieldset>
<div id="semester-error" class="staff-error"></div>
    <label for="profile_photo">Profile Photo:</label>
    <input type="file" name="profile_photo" accept="image/*" required />
    <select name="status" required>
      <option value="" disabled selected>Select Status</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>
    <button type="submit" name="add_staff_btn">Add Staff</button>
  </form>
  </div>
  </div>
<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<!-- Sidebar Dropdown Script -->
<script>
  document.querySelectorAll('.dropdown-toggle').forEach(button => {
    button.addEventListener('click', function (e) {
      e.preventDefault();
      const dropdown = this.nextElementSibling;
      document.querySelectorAll('.dropdown-content').forEach(menu => {
        if (menu !== dropdown) menu.style.display = 'none';
      });
      dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    });
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.dropdown')) {
      document.querySelectorAll('.dropdown-content').forEach(menu => {
        menu.style.display = 'none';
      });
    }
  });
</script>

<!-- Toast Message -->
<div id="toast"></div>

<!-- Inject toast message if available -->
<?php if (!empty($message)): ?>
<script>
  const toastMessage = "<?php echo addslashes($message); ?>";
</script>
<?php endif; ?>

<!-- Always include script -->
<script src="../js/toast_script.js" defer></script>
<script src="../js/validate_staff.js" defer></script>

</body>
</html>
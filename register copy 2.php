<?php 
include("connect.php"); 
$query = "SELECT course_id, course_name FROM course";
$result = $conn->query($query);

$semQuery = "SELECT sem_id, sem_name FROM semester";
$semResult = $conn->query($semQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register - College Management System</title>
  <link rel="stylesheet" href="css/register.css" />
  <link rel="icon" href="Prime-College-Logo.ico" type="image/x-icon">

  <style>
    .error-message { color: red; font-size: 0.85em; margin-top: 4px; }
  </style>
</head>
<body>

<?php include("header.php"); ?>

<div class="login-container">
<form id="registerForm" action="register.php" method="post" enctype="multipart/form-data" novalidate>
  <h2>Register Form</h2>

  <div class="form-group">
    <label for="full_name">Full Name</label>
    <input type="text" id="full_name" name="full_name" required />
  </div>

  <div class="form-group">
    <label for="id_number">Student ID</label>
    <input type="text" id="id_number" name="id_number" required />
  </div>

  <div class="form-group">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" required />
  </div>

  <div class="form-group">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required />
  </div>

  <div class="form-group">
    <label for="confirm_password">Confirm Password</label>
    <input type="password" id="confirm_password" name="confirm_password" required />
  </div>

<div class="form-group">
    <label for="course_id">Select Course</label>
    <select name="course_id" id="course_id" required>
        <option value="">Select Course</option>
        <?php while($row = $result->fetch_assoc()): ?>
            <option value="<?php echo $row['course_id']; ?>">
                <?php echo htmlspecialchars($row['course_name']); ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>
<div class="form-group">
  <label for="sem_id">Select Semester</label>
  <select name="sem_id" id="sem_id" required>
    <option value="">Select Semester</option>
    <?php while($row = $semResult->fetch_assoc()): ?>
      <option value="<?php echo $row['sem_id']; ?>">
        <?php echo htmlspecialchars($row['sem_name']); ?>
      </option>
    <?php endwhile; ?>
  </select>
</div>

  <div class="form-group">
    <label for="gender">Gender</label>
    <select id="gender" name="gender" required>
      <option value="">Select Gender</option>
      <option value="Male">Male</option>
      <option value="Female">Female</option>
      <option value="Others">Others</option>
    </select>
  </div>

  <div class="form-group">
    <label for="dob">Date of Birth</label>
    <input type="date" id="dob" name="date_of_birth" required max="<?php echo date('Y-m-d'); ?>" />
  </div>

  <div class="form-group">
    <label for="contact_number">Contact Number</label>
    <input type="tel" id="contact_number" name="contact_number" required pattern="\d{10,15}" placeholder="Digits only" />
  </div>

  <div class="form-group">
    <label for="address">Address</label>
    <input type="text" id="address" name="address" required />

  </div>

  <div class="form-group">
    <label for="profile_photo">Profile Picture</label>
    <input type="file" id="profile_photo" name="profile_photo" accept="image/*" required />
  </div>

  <button type="submit" name="register">Register</button>
</form>
</div>

<?php include("footer.php"); ?>

<script>
  // Simple JS password confirmation check before submit
  document.getElementById('registerForm').addEventListener('submit', function(e) {
    const pw = document.getElementById('password').value;
    const cpw = document.getElementById('confirm_password').value;
    if (pw !== cpw) {
      alert('Passwords do not match!');
      e.preventDefault();
    }
  });
</script>

</body>
</html>
<?php
if (isset($_POST['register'])) {
    // Sanitize and assign inputs
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $id_number = mysqli_real_escape_string($conn, trim($_POST['id_number']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $course_id = $_POST['course_id']; // should match the name="course_id"
    $sem_id = $_POST['sem_id']; // should match the name="course_id"
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $dob = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    $contact_number = mysqli_real_escape_string($conn, trim($_POST['contact_number']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $role_id = 2;
    
    if (empty($course_id)) {
      die("Please select a course.");
    }
    if (empty($sem_id)) {
    die("Please select a semester.");
}

    // Validate passwords match (extra security)
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match'); window.history.back();</script>";
        exit();
    }
  
    // Handle profile photo upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_photo']['tmp_name'];
        $file_name = $_FILES['profile_photo']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];

        if (!in_array($ext, $allowed)) {
            echo "<script>alert('Invalid image format. Allowed: jpg, jpeg, png, gif'); window.history.back();</script>";
            exit();
        }

        $new_file_name = uniqid() . "." . $ext;
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $upload_path = $upload_dir . $new_file_name;

        if (!move_uploaded_file($file_tmp, $upload_path)) {
            echo "<script>alert('Failed to upload profile picture'); window.history.back();</script>";
            exit();
        }
    } else {
        echo "<script>alert('Profile picture is required'); window.history.back();</script>";
        exit();
    }

    // Default status 0 = pending approval
    $status = 0;

    // Check if email or id_number already exists to avoid duplicates
    $check_sql = "SELECT * FROM users WHERE email='$email' OR id_number='$id_number'";
    $check_result = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($check_result) > 0) {
        echo "<script>alert('Email or ID number already registered'); window.history.back();</script>";
        exit();
    }

    // Insert into database
    $insert_sql = "INSERT INTO users 
  (full_name, id_number, email, password, role_id, gender, date_of_birth, contact_number, address, course_id, sem_id, profile_photo, status)
VALUES 
  ('$full_name', '$id_number', '$email', '$password', '$role_id', '$gender', '$dob', '$contact_number', '$address','$course_id', '$sem_id', '$new_file_name', '$status')";

    if (mysqli_query($conn, $insert_sql)) {
        echo "<script>alert('Registration successful! Please wait for admin approval.'); window.location.href='login.php';</script>";
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<?php
session_start();
include("connect.php");

// Assume Super Admin is logged in and their user_id is stored in session
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $query = "SELECT password FROM users WHERE user_id = '1'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);

    if ($row && $current_pass === $row['password']) {
        if ($new_pass === $confirm_pass) {
            $update = "UPDATE users SET password = '$new_pass' WHERE user_id = '1'";
            if (mysqli_query($conn, $update)) {
                $msg = "Password changed successfully.";
            } else {
                $msg = "Error updating password.";
            }
        } else {
            $msg = "New passwords do not match.";
        }
    } else {
        $msg = "Current password is incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Password</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/change_password.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <style>
   #toast {
      visibility: hidden;
      background-color: green;
      color: white;
      padding: 12px 20px;
      position: fixed;
      left: 50%;
      bottom: 30px;
      transform: translateX(-50%);
      border-radius: 8px;
      font-size: 16px;
      z-index: 1000;
      opacity: 0;
      transition: opacity 0.5s ease, bottom 0.5s ease;
    }
    #toast.show {
      visibility: visible;
      opacity: 1;
      bottom: 50px;
    }
  </style>
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="change-password-container">
        <h2>Change Password</h2>
        <form method="POST">
            <label>Current Password:</label>
            <input type="password" name="current_password" required><br>

            <label>New Password:</label>
            <input type="password" name="new_password" required><br>

            <label>Confirm New Password:</label>
            <input type="password" name="confirm_password" required><br>

            <button type="submit" class="update-btn">Update Password</button>
            <a href="dashboard.php" class="btn-cancel">Cancel</a>
        </form>
    </div>
    </div>
<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<!-- Toast Message -->
<div id="toast"></div>

<!-- Inject toast message if available -->
<?php if (!empty($msg)): ?>
<script>
    const toastMessage = "<?= addslashes($msg); ?>";
</script>
<?php else: ?>
<script>
    const toastMessage = "";
</script>
<?php endif; ?>
<!-- Always include script -->
<script src="../js/toast_script.js" defer></script>

</body>
</html>

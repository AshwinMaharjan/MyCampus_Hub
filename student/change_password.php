<?php
session_start();
include("connect.php");

$msg = '';
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}
$student_id = $_SESSION['uid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row && $current_pass === $row['password']) {
        if ($new_pass === $confirm_pass) {
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_pass, $student_id);

            if ($stmt->execute()) {
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

<div class="page-wrapper">
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

<div id="toast"></div>

<?php if (!empty($msg)): ?>
<script>
    const toastMessage = "<?= addslashes($msg); ?>";
</script>
<?php else: ?>
<script>
    const toastMessage = "";
</script>
<?php endif; ?>
<script src="../js/toast_script.js" defer></script>

</body>
</html>

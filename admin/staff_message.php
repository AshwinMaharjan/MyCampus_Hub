<?php
session_start();
include("connect.php");
if (!isset($_SESSION['uid'])) {
  header("Location: ../login.php");
  exit();
}?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff Message</title>
  <link rel="stylesheet" href="../css/all.min.css">
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">

  <link rel="stylesheet" href="../css/admin_menu.css">
</head>

<body>
<?php include("header.php"); ?>

<?php include("menu.php"); ?>
  <div class="main-content">
    <h1>Welcome Super Admin</h1>
    <p>This is your dashboard homepage.</p>
  </div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>
</body>
</html>

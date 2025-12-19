<?php
include("connect.php");
include ("auth_check.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Profile</title>
  <link rel="stylesheet" href="../css/user_profile.css">
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="../css/admin_menu.css" />
</head>
<body>
<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="profile-container">
<?php
if (isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $query = "SELECT * FROM users WHERE user_id = $user_id";
    $result = mysqli_query($conn, $query);

    if ($row = mysqli_fetch_assoc($result)) {
        echo "<img src='../admin/images/uploads/profile_photos//{$row['profile_photo']}' alt='Profile Photo'>";

        echo "<div class='field'><strong>Full Name:</strong> " . htmlspecialchars($row['full_name']) . "</div>";
        echo "<div class='field'><strong>ID:</strong> " . htmlspecialchars($row['id_number']) . "</div>";
        echo "<div class='field'><strong>Email:</strong> " . htmlspecialchars($row['email']) . "</div>";
        echo "<div class='field'><strong>Gender:</strong> " . htmlspecialchars($row['gender']) . "</div>";
        echo "<div class='field'><strong>DOB:</strong> " . htmlspecialchars($row['date_of_birth']) . "</div>";
        echo "<div class='field'><strong>Contact:</strong> " . htmlspecialchars($row['contact_number']) . "</div>";
        echo "<div class='field'><strong>Address:</strong> " . htmlspecialchars($row['address']) . "</div>";
        echo "<div class='field'><strong>Course:</strong> " . htmlspecialchars($row['course_name']) . "</div>";
        echo "<div class='field'><strong>Status:</strong> " . htmlspecialchars($row['status']) . "</div>";
    } else {
        echo "<p>User not found.</p>";
    }
} else {
    echo "<p>Invalid request.</p>";
}
?>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>
</body>
</html>
<?php
include("connect.php");
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
  <style>
    .status.active {
    color: green;
    font-weight: bold;
}

.status.inactive {
    color: red;
    font-weight: bold;
}
  </style>
</head>
<body>
<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="profile-container">
<?php
if (isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
$query = "SELECT u.*, c.course_name, s.sem_name
          FROM users u
          LEFT JOIN course c ON u.course_id = c.course_id
          LEFT JOIN semester s ON u.sem_id = s.sem_id
          WHERE u.user_id = $user_id";

    $result = mysqli_query($conn, $query);

    if ($row = mysqli_fetch_assoc($result)) {
        echo "<img src='images/uploads/profile_photos/{$row['profile_photo']}' alt='Profile Photo'>";
        echo "<div class='field'><strong>Full Name:</strong> " . htmlspecialchars($row['full_name']) . "</div>";
        echo "<div class='field'><strong>ID:</strong> " . htmlspecialchars($row['id_number']) . "</div>";
        echo "<div class='field'><strong>Email:</strong> " . htmlspecialchars($row['email']) . "</div>";
        echo "<div class='field'><strong>Gender:</strong> " . htmlspecialchars($row['gender']) . "</div>";
        echo "<div class='field'><strong>DOB:</strong> " . htmlspecialchars($row['date_of_birth']) . "</div>";
        echo "<div class='field'><strong>Contact:</strong> " . htmlspecialchars($row['contact_number']) . "</div>";
        echo "<div class='field'><strong>Address:</strong> " . htmlspecialchars($row['address']) . "</div>";
        echo "<div class='field'><strong>Course:</strong> " . htmlspecialchars($row['course_name']) . "</div>";
        echo "<div class='field'><strong>Semester:</strong> " . htmlspecialchars($row['sem_name']) . "</div>";
$status = htmlspecialchars($row['status']);
$statusClass = strtolower($status);

echo "<div class='field status {$statusClass}'><strong>Status:</strong> {$status}</div>";

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

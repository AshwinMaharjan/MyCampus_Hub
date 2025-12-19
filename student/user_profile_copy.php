<?php
include("connect.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Profile</title>
  <link rel="stylesheet" href="../css/user_profile_copy.css">
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="../css/admin_menu.css" />
</head>
<style>
    .status-active {
    color: green;
    font-weight: bold;
}

.status-inactive {
    color: red;
    font-weight: bold;
}

</style>
<body>
<?php include("header.php"); ?>
<div class="page-wrapper">

<?php include("menu.php"); ?>

<div class="profile-container">
<?php
if (isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    
    $query = "
        SELECT 
            u.*,
            c.course_name,
            s.sem_name
        FROM users u
        LEFT JOIN course c ON u.course_id = c.course_id
        LEFT JOIN semester s ON u.sem_id = s.sem_id
        WHERE u.user_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $profile_photo = !empty($row['profile_photo']) ? $row['profile_photo'] : 'default_profile.png';
        $profile_path = "../uploads/{$profile_photo}";
        
        if (!file_exists($profile_path)) {
            $profile_path = "../uploads/default_profile.png";
        }
        
        echo "<img src='{$profile_path}' alt='Profile Photo' onerror=\"this.src='../uploads/default_profile.png'\">";
        echo "<div class='field'><strong>Full Name:</strong> " . htmlspecialchars($row['full_name']) . "</div>";
        echo "<div class='field'><strong>ID:</strong> " . htmlspecialchars($row['id_number']) . "</div>";
        echo "<div class='field'><strong>Email:</strong> " . htmlspecialchars($row['email']) . "</div>";
        echo "<div class='field'><strong>Gender:</strong> " . htmlspecialchars($row['gender']) . "</div>";
        echo "<div class='field'><strong>DOB:</strong> " . htmlspecialchars($row['date_of_birth']) . "</div>";
        echo "<div class='field'><strong>Contact:</strong> " . htmlspecialchars($row['contact_number']) . "</div>";
        echo "<div class='field'><strong>Address:</strong> " . htmlspecialchars($row['address']) . "</div>";
        
        $course_name = !empty($row['course_name']) ? $row['course_name'] : 'Not Assigned';
        $sem_name = !empty($row['sem_name']) ? $row['sem_name'] : 'Not Assigned';
        
        echo "<div class='field'><strong>Course:</strong> " . htmlspecialchars($course_name) . "</div>";
        echo "<div class='field'><strong>Semester:</strong> " . htmlspecialchars($sem_name) . "</div>";
        $status = htmlspecialchars($row['status']);
        $status_class = strtolower($status) === 'active' ? 'status-active' : 'status-inactive';
        echo "<div class='field'><strong>Status:</strong> <span class='{$status_class}'>" . $status . "</span></div>";
        
    } else {
        echo "<p>User not found.</p>";
    }
    
    $stmt->close();
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
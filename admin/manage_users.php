<?php include("connect.php"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Users</title>
  <link rel="stylesheet" href="../css/all.min.css">
  <link rel="stylesheet" href="../css/admin_menu.css">
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">

  <link rel="stylesheet" href="../css/approve_registrations.css">

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
      <li><a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
      <li><a href="approve_registrations.php"><i class="fas fa-user-check"></i> Approve Registrations</a></li>
      <li><a href="manage_courses.php"><i class="fas fa-book"></i> Manage Courses</a></li>
      <li><a href="manage_departments.php"><i class="fas fa-building"></i> Manage Departments</a></li>
      <li><a href="manage_subjects.php"><i class="fas fa-book-open"></i> Manage Subjects</a></li>
      <li><a href="attendance_records.php"><i class="fas fa-calendar-check"></i> Attendance Records</a></li>
      <li><a href="upload_materials.php"><i class="fas fa-upload"></i> Upload Materials</a></li>
      <li><a href="view_reports.php"><i class="fas fa-chart-line"></i> Reports / Logs</a></li>
      <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Admin Settings</a></li>
      <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </div>

  <div class="main-content">
    <h1>Manage Users</h1>
    <table>
      <thead>
        <tr>
          <th>User ID</th>
          <th>Profile</th>
          <th>Full Name</th>
          <th>ID Number</th>
          <th>Email</th>
          <th>Gender</th>
          <th>Contact</th>
          <th>Address</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $query = "SELECT * FROM users WHERE role_id IN (2,3) ORDER BY user_id DESC";
        $result = mysqli_query($conn, $query);

        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>{$row['user_id']}</td>";
            echo "<td>";
            if (!empty($row['profile_photo'])) {
                echo "<a href='../student/user_profile.php?user_id={$row['user_id']}'><img src='../uploads/{$row['profile_photo']}' class='profile-pic' alt='Profile' /></a>";
            } else {
                echo "<a href='user_profile.php?user_id={$row['user_id']}'><img src='../images/default_profile.png' class='profile-pic' alt='No Photo' /></a>";
            }
            echo "</td>";

            echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['id_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['gender']) . "</td>";
            echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['address']) . "</td>";
          
            echo "<td>";
            if ($row['status'] == 'Active') {
                echo "<span style='color: green; font-weight: bold;'>Approved</span>";
            } elseif ($row['status'] == 'Inactive') {
                echo "<span style='color: red; font-weight: bold;'>Declined</span>";
            } else {
                echo "<form method='POST' action='update_user_status.php' style='display:inline-block; margin-right:5px;'>
                        <input type='hidden' name='user_id' value='{$row['user_id']}'>
                        <button type='submit' name='action' value='approve' class='action-btn approve'>Approve</button>
                      </form>";
                echo "<form method='POST' action='update_user_status.php' style='display:inline-block;'>
                        <input type='hidden' name='user_id' value='{$row['user_id']}'>
                        <button type='submit' name='action' value='decline' class='action-btn decline'>Decline</button>
                      </form>";
            }
            echo "</td>";
                                              
            echo "</tr>";
          }
          
        ?>
      </tbody>
    </table>
  </div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

</body>
</html>

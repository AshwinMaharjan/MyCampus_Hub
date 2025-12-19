<?php
session_start();
include("connect.php");
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        header("Location: view_notification.php?msg=deleted");
        exit;
    } else {
        $error = "Error deleting notification: " . $stmt->error;
    }
    $stmt->close();
}

$showToast = false;
$toastMessage = "";
$toastStatus = "";

if (isset($_GET['msg'])) {
    $showToast = true;
    if ($_GET['msg'] === 'deleted') {
        $toastMessage = "Notification deleted successfully.";
        $toastStatus = "success";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>View Notifications</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/notify_staff.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <style>
    #toast {
      visibility: hidden;
      min-width: 250px;
      background-color: green;
      color: #fff;
      text-align: center;
      border-radius: 8px;
      padding: 16px 24px;
      position: fixed;
      z-index: 1000;
      left: 50%;
      bottom: 30px;
      transform: translateX(-50%);
      font-size: 16px;
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

<div class="main-content">
  <h1 style="color: #263576;">View Notifications</h1>

  <?php if (!empty($error)) echo "<p style='color:red; font-weight:bold;'>" . htmlspecialchars($error) . "</p>"; ?>

  <table>
    <thead>
      <tr>
        <th>User ID</th>
        <th>Profile</th>
        <th>Full Name</th>
        <th>ID Number</th>
        <th>Message</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php
      $query = "SELECT n.id, n.message, n.user_id, u.full_name, u.id_number, u.profile_photo, u.role_id 
      FROM notifications n 
      JOIN users u ON n.user_id = u.user_id 
      ORDER BY n.date_sent DESC";

      $result = mysqli_query($conn, $query);

      while ($row = mysqli_fetch_assoc($result)) {
          echo "<tr>";
          echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
          $role_id = $row['role_id'];
          $profile = !empty($row['profile_photo']) ? $row['profile_photo'] : 'default_profile.png';
          
          if ($role_id == 3) {
              $profilePath = "images/uploads/profile_photos/$profile";
          } if ($role_id == 2){
              $profilePath = "../uploads/$profile";
          }          
          echo "<td><img src='$profilePath' class='profile-pic' alt='Profile'></td>";
          
          echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
          echo "<td>" . htmlspecialchars($row['id_number']) . "</td>";
          echo "<td style='text-align:left;'>" . nl2br(htmlspecialchars($row['message'])) . "</td>";
          echo "<td>
                  <a href='edit_notification.php?id={$row['id']}' class='btn btn-edit'><i class='fas fa-edit'></i> Edit</a>
                  <a href='view_notification.php?delete_id={$row['id']}' onclick=\"return confirm('Are you sure you want to delete this?');\" class='btn btn-delete'><i class='fas fa-trash'></i> Delete</a>
                </td>";
          echo "</tr>";
      }
    ?>
    </tbody>
  </table>
  </div>
  </div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<div id="toast"></div>
<script>
  function showToast(message, status) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.style.backgroundColor = status === 'success' ? 'green' : 'red';
    toast.classList.add('show');
    setTimeout(() => {
      toast.classList.remove('show');
    }, 5000);
  }

  <?php if ($showToast): ?>
  window.onload = function() {
    showToast("<?= addslashes($toastMessage); ?>", "<?= $toastStatus; ?>");
  };
  <?php endif; ?>
</script>

</body>
</html>

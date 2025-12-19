<?php
session_start();
include("connect.php"); 
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$notification = null;
$notification_type = null;
$show_confirmation = false;
$delete_id_to_confirm = null;
$message_to_delete = null;
$redirect_delay = 2000;

// Handle delete notification if confirmed
if (isset($_GET['delete_id']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $notification = "Notification deleted successfully.";
        $notification_type = "success";
    } else {
        $notification = "Error deleting notification.";
        $notification_type = "error";
    }
    $stmt->close();
}
// Show confirmation modal if delete_id is present without confirmation
elseif (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT message FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result_check = $stmt->get_result();
    if ($result_check->num_rows > 0) {
        $row = $result_check->fetch_assoc();
        $show_confirmation = true;
        $delete_id_to_confirm = $delete_id;
        $message_to_delete = substr($row['message'], 0, 100) . (strlen($row['message']) > 100 ? '...' : '');
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - View Notifications</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/view_notification.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
  <div class="page-header">
    <div class="header-content">
      <h1><i class="fas fa-bell"></i> View Notifications</h1>
      <p class="header-subtitle">Manage sent notifications and messages</p>
    </div>
  </div>

  <div class="table-container">
    <table class="notifications-table">
      <thead>
        <tr>
          <th><i class="fas fa-user"></i> Profile</th>
          <th><i class="fas fa-id-card"></i> Full Name</th>
          <th><i class="fas fa-barcode"></i> ID Number</th>
          <th><i class="fas fa-envelope"></i> Message</th>
          <th><i class="fas fa-cogs"></i> Action</th>
        </tr>
      </thead>
      <tbody>
      <?php
        $query = "SELECT n.id, n.message, n.user_id, u.full_name, u.id_number, u.profile_photo, u.role_id 
        FROM notifications n 
        JOIN users u ON n.user_id = u.user_id 
        ORDER BY n.date_sent DESC";

        $result = mysqli_query($conn, $query);
        $notification_count = mysqli_num_rows($result);

        if ($notification_count > 0) {
          while ($row = mysqli_fetch_assoc($result)) {
              echo "<tr>";
              
              $role_id = $row['role_id'];
              $profile = !empty($row['profile_photo']) ? $row['profile_photo'] : 'default_profile.png';
              
              if ($role_id == 3) {
                  $profilePath = "images/uploads/profile_photos/$profile";
              } elseif ($role_id == 2){
                  $profilePath = "../uploads/$profile";
              } else {
                  $profilePath = "images/default_profile.png";
              }
              
              echo "<td class='profile-cell'>
                      <a href='user_profile.php?user_id={$row['user_id']}' class='profile-link'>
                        <img src='$profilePath' class='profile-pic' alt='Profile'>
                      </a>
                    </td>";
              
              echo "<td class='staff-name'>" . htmlspecialchars($row['full_name']) . "</td>";
              echo "<td class='id-number'>" . htmlspecialchars($row['id_number']) . "</td>";
              echo "<td class='message-cell'>" . nl2br(htmlspecialchars($row['message'])) . "</td>";
              echo "<td class='action-cell'>
                      <a href='edit_notification.php?id={$row['id']}' class='btn btn-edit'>
                        <i class='fas fa-edit'></i> Edit
                      </a>
                      <a href='view_notification.php?delete_id={$row['id']}' class='btn btn-delete'>
                        <i class='fas fa-trash'></i> Delete
                      </a>
                    </td>";
              echo "</tr>";
          }
        } else {
          echo "<tr><td colspan='5' class='no-data'><i class='fas fa-inbox'></i> No notifications found</td></tr>";
        }
      ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<!-- Confirmation Modal -->
<?php if ($show_confirmation): ?>
<div class="notification-overlay active" id="confirmationOverlay">
    <div class="notification-modal confirm">
        <div class="notification-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="notification-title">Delete Notification?</div>
        <div class="notification-message">Are you sure you want to delete this notification?</div>
        <div class="confirmation-message-preview">
            <strong>Message:</strong> <?php echo htmlspecialchars($message_to_delete); ?>
        </div>
        <div class="notification-buttons">
            <button class="notification-button notification-button-cancel" onclick="cancelDelete()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="notification-button notification-button-confirm" onclick="confirmDelete(<?php echo $delete_id_to_confirm; ?>)">
                <i class="fas fa-check"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
    function cancelDelete() {
        const overlay = document.getElementById('confirmationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            overlay.classList.remove('active');
        }, 300);
    }

    function confirmDelete(notificationId) {
        window.location.href = 'view_notification.php?delete_id=' + notificationId + '&confirm=yes';
    }
</script>
<?php endif; ?>

<!-- Notification Modal (Success/Error) -->
<?php if ($notification): ?>
<div class="notification-overlay active" id="notificationOverlay">
    <div class="notification-modal <?php echo $notification_type; ?>">
        <div class="notification-icon">
            <?php
            switch ($notification_type) {
                case 'success':
                    echo '<i class="fas fa-check-circle"></i>';
                    break;
                case 'error':
                    echo '<i class="fas fa-times-circle"></i>';
                    break;
            }
            ?>
        </div>
        <div class="notification-title">
            <?php
            switch ($notification_type) {
                case 'success':
                    echo 'Success!';
                    break;
                case 'error':
                    echo 'Error';
                    break;
            }
            ?>
        </div>
        <div class="notification-message"><?php echo htmlspecialchars($notification); ?></div>
        <div class="notification-progress">
            <div class="notification-progress-bar"></div>
        </div>
        <button class="notification-button" onclick="closeNotification()">
            Okay
        </button>
    </div>
</div>

<script>
    function closeNotification() {
        const overlay = document.getElementById('notificationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            overlay.classList.remove('active');
        }, 300);
    }

    setTimeout(() => {
        closeNotification();
    }, <?php echo $redirect_delay; ?>);
</script>
<?php endif; ?>

</body>
</html>
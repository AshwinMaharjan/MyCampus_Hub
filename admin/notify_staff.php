<?php
session_start();
include("connect.php"); 
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$notification = null;
$notification_type = null;
$redirect_delay = 2000;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['message'], $_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $message = trim($_POST['message']);

    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $message);
        if ($stmt->execute()) {
            $notification = "Staff notified successfully!";
            $notification_type = "success";
        } else {
            $notification = "Failed to send message.";
            $notification_type = "error";
        }
        $stmt->close();
    }
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $notification = "Staff deleted successfully.";
        $notification_type = "success";
    } else {
        $notification = "Error deleting staff.";
        $notification_type = "error";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - Notify Staff</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/notify_staff.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
  <div class="page-header">
    <div class="header-content">
      <h1><i class="fas fa-bell"></i> Notify Staff</h1>
      <p class="header-subtitle">Send messages and notifications to your staff members</p>
    </div>
  </div>

  <div class="table-container">
    <table class="staff-table">
      <thead>
        <tr>
          <th><i class="fas fa-user"></i> Profile</th>
          <th><i class="fas fa-id-card"></i> Full Name</th>
          <th><i class="fas fa-barcode"></i> ID Number</th>
          <th><i class="fas fa-envelope"></i> Email</th>
          <th><i class="fas fa-cogs"></i> Action</th>
        </tr>
      </thead>
      <tbody>
      <?php
        $query = "SELECT users.*, course.course_name 
                  FROM users 
                  LEFT JOIN course ON users.course_id = course.course_id 
                  WHERE users.role_id = 3
                  ORDER BY users.user_id DESC";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
          while ($row = mysqli_fetch_assoc($result)) {
              echo "<tr>";
              echo "<td class='profile-cell'>
                      <a href='user_profile.php?user_id={$row['user_id']}' class='profile-link'>
                        <img src='images/" . (!empty($row['profile_photo']) ? "uploads/profile_photos/{$row['profile_photo']}" : "default_profile.png") . "' class='profile-pic' alt='Profile' />
                      </a>
                    </td>";
              echo "<td class='staff-name'>" . htmlspecialchars($row['full_name']) . "</td>";
              echo "<td>" . htmlspecialchars($row['id_number']) . "</td>";
              echo "<td class='email-cell'>" . htmlspecialchars($row['email']) . "</td>";
              echo "<td class='action-cell'>
                      <button onclick=\"openNotifyForm({$row['user_id']}, '" . htmlspecialchars($row['full_name']) . "')\" class='btn btn-notify' title='Send notification'>
                        <i class='fas fa-paper-plane'></i> Notify
                      </button>
                    </td>";
              echo "</tr>";
          }
        } else {
          echo "<tr><td colspan='5' class='no-data'><i class='fas fa-inbox'></i> No staff members found</td></tr>";
        }
      ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<!-- Notify Modal -->
<div class="notify-modal" id="notifyModal">
  <div class="notify-modal-content">
    <div class="modal-header">
      <h2><i class="fas fa-envelope"></i> Send Notification</h2>
      <button type="button" class="close-btn" onclick="closeNotifyForm()">&times;</button>
    </div>
    <form method="POST" action="notify_staff.php" class="notify-form-content">
      <div class="form-group">
        <label for="staffName">To:</label>
        <input type="text" id="staffName" class="form-control" readonly>
      </div>
      <div class="form-group">
        <label for="message">Message <span class="required">*</span></label>
        <textarea name="message" id="message" class="form-control" placeholder="Enter your message here..." required maxlength="500"></textarea>
        <small class="char-count"><span id="charCount">0</span>/500 characters</small>
      </div>
      <input type="hidden" name="user_id" id="notifyUserId">
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-check"></i> Send Message
        </button>
        <button type="button" class="btn btn-secondary" onclick="closeNotifyForm()">
          <i class="fas fa-times"></i> Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Notification Modal -->
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
        <button class="notification-button" onclick="closeNotificationModal()">
            Okay
        </button>
    </div>
</div>

<script>
    function closeNotificationModal() {
        const overlay = document.getElementById('notificationOverlay');
        overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => {
            overlay.classList.remove('active');
        }, 300);
    }

    setTimeout(() => {
        closeNotificationModal();
    }, <?php echo $redirect_delay; ?>);
</script>
<?php endif; ?>

<script>
  function openNotifyForm(userId, staffName) {
    document.getElementById('notifyUserId').value = userId;
    document.getElementById('staffName').value = staffName;
    document.getElementById('message').value = '';
    document.getElementById('charCount').textContent = '0';
    document.getElementById('notifyModal').style.display = 'flex';
  }

  function closeNotifyForm() {
    document.getElementById('notifyModal').style.display = 'none';
  }

  document.getElementById('message').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
  });

  window.addEventListener('click', function(event) {
    const modal = document.getElementById('notifyModal');
    if (event.target === modal) {
      closeNotifyForm();
    }
  });
</script>

</body>
</html>
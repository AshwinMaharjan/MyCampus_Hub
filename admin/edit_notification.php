<?php
session_start();
include("connect.php");
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$notification_msg = null;
$notification_type = null;
$redirect_delay = 2000;

// Validate and fetch notification
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: view_notification.php");
    exit;
}

$notif_id = intval($_GET['id']);

// Fetch the notification from database
$stmt = $conn->prepare("SELECT * FROM notifications WHERE id = ?");
$stmt->bind_param("i", $notif_id);
$stmt->execute();
$result = $stmt->get_result();
$notification = $result->fetch_assoc();
$stmt->close();

if (!$notification) {
    header("Location: view_notification.php");
    exit;
}

// Handle update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newMessage = trim($_POST['message']);

    if (!empty($newMessage)) {
        $stmt = $conn->prepare("UPDATE notifications SET message = ? WHERE id = ?");
        $stmt->bind_param("si", $newMessage, $notif_id);
        if ($stmt->execute()) {
            $notification_msg = "Notification updated successfully!";
            $notification_type = "success";
            $notification['message'] = $newMessage;
        } else {
            $notification_msg = "Failed to update notification.";
            $notification_type = "error";
        }
        $stmt->close();
    } else {
        $notification_msg = "Message cannot be empty.";
        $notification_type = "invalid";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard - Edit Notification</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/edit_notification.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="form-container">
  <div class="form-header">
    <h2><i class="fas fa-edit"></i> Edit Notification</h2>
    <p class="form-subtitle">Update the notification message</p>
  </div>

  <form method="POST" class="edit-form">
    <div class="form-group">
      <label for="message">Notification Message <span class="required">*</span></label>
      <textarea name="message" id="message" class="form-control" placeholder="Enter notification message..." required maxlength="500"><?= htmlspecialchars($notification['message']) ?></textarea>
      <small class="char-count"><span id="charCount"><?= strlen($notification['message']) ?></span>/500 characters</small>
    </div>

    <div class="button-row">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-check"></i> Update Notification
      </button>
      <button type="button" class="btn btn-secondary" onclick="window.location.href='view_notification.php'">
        <i class="fas fa-times"></i> Cancel
      </button>
    </div>
  </form>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<!-- Notification Modal -->
<?php if ($notification_msg): ?>
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
                case 'invalid':
                    echo '<i class="fas fa-exclamation-circle"></i>';
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
                case 'invalid':
                    echo 'Invalid Input';
                    break;
            }
            ?>
        </div>
        <div class="notification-message"><?php echo htmlspecialchars($notification_msg); ?></div>
        <div class="notification-progress">
            <div class="notification-progress-bar"></div>
        </div>
        <div class="notification-buttons">
            <?php if ($notification_type === 'success'): ?>
                <button class="notification-button" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i> Back to Notifications
                </button>
            <?php else: ?>
                <button class="notification-button" onclick="closeNotification()">
                    Okay
                </button>
            <?php endif; ?>
        </div>
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

    function goBack() {
        window.location.href = 'view_notification.php';
    }

    // Auto-close after 2 seconds for non-success messages
    <?php if ($notification_type !== 'success'): ?>
    setTimeout(() => {
        closeNotification();
    }, <?php echo $redirect_delay; ?>);
    <?php endif; ?>
</script>
<?php endif; ?>

<script>
  document.getElementById('message').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
  });
</script>

</body>
</html>
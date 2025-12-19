<?php
session_start();
include("connect.php");
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
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
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
  <h1 style="color: #263576;">View Notifications</h1>

 <table>
    <thead>
      <tr>
        <th>Date Sent</th>
        <th>ID Number</th>
        <th>Full Name</th>
        <th>Message</th>
      </tr>
    </thead>
    <tbody>
<?php
$student_id = $_SESSION['uid'];

$query = "SELECT n.id, n.message, n.user_id, n.date_sent, u.full_name, u.id_number, u.profile_photo, u.role_id 
          FROM notifications n 
          JOIN users u ON n.user_id = u.user_id 
          WHERE n.user_id = ? 
          ORDER BY n.date_sent DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['date_sent']) . "</td>";
    echo "<td>" . htmlspecialchars($row['id_number']) . "</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td style='text-align:justify;'>" . nl2br(htmlspecialchars($row['message'])) . "</td>";
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

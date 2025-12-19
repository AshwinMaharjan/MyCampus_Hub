<?php
session_start();
include("connect.php");
if (!isset($_SESSION['uid'])) {
  header("Location: ../login.php");
  exit();
}
  
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];

    if ($action == "approve") {
        $sql = "UPDATE users SET status = 1 WHERE user_id = $user_id";
        $message = "User Approved Successfully!";
    } elseif ($action == "decline") {
        $sql = "DELETE FROM users WHERE user_id = $user_id";
        $message = "User Declined and Deleted Successfully!";
    } else {
        exit("Invalid action.");
    }

    if (mysqli_query($conn, $sql)) {
        echo "<script>
            alert('$message');
            window.location.href = 'manage_users.php';
        </script>";
    } else {
        echo "<script>
            alert('Database error: " . mysqli_error($conn) . "');
            window.location.href = 'manage_users.php';
        </script>";
    }
    exit();
}
?>

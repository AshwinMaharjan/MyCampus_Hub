<?php
include ("connect.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['uid'];

// Check if the user still exists in DB and is active
$query = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND status = 'active'");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    // User no longer exists or inactive
    session_unset();
    session_destroy();
    header("Location: ../login.php?msg=account_removed");
    exit();
}

$query->close();
?>

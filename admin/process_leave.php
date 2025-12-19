<?php
session_start();
include("connect.php");

// Check if user is logged in and is admin
if (!isset($_SESSION['uid'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get parameters
$leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$admin_id = $_SESSION['uid'];

// Validate input
if ($leave_id <= 0 || !in_array($action, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Update leave request status
$query = "UPDATE staff_leave_request 
          SET status = ?, 
              admin_id = ?, 
              processed_at = NOW() 
          WHERE leave_id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "sii", $action, $admin_id, $leave_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Leave request ' . $action . ' successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
<?php
session_start();
include("connect.php");
include("auth_check.php");

// Check if user is logged in and is faculty
if (!isset($_SESSION['uid'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get parameters
$leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$faculty_id = $_SESSION['uid'];

// Validate input
if ($leave_id <= 0 || !in_array($action, ['Approved', 'Rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Verify that this leave request belongs to a student in faculty's subject
$verify_query = "
    SELECT slr.leave_id 
    FROM student_leave_request slr
    JOIN users u ON slr.student_id = u.user_id
    JOIN subject s ON slr.subject_id = s.sub_id
    WHERE slr.leave_id = ? 
    AND s.faculty_id = ?
";

$verify_stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($verify_stmt, "is", $leave_id, $faculty_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized to process this leave request']);
    exit();
}

// Update leave request status
$update_query = "
    UPDATE student_leave_request 
    SET status = ?, 
        faculty_id = ?, 
        processed_at = NOW() 
    WHERE leave_id = ?
";

$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, "ssi", $action, $faculty_id, $leave_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Leave request ' . $action . ' successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}

mysqli_stmt_close($stmt);
mysqli_stmt_close($verify_stmt);
mysqli_close($conn);
?>
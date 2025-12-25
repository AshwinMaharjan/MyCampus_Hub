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
$material_id = isset($_POST['material_id']) ? intval($_POST['material_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$faculty_id = $_SESSION['uid'];

// Validate input
if ($material_id <= 0 || !in_array($action, ['Approved', 'Rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Verify that this material belongs to faculty's subject
$verify_query = "
    SELECT sm.material_id 
    FROM study_material sm
    JOIN subject s ON sm.subject_id = s.sub_id
    WHERE sm.material_id = ? 
    AND s.faculty_id = ?
";

$verify_stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($verify_stmt, "is", $material_id, $faculty_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized to process this material']);
    exit();
}

// Update material approval status
$update_query = "
    UPDATE study_material 
    SET approval_status = ?, 
        approved_by = ?, 
        approval_date = NOW() 
    WHERE material_id = ?
";

$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, "ssi", $action, $faculty_id, $material_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Material ' . $action . ' successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}

mysqli_stmt_close($stmt);
mysqli_stmt_close($verify_stmt);
mysqli_close($conn);
?>
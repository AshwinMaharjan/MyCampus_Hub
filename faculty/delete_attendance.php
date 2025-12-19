<?php
session_start();
include("connect.php");
include("auth_check.php");

header('Content-Type: application/json');

if(!isset($_SESSION['uid'])){
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$staff_id = $_SESSION['uid'];

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_id'])){
    $attendance_id = intval($_POST['attendance_id']);

    if($attendance_id > 0){
        $stmt = $conn->prepare("DELETE FROM attendance WHERE attendance_id = ? AND attendance_done_by = ?");
        $stmt->bind_param("ii", $attendance_id, $staff_id);
        if($stmt->execute()){
            if($stmt->affected_rows > 0){
                echo json_encode(['success' => true, 'message' => 'Attendance record deleted successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => "No record found or you don't have permission."]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid attendance ID.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

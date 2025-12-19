<?php
session_start();
include("auth_check.php");
include("connect.php");

if (!isset($_SESSION['uid'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$staff_id = $_SESSION['uid'];

$course_id = intval($_POST['course_id'] ?? 0);
$sem_id = intval($_POST['sem_id'] ?? 0);
$subject_id = intval($_POST['subject_id'] ?? 0);
$date = $_POST['date'] ?? '';

if (!$course_id || !$sem_id || !$subject_id || !$date) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Check duplicate attendance
$checkStmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM attendance 
    WHERE sub_id = ? AND course_id = ? AND sem_id = ? AND attendance_date = ?
");
$checkStmt->bind_param("iiis", $subject_id, $course_id, $sem_id, $date);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$checkRow = $checkResult->fetch_assoc();
$checkStmt->close();

if ($checkRow['count'] > 0) {
    echo json_encode([
        'duplicate' => true,
        'message' => 'Attendance for this subject, course, semester, and date has already been recorded.'
    ]);
    exit;
}

// Fetch students directly from users table
$sql = "
    SELECT user_id, id_number, full_name
    FROM users
    WHERE role_id = 2 AND course_id = ? AND sem_id = ?
    ORDER BY full_name
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $course_id, $sem_id);
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['students' => $students]);
exit;
?>

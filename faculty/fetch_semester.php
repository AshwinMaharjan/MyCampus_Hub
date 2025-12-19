<?php
session_start();
include("auth_check.php");
include("connect.php");

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
$sem_id = isset($_POST['sem_id']) ? intval($_POST['sem_id']) : 0;
$subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
$date = isset($_POST['date']) ? $_POST['date'] : '';
$session = isset($_POST['session']) ? $_POST['session'] : '';

if ($course_id <= 0 || $sem_id <= 0 || $subject_id <= 0 || empty($date) || empty($session)) {
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

// Check if attendance already exists for this combination
$checkStmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM attendance 
    WHERE sub_id = ? AND course_id = ? AND sem_id = ? AND attendance_date = ? AND session = ?
");

if ($checkStmt) {
    $checkStmt->bind_param("iiiss", $subject_id, $course_id, $sem_id, $date, $session);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkRow = $checkResult->fetch_assoc();
    
    if ($checkRow['count'] > 0) {
        echo json_encode([
            'duplicate' => true,
            'message' => 'Attendance for this subject, date, and session has already been recorded.'
        ]);
        $checkStmt->close();
        $conn->close();
        exit;
    }
    $checkStmt->close();
}

// Fetch students for the selected course and semester
$stmt = $conn->prepare("
    SELECT user_id, id_number, full_name, course_name, sem_name
    FROM users 
    WHERE course_id = ? AND sem_id = ? AND role_id = 2 AND status = 'Active'
    ORDER BY id_number
");

if (!$stmt) {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ii", $course_id, $sem_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$stmt->close();
$conn->close();

if (empty($students)) {
    echo json_encode(['error' => 'No students found for this course and semester']);
} else {
    echo json_encode(['students' => $students]);
}
?>
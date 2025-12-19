<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if (!isset($_SESSION['uid'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['uid'];

// Verify user is faculty (role_id = 3)
$user_check = $conn->prepare("SELECT role_id FROM users WHERE user_id = ?");
$user_check->bind_param("i", $user_id);
$user_check->execute();
$user_result = $user_check->get_result();
$user_data = $user_result->fetch_assoc();

if (!$user_data || $user_data['role_id'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
$user_check->close();

if (!isset($_GET['course_id'], $_GET['sem_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$course_id = filter_var($_GET['course_id'], FILTER_VALIDATE_INT);
$sem_id    = filter_var($_GET['sem_id'], FILTER_VALIDATE_INT);

if ($course_id === false || $sem_id === false || $course_id <= 0 || $sem_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Only return subjects assigned to this faculty member
$sql = "
    SELECT sub_id, sub_name
    FROM subject
    WHERE course_id = ? AND sem_id = ? AND role_id = ?
    ORDER BY sub_name ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param('iii', $course_id, $sem_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

$subjects = [];
while ($row = $res->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

echo json_encode([
    'success'  => true,
    'count'    => count($subjects),
    'subjects' => $subjects
]);
exit;
?>
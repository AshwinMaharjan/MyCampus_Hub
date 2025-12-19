<?php
require_once 'connect.php';

header('Content-Type: application/json; charset=utf-8');

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

$sql = "
    SELECT sub_id, sub_name
    FROM subject
    WHERE course_id = ? AND sem_id = ?
    ORDER BY sub_name ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed']);
    exit;
}

$stmt->bind_param('ii', $course_id, $sem_id);
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

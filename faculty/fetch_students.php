<?php
session_start();
include("connect.php");
include("auth_check.php");

// Check if user is authenticated
if (!isset($_SESSION['uid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$staff_id = $_SESSION['uid'];

// Check if subject_id is provided
if (!isset($_POST['subject_id']) || empty($_POST['subject_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Subject ID is required']);
    exit();
}

$subject_id = intval($_POST['subject_id']);
$exam_type_id = isset($_POST['exam_type_id']) ? intval($_POST['exam_type_id']) : null;

// First, get the course_id and sem_id from the subject table
$subjectStmt = $conn->prepare("SELECT course_id, sem_id FROM subject WHERE sub_id = ? AND role_id = ?");

if (!$subjectStmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit();
}

$subjectStmt->bind_param("ii", $subject_id, $staff_id);
$subjectStmt->execute();
$subjectResult = $subjectStmt->get_result();

if ($subjectResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Subject not found or you do not have permission']);
    exit();
}

$subjectRow = $subjectResult->fetch_assoc();
$course_id_from_subject = $subjectRow['course_id'];
$sem_id_from_subject = $subjectRow['sem_id'];
$subjectStmt->close();

// Fetch students enrolled in this course and semester
// Only exclude students who have marks for THIS SPECIFIC SUBJECT AND EXAM TYPE
if ($exam_type_id) {
    $sql = "
        SELECT 
            u.user_id, 
            u.id_number, 
            u.full_name,
            u.course_id,
            u.sem_id,
            c.course_name
        FROM users u
        INNER JOIN course c 
            ON u.course_id = c.course_id
        LEFT JOIN marks m 
            ON u.user_id = m.user_id 
            AND m.sub_id = ?
            AND m.exam_type_id = ?
        WHERE u.role_id = 2
        AND u.status = 'Active'
          AND u.course_id = ?
          AND u.sem_id = ?
          AND m.user_id IS NULL
        ORDER BY u.full_name ASC
    ";
} else {
    $sql = "
        SELECT 
            u.user_id, 
            u.id_number, 
            u.full_name,
            u.course_id,
            u.sem_id,
            c.course_name
        FROM users u
        INNER JOIN course c 
            ON u.course_id = c.course_id
        WHERE u.role_id = 2
          AND u.course_id = ?
          AND u.sem_id = ?
        ORDER BY u.full_name ASC
    ";
}

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit();
}

if ($exam_type_id) {
    $stmt->bind_param("iiii", $subject_id, $exam_type_id, $course_id_from_subject, $sem_id_from_subject);
} else {
    $stmt->bind_param("ii", $course_id_from_subject, $sem_id_from_subject);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Query execution failed: ' . $stmt->error]);
    exit();
}

$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode($students);
?>
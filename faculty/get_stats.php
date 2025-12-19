<?php
session_start();
include("connect.php");
include("auth_check.php");

header('Content-Type: application/json');

if (!isset($_SESSION['uid'])) {
    echo json_encode(['error' => 'Unauthorized', 'total_students' => 0]);
    exit();
}

$staff_id = $_SESSION['uid'];

// Get filter parameters from POST
$subject = isset($_POST['subject']) ? intval($_POST['subject']) : 0;
$semester = isset($_POST['semester']) ? intval($_POST['semester']) : 0;
$course = isset($_POST['course']) ? intval($_POST['course']) : 0;
$exam_type = isset($_POST['exam_type']) ? intval($_POST['exam_type']) : 0;
$search = isset($_POST['search']) ? trim($_POST['search']) : '';

// Build where conditions
$whereConditions = ["m.entered_by_staff = ?"];
$params = [$staff_id];
$paramTypes = "i";

if ($subject > 0) {
    $whereConditions[] = "m.sub_id = ?";
    $params[] = $subject;
    $paramTypes .= "i";
}

if ($semester > 0) {
    $whereConditions[] = "m.sem_id = ?";
    $params[] = $semester;
    $paramTypes .= "i";
}

if ($course > 0) {
    $whereConditions[] = "m.course_id = ?";
    $params[] = $course;
    $paramTypes .= "i";
}

if ($exam_type > 0) {
    $whereConditions[] = "m.exam_type_id = ?";
    $params[] = $exam_type;
    $paramTypes .= "i";
}

if (!empty($search)) {
    $whereConditions[] = "(u.full_name LIKE ? OR u.id_number LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= "ss";
}

$whereClause = implode(" AND ", $whereConditions);

// Calculate statistics query
$statsQuery = "SELECT 
    COUNT(m.marks_id) as total_students,
    ROUND(AVG(m.obtained_marks), 2) as avg_marks,
    MAX(m.obtained_marks) as highest_marks,
    MIN(m.obtained_marks) as lowest_marks,
    ROUND(AVG(m.percentage), 2) as avg_percentage,
    SUM(CASE WHEN m.percentage >= 40 THEN 1 ELSE 0 END) as pass_count,
    SUM(CASE WHEN m.percentage < 40 THEN 1 ELSE 0 END) as fail_count
FROM marks m 
LEFT JOIN users u ON m.user_id = u.user_id 
LEFT JOIN subject s ON m.sub_id = s.sub_id 
LEFT JOIN semester sem ON m.sem_id = sem.sem_id
LEFT JOIN course c ON m.course_id = c.course_id
LEFT JOIN exam_types et ON m.exam_type_id = et.exam_type_id
WHERE $whereClause";

// Prepare and execute the query
$stmt = $conn->prepare($statsQuery);

if (!empty($paramTypes)) {
    $stmt->bind_param($paramTypes, ...$params);
}

if (!$stmt->execute()) {
    echo json_encode(['error' => 'Query execution failed', 'total_students' => 0]);
    exit();
}

$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$stmt->close();

// Handle null values
$total_students = (int)($stats['total_students'] ?? 0);
$avg_marks = (float)($stats['avg_marks'] ?? 0);
$highest_marks = (int)($stats['highest_marks'] ?? 0);
$lowest_marks = (int)($stats['lowest_marks'] ?? 0);
$pass_count = (int)($stats['pass_count'] ?? 0);
$fail_count = (int)($stats['fail_count'] ?? 0);

// Calculate pass rate
$pass_rate = $total_students > 0 
    ? round(($pass_count / $total_students) * 100, 2)
    : 0;

// Return JSON response
echo json_encode([
    'total_students' => $total_students,
    'avg_marks' => $avg_marks,
    'highest_marks' => $highest_marks,
    'lowest_marks' => $lowest_marks,
    'avg_percentage' => (float)($stats['avg_percentage'] ?? 0),
    'pass_count' => $pass_count,
    'fail_count' => $fail_count,
    'pass_rate' => $pass_rate
]);
?>
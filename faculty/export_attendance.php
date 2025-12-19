<?php
session_start();
include("auth_check.php");
include("connect.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$staff_id = $_SESSION['uid'];

// Get filter values
$filter_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_semester = isset($_GET['sem_id']) ? intval($_GET['sem_id']) : 0;
$filter_subject = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get course name for filename
$course_name = "All";
if ($filter_course > 0) {
    $courseStmt = $conn->prepare("SELECT course_name FROM course WHERE course_id = ?");
    $courseStmt->bind_param("i", $filter_course);
    $courseStmt->execute();
    $courseResult = $courseStmt->get_result();
    if ($courseRow = $courseResult->fetch_assoc()) {
        $course_name = preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $courseRow['course_name']));
    }
    $courseStmt->close();
}

// Get semester name for filename
$semester_name = "All";
if ($filter_semester > 0) {
    $semesterStmt = $conn->prepare("SELECT sem_name FROM semester WHERE sem_id = ?");
    $semesterStmt->bind_param("i", $filter_semester);
    $semesterStmt->execute();
    $semesterResult = $semesterStmt->get_result();
    if ($semesterRow = $semesterResult->fetch_assoc()) {
        $semester_name = preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $semesterRow['sem_name']));
    }
    $semesterStmt->close();
}
// Get subject name for filename
$subject_name = "All";
if ($filter_subject > 0) {
    $subjectStmt = $conn->prepare("SELECT sub_name FROM subject WHERE sub_id = ?");
    $subjectStmt->bind_param("i", $filter_subject);
    $subjectStmt->execute();
    $subjectResult = $subjectStmt->get_result();
    if ($subjectRow = $subjectResult->fetch_assoc()) {
        $subject_name = preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $subjectRow['sub_name']));
    }
    $subjectStmt->close();
}

// Create date part for filename
$date_part = date('Y-m-d');
if (!empty($filter_date_from) && !empty($filter_date_to)) {
    $date_part = date('Ymd', strtotime($filter_date_from)) . '_to_' . date('Ymd', strtotime($filter_date_to));
} elseif (!empty($filter_date_from)) {
    $date_part = 'from_' . date('Ymd', strtotime($filter_date_from));
} elseif (!empty($filter_date_to)) {
    $date_part = 'until_' . date('Ymd', strtotime($filter_date_to));
}

// Build report query
$reportQuery = "
    SELECT 
        u.id_number,
        u.full_name,
        c.course_name,
        s.sem_name,
        sub.sub_name,
        COUNT(a.attendance_id) AS total_classes,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
    FROM attendance a
    INNER JOIN users u ON a.user_id = u.user_id
    INNER JOIN course c ON a.course_id = c.course_id
    INNER JOIN semester s ON a.sem_id = s.sem_id
    INNER JOIN subject sub ON a.sub_id = sub.sub_id
    WHERE sub.role_id = ?
";

$params = [$staff_id];
$types = "i";

if ($filter_course > 0) {
    $reportQuery .= " AND a.course_id = ?";
    $params[] = $filter_course;
    $types .= "i";
}

if ($filter_semester > 0) {
    $reportQuery .= " AND a.sem_id = ?";
    $params[] = $filter_semester;
    $types .= "i";
}

if ($filter_subject > 0) {
    $reportQuery .= " AND a.sub_id = ?";
    $params[] = $filter_subject;
    $types .= "i";
}

if (!empty($filter_date_from)) {
    $reportQuery .= " AND a.attendance_date >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if (!empty($filter_date_to)) {
    $reportQuery .= " AND a.attendance_date <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

$reportQuery .= " GROUP BY u.user_id, a.sub_id ORDER BY sub.sub_name, u.full_name ASC";

$reportStmt = $conn->prepare($reportQuery);
if ($reportStmt) {
    $reportStmt->bind_param($types, ...$params);
    $reportStmt->execute();
    $result = $reportStmt->get_result();
    $reportData = $result->fetch_all(MYSQLI_ASSOC);
    $reportStmt->close();
} else {
    die("Error preparing report query.");
}

// Generate dynamic filename
$filename = "attendance_report_{$course_name}_{$semester_name}_{$subject_name}_{$date_part}.csv";

// Set CSV headers for Excel with UTF-8 BOM for proper encoding
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel to recognize encoding properly
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Column headers only
fputcsv($output, [
    'Student ID',
    'Student Name',
    'Course',
    'Semester',
    'Subject',
    'Total Classes',
    'Present',
    'Late',
    'Absent',
    'Attendance %'
]);

// Populate CSV rows
foreach ($reportData as $row) {
    $percentage = $row['total_classes'] > 0 
        ? round((($row['present_count'] + $row['late_count']) / $row['total_classes']) * 100, 2)
        : 0;

    fputcsv($output, [
        $row['id_number'],
        $row['full_name'],
        $row['course_name'],
        $row['sem_name'],
        $row['sub_name'],
        $row['total_classes'],
        $row['present_count'],
        $row['late_count'],
        $row['absent_count'],
        $percentage . '%'
    ]);
}

fclose($output);
exit();
?>
<?php
include("connect.php");

$sub_id = intval($_GET['sub_id']);

// First, get course_id and sem_id for the selected subject
$stmt = $conn->prepare("SELECT course_id, sem_id FROM subject WHERE sub_id = ?");
$stmt->bind_param("i", $sub_id);
$stmt->execute();
$stmt->bind_result($course_id, $sem_id);
$stmt->fetch();
$stmt->close();

// Now fetch students from users table who match that course + semester
$sql = "SELECT 
            user_id,
            full_name,
            id_number,
            course_id,
            course_name,
            sem_id,
            sem_name
        FROM users
        WHERE role_id = 2
          AND course_id = ?
          AND sem_id = ?
          AND status = 'approved'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $course_id, $sem_id);
$stmt->execute();
$result = $stmt->get_result();

$students = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($students);

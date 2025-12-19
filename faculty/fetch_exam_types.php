<?php
include("connect.php");
$sub_id = intval($_GET['sub_id']);
$sql = "SELECT sec.exam_type_id, et.exam_name, sec.full_marks
        FROM subject_exam_config sec
        JOIN exam_types et ON sec.exam_type_id = et.exam_type_id
        WHERE sec.sub_id = ? AND sec.is_applicable = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $sub_id);
$stmt->execute();
$result = $stmt->get_result();
$exam_types = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($exam_types);

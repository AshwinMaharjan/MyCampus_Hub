<?php
session_start();
include("connect.php");

header('Content-Type: application/json');

// Fetch all existing subject names
$query = "SELECT DISTINCT sub_name FROM subject ORDER BY sub_name";
$result = $conn->query($query);

$subjects = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row['sub_name'];
    }
}

echo json_encode(['subjects' => $subjects]);
?>
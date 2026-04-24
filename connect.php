<?php

// Check if a session is already started before calling session_start()
// Database connection
$servername = "localhost";  // Your database host (usually localhost)
$user = "root";       // Your database username
$pass = "";           // Your database password (default is empty for XAMPP)
$dbname = "mycampus_hub"; // Your database name

$conn = mysqli_connect($servername, $user, $pass, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>

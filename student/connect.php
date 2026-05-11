<?php

<<<<<<< HEAD
// Check if a session is already started before calling session_start()
// Database connection
$servername = "localhost";  // Your database host (usually localhost)
$user = "root";       // Your database username
$pass = "";           // Your database password (default is empty for XAMPP)
$dbname = "mycampus_hub"; // Your database name

$conn = mysqli_connect($servername, $user, $pass, $dbname);

// Check connection
=======
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$servername = "localhost";  
$user = "root";       
$pass = "";           
$dbname = "mycampus_hub";
$conn = mysqli_connect($servername, $user, $pass, $dbname);

>>>>>>> 0dc93962c238aa94f92f2abe36a5fb6823d3485d
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>

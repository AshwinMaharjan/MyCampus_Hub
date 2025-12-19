<?php
session_start();
include("connect.php");
if (!isset($_SESSION['uid'])) {
  header("Location: ../login.php");
  exit();
}
$staffId = $_SESSION['uid']; // Logged-in staff user ID

// 1. My Subjects
$subjectQuery = "SELECT COUNT(*) AS total FROM subjects WHERE staff_id = $staffId";
$subjectResult = mysqli_query($conn, $subjectQuery);
$mySubjects = mysqli_fetch_assoc($subjectResult)['total'];

// 2. Students Assigned
$studentQuery = "
    SELECT COUNT(DISTINCT m.student_id) AS total
    FROM marks m
    JOIN subjects s ON m.subject_id = s.subject_id
    WHERE s.staff_id = $staffId";
$studentResult = mysqli_query($conn, $studentQuery);
$studentsAssigned = mysqli_fetch_assoc($studentResult)['total'];

// 3. Mark Entries
$markEntriesQuery = "SELECT COUNT(*) AS total FROM marks WHERE entered_by_staff = $staffId";
$markEntriesResult = mysqli_query($conn, $markEntriesQuery);
$markEntries = mysqli_fetch_assoc($markEntriesResult)['total'];

// 4. Pending Mark Submissions
$pendingQuery = "
    SELECT COUNT(*) AS total
    FROM subjects s
    WHERE s.staff_id = $staffId
      AND NOT EXISTS (
          SELECT 1 FROM marks m
          WHERE m.subject_id = s.subject_id
      )";
$pendingResult = mysqli_query($conn, $pendingQuery);
$pendingMarks = mysqli_fetch_assoc($pendingResult)['total'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Faculty Dashboard</title>
  <link rel="stylesheet" href="../css/all.min.css">
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="../css/admin_menu.css">
  <link rel="stylesheet" href="../css/student_homepage.css">
</head>

<body>

  <?php include("header.php"); ?>
  <?php include("menu.php"); ?>

  <!-- Main Dashboard Content -->
  <div class="main-content">
    <h1>Faculty Dashboard</h1>

    <div class="box-container card-column">

      <div class="card-container">

        <!-- My Subjects -->
        <div class="card red-card">
          <div class="card-icon"><i class="fas fa-book"></i></div>
          <div class="card-content">
            <h2><?php echo $mySubjects; ?></h2>
            <p>My Subjects</p>
          </div>
          <a href="faculty_subjects.php" class="more-info">
            More info <i class="fas fa-arrow-circle-right"></i>
          </a>
        </div>

        <!-- Students Assigned -->
        <div class="card blue-card">
          <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
          <div class="card-content">
            <h2><?php echo $studentsAssigned; ?></h2>
            <p>Students Assigned</p>
          </div>
          <a href="faculty_students.php" class="more-info">
            More info <i class="fas fa-arrow-circle-right"></i>
          </a>
        </div>

        <!-- Mark Entries -->
        <div class="card green-card">
          <div class="card-icon"><i class="fas fa-pen-nib"></i></div>
          <div class="card-content">
            <h2><?php echo $markEntries; ?></h2>
            <p>Mark Entries</p>
          </div>
          <a href="faculty_marks.php" class="more-info">
            More info <i class="fas fa-arrow-circle-right"></i>
          </a>
        </div>

        <!-- Pending Mark Submissions -->
        <div class="card pending-card">
          <div class="card-icon"><i class="fas fa-hourglass-half"></i></div>
          <div class="card-content">
            <h2><?php echo $pendingMarks; ?></h2>
            <p>Pending Mark Submissions</p>
          </div>
          <a href="pending_marks.php" class="more-info">
            More info <i class="fas fa-arrow-circle-right"></i>
          </a>
        </div>

      </div>
    </div>
  </div>

  <?php include("footer.php"); ?>
  <?php include("lower_footer.php"); ?>

</body>
</html>
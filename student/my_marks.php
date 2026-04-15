<?php
session_start();
include("connect.php");
include("auth_check.php");


if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['uid'];
$selectedSubjectId = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$filterGrade = isset($_GET['grade']) ? $_GET['grade'] : '';
$filterSemester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$filterSubject = isset($_GET['filter_subject']) ? intval($_GET['filter_subject']) : 0;
$filterExamType = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';

// Get all exam types for filter
$examTypesQuery = "
    SELECT DISTINCT et.exam_type_id, et.exam_name
    FROM marks m
    INNER JOIN exam_types et ON m.exam_type_id = et.exam_type_id
    WHERE m.user_id = ? AND m.exam_type_id IS NOT NULL
    ORDER BY et.exam_name
";
$examTypesStmt = $conn->prepare($examTypesQuery);
$examTypesStmt->bind_param("i", $student_id);
$examTypesStmt->execute();
$examTypes = $examTypesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$examTypesStmt->close();


// Get subject details
$subjectInfo = null;
if ($selectedSubjectId !== 0) {
    $subjectQuery = "SELECT 
                        s.sub_id, 
                        s.sub_name, 
                        s.course_id, 
                        s.sem_id,
                        u.full_name AS teacher_name, 
                        u.email AS teacher_email
                     FROM subject s
                     LEFT JOIN users u ON s.role_id = u.user_id AND u.role_id = 3
                     WHERE s.sub_id = ?";
    $stmt = $conn->prepare($subjectQuery);
    $stmt->bind_param("i", $selectedSubjectId);
    $stmt->execute();
    $subjectResult = $stmt->get_result();
    $subjectInfo = $subjectResult->fetch_assoc();
    $stmt->close();
}

$studentSemesterQuery = "SELECT sem_id FROM users WHERE user_id = ?";
$studentSemesterStmt = $conn->prepare($studentSemesterQuery);
$studentSemesterStmt->bind_param("i", $student_id);
$studentSemesterStmt->execute();
$studentSemesterResult = $studentSemesterStmt->get_result();
$studentSemester = $studentSemesterResult->fetch_assoc();
$studentSemesterStmt->close();

$subjectQuery = "SELECT sub_id, sub_name FROM subject WHERE sem_id = ?";
$subjectStmt = $conn->prepare($subjectQuery);
$subjectStmt->bind_param("i", $studentSemester['sem_id']);
$subjectStmt->execute();
$subjects = $subjectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$subjectStmt->close();

$studentQuery = "SELECT full_name, id_number, profile_photo FROM users WHERE user_id = ?";
$studentStmt = $conn->prepare($studentQuery);
$studentStmt->bind_param("i", $student_id);
$studentStmt->execute();
$studentInfo = $studentStmt->get_result()->fetch_assoc();
$studentStmt->close();

// Get all semesters for filter
$semesterQuery = "SELECT DISTINCT sem.sem_id, sem.sem_name FROM semester sem 
                  INNER JOIN marks m ON m.sem_id = sem.sem_id 
                  WHERE m.user_id = ? ORDER BY sem.sem_id";
$semStmt = $conn->prepare($semesterQuery);
$semStmt->bind_param("i", $student_id);
$semStmt->execute();
$semesters = $semStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$semStmt->close();

// Get all subjects for filter (subjects that have marks for this student)
$filterSubjectsQuery = "SELECT DISTINCT s.sub_id, s.sub_name 
                        FROM subject s
                        INNER JOIN marks m ON m.sub_id = s.sub_id 
                        WHERE m.user_id = ? 
                        ORDER BY s.sub_name";
$filterSubStmt = $conn->prepare($filterSubjectsQuery);
$filterSubStmt->bind_param("i", $student_id);
$filterSubStmt->execute();
$filterSubjects = $filterSubStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$filterSubStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>My Marks</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/student/my_marks.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <style>
    .subject-info-banner {
      background: linear-gradient(135deg, #263576 0%, #4a5fa8 100%);
      color: white;
      padding: 20px 30px;
      border-radius: 12px;
      margin-bottom: 25px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .subject-info-banner h2 {
      margin: 0 0 10px 0;
      font-size: 1.8em;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .subject-info-banner .subject-details {
      display: flex;
      gap: 30px;
      font-size: 1em;
      margin-top: 15px;
      flex-wrap: wrap;
    }
    .teacher-info-box {
      background: rgba(255, 255, 255, 0.15);
      padding: 12px 15px;
      border-radius: 8px;
      margin-top: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background-color: white;
      color: #263576;
      padding: 10px 20px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      margin-bottom: 20px;
      transition: all 0.3s ease;
    }
    .back-btn:hover {
      background-color: #f0f0f0;
      transform: translateX(-5px);
    }
    
    /* Filter Section */
    .filter-section {
      background: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 25px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .filter-section h3 {
      margin: 0 0 15px 0;
      color: #263576;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .filter-container {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      align-items: flex-end;
    }
    .filter-group {
      flex: 1;
      min-width: 200px;
    }
    .filter-group label {
      display: block;
      margin-bottom: 5px;
      color: #555;
      font-weight: 600;
      font-size: 0.9em;
    }
    .filter-group select {
      width: 100%;
      padding: 10px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 1em;
      transition: border-color 0.3s;
    }
    .filter-group select:focus {
      outline: none;
      border-color: #263576;
    }
    .filter-buttons {
      display: flex;
      gap: 10px;
    }
    .btn-filter {
      padding: 10px 25px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .btn-apply {
      background: #263576;
      color: white;
    }
    .btn-apply:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(38, 53, 118, 0.3);
    }
    .btn-clear {
      background: #505050ff;
      color: white;
      text-decoration: none;
    }
    .btn-clear:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(38, 53, 118, 0.3);
    }
    
    .stats-container {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-bottom: 25px;
    }
    .stat-card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      flex: 1;
      min-width: 200px;
      transition: transform 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
    }
    .stat-card.card-average{
      background: linear-gradient(135deg, #42a5f5 0%, #1e88e5 100%); /* Blue tone for Average Percentage */
    }
    .stat-card.card-highest {
      background: linear-gradient(135deg, #66bb6a 0%, #388e3c 100%); /* Green tone for Highest Score */
    }
    .stat-card.card-lowest {
      background: linear-gradient(135deg, #ef5350 0%, #d32f2f 100%); /* Red tone for Lowest Score */
    }
    .stat-card.card-total {
      background: linear-gradient(135deg, #f44336 0%, #ff9800 100%);/* Red tone for Lowest Score */
    }
    .stat-card.card-performance {
      background: linear-gradient(135deg, #ffb74d 0%, #f57c00 100%); /* Orange tone for Total Subjects */
    }
    .stat-card:hover {
      transform: translateY(-5px);
    }
    .stat-card h3 {
      margin: 0 0 10px 0;
      color: white;
      font-size: 1.1em;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .stat-value {
      font-size: 2em;
      font-weight: bold;
      color: white;
    }
    .stat-label {
      color: white;
      font-size: 0.9em;
    }
    .performance-message {
      font-size: 1.3em;
      margin-top: 5px;
      font-weight: bold;
    }
    /* Grade Badges - All Grades */
    .grade-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 6px;
      font-weight: bold;
      text-transform: uppercase;
      font-size: 0.9em;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .grade-A\+ { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
    .grade-A { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; }
    .grade-B\+ { background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white; }
    .grade-B { background: linear-gradient(135deg, #03A9F4 0%, #0288D1 100%); color: white; }
    .grade-C\+ { background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); color: white; }
    .grade-C { background: linear-gradient(135deg, #FFA726 0%, #FB8C00 100%); color: white; }
    .grade-D\+ { background: linear-gradient(135deg, #FF5722 0%, #E64A19 100%); color: white; }
    .grade-D { background: linear-gradient(135deg, #F44336 0%, #D32F2F 100%); color: white; }
    .grade-E { background: linear-gradient(135deg, #9E9E9E 0%, #757575 100%); color: white; }
    .grade-F { background: linear-gradient(135deg, #424242 0%, #212121 100%); color: white; }
    
    .no-marks-message {
      text-align: center;
      padding: 30px;
      color: #666;
    }
    .no-marks-message h3 {
      color: #263576;
      margin-bottom: 10px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      background-color: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    th {
      background-color: #263576;
      color: white;
      padding: 15px;
      text-align: left;
    }
    td {
      padding: 15px;
      border-bottom: 1px solid #eee;
    }
    tr:last-child td {
      border-bottom: none;
    }
    tr:hover {
      background-color: #f5f5f5;
    }
  </style>
</head>
<body>

<?php include("header.php"); ?>
<div class="page-wrapper">
<?php include("menu.php"); ?>

<div class="main-content">

<?php if ($selectedSubjectId !== 0 && $subjectInfo): ?>
  <a href="my_subjects.php" class="back-btn">
    <i class="fas fa-arrow-left"></i> Back to My Subjects
  </a>

  <div class="subject-info-banner">
    <h2><i class="fas fa-chart-line"></i> My Marks - <?= htmlspecialchars($subjectInfo['sub_name']) ?></h2>
    <div class="subject-details">
      <span><i class="fas fa-code"></i> <strong>Subject Code:</strong> SUB-<?= str_pad($subjectInfo['sub_id'], 3, '0', STR_PAD_LEFT) ?></span>
    </div>

    <?php if (!empty($subjectInfo['teacher_name'])): ?>
      <div class="teacher-info-box">
        <i class="fas fa-chalkboard-teacher"></i>
        <div>
          <strong>Teacher:</strong> <?= htmlspecialchars($subjectInfo['teacher_name']) ?>
          <?php if (!empty($subjectInfo['teacher_email'])): ?>
            <br><small><?= htmlspecialchars($subjectInfo['teacher_email']) ?></small>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="student-info">
    <?php
      $profile = !empty($studentInfo['profile_photo']) ? $studentInfo['profile_photo'] : 'default_profile.png';
      $profilePath = "../uploads/$profile";
    ?>
    <img src="<?= $profilePath ?>" class="student-avatar" alt="Profile" onerror="this.src='../uploads/default_profile.png'">
    <div class="student-details">
      <h2><?= htmlspecialchars($studentInfo['full_name']) ?></h2>
      <p>ID Number: <?= htmlspecialchars($studentInfo['id_number']) ?></p>
    </div>
  </div>
<?php endif; ?>

<!-- Filter Section -->
<div class="filter-section">
  <form method="GET" action="" class="filter-container">
    <?php if ($selectedSubjectId > 0): ?>
      <input type="hidden" name="subject_id" value="<?= $selectedSubjectId ?>">
    <?php endif; ?>
    
    <div class="filter-group">
      <select name="grade" id="grade">
        <option value="">All Grades</option>
        <option value="A+" <?= $filterGrade === 'A+' ? 'selected' : '' ?>>A+</option>
        <option value="A" <?= $filterGrade === 'A' ? 'selected' : '' ?>>A</option>
        <option value="B+" <?= $filterGrade === 'B+' ? 'selected' : '' ?>>B+</option>
        <option value="B" <?= $filterGrade === 'B' ? 'selected' : '' ?>>B</option>
        <option value="C+" <?= $filterGrade === 'C+' ? 'selected' : '' ?>>C+</option>
        <option value="C" <?= $filterGrade === 'C' ? 'selected' : '' ?>>C</option>
        <option value="D+" <?= $filterGrade === 'D+' ? 'selected' : '' ?>>D+</option>
        <option value="D" <?= $filterGrade === 'D' ? 'selected' : '' ?>>D</option>
        <option value="E" <?= $filterGrade === 'E' ? 'selected' : '' ?>>E</option>
        <option value="F" <?= $filterGrade === 'F' ? 'selected' : '' ?>>F</option>
      </select>
    </div>
    
    <!-- <?php if (count($semesters) > 0 && $selectedSubjectId === 0): ?>
    <div class="filter-group">
      <select name="semester" id="semester">
        <option value="0">All Semesters</option>
        <?php foreach ($semesters as $sem): ?>
          <option value="<?= $sem['sem_id'] ?>" <?= $filterSemester === $sem['sem_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($sem['sem_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?> -->

<?php if (count($examTypes) > 0): ?>
<div class="filter-group">
  <select name="exam_type" id="exam_type">
    <option value="">All Exam Types</option>
    <?php foreach ($examTypes as $examType): ?>
      <option value="<?= $examType['exam_type_id'] ?>" <?= $filterExamType == $examType['exam_type_id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($examType['exam_name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>
<?php endif; ?>
    
    <?php if (count($filterSubjects) > 0 && $selectedSubjectId === 0): ?>
    <div class="filter-group">
      <select name="filter_subject" id="filter_subject">
        <option value="0">All Subjects</option>
        <?php foreach ($filterSubjects as $subj): ?>
          <option value="<?= $subj['sub_id'] ?>" <?= $filterSubject === $subj['sub_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($subj['sub_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    
    <div class="filter-buttons">
      <button type="submit" class="btn-filter btn-apply">
        <i class="fas fa-search"></i> Apply
      </button>
      <a href="?<?= $selectedSubjectId > 0 ? 'subject_id=' . $selectedSubjectId : '' ?>" class="btn-filter btn-clear">
        <i class="fas fa-times"></i> Clear
      </a>
    </div>
  </form>
</div>

<?php
// Get marks data with filters
$whereConditions = ["m.user_id = ?"];
$params = [$student_id];
$paramTypes = "i";

if ($selectedSubjectId > 0 && $subjectInfo) {
    $whereConditions[] = "m.sub_id = ?";
    $params[] = $selectedSubjectId;
    $paramTypes .= "i";
}

if (!empty($filterGrade)) {
    $whereConditions[] = "m.grade = ?";
    $params[] = $filterGrade;
    $paramTypes .= "s";
}
if (!empty($filterExamType)) {
    $whereConditions[] = "m.exam_type_id = ?";
    $params[] = $filterExamType;
    $paramTypes .= "s";
}

if ($filterSemester > 0) {
    $whereConditions[] = "m.sem_id = ?";
    $params[] = $filterSemester;
    $paramTypes .= "i";
}

if ($filterSubject > 0) {
    $whereConditions[] = "m.sub_id = ?";
    $params[] = $filterSubject;
    $paramTypes .= "i";
}

$whereClause = implode(" AND ", $whereConditions);

$query = "SELECT 
    m.marks_id,
    m.full_marks,
    m.obtained_marks,
    m.percentage,
    m.grade,
    m.remarks,
    m.exam_type_id,
    et.exam_name AS exam_type, -- add this
    s.sub_id,
    s.sub_name,
    sem.sem_name,
    c.course_name,
    u.full_name AS entered_by_staff_name
  FROM marks m
  LEFT JOIN exam_types et ON m.exam_type_id = et.exam_type_id -- join here
  LEFT JOIN subject s ON m.sub_id = s.sub_id
  LEFT JOIN semester sem ON m.sem_id = sem.sem_id
  LEFT JOIN course c ON m.course_id = c.course_id
  LEFT JOIN users u ON m.entered_by_staff = u.user_id
  WHERE $whereClause
  ORDER BY sem.sem_id DESC, s.sub_name ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Calculate statistics
$stats = [];
$totalSubjects = 0;
$totalObtainedMarks = 0;
$totalFullMarks = 0;
$averagePercentage = 0;
$highestPercentage = 0;
$lowestPercentage = 100;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $totalSubjects++;
        $totalObtainedMarks += $row['obtained_marks'];
        $totalFullMarks += $row['full_marks'];
        
        if ($row['percentage'] > $highestPercentage) {
            $highestPercentage = $row['percentage'];
        }
        
        if ($row['percentage'] < $lowestPercentage) {
            $lowestPercentage = $row['percentage'];
        }
        
        $subId = $row['sub_id'];
        if (!isset($stats[$subId])) {
            $stats[$subId] = [
                'sub_name' => $row['sub_name'],
                'exams' => [],
                'total_obtained' => 0,
                'total_full' => 0,
                'average_percentage' => 0,
                'highest' => 0,
                'lowest' => 100
            ];
        }
        
        $stats[$subId]['exams'][] = $row;
        $stats[$subId]['total_obtained'] += $row['obtained_marks'];
        $stats[$subId]['total_full'] += $row['full_marks'];
        
        if ($row['percentage'] > $stats[$subId]['highest']) {
            $stats[$subId]['highest'] = $row['percentage'];
        }
        
        if ($row['percentage'] < $stats[$subId]['lowest']) {
            $stats[$subId]['lowest'] = $row['percentage'];
        }
    }
    
    foreach ($stats as $subId => &$subjectStats) {
        if ($subjectStats['total_full'] > 0) {
            $subjectStats['average_percentage'] = round(($subjectStats['total_obtained'] / $subjectStats['total_full']) * 100, 2);
        }
    }
    
    if ($totalFullMarks > 0) {
        $averagePercentage = round(($totalObtainedMarks / $totalFullMarks) * 100, 2);
    }
}

$result->data_seek(0);
?>

<?php if ($result->num_rows > 0): ?>
  <?php if ($selectedSubjectId > 0 && $subjectInfo && isset($stats[$selectedSubjectId])): ?>
    <!-- Subject-specific statistics -->
    <div class="stats-container">
      <div class="stat-card card-average">
        <h3>Average Percentage</h3>
        <div class="stat-value"><?= $stats[$selectedSubjectId]['average_percentage'] ?>%</div>
        <div class="stat-label">Across all exams</div>
      </div>
      <div class="stat-card card-highest">
        <h3>Highest Score</h3>
        <div class="stat-value"><?= $stats[$selectedSubjectId]['highest'] ?>%</div>
        <div class="stat-label">Best performance</div>
      </div>
      <div class="stat-card card-lowest">
        <h3>Lowest Score</h3>
        <div class="stat-value"><?= $stats[$selectedSubjectId]['lowest'] ?>%</div>
        <div class="stat-label">Needs improvement</div>
      </div>
      <div class="stat-card card-total">
        <h3>Total Exams</h3>
        <div class="stat-value"><?= count($stats[$selectedSubjectId]['exams']) ?></div>
        <div class="stat-label">Completed assessments</div>
      </div>
    </div>
  <?php else: ?>
    <!-- Overall statistics -->
    <div class="stats-container">
      <div class="stat-card card-average">
        <h3>Average Percentage</h3>
        <div class="stat-value"><?= $averagePercentage ?>%</div>
        <div class="stat-label">Across all subjects</div>
      </div>
      <div class="stat-card card-highest">
        <h3>Highest Score</h3>
        <div class="stat-value"><?= $highestPercentage ?>%</div>
        <div class="stat-label">Best performance</div>
      </div>
      <div class="stat-card card-lowest">
        <h3>Lowest Score</h3>
        <div class="stat-value"><?= $lowestPercentage ?>%</div>
        <div class="stat-label">Needs improvement</div>
      </div>
<div class="stat-card card-performance">
  <h3>Performance</h3>
  <div class="performance-bar-container">
    <div class="performance-bar" style="width: <?= $averagePercentage ?>%;"></div>
  </div>
  <div class="performance-message">
    <?php
      if ($averagePercentage < 20) {
    echo 'Tragic. Time to rethink everything.';
} elseif ($averagePercentage < 40) {
    echo 'Struggling. You need a serious wake-up call.';
} elseif ($averagePercentage < 60) {
    echo 'Mediocre. Improvement is necessary.';
} elseif ($averagePercentage < 80) {
    echo 'Good effort. Keep pushing further.';
} else {
    echo 'Excellent. You’ve earned this!';
}
    ?>
  </div>
</div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th>Subject Name</th>
      <!-- <th>Semester</th> -->
      <!-- <th>Course</th> -->
      <th>Exam Type</th>
      <th>Full Marks</th>
      <th>Obtained</th>
      <th>Percentage</th>
      <th>Grade</th>
      <th>Remarks</th>
    </tr>
  </thead>
  <tbody>
  <?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['sub_name'] ?? 'N/A') ?></td>
        <!-- <td><?= htmlspecialchars($row['sem_name'] ?? 'N/A') ?></td> -->
        <!-- <td><?= htmlspecialchars($row['course_name'] ?? 'N/A') ?></td> -->
        <td><?= htmlspecialchars($row['exam_type'] ?? 'N/A') ?></td>
        <td><?= htmlspecialchars($row['full_marks'] ?? 'N/A') ?></td>
        <td><?= htmlspecialchars($row['obtained_marks'] ?? 'N/A') ?></td>
        <td><?= htmlspecialchars($row['percentage'] ?? 'N/A') ?>%</td>
        <td><span class="grade-badge grade-<?= htmlspecialchars($row['grade'] ?? '') ?>"><?= htmlspecialchars($row['grade'] ?? 'N/A') ?></span></td>
        <td style="text-align:left;"><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
      </tr>
    <?php endwhile; ?>
  <?php else: ?>
      <tr>
        <td colspan="8" class="no-marks-message">
          <?php if ($selectedSubjectId > 0 && $subjectInfo): ?>
            <h3>No marks found for this subject</h3>
            <p>Your marks for <?= htmlspecialchars($subjectInfo['sub_name']) ?> haven't been entered yet.</p>
            <p>Please check with your teacher or wait for marks to be published.</p>
          <?php else: ?>
            <h3>No marks found</h3>
            <p>You don't have any marks recorded yet with the selected filters.</p>
            <p>Try adjusting your filters or check with your faculty.</p>
          <?php endif; ?>
        </td>
      </tr>
  <?php endif; ?>
  </tbody>
</table>

</div>
</div>
</div>
<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>
</body>
</html>
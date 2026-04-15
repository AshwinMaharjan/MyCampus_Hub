<?php
session_start();
include("connect.php");
include("auth_check.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$staffId = $_SESSION['uid'];

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Filters
$selectedSubject = isset($_GET['sub_id']) ? intval($_GET['sub_id']) : 0;
$selectedCourse = isset($_GET['course']) ? intval($_GET['course']) : 0;
$selectedSem = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$search_student = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get subject info if subject selected
$subjectInfo = null;
if ($selectedSubject !== 0) {
    $subjectQuery = "SELECT sub_id, sub_name, course_id, sem_id FROM subject WHERE sub_id = ? AND role_id = ?";
    $stmt = $conn->prepare($subjectQuery);
    $stmt->bind_param("ii", $selectedSubject, $staffId);
    $stmt->execute();
    $subjectInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch semesters & courses for filters
$semesters = $conn->query("SELECT sem_id, sem_name FROM semester ORDER BY sem_id")->fetch_all(MYSQLI_ASSOC);
$courses = $conn->query("SELECT course_id, course_name FROM course ORDER BY course_name")->fetch_all(MYSQLI_ASSOC);

// Build SQL dynamically
$types = "";
$params = [];

if ($selectedSubject !== 0 && $subjectInfo) {
    // Students for specific subject
    $sql = "
        SELECT u.id_number, u.user_id, u.full_name, u.email, u.profile_photo,
               c.course_name, s.sem_name
        FROM users u
        INNER JOIN course c ON u.course_id = c.course_id
        INNER JOIN semester s ON u.sem_id = s.sem_id
        WHERE u.role_id = 2
          AND u.status = 'Active'
          AND u.course_id = ?
          AND u.sem_id = ?
        ORDER BY u.full_name ASC
        LIMIT ? OFFSET ?
    ";
    $types = "iiii";
    $params = [$subjectInfo['course_id'], $subjectInfo['sem_id'], $limit, $offset];
} else {
    // All students for this faculty
    $sql = "
        SELECT u.id_number, u.user_id, u.full_name, u.email, u.profile_photo,
               c.course_name, s.sem_name
        FROM users u
        INNER JOIN course c ON u.course_id = c.course_id
        INNER JOIN semester s ON u.sem_id = s.sem_id
        INNER JOIN subject sub ON u.course_id = sub.course_id AND u.sem_id = sub.sem_id
        WHERE u.role_id = 2 AND sub.role_id = ?
    ";
    $types = "i";
    $params = [$staffId];

    if ($selectedSem !== 0) {
        $sql .= " AND u.sem_id = ?";
        $types .= "i";
        $params[] = $selectedSem;
    }

    if ($selectedCourse !== 0) {
        $sql .= " AND u.course_id = ?";
        $types .= "i";
        $params[] = $selectedCourse;
    }

    if (!empty($search_student)) {
        $sql .= " AND (u.full_name LIKE ? OR u.id_number LIKE ?)";
        $types .= "ss";
        $searchTerm = "%" . $search_student . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $sql .= " GROUP BY u.user_id ORDER BY u.full_name ASC LIMIT ? OFFSET ?";
    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;
}

// Count query for pagination
$countSql = preg_replace('/ORDER BY.*/', '', $sql);
$countSql = preg_replace('/LIMIT.*/', '', $countSql);

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = $countResult->num_rows;
$countStmt->close();

$totalPages = ceil($totalRecords / $limit);

// Fetch students
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$startRecord = ($page - 1) * $limit + 1; // first record on current page
$endRecord = min($startRecord + count($students) - 1, $totalRecords); // last record on current page

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Students</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/my_subjects.css" />
  <link rel="stylesheet" href="../css/my_students.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon" />
</head>
<body>
<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
  <?php if ($selectedSubject !== 0 && $subjectInfo): ?>
    <a href="my_subjects.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
    <div class="subject-info-banner">
      <h2><i class="fas fa-users"></i> Students Enrolled in <?= htmlspecialchars($subjectInfo['sub_name']) ?></h2>
      <div class="subject-details">
        <span><strong>Subject Code:</strong> SUB-<?= str_pad($subjectInfo['sub_id'], 3, '0', STR_PAD_LEFT) ?></span>
        <span><strong>Total Students:</strong> <?= count($students) ?></span>
      </div>
    </div>
  <?php else: ?>
    <div class="filter-container">
      <form method="GET" style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;">
        <div class="filter-group">
          <select name="semester">
            <option value="0">All Semesters</option>
            <?php foreach ($semesters as $sem): ?>
              <option value="<?= $sem['sem_id'] ?>" <?= $selectedSem == $sem['sem_id'] ? 'selected' : '' ?>><?= htmlspecialchars($sem['sem_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <select name="course">
            <option value="0">All Courses</option>
            <?php foreach ($courses as $course): ?>
              <option value="<?= $course['course_id'] ?>" <?= $selectedCourse == $course['course_id'] ? 'selected' : '' ?>><?= htmlspecialchars($course['course_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="search-group">
          <input type="text" name="search" placeholder="Enter name or ID..." value="<?= htmlspecialchars($search_student) ?>">
          <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
        </div>
        <button type="button" class="clear-filters-btn" onclick="window.location='my_students.php';"><i class="fas fa-times"></i> Clear Filters</button>
      </form>
    </div>
  <?php endif; ?>
  <?php if ($totalRecords > 0): ?>
    <div class="pagination-info">
        Showing <?= $startRecord ?>–<?= $endRecord ?> of <?= $totalRecords ?> students
    </div>
<?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>ID Number</th>
        <th>Profile</th>
        <th>Name</th>
        <th>Course</th>
        <th>Semester</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($students)): ?>
        <?php $i = 1; foreach ($students as $stu): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($stu['id_number']) ?></td>
            <td>
              <?php if (!empty($stu['profile_photo'])): ?>
                <img src="../uploads/<?= htmlspecialchars($stu['profile_photo']) ?>" class="profile-photo" />
              <?php else: ?>
                <span class="no-photo">No Photo</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($stu['full_name']) ?></td>
            <td><?= htmlspecialchars($stu['course_name']) ?></td>
            <td><?= htmlspecialchars($stu['sem_name']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6" style="color:red;font-weight:bold;">No students found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Prev</a>
    <?php else: ?><span class="disabled">&laquo; Prev</span><?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?= $i == $page ? "<span class='active'>$i</span>" : "<a href='?".http_build_query(array_merge($_GET, ['page'=>$i]))."'>$i</a>" ?>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &raquo;</a>
    <?php else: ?><span class="disabled">Next &raquo;</span><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>
</body>
</html>

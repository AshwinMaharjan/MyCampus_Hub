<?php
session_start();
include("connect.php");
include("auth_check.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$staffId = $_SESSION['uid']; 
$query = "
  SELECT 
    s.sub_id,
    s.sub_name,

    c.course_name,
    sem.sem_name AS semester_name,

    -- Total students (using same logic as my_students.php)
    (
      SELECT COUNT(DISTINCT u.user_id)
      FROM users u
      INNER JOIN subject sub_check ON u.course_id = sub_check.course_id AND u.sem_id = sub_check.sem_id
      WHERE u.role_id = 2 /* student */
        AND u.status = 'Active'
        AND sub_check.sub_id = s.sub_id
        AND sub_check.role_id = ?
    ) AS total_students,

    -- Marks entered for this specific subject
    (
      SELECT COUNT(DISTINCT m.user_id)
      FROM marks m
      WHERE m.sub_id = s.sub_id
        AND m.entered_by_staff = ?
    ) AS marks_entered,

    -- Pending marks for this specific subject
    (
      SELECT COUNT(DISTINCT u.user_id)
      FROM users u
      INNER JOIN subject sub_check ON u.course_id = sub_check.course_id AND u.sem_id = sub_check.sem_id
      WHERE u.role_id = 2 /* student */
      AND u.status = 'Active'
        AND sub_check.sub_id = s.sub_id
        AND sub_check.role_id = ?
        AND NOT EXISTS (
          SELECT 1
          FROM marks m
          WHERE m.user_id = u.user_id 
            AND m.sub_id = s.sub_id
            AND m.entered_by_staff = ?
        )
    ) AS pending_marks

  FROM subject s
  LEFT JOIN course c ON s.course_id = c.course_id
  LEFT JOIN semester sem ON s.sem_id = sem.sem_id
  WHERE s.role_id = ?
  ORDER BY s.sub_name
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiiii", $staffId, $staffId, $staffId, $staffId, $staffId);
$stmt->execute();
$result = $stmt->get_result();

$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Subjects</title>
  <link rel="stylesheet" href="../css/all.min.css" />
  <link rel="stylesheet" href="../css/admin_menu.css" />
  <link rel="stylesheet" href="../css/my_subjects.css" />
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <style>
    #toast {
      visibility: hidden;
      min-width: 250px;
      background-color: green;
      color: #fff;
      text-align: center;
      border-radius: 8px;
      padding: 16px 24px;
      position: fixed;
      z-index: 1000;
      left: 50%;
      bottom: 30px;
      transform: translateX(-50%);
      font-size: 16px;
      opacity: 0;
      transition: opacity 0.5s ease, bottom 0.5s ease;
    }
    #toast.show {
      visibility: visible;
      opacity: 1;
      bottom: 50px;
    }

    /* Card-based layout styles */
    .subjects-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 25px;
      margin-top: 30px;
      padding: 20px 0;
    }

    .subject-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      padding: 25px;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      border: 1px solid #e0e0e0;
    }

    .subject-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    }

    .subject-header {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 2px solid #f0f0f0;
    }

    .subject-icon {
      background: linear-gradient(135deg, #263576 0%, #4a5fa8 100%);
      width: 50px;
      height: 50px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 24px;
    }

    .subject-name {
      font-size: 1.3em;
      color: #263576;
      margin: 0;
      font-weight: 600;
      flex: 1;
    }

    .subject-details {
      display: grid;
      gap: 12px;
      margin-bottom: 20px;
    }

    .subject-detail {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
    }

    .detail-label {
      font-weight: 600;
      color: #555;
      font-size: 0.95em;
    }

    .detail-value {
      color: #333;
      font-weight: 500;
    }

    .stats-container {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin: 15px 0;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
    }

    .stat-box {
      text-align: center;
      padding: 10px;
    }

    .stat-number {
      display: block;
      font-size: 1.8em;
      font-weight: 700;
      color: #263576;
      margin-bottom: 5px;
    }

    .stat-label {
      display: block;
      font-size: 0.85em;
      color: #666;
      font-weight: 500;
    }

    .stat-box.pending .stat-number {
      color: #ff9800;
    }

    .stat-box.completed .stat-number {
      color: #4CAF50;
    }

    .subject-actions {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #e0e0e0;
    }

    .action-btn {
      padding: 12px 10px;
      border: none;
      border-radius: 8px;
      font-size: 0.9em;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      color: white;
    }

    .marks-btn {
      background-color: #2196F3;
    }

    .marks-btn:hover {
      background-color: #0b7dda;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
    }

    .attendance-btn {
      background-color: #4CAF50;
    }

    .attendance-btn:hover {
      background-color: #45a049;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
    }

    .students-btn {
      background-color: #FF9800;
    }

    .students-btn:hover {
      background-color: #f57c00;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(255, 152, 0, 0.3);
    }

    .action-btn i {
      font-size: 1em;
    }

    .no-subjects {
      grid-column: 1 / -1;
      text-align: center;
      padding: 60px 20px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .no-subjects i {
      font-size: 4em;
      color: #ccc;
      margin-bottom: 20px;
    }

    .no-subjects h3 {
      color: #263576;
      margin-bottom: 10px;
    }

    .no-subjects p {
      color: #666;
      line-height: 1.6;
    }

    @media (max-width: 768px) {
      .subjects-container {
        grid-template-columns: 1fr;
      }
      
      .stats-container {
        grid-template-columns: 1fr;
      }

      .subject-actions {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
  <div class="subjects-container">
    <?php if (!empty($subjects)) : ?>
      <?php foreach ($subjects as $sub): ?>
        <div class="subject-card">
          <div class="subject-header">
            <div class="subject-icon">
              <i class="fas fa-book-open"></i>
            </div>
            <h2 class="subject-name"><?= htmlspecialchars($sub['sub_name']) ?></h2>
          </div>

          <div class="subject-details">
            <div class="subject-detail">
              <span class="detail-label">Subject Code:</span>
              <span class="detail-value">SUB-<?= str_pad($sub['sub_id'], 3, '0', STR_PAD_LEFT) ?></span>
            </div>
            <!-- <div class="subject-detail">
              <span class="detail-label">Full Marks:</span>
              <span class="detail-value"><?= htmlspecialchars($sub['full_marks'] ?? 'N/A') ?></span>
            </div> -->
            <div class="subject-detail">
              <span class="detail-label">Course:</span>
              <span class="detail-value"><?= htmlspecialchars($sub['course_name']) ?></span>
            </div>
            <div class="subject-detail">
              <span class="detail-label">Semester:</span>
              <span class="detail-value"><?= htmlspecialchars($sub['semester_name']) ?></span>
            </div>
          </div>

          <div class="stats-container">
            <div class="stat-box">
              <span class="stat-number"><?= htmlspecialchars($sub['total_students']) ?></span>
              <span class="stat-label">Total Students</span>
            </div>
            <div class="stat-box completed">
              <!-- <span class="stat-number"><?= htmlspecialchars($sub['marks_entered']) ?></span>
              <span class="stat-label">Marks Entered</span> -->
            </div>
            <div class="stat-box pending">
              <span class="stat-number"><?= htmlspecialchars($sub['pending_marks']) ?></span>
              <span class="stat-label">Pending Marks</span>
            </div>
          </div>

          <div class="subject-actions">
            <a href="view_marks.php?sub_id=<?= $sub['sub_id'] ?>" class="action-btn marks-btn">
              <i class="fas fa-chart-line"></i>
              <span>Marks</span>
            </a>
            <a href="attendance.php?sub_id=<?= $sub['sub_id'] ?>" class="action-btn attendance-btn">
              <i class="fas fa-calendar-check"></i>
              <span>Attendance</span>
            </a>
            <a href="my_students.php?sub_id=<?= $sub['sub_id'] ?>" class="action-btn students-btn">
              <i class="fas fa-users"></i>
              <span>Students</span>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="no-subjects">
        <i class="fas fa-book-open"></i>
        <h3>No Subjects Assigned</h3>
        <p>You don't have any subjects assigned yet.</p>
        <p>Please contact the administration if you believe this is an error.</p>
      </div>
    <?php endif; ?>
  </div>
</div>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

<div id="toast"></div>
<script>
function showToast(message, status) {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.style.backgroundColor = status === 'success' ? 'green' : 'red';
  toast.classList.add('show');
  setTimeout(() => {
    toast.classList.remove('show');
  }, 6000);
}

<?php if (!empty($showToast)): ?>
  window.onload = function() {
    showToast("<?= addslashes($toastMessage); ?>", "<?= $toastStatus; ?>");
  };
<?php endif; ?>
</script>

</body>
</html>
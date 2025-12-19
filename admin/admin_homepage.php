<?php
session_start();
include("connect.php");
if (!isset($_SESSION['uid'])) {
  header("Location: login.php");
  exit();
}

// Fetch Active Students
$active_students_query = "
    SELECT COUNT(*) AS total 
    FROM users 
    WHERE role_id = 2 
    AND status = 'Active'
";
$active_students_result = mysqli_query($conn, $active_students_query);
$active_students = mysqli_fetch_assoc($active_students_result)['total'];

// Fetch Active Staff (Faculty + Non-Faculty)
$active_staff_query = "
    SELECT COUNT(*) AS total 
    FROM users 
    WHERE role_id = 3 
    AND status = 'Active'
";
$active_staff_result = mysqli_query($conn, $active_staff_query);
$active_staff = mysqli_fetch_assoc($active_staff_result)['total'];

// Fetch Pending Leave Requests
$pending_leave_query = "SELECT COUNT(*) as total FROM staff_leave_requests WHERE status = 'pending'";
$pending_leave_result = mysqli_query($conn, $pending_leave_query);
$pending_leave = mysqli_fetch_assoc($pending_leave_result)['total'];

// Fetch 1 Week Attendance Overview
$one_week_ago = date('Y-m-d', strtotime('-7 days'));
$attendance_query = "SELECT 
    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
    COUNT(*) as total_count
    FROM attendance 
    WHERE attendance_date >= '$one_week_ago'";
$attendance_result = mysqli_query($conn, $attendance_query);
$attendance_data = mysqli_fetch_assoc($attendance_result);
$attendance_percentage = $attendance_data['total_count'] > 0 ? 
    round(($attendance_data['present_count'] / $attendance_data['total_count']) * 100, 1) : 0;

// Student Enrollment by Course
$enrollment_query = " SELECT c.course_name, COUNT(u.user_id) as student_count FROM course c LEFT JOIN users u ON c.course_id = u.course_id AND u.role_id = 2 AND u.status = 'Active' GROUP BY c.course_id, c.course_name ORDER BY student_count DESC ";
$enrollment_result = mysqli_query($conn, $enrollment_query);
$enrollment_data = [];
while($row = mysqli_fetch_assoc($enrollment_result)) {
    $enrollment_data[] = $row;
}

// Leave Requests Over Time (Last 30 days)
$leave_time_query = "SELECT DATE(requested_at) as date, COUNT(*) as count
    FROM staff_leave_requests
    WHERE requested_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(requested_at)
    ORDER BY date ASC";
$leave_time_result = mysqli_query($conn, $leave_time_query);
$leave_time_data = [];
while($row = mysqli_fetch_assoc($leave_time_result)) {
    $leave_time_data[] = $row;
}

// User Composition
$user_comp_query = " SELECT SUM(CASE WHEN role_id = 2 THEN 1 ELSE 0 END) as students, SUM(CASE WHEN role_id = 3 AND staff_type = 'Teaching' THEN 1 ELSE 0 END) as faculty, SUM(CASE WHEN role_id = 3 AND staff_type = 'Non Teaching' THEN 1 ELSE 0 END) as non_faculty FROM users WHERE status = 'Active' ";
$user_comp_result = mysqli_query($conn, $user_comp_query);
$user_comp = mysqli_fetch_assoc($user_comp_result);

// Study Material Upload Trend (Last 7 days)
$material_trend_query = "SELECT DATE(upload_date) as date, COUNT(*) as count
    FROM study_material
    WHERE upload_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(upload_date)
    ORDER BY date ASC";
$material_trend_result = mysqli_query($conn, $material_trend_query);
$material_trend_data = [];
while($row = mysqli_fetch_assoc($material_trend_result)) {
    $material_trend_data[] = $row;
}

// Subject-wise Class Average Marks
$subject_avg_query = "SELECT s.sub_name, AVG(m.percentage) as avg_percentage
    FROM marks m
    JOIN subject s ON m.sub_id = s.sub_id
    GROUP BY m.sub_id, s.sub_name
    ORDER BY avg_percentage DESC
    LIMIT 10";
$subject_avg_result = mysqli_query($conn, $subject_avg_query);
$subject_avg_data = [];
while($row = mysqli_fetch_assoc($subject_avg_result)) {
    $subject_avg_data[] = $row;
}

// Grade Distribution (Overall)
$grade_dist_query = "
SELECT UPPER(TRIM(grade)) AS grade, COUNT(*) as count
FROM marks
WHERE grade IS NOT NULL AND TRIM(grade) != ''
GROUP BY UPPER(TRIM(grade))
ORDER BY FIELD(grade, 'A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'F')
";

$grade_dist_result = mysqli_query($conn, $grade_dist_query);
$grade_dist_data = [];
while($row = mysqli_fetch_assoc($grade_dist_result)) {
    $grade_dist_data[$row['grade']] = intval($row['count']);
}

// Ensure all grades are present, even if count = 0
$grades = ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'F'];
foreach ($grades as $g) {
    if (!isset($grade_dist_data[$g])) $grade_dist_data[$g] = 0;
}

// Top 10 Students by Average Grade
$top_students_query = "
    SELECT u.full_name, u.id_number, AVG(m.percentage) as avg_percentage, 
    COUNT(DISTINCT m.sub_id) as subjects_taken
    FROM users u
    JOIN marks m ON u.user_id = m.user_id
    WHERE u.role_id = 2
    GROUP BY u.user_id, u.full_name, u.id_number
    ORDER BY avg_percentage DESC
    LIMIT 10
";
$top_students_result = mysqli_query($conn, $top_students_query);
$top_students_data = [];
while($row = mysqli_fetch_assoc($top_students_result)) {
    $top_students_data[] = $row;
}

// Subject Count Per Course
$subject_per_course_query = "
    SELECT c.course_name, COUNT(s.sub_id) AS subject_count
    FROM course c
    LEFT JOIN subject s ON c.course_id = s.course_id
    GROUP BY c.course_id, c.course_name
    ORDER BY subject_count DESC
";

$subject_per_course_result = mysqli_query($conn, $subject_per_course_query);
$subject_per_course_data = [];
while ($row = mysqli_fetch_assoc($subject_per_course_result)) {
    $subject_per_course_data[] = $row;
}


// Latest 5 Leave Requests
$latest_leave_query = "SELECT slr.leave_id, u.full_name, slr.leave_type, slr.start_date, 
    slr.end_date, slr.status, slr.requested_at
    FROM staff_leave_requests slr
    JOIN users u ON slr.staff_id = u.user_id
    ORDER BY slr.requested_at DESC
    LIMIT 5";
$latest_leave_result = mysqli_query($conn, $latest_leave_query);
$latest_leave_data = [];
while($row = mysqli_fetch_assoc($latest_leave_result)) {
    $latest_leave_data[] = $row;
}

// Recent Study Materials
$recent_materials_query = "SELECT sm.material_id, s.sub_name, u.full_name as full_name, 
    sm.material_type, sm.upload_date, sm.file_path, sm.file_name, sm.approval_status
    FROM study_material sm
    JOIN subject s ON sm.subject_id = s.sub_id
    JOIN users u ON sm.user_id = u.user_id
    ORDER BY sm.upload_date DESC
    LIMIT 5";
$recent_materials_result = mysqli_query($conn, $recent_materials_query);
$recent_materials_data = [];
while($row = mysqli_fetch_assoc($recent_materials_result)) {
    $recent_materials_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Super Admin Dashboard</title>
  <link rel="stylesheet" href="../css/all.min.css">
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="../css/admin_menu.css">
  <link rel="stylesheet" href="../css/admin_dashboard.css">
</head>

<body>
  <?php include("header.php"); ?>
  <?php include("menu.php"); ?>
  
  <div class="dashboard-container">
    <!-- Row 1: Stat Cards -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon blue">
          <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $active_students; ?></h3>
          <p>Active Students</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $active_staff; ?></h3>
          <p>Active Staff</p>
        </div>
      </div>

      <div class="stat-card <?php echo $pending_leave > 3 ? 'alert' : ''; ?>">
        <div class="stat-icon <?php echo $pending_leave > 3 ? 'red' : 'orange'; ?>">
          <i class="fas fa-calendar-times"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $pending_leave; ?></h3>
          <p>Pending Leave Requests</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon purple">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $attendance_percentage; ?>%</h3>
          <p>Attendance (7 Days)</p>
        </div>
      </div>
    </div>

    <!-- Row 2: Charts -->
    <div class="charts-row">
      <div class="chart-card">
        <h3>Student Enrollment by Course</h3>
        <canvas id="enrollmentChart"></canvas>
      </div>

      <div class="chart-card">
        <h3>Leave Requests Over Time</h3>
        <canvas id="leaveTimeChart"></canvas>
      </div>

      <div class="chart-card">
        <h3>User Composition</h3>
        <canvas id="userCompositionChart"></canvas>
      </div>
    </div>

    <div class="charts-row">
      <div class="chart-card">
        <h3>Study Material Upload Trend</h3>
        <canvas id="materialTrendChart"></canvas>
      </div>

      <div class="chart-card">
        <h3>Subject-wise Average Marks</h3>
        <canvas id="subjectAvgChart"></canvas>
      </div>

      <div class="chart-card">
        <h3>Grade Distribution</h3>
        <canvas id="gradeDistChart"></canvas>
      </div>
    </div>

    <div class="charts-row">
      <div class="chart-card full-width">
        <h3>Top 10 Students by Marks Performance</h3>
        <div class="top-students-grid">
          <?php if (count($top_students_data) > 0): ?>
            <?php foreach($top_students_data as $index => $student): ?>
              <div class="student-rank-card">
                <div class="rank-badge">#<?php echo $index + 1; ?></div>
                <div class="student-info">
                  <h4><?php echo htmlspecialchars($student['full_name']); ?></h4>
                  <p class="student-id"><?php echo htmlspecialchars($student['id_number']); ?></p>
                  <p class="student-avg"><?php echo round($student['avg_percentage'], 2); ?>%</p>
                  <p class="student-subjects"><?php echo $student['subjects_taken']; ?> Subjects</p>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="text-align:center;color:#6b7280;padding:40px;grid-column:1/-1;">No student performance data available yet.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="chart-card">
        <h3>Subject Count per Course</h3>
        <canvas id="subjectPerCourseChart"></canvas>
      </div>
    </div>

    <!-- Row 3: Tables -->
    <div class="tables-row">
      <div class="table-card">
        <h3>Latest Leave Requests</h3>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Staff Name</th>
                <th>Leave Type</th>
                <th>Date Range</th>
                <th>Status</th>
                <!-- <th>Actions</th> -->
              </tr>
            </thead>
            <tbody>
              <?php if (count($latest_leave_data) > 0): ?>
                <?php foreach($latest_leave_data as $leave): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($leave['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                    <td><?php echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d', strtotime($leave['end_date'])); ?></td>
                    <td>
                      <span class="status-badge <?php echo strtolower($leave['status']); ?>">
                        <?php echo ucfirst($leave['status']); ?>
                      </span>
                    </td>
                    <!-- <td class="action-buttons">
                      <?php if(strtolower($leave['status']) == 'pending'): ?>
                        <button class="btn-accept" onclick="handleLeave(<?php echo $leave['leave_id']; ?>, 'Approved')">
                          <i class="fas fa-check"></i>
                        </button>
                        <button class="btn-reject" onclick="handleLeave(<?php echo $leave['leave_id']; ?>, 'Rejected')">
                          <i class="fas fa-times"></i>
                        </button>
                      <?php else: ?>
                        <span class="text-muted">Processed</span>
                      <?php endif; ?>
                    </td> -->
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align:center;color:#6b7280;padding:20px;">No leave requests yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="table-card">
        <h3>Recent Study Materials</h3>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Subject Name</th>
                <th>Full Name</th>
                <th>File Type</th>
                <th>Uploaded At</th>
                <!-- <th>Actions</th> -->
                <th>Status</th>
                 
              </tr>
            </thead>
            <tbody>
              <?php foreach($recent_materials_data as $material): ?>
                <tr>
                  <td><?php echo htmlspecialchars($material['sub_name']); ?></td>
                  <td><?php echo htmlspecialchars($material['full_name']); ?></td>
                  <td>
                    <span class="file-type-badge">
                      <?php echo strtoupper($material['material_type']); ?>
                    </span>
                  </td>
                  <td><?php echo date('M d, Y H:i', strtotime($material['upload_date'])); ?></td>
                  <td>
  <span class="status-badge <?php echo strtolower($material['approval_status']); ?>">
    <?php echo ucfirst($material['approval_status']); ?>
  </span>
</td>

                  <!-- <td class="action-buttons">
                    <a href="<?php echo htmlspecialchars($material['file_path']); ?>" 
                       class="btn-view" target="_blank" title="View">
                      <i class="fas fa-eye"></i>
                    </a>
                    <a href="<?php echo htmlspecialchars($material['file_path']); ?>" 
                       class="btn-download" download title="Download">
                      <i class="fas fa-download"></i>
                    </a>
                  </td> -->
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    </div>
  </div>

  <?php include("footer.php"); ?>
  <?php include("lower_footer.php"); ?>

  <script src="../js/chart.min.js"></script>
  <script src="../js/chartjs-plugin-datalabels.min.js"></script>
  <script>
    // Pass PHP data to JavaScript
    const dashboardData = {
      enrollment: <?php echo json_encode($enrollment_data); ?>,
      leaveTime: <?php echo json_encode($leave_time_data); ?>,
      userComp: <?php echo json_encode($user_comp); ?>,
      materialTrend: <?php echo json_encode($material_trend_data); ?>,
      subjectAvg: <?php echo json_encode($subject_avg_data); ?>,
      gradeDist: <?php echo json_encode($grade_dist_data); ?>,
      subjectPerCourse: <?php echo json_encode($subject_per_course_data); ?>
    };
  </script>
  <script src="../js/admin_dashboard.js"></script>
</body>
</html>
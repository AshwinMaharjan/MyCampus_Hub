<?php
session_start();
include("connect.php");
include("auth_check.php");


if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['uid'];

// Fetch Total Subjects Enrolled
$subjects_query = "
    SELECT COUNT(*) AS total
    FROM subject s
    JOIN users u ON s.course_id = u.course_id AND s.sem_id = u.sem_id
    WHERE u.user_id = '$student_id';
";
$subjects_result = mysqli_query($conn, $subjects_query);
$total_subjects = mysqli_fetch_assoc($subjects_result)['total'];

// Fetch Attendance Percentage (Last 30 days)
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
$attendance_query = "
    SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
        COUNT(*) as total_count
    FROM attendance
    WHERE user_id = '$student_id'
    AND attendance_date >= '$thirty_days_ago'
";
$attendance_result = mysqli_query($conn, $attendance_query);
$attendance_data = mysqli_fetch_assoc($attendance_result);
$attendance_percentage = $attendance_data['total_count'] > 0 ? 
    round(($attendance_data['present_count'] / $attendance_data['total_count']) * 100, 1) : 0;

// Fetch Average Marks
$average_marks_query = "
    SELECT AVG(percentage) as avg_percentage
    FROM marks
    WHERE user_id = '$student_id'
";
$average_marks_result = mysqli_query($conn, $average_marks_query);
$average_marks_data = mysqli_fetch_assoc($average_marks_result);
$average_marks = $average_marks_data['avg_percentage'] ? round($average_marks_data['avg_percentage'], 1) : 0;

// Fetch Pending Leave Requests
$pending_leave_query = "
    SELECT COUNT(*) as total
    FROM student_leave_requests
    WHERE student_id = '$student_id'
    AND status = 'Pending'
";
$pending_leave_result = mysqli_query($conn, $pending_leave_query);
$pending_leaves = mysqli_fetch_assoc($pending_leave_result)['total'];

// Fetch Pending Study Material Approvals
$pending_materials_query = "
    SELECT COUNT(*) as total
    FROM study_material
    WHERE user_id = '$student_id'
    AND approval_status = 'Pending'
";
$pending_materials_result = mysqli_query($conn, $pending_materials_query);
$pending_materials = mysqli_fetch_assoc($pending_materials_result)['total'];

// Attendance Trend (Last 30 Days)
$attendance_trend_query = "
    SELECT 
        DATE(attendance_date) as date,
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
        COUNT(*) as total_count
    FROM attendance
    WHERE user_id = '$student_id'
    AND attendance_date >= '$thirty_days_ago'
    GROUP BY DATE(attendance_date)
    ORDER BY date ASC
";
$attendance_trend_result = mysqli_query($conn, $attendance_trend_query);
$attendance_trend_data = [];
while($row = mysqli_fetch_assoc($attendance_trend_result)) {
    $row['percentage'] = $row['total_count'] > 0 ? 
        round(($row['present_count'] / $row['total_count']) * 100, 1) : 0;
    $attendance_trend_data[] = $row;
}

// Marks Overview by Subject
$marks_overview_query = "
    SELECT 
        s.sub_name,
        AVG(m.percentage) as avg_percentage
    FROM marks m
    JOIN subject s ON m.sub_id = s.sub_id
    WHERE m.user_id = '$student_id'
    GROUP BY m.sub_id, s.sub_name
    ORDER BY avg_percentage DESC
";
$marks_overview_result = mysqli_query($conn, $marks_overview_query);
$marks_overview_data = [];
while($row = mysqli_fetch_assoc($marks_overview_result)) {
    $marks_overview_data[] = $row;
}

// Grade Distribution
$grades = ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'F'];
$grades_list = "'" . implode("','", $grades) . "'";

$grade_dist_query = "
    SELECT grade, COUNT(*) as count
    FROM marks
    WHERE user_id = '$student_id'
    AND grade IN ($grades_list)
    GROUP BY grade
    ORDER BY FIELD(grade, $grades_list)
";
$grade_dist_result = mysqli_query($conn, $grade_dist_query);
$grade_dist_data = [];
foreach ($grades as $g) $grade_dist_data[$g] = 0;
while ($row = mysqli_fetch_assoc($grade_dist_result)) {
    $grade_dist_data[$row['grade']] = intval($row['count']);
}

// Recent Marks Table
$recent_marks_query = "
    SELECT 
        s.sub_name,
        m.exam_type_id,
        m.obtained_marks,
        m.full_marks,
        m.percentage,
        m.grade,
        m.exam_date
    FROM marks m
    JOIN subject s ON m.sub_id = s.sub_id
    WHERE m.user_id = '$student_id'
    ORDER BY m.exam_date DESC
    LIMIT 10
";
$recent_marks_result = mysqli_query($conn, $recent_marks_query);
$recent_marks_data = [];
while($row = mysqli_fetch_assoc($recent_marks_result)) {
    $recent_marks_data[] = $row;
}

// Attendance Summary Table
$attendance_summary_query = "
    SELECT 
        a.attendance_date,
        a.status,
        s.sub_name
    FROM attendance a
    JOIN subject s ON a.sub_id = s.sub_id
    WHERE a.user_id = '$student_id'
    ORDER BY a.attendance_date DESC
    LIMIT 15
";
$attendance_summary_result = mysqli_query($conn, $attendance_summary_query);
$attendance_summary_data = [];
while($row = mysqli_fetch_assoc($attendance_summary_result)) {
    $attendance_summary_data[] = $row;
}

// Leave Request History
$leave_history_query = "
    SELECT 
        start_date,
        end_date,
        reason,
        status,
        requested_at
    FROM student_leave_requests
    WHERE student_id = '$student_id'
    ORDER BY requested_at DESC
    LIMIT 10
";
$leave_history_result = mysqli_query($conn, $leave_history_query);
$leave_history_data = [];
while($row = mysqli_fetch_assoc($leave_history_result)) {
    $leave_history_data[] = $row;
}

// Study Materials Uploaded
$materials_uploaded_query = "
    SELECT 
        s.sub_name,
        sm.file_name,
        sm.file_path,
        sm.upload_date,
        sm.approval_status
    FROM study_material sm
    JOIN subject s ON sm.subject_id = s.sub_id
    WHERE sm.user_id = '$student_id'
    ORDER BY sm.upload_date DESC
    LIMIT 10
";
$materials_uploaded_result = mysqli_query($conn, $materials_uploaded_query);
$materials_uploaded_data = [];
while($row = mysqli_fetch_assoc($materials_uploaded_result)) {
    $materials_uploaded_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="../css/all.min.css">
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="../css/admin_menu.css">
  <link rel="stylesheet" href="../css/student_dashboard.css">
</head>

<body>
  <?php include("header.php"); ?>
  
  <div class="page-wrapper">
  <?php include("menu.php"); ?>
  <div class="dashboard-container">

    <!-- Row 1: Stats Cards -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon blue">
          <i class="fas fa-book-open"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $total_subjects; ?></h3>
          <p>Subjects Enrolled</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon <?php echo $attendance_percentage >= 75 ? 'green' : ($attendance_percentage >= 60 ? 'orange' : 'red'); ?>">
          <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $attendance_percentage; ?>%</h3>
          <p>Attendance (30 Days)</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon <?php echo $average_marks >= 75 ? 'green' : ($average_marks >= 60 ? 'purple' : 'orange'); ?>">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $average_marks; ?>%</h3>
          <p>Average Marks</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon <?php echo $pending_leaves > 0 ? 'orange' : 'teal'; ?>">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $pending_leaves; ?></h3>
          <p>Pending Leave Requests</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon <?php echo $pending_materials > 0 ? 'purple' : 'teal'; ?>">
          <i class="fas fa-file-upload"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $pending_materials; ?></h3>
          <p>Pending Material Approvals</p>
        </div>
      </div>
    </div>

    <!-- Row 2: Visualizations -->
    <div class="charts-row">
      <div class="chart-card">
        <h3><i class="fas fa-chart-line"></i> Attendance Trend (Last 30 Days)</h3>
        <canvas id="attendanceTrendChart"></canvas>
      </div>

      <div class="chart-card">
        <h3><i class="fas fa-chart-bar"></i> Marks Overview by Subject</h3>
        <canvas id="marksOverviewChart"></canvas>
      </div>

      <div class="chart-card">
        <h3><i class="fas fa-chart-pie"></i> Grade Distribution</h3>
        <canvas id="gradeDistributionChart"></canvas>
      </div>
    </div>

    <!-- Row 3: Tables -->
    <div class="tables-row">
      <div class="table-card">
        <h3><i class="fas fa-graduation-cap"></i> Recent Marks</h3>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Subject</th>
                <!-- <th>Exam Type</th> -->
                <th>Marks</th>
                <th>Percentage</th>
                <th>Grade</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($recent_marks_data) > 0): ?>
                <?php foreach($recent_marks_data as $mark): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($mark['sub_name']); ?></td>
                    <!-- <td>
                      <?php
                      $exam_types = ['1' => 'First Terminal', '2' => 'Second Terminal', '3' => 'Final'];
                      echo $exam_types[$mark['exam_type_id']] ?? 'N/A';
                      ?>
                    </td> -->
                    <td><?php echo $mark['obtained_marks'] . '/' . $mark['full_marks']; ?></td>
                    <td><?php echo round($mark['percentage'], 1); ?>%</td>
                    <td>
                      <span class="grade-badge grade-<?php echo strtolower(str_replace('+', 'plus', $mark['grade'])); ?>">
                        <?php echo $mark['grade']; ?>
                      </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($mark['exam_date'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align:center;color:#6b7280;padding:20px;">No marks recorded yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="table-card">
        <h3><i class="fas fa-calendar-alt"></i> Attendance Summary</h3>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Status</th>
                <th>Subject</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($attendance_summary_data) > 0): ?>
                <?php foreach($attendance_summary_data as $attendance): ?>
                  <tr>
                    <td><?php echo date('M d, Y', strtotime($attendance['attendance_date'])); ?></td>
                    <td>
                      <span class="attendance-badge <?php echo strtolower($attendance['status']); ?>">
                        <?php echo ucfirst($attendance['status']); ?>
                      </span>
                    </td>
                    <td><?php echo htmlspecialchars($attendance['sub_name']); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="3" style="text-align:center;color:#6b7280;padding:20px;">No attendance records yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="tables-row">
      <div class="table-card">
        <h3><i class="fas fa-file-alt"></i> Leave Request History</h3>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date Range</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Requested At</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($leave_history_data) > 0): ?>
                <?php foreach($leave_history_data as $leave): ?>
                  <tr>
                    <td><?php echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d, Y', strtotime($leave['end_date'])); ?></td>
                    <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)) . (strlen($leave['reason']) > 50 ? '...' : ''); ?></td>
                    <td>
                      <span class="status-badge <?php echo strtolower($leave['status']); ?>">
                        <?php echo ucfirst($leave['status']); ?>
                      </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($leave['requested_at'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" style="text-align:center;color:#6b7280;padding:20px;">No leave requests yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="table-card">
        <h3><i class="fas fa-cloud-upload-alt"></i> Study Materials Uploaded</h3>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Subject</th>
                <th>File</th>
                <th>Uploaded At</th>
                <th>Status</th>
                <!-- <th>Actions</th> -->
              </tr>
            </thead>
            <tbody>
              <?php if (count($materials_uploaded_data) > 0): ?>
                <?php foreach($materials_uploaded_data as $material): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($material['sub_name']); ?></td>
                    <td><?php echo htmlspecialchars($material['file_name']); ?></td>
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
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align:center;color:#6b7280;padding:20px;">No study materials uploaded yet.</td>
                </tr>
              <?php endif; ?>
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
    const studentDashboardData = {
      attendanceTrend: <?php echo json_encode($attendance_trend_data); ?>,
      marksOverview: <?php echo json_encode($marks_overview_data); ?>,
      gradeDistribution: <?php echo json_encode($grade_dist_data); ?>
    };
  </script>
  <script src="../js/student_dashboard.js"></script>
</body>
</html>
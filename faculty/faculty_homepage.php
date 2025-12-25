<?php
session_start();
include("connect.php");
include("auth_check.php");

if (!isset($_SESSION['uid'])) {
  header("Location: login.php");
  exit();
}
 
$uid = $_SESSION['uid'];
$role_id = $_SESSION['type']; // 3 for faculty

// Test if subjects exist using user_id
$test_query = "SELECT 1 FROM subject WHERE role_id = '$uid' LIMIT 1";
$test_result = mysqli_query($conn, $test_query);

if (mysqli_num_rows($test_result) > 0) {
    // Correct mapping: role_id stores user_id
    $faculty_id = $uid;
} else {
    // Fallback mapping: role_id stores faculty role (3)
    $faculty_id = $role_id;
}

// Fetch Number of Subjects Faculty Teaches
$subjects_query = "
    SELECT COUNT(DISTINCT sub_id) as total 
    FROM subject 
    WHERE role_id = '$faculty_id'
";
$subjects_result = mysqli_query($conn, $subjects_query);
$total_subjects = mysqli_fetch_assoc($subjects_result)['total'];

// Fetch Total Students Across All Subjects
$students_query = "
    SELECT COUNT(DISTINCT u.user_id) as total
    FROM users u
    JOIN subject s 
        ON u.course_id = s.course_id 
        AND u.sem_id = s.sem_id
    WHERE s.role_id = '$faculty_id'
      AND u.role_id = 2
      AND u.status = 'Active'
";
$students_result = mysqli_query($conn, $students_query);
$total_students = mysqli_fetch_assoc($students_result)['total'];

// Fetch 7-Day Average Attendance
$seven_days_ago = date('Y-m-d', strtotime('-7 days'));
$attendance_avg_query = "
    SELECT 
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
        COUNT(*) as total_count
    FROM attendance a
    JOIN subject s ON a.sub_id = s.sub_id
    WHERE s.role_id = '$faculty_id' 
    AND a.attendance_date >= '$seven_days_ago'
";
$attendance_avg_result = mysqli_query($conn, $attendance_avg_query);
$attendance_avg_data = mysqli_fetch_assoc($attendance_avg_result);
$attendance_percentage = $attendance_avg_data['total_count'] > 0 ? 
    round(($attendance_avg_data['present_count'] / $attendance_avg_data['total_count']) * 100, 1) : 0;

// // Fetch Pending Student Leave Requests (for faculty's subjects)
// $pending_student_leave_query = "
//     SELECT COUNT(*) as total 
//     FROM student_leave_requests slr
//     JOIN users u ON slr.student_id = u.user_id
//     JOIN subject s ON u.course_id = s.course_id
//     WHERE s.role_id = '$faculty_id' 
//     AND slr.status = 'Pending'
// ";
// $pending_student_leave_result = mysqli_query($conn, $pending_student_leave_query);
// $pending_teacher_leaves = mysqli_fetch_assoc($pending_student_leave_result)['total'] ?? 0;

// Fetch Pending Leave Requests of Logged-in Teacher
$pending_teacher_leave_query = "
    SELECT COUNT(*) as total
    FROM staff_leave_requests
    WHERE staff_id = '$faculty_id'
    AND status = 'Pending'
";
$pending_teacher_leave_result = mysqli_query($conn, $pending_teacher_leave_query);
$pending_teacher_leaves = mysqli_fetch_assoc($pending_teacher_leave_result)['total'] ?? 0;

// Fetch Total Study Materials Uploaded by Faculty
$study_materials_query = "
    SELECT COUNT(*) as total 
    FROM study_material 
    WHERE user_id = '$faculty_id'
";
$study_materials_result = mysqli_query($conn, $study_materials_query);
$total_study_materials = mysqli_fetch_assoc($study_materials_result)['total'];

// Attendance Marking Status (by subject)
$attendance_marking_query = "
    SELECT 
        s.sub_name,
        COUNT(DISTINCT a.attendance_date) as days_marked
    FROM subject s
    LEFT JOIN attendance a ON s.sub_id = a.sub_id 
        AND a.attendance_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE s.role_id = '$faculty_id'
    GROUP BY s.sub_id, s.sub_name
    ORDER BY days_marked DESC
";
$attendance_marking_result = mysqli_query($conn, $attendance_marking_query);
$attendance_marking_data = [];
while($row = mysqli_fetch_assoc($attendance_marking_result)) {
    $attendance_marking_data[] = $row;
}

// Student Attendance Report (Last 30 Days)
$student_attendance_report_query = "
    SELECT 
        DATE(a.attendance_date) as date,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
        COUNT(*) as total_count
    FROM attendance a
    JOIN subject s ON a.sub_id = s.sub_id
    WHERE s.role_id = '$faculty_id'
    AND a.attendance_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(a.attendance_date)
    ORDER BY date ASC
";
$student_attendance_report_result = mysqli_query($conn, $student_attendance_report_query);
$student_attendance_report_data = [];
while($row = mysqli_fetch_assoc($student_attendance_report_result)) {
    $row['percentage'] = $row['total_count'] > 0 ? 
        round(($row['present_count'] / $row['total_count']) * 100, 1) : 0;
    $student_attendance_report_data[] = $row;
}

// Marks Entry Status (Last 30 Days)
$marks_entry_status_query = "
    SELECT 
        COUNT(CASE WHEN m.marks_id IS NOT NULL THEN 1 END) as entered,
        (SELECT COUNT(DISTINCT u.user_id) * COUNT(DISTINCT s.sub_id)
         FROM users u, subject s 
         WHERE s.role_id = '$faculty_id' 
         AND u.role_id = 2 
         AND u.status = 'Active') as total_expected
    FROM users u
    CROSS JOIN subject s
    LEFT JOIN marks m ON u.user_id = m.user_id 
        AND s.sub_id = m.sub_id 
        AND m.exam_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE s.role_id = '$faculty_id'
    AND u.role_id = 2
    AND u.status = 'Active'
";
$marks_entry_result = mysqli_query($conn, $marks_entry_status_query);
$marks_entry_data = mysqli_fetch_assoc($marks_entry_result);
$marks_entered = $marks_entry_data['entered'] ?? 0;
$marks_pending = max(0, ($marks_entry_data['total_expected'] ?? 0) - $marks_entered);

// Study Material Upload Trend (Last 30 Days)
$material_upload_trend_query = "
    SELECT 
        DATE(upload_date) as date,
        COUNT(*) as count
    FROM study_material
    WHERE user_id = '$faculty_id'
    AND upload_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(upload_date)
    ORDER BY date ASC
";
$material_upload_trend_result = mysqli_query($conn, $material_upload_trend_query);
$material_upload_trend_data = [];
while($row = mysqli_fetch_assoc($material_upload_trend_result)) {
    $material_upload_trend_data[] = $row;
}

// Subject-wise Student Performance
$subject_performance_query = "
    SELECT 
        s.sub_name,
        AVG(m.percentage) as avg_percentage
    FROM marks m
    JOIN subject s ON m.sub_id = s.sub_id
    WHERE s.role_id = '$faculty_id'
    GROUP BY s.sub_id, s.sub_name
    ORDER BY avg_percentage DESC
";
$subject_performance_result = mysqli_query($conn, $subject_performance_query);
$subject_performance_data = [];
while($row = mysqli_fetch_assoc($subject_performance_result)) {
    $subject_performance_data[] = $row;
}

// Pending Student Leave Requests
// $pending_leaves_table_query = "
//     SELECT 
//         slr.leave_id,
//         u.full_name as student_name,
//         s.sub_name,
//         slr.reason,
//         slr.start_date,
//         slr.end_date,
//         slr.status
//     FROM student_leave_requests slr
//     JOIN users u ON slr.student_id = u.user_id
//     JOIN subject s 
//         ON s.course_id = slr.course_id 
//         AND s.sem_id = slr.sem_id
//         AND s.role_id = '$faculty_id'
//     WHERE slr.status = 'Pending'
//     ORDER BY slr.requested_at DESC
//     LIMIT 10
// ";
// $pending_leaves_table_result = mysqli_query($conn, $pending_leaves_table_query);
// $pending_leaves_table_data = [];
// while($row = mysqli_fetch_assoc($pending_leaves_table_result)) {
//     $pending_leaves_table_data[] = $row;
// }
// Fetch Pending Leave Requests of Logged-in Teacher (Table View)
$pending_leaves_table_query = "
    SELECT 
        slr.leave_id,
        slr.reason,
        slr.start_date,
        slr.end_date,
        slr.status,
        slr.requested_at
    FROM staff_leave_requests slr
    WHERE slr.staff_id = '$faculty_id'
    AND slr.status = 'Pending'
    ORDER BY slr.requested_at DESC
    LIMIT 10
";
$pending_leaves_table_result = mysqli_query($conn, $pending_leaves_table_query);
$pending_leaves_table_data = [];
while($row = mysqli_fetch_assoc($pending_leaves_table_result)) {
    $pending_leaves_table_data[] = $row;
}

// Study Materials Pending Approval
$materials_pending_query = "
    SELECT 
        sm.material_id,
        u.full_name AS student_name,
        s.sub_name,
        sm.file_name,
        sm.file_path,
        sm.upload_date,
        sm.approval_status
    FROM study_material sm
    JOIN users u ON sm.user_id = u.user_id
    JOIN subject s ON sm.subject_id = s.sub_id
    WHERE s.role_id = '$faculty_id'  -- teacher assigned to subject
      AND sm.approval_status = 'pending'
    ORDER BY sm.upload_date DESC
    LIMIT 10
";
$materials_pending_result = mysqli_query($conn, $materials_pending_query);
$materials_pending_data = [];
while($row = mysqli_fetch_assoc($materials_pending_result)) {
    $materials_pending_data[] = $row;
}

// Latest Marks Entry
$latest_marks_query = "
    SELECT 
        m.marks_id,
        u.full_name as student_name,
        s.sub_name,
        m.obtained_marks,
        m.full_marks,
        m.exam_type_id,
        m.grade,
        m.percentage,
        m.exam_date
    FROM marks m
    JOIN users u ON m.user_id = u.user_id
    JOIN subject s ON m.sub_id = s.sub_id
    WHERE m.entered_by_staff = '$faculty_id'
    ORDER BY m.exam_date DESC
    LIMIT 5
";
$latest_marks_result = mysqli_query($conn, $latest_marks_query);
$latest_marks_data = [];
while($row = mysqli_fetch_assoc($latest_marks_result)) {
    $latest_marks_data[] = $row;
}

// Top 10 Performing Students in Faculty's Subjects
$top_students_query = "
    SELECT 
        u.full_name,
        u.id_number,
        AVG(m.percentage) as avg_percentage,
        COUNT(DISTINCT m.sub_id) as subjects_taken
    FROM users u
    JOIN marks m ON u.user_id = m.user_id
    JOIN subject s ON m.sub_id = s.sub_id
    WHERE s.role_id = '$faculty_id'
    AND u.role_id = 2
    GROUP BY u.user_id, u.full_name, u.id_number
    ORDER BY avg_percentage DESC
    LIMIT 10
";
$top_students_result = mysqli_query($conn, $top_students_query);
$top_students_data = [];
while($row = mysqli_fetch_assoc($top_students_result)) {
    $top_students_data[] = $row;
}
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
  <link rel="stylesheet" href="../css/faculty_dashboard.css">
</head>

<body>
  <?php include("header.php"); ?>
  <?php include("menu.php"); ?>
  
  <div class="dashboard-container">
    <!-- Top Stats Cards -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon blue">
          <i class="fas fa-book"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $total_subjects; ?></h3>
          <p>Subjects Teaching</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green">
          <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $total_students; ?></h3>
          <p>Total Students</p>
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

      <div class="stat-card <?php echo $pending_teacher_leaves > 5 ? 'alert' : ''; ?>">
        <div class="stat-icon <?php echo $pending_teacher_leaves > 5 ? 'red' : 'orange'; ?>">
          <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $pending_teacher_leaves; ?></h3>
          <p>Pending Leave Requests</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon teal">
          <i class="fas fa-file-upload"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $total_study_materials; ?></h3>
          <p>Study Materials Uploaded</p>
        </div>
      </div>
    </div>

    <!-- Visualizations Row 1 -->
    <div class="charts-row">
      <div class="chart-card">
        <h3>Attendance Marking Status (Last 30 Days)</h3>
        <canvas id="attendanceMarkingChart"></canvas>
      </div>

      <div class="chart-card">
        <h3>Student Attendance Trend (Last 30 Days)</h3>
        <canvas id="studentAttendanceTrendChart"></canvas>
      </div>
    </div>

    <!-- Visualizations Row 2 -->
    <div class="charts-row">
      <div class="chart-card">
        <h3>Marks Entry Status (Last 30 Days)</h3>
        <canvas id="marksEntryStatusChart"></canvas>
      </div>

      <div class="chart-card">
        <h3>Study Material Upload Trend (Last 30 Days)</h3>
        <canvas id="materialUploadTrendChart"></canvas>
      </div>

      <div class="chart-card">
        <h3>Subject-wise Student Performance</h3>
        <canvas id="subjectPerformanceChart"></canvas>
      </div>
    </div>

    <!-- Top Students Section -->
    <div class="charts-row">
      <div class="chart-card full-width">
        <h3>Top 10 Performing Students</h3>
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
    </div>

    <!-- Tables Section -->
    <div class="tables-row">
      <div class="table-card">
        <h3>My Pending Leave Requests</h3>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Reason</th>
                <th>Date Range</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($pending_leaves_table_data) > 0): ?>
                <?php foreach($pending_leaves_table_data as $leave): ?>
                  <tr>
                    <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 30)) . '...'; ?></td>
                    <td><?php echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d', strtotime($leave['end_date'])); ?></td>
                    <td>
                      <span class="status-badge <?php echo strtolower($leave['status']); ?>">
                        <?php echo ucfirst($leave['status']); ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align:center;color:#6b7280;padding:20px;">No pending leave requests.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

<div class="table-card">
  <h3>Study Materials Pending Approval</h3>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Student</th>
          <th>Subject</th>
          <th>File</th>
          <th>Uploaded At</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($materials_pending_data) > 0): ?>
          <?php foreach($materials_pending_data as $material): ?>
            <tr>
              <td><?php echo htmlspecialchars($material['student_name']); ?></td>
              <td><?php echo htmlspecialchars($material['sub_name']); ?></td>
              <td><?php echo htmlspecialchars($material['file_name']); ?></td>
              <td><?php echo date('M d, Y H:i', strtotime($material['upload_date'])); ?></td>

              <td>
                <span class="status-badge 
                  <?php echo strtolower($material['approval_status']); ?>">
                  <?php echo ucfirst($material['approval_status']); ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" style="text-align:center;color:#6b7280;padding:20px;">
              No materials pending approval.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
      </div>
    </div>

    <div class="tables-row">
      <div class="table-card full-width">
        <h3>Latest Marks Entry</h3>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Subject</th>
                <th>Obtained Marks</th>
                <th>Full Marks</th>
                <th>Percentage</th>
                <th>Grade</th>
                <th>Exam Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($latest_marks_data) > 0): ?>
                <?php foreach($latest_marks_data as $mark): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($mark['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($mark['sub_name']); ?></td>
                    <td><?php echo $mark['obtained_marks']; ?></td>
                    <td><?php echo $mark['full_marks']; ?></td>
                    <td><?php echo round($mark['percentage'], 2); ?>%</td>
                    <td>
                      <span class="grade-badge grade-<?php echo strtolower($mark['grade']); ?>">
                        <?php echo $mark['grade']; ?>
                      </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($mark['exam_date'])); ?></td>
                    <td class="action-buttons">
                      <button class="btn-edit" onclick="editMarks(<?php echo $mark['marks_id']; ?>)">
                        <i class="fas fa-edit"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="9" style="text-align:center;color:#6b7280;padding:20px;">No marks entries yet.</td>
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
    const facultyDashboardData = {
      attendanceMarking: <?php echo json_encode($attendance_marking_data); ?>,
      studentAttendanceTrend: <?php echo json_encode($student_attendance_report_data); ?>,
      marksEntryStatus: {
        entered: <?php echo $marks_entered; ?>,
        pending: <?php echo $marks_pending; ?>
      },
      materialUploadTrend: <?php echo json_encode($material_upload_trend_data); ?>,
      subjectPerformance: <?php echo json_encode($subject_performance_data); ?>
    };
  </script>
  <script src="../js/faculty_dashboard.js"></script>
</body>
</html>
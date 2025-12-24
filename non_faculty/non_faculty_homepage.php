<?php
session_start();
include("connect.php");

if (!isset($_SESSION['uid'])) {
    header("Location: login.php");
    exit();
}

$coordinator_user_id = $_SESSION['uid'];

// Debug: Check if coordinator_id is set
error_log("Coordinator User ID: " . $coordinator_user_id);

// UPDATED: Get Coordinator's information from COORDINATORS table
$coordinator_query = "
    SELECT 
        c.coordinator_id,
        c.coordinator_for,
        c.full_name,
        c.email,
        c.contact_number,
        c.status,
        co.course_name,
        co.course_id
    FROM coordinators c
    JOIN course co ON c.coordinator_for = co.course_id
    WHERE c.user_id = '$coordinator_user_id'
    LIMIT 1
";
$coordinator_result = mysqli_query($conn, $coordinator_query);

if (!$coordinator_result) {
    die("Query Error: " . mysqli_error($conn));
}

$coordinator_data = mysqli_fetch_assoc($coordinator_result);

if (!$coordinator_data) {
    die("Coordinator not found for user_id: $coordinator_user_id. Please ensure this user is assigned as a coordinator.");
}

// Debug output
error_log("Coordinator Data: " . print_r($coordinator_data, true));

$coordinator_id = $coordinator_data['coordinator_id'];
$course_id = $coordinator_data['coordinator_for'];
$course_name = $coordinator_data['course_name'];
$coordinator_name = $coordinator_data['full_name'];
$coordinator_email = $coordinator_data['email'];
$coordinator_status = $coordinator_data['status'];

// Since coordinators manage entire course (all semesters), we'll aggregate data across all semesters
// Or you can modify to track specific semester if needed

// 1. Subjects Assigned (for their course - ALL semesters)
$subjects_query = "
    SELECT COUNT(*) as total
    FROM subject
    WHERE course_id = '$course_id'
";
$subjects_result = mysqli_query($conn, $subjects_query);
if (!$subjects_result) {
    error_log("Subjects Query Error: " . mysqli_error($conn));
    $total_subjects = 0;
} else {
    $total_subjects = mysqli_fetch_assoc($subjects_result)['total'];
}

// 2. Total Students (in their course - ALL semesters and active)
$students_query = "
    SELECT COUNT(*) as total
    FROM users
    WHERE role_id = 2
    AND course_id = '$course_id'
    AND status = 'Active'
";
$students_result = mysqli_query($conn, $students_query);
if (!$students_result) {
    error_log("Students Query Error: " . mysqli_error($conn));
    $total_students = 0;
} else {
    $total_students = mysqli_fetch_assoc($students_result)['total'];
}

// 3. Average Attendance (Last 7 Days) for the entire course
$seven_days_ago = date('Y-m-d', strtotime('-7 days'));
$attendance_query = "
    SELECT 
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
        COUNT(*) as total_count
    FROM attendance a
    JOIN subject s ON a.sub_id = s.sub_id
    WHERE s.course_id = '$course_id'
    AND a.attendance_date >= '$seven_days_ago'
";
$attendance_result = mysqli_query($conn, $attendance_query);
if (!$attendance_result) {
    error_log("Attendance Query Error: " . mysqli_error($conn));
    $attendance_percentage = 0;
} else {
    $attendance_data = mysqli_fetch_assoc($attendance_result);
    $attendance_percentage = $attendance_data['total_count'] > 0 ? 
        round(($attendance_data['present_count'] / $attendance_data['total_count']) * 100, 1) : 0;
}

// 4. Pending Student Leave Requests for the entire course
$pending_leaves_query = "
    SELECT COUNT(*) as total
    FROM student_leave_requests slr
    JOIN users u ON slr.student_id = u.user_id
    WHERE u.course_id = '$course_id'
    AND slr.status = 'Pending'
";
$pending_leaves_result = mysqli_query($conn, $pending_leaves_query);
if (!$pending_leaves_result) {
    error_log("Pending Leaves Query Error: " . mysqli_error($conn));
    $pending_leaves = 0;
} else {
    $pending_leaves = mysqli_fetch_assoc($pending_leaves_result)['total'];
}

// 5. Total Study Materials for the entire course
$study_materials_query = "
    SELECT COUNT(*) as total
    FROM study_material sm
    JOIN subject s ON sm.subject_id = s.sub_id
    WHERE s.course_id = '$course_id'
";
$study_materials_result = mysqli_query($conn, $study_materials_query);
if (!$study_materials_result) {
    error_log("Study Materials Query Error: " . mysqli_error($conn));
    $total_study_materials = 0;
} else {
    $total_study_materials = mysqli_fetch_assoc($study_materials_result)['total'];
}

// CHARTS DATA
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));

// 1. Attendance Trend (Last 30 Days) for the entire course
$attendance_trend_query = "
    SELECT 
        DATE(a.attendance_date) as date,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
        COUNT(*) as total_count
    FROM attendance a
    JOIN subject s ON a.sub_id = s.sub_id
    WHERE s.course_id = '$course_id'
    AND a.attendance_date >= '$thirty_days_ago'
    GROUP BY DATE(a.attendance_date)
    ORDER BY date ASC
";
$attendance_trend_result = mysqli_query($conn, $attendance_trend_query);
$attendance_trend_data = [];
if ($attendance_trend_result) {
    while($row = mysqli_fetch_assoc($attendance_trend_result)) {
        $row['percentage'] = $row['total_count'] > 0 ? 
            round(($row['present_count'] / $row['total_count']) * 100, 1) : 0;
        $attendance_trend_data[] = $row;
    }
}

// 2. Subject Load Distribution (by faculty)
$subject_load_query = "
    SELECT 
        COALESCE(u.full_name, 'Unassigned') as faculty_name,
        COUNT(s.sub_id) as subject_count
    FROM subject s
    LEFT JOIN users u ON s.role_id = u.user_id
    WHERE s.course_id = '$course_id'
    GROUP BY u.user_id, u.full_name
    ORDER BY subject_count DESC
";
$subject_load_result = mysqli_query($conn, $subject_load_query);
$subject_load_data = [];
if ($subject_load_result) {
    while($row = mysqli_fetch_assoc($subject_load_result)) {
        $subject_load_data[] = $row;
    }
}

// 3. Marks Performance (Subject-wise) for the entire course
$marks_performance_query = "
    SELECT 
        s.sub_name,
        AVG(m.percentage) as avg_percentage
    FROM marks m
    JOIN subject s ON m.sub_id = s.sub_id
    WHERE s.course_id = '$course_id'
    GROUP BY m.sub_id, s.sub_name
    ORDER BY avg_percentage DESC
";
$marks_performance_result = mysqli_query($conn, $marks_performance_query);
$marks_performance_data = [];
if ($marks_performance_result) {
    while($row = mysqli_fetch_assoc($marks_performance_result)) {
        $marks_performance_data[] = $row;
    }
}

// 4. Study Material Upload Trend (Last 30 Days)
$material_trend_query = "
    SELECT 
        DATE(sm.upload_date) as date,
        COUNT(*) as count
    FROM study_material sm
    JOIN subject s ON sm.subject_id = s.sub_id
    WHERE s.course_id = '$course_id'
    AND sm.upload_date >= '$thirty_days_ago'
    GROUP BY DATE(sm.upload_date)
    ORDER BY date ASC
";
$material_trend_result = mysqli_query($conn, $material_trend_query);
$material_trend_data = [];
if ($material_trend_result) {
    while($row = mysqli_fetch_assoc($material_trend_result)) {
        $material_trend_data[] = $row;
    }
}

// 5. Student Leave Pattern (Last 30 Days)
$leave_pattern_query = "
    SELECT 
        DATE(slr.requested_at) as date,
        COUNT(*) as count
    FROM student_leave_requests slr
    JOIN users u ON slr.student_id = u.user_id
    WHERE u.course_id = '$course_id'
    AND slr.requested_at >= '$thirty_days_ago'
    GROUP BY DATE(slr.requested_at)
    ORDER BY date ASC
";
$leave_pattern_result = mysqli_query($conn, $leave_pattern_query);
$leave_pattern_data = [];
if ($leave_pattern_result) {
    while($row = mysqli_fetch_assoc($leave_pattern_result)) {
        $leave_pattern_data[] = $row;
    }
}

// TABLES DATA

// 1. Pending Student Leave Requests
$pending_leaves_table_query = "
    SELECT 
        slr.leave_id,
        u.full_name as student_name,
        slr.leave_type,
        slr.start_date,
        slr.end_date,
        slr.status,
        slr.requested_at,
        slr.reason,
        sem.sem_name
    FROM student_leave_requests slr
    JOIN users u ON slr.student_id = u.user_id
    LEFT JOIN semester sem ON u.sem_id = sem.sem_id
    WHERE u.course_id = '$course_id'
      AND slr.status = 'Pending'
    ORDER BY slr.requested_at DESC
    LIMIT 15
";

$pending_leaves_table_data = [];
$pending_leaves_table_result = mysqli_query($conn, $pending_leaves_table_query);
if ($pending_leaves_table_result) {
    while($row = mysqli_fetch_assoc($pending_leaves_table_result)) {
        $pending_leaves_table_data[] = $row;
    }
}

// 2. Recent Study Materials
$recent_materials_query = "
    SELECT 
        sm.material_id,
        s.sub_name,
        u.full_name as uploaded_by,
        u.role_id,
        sm.file_name,
        sm.file_path,
        sm.upload_date,
        sm.approval_status,
        sem.sem_name
    FROM study_material sm
    JOIN subject s ON sm.subject_id = s.sub_id
    JOIN users u ON sm.user_id = u.user_id
    LEFT JOIN semester sem ON s.sem_id = sem.sem_id
    WHERE s.course_id = '$course_id'
    ORDER BY sm.upload_date DESC
    LIMIT 15
";
$recent_materials_result = mysqli_query($conn, $recent_materials_query);
$recent_materials_data = [];
if ($recent_materials_result) {
    while($row = mysqli_fetch_assoc($recent_materials_result)) {
        $recent_materials_data[] = $row;
    }
}

// 3. Coordinator's Own Leave Requests
$coordinator_leaves_query = "
    SELECT 
        leave_id,
        leave_type,
        start_date,
        end_date,
        reason,
        status,
        requested_at
    FROM staff_leave_requests
    WHERE staff_id = '$coordinator_user_id'
    ORDER BY requested_at DESC
    LIMIT 10
";
$coordinator_leaves_result = mysqli_query($conn, $coordinator_leaves_query);
$coordinator_leaves_data = [];
if ($coordinator_leaves_result) {
    while($row = mysqli_fetch_assoc($coordinator_leaves_result)) {
        $coordinator_leaves_data[] = $row;
    }
}

// Get semester breakdown for additional info
$semester_breakdown_query = "
    SELECT 
        sem.sem_name,
        COUNT(DISTINCT u.user_id) as student_count,
        COUNT(DISTINCT s.sub_id) as subject_count
    FROM semester sem
    LEFT JOIN users u ON u.sem_id = sem.sem_id AND u.course_id = '$course_id' AND u.role_id = 2 AND u.status = 'Active'
    LEFT JOIN subject s ON s.sem_id = sem.sem_id AND s.course_id = '$course_id'
    GROUP BY sem.sem_id, sem.sem_name
    ORDER BY sem.sem_id ASC
";
$semester_breakdown_result = mysqli_query($conn, $semester_breakdown_query);
$semester_breakdown_data = [];
if ($semester_breakdown_result) {
    while($row = mysqli_fetch_assoc($semester_breakdown_result)) {
        if ($row['student_count'] > 0 || $row['subject_count'] > 0) {
            $semester_breakdown_data[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Coordinator Dashboard - <?php echo htmlspecialchars($course_name); ?></title>
  <link rel="stylesheet" href="../css/all.min.css">
  <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
  <link rel="stylesheet" href="../css/admin_menu.css">
  <link rel="stylesheet" href="../css/faculty_dashboard.css">
</head>

<body>
  <?php include("header.php"); ?>
  <?php include("menu.php"); ?>
  
  <div class="dashboard-container">
    <!-- Coordinator Info Banner -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; margin-bottom: 20px; border-radius: 12px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h2 style="margin: 0 0 10px 0; font-size: 24px;">
        <i class="fas fa-user-tie"></i> Course Coordinator Dashboard
      </h2>
      <div style="display: flex; gap: 30px; flex-wrap: wrap; font-size: 14px;">
        <div>
          <strong>Coordinator:</strong> <?php echo htmlspecialchars($coordinator_name); ?>
        </div>
        <div>
          <strong>Course:</strong> <?php echo htmlspecialchars($course_name); ?>
        </div>
        <div>
          <strong>Email:</strong> <?php echo htmlspecialchars($coordinator_email); ?>
        </div>
        <div>
          <strong>Status:</strong> 
          <span style="background: <?php echo $coordinator_status === 'active' ? '#10b981' : '#ef4444'; ?>; padding: 2px 10px; border-radius: 12px; font-weight: 600;">
            <?php echo ucfirst($coordinator_status); ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Debug Info (Remove in production) -->
    <?php if (false): // Set to true for debugging ?>
    <div style="background: #fff3cd; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
      <strong>Debug Info:</strong><br>
      Coordinator: <?php echo $coordinator_name; ?><br>
      Course ID: <?php echo $course_id; ?><br>
      Course Name: <?php echo $course_name; ?><br>
      Total Subjects: <?php echo $total_subjects; ?><br>
      Total Students: <?php echo $total_students; ?>
    </div>
    <?php endif; ?>

    <!-- Section 1: Stats Cards -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon blue">
          <i class="fas fa-book"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $total_subjects; ?></h3>
          <p>Subjects Assigned</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $total_students; ?></h3>
          <p>Total Students</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon <?php echo $attendance_percentage >= 75 ? 'teal' : ($attendance_percentage >= 60 ? 'orange' : 'red'); ?>">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $attendance_percentage; ?>%</h3>
          <p>Attendance (7 Days)</p>
        </div>
      </div>

      <div class="stat-card <?php echo $pending_leaves > 5 ? 'alert' : ''; ?>">
        <div class="stat-icon <?php echo $pending_leaves > 5 ? 'red' : 'purple'; ?>">
          <i class="fas fa-calendar-times"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $pending_leaves; ?></h3>
          <p>Pending Leave Requests</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon indigo">
          <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-content">
          <h3><?php echo $total_study_materials; ?></h3>
          <p>Total Study Materials</p>
        </div>
      </div>
    </div>

    <!-- Semester Breakdown Section -->
    <?php if (count($semester_breakdown_data) > 0): ?>
    <div style="background: white; padding: 20px; margin-bottom: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
      <h3 style="margin: 0 0 15px 0; color: #1f2937; font-size: 18px;">
        <i class="fas fa-layer-group"></i> Semester Breakdown
      </h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <?php foreach($semester_breakdown_data as $sem): ?>
        <div style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); padding: 15px; border-radius: 8px; border-left: 4px solid #3b82f6;">
          <div style="font-weight: 600; color: #1f2937; margin-bottom: 8px;">
            <?php echo htmlspecialchars($sem['sem_name']); ?> Semester
          </div>
          <div style="font-size: 13px; color: #6b7280;">
            <i class="fas fa-users"></i> <?php echo $sem['student_count']; ?> Students
          </div>
          <div style="font-size: 13px; color: #6b7280;">
            <i class="fas fa-book"></i> <?php echo $sem['subject_count']; ?> Subjects
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Section 2: Visual Charts -->
    <div class="charts-row">
      <div class="chart-card large">
        <h3><i class="fas fa-chart-line"></i> Attendance Trend (Last 30 Days)</h3>
        <canvas id="attendanceTrendChart"></canvas>
      </div>

      <div class="chart-card">
        <h3><i class="fas fa-chart-pie"></i> Subject Load Distribution</h3>
        <canvas id="subjectLoadChart"></canvas>
      </div>
    </div>

    <div class="charts-row">
      <div class="chart-card">
        <h3><i class="fas fa-chart-bar"></i> Marks Performance (Subject-wise)</h3>
        <canvas id="marksPerformanceChart"></canvas>
      </div>

      <div class="chart-card">
        <h3><i class="fas fa-upload"></i> Study Material Upload Trend</h3>
        <canvas id="materialUploadChart"></canvas>
      </div>

      <div class="chart-card">
        <h3><i class="fas fa-calendar-day"></i> Student Leave Pattern (30 Days)</h3>
        <canvas id="leavePatternChart"></canvas>
      </div>
    </div>

    <!-- Section 3: Tables -->
    <div class="tables-row">
      <div class="table-card large">
        <h3><i class="fas fa-clock"></i> Pending Leave Requests (Students)</h3>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Student Name</th>
                <th>Semester</th>
                <th>Leave Type</th>
                <th>Date Range</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Requested At</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($pending_leaves_table_data) > 0): ?>
                <?php foreach($pending_leaves_table_data as $leave): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($leave['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($leave['sem_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                    <td><?php echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d, Y', strtotime($leave['end_date'])); ?></td>
                    <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 30)) . (strlen($leave['reason']) > 30 ? '...' : ''); ?></td>
                    <td>
                      <span class="status-badge <?php echo strtolower($leave['status']); ?>">
                        <?php echo ucfirst($leave['status']); ?>
                      </span>
                    </td>
                    <td><?php echo date('M d, Y H:i', strtotime($leave['requested_at'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" style="text-align:center;color:#6b7280;padding:20px;">No pending leave requests.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="table-card">
        <h3><i class="fas fa-folder-open"></i> Recent Study Materials</h3>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Subject</th>
                <th>Semester</th>
                <th>Uploaded By</th>
                <th>File Name</th>
                <th>Upload Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($recent_materials_data) > 0): ?>
                <?php foreach($recent_materials_data as $material): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($material['sub_name']); ?></td>
                    <td><?php echo htmlspecialchars($material['sem_name'] ?? 'N/A'); ?></td>
                    <td>
                      <?php echo htmlspecialchars($material['uploaded_by']); ?>
                      <span class="role-badge">
                        <?php echo $material['role_id'] == 2 ? 'Student' : 'Faculty'; ?>
                      </span>
                    </td>
                    <td><?php echo htmlspecialchars($material['file_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($material['upload_date'])); ?></td>
                    <td>
                      <span class="status-badge <?php echo strtolower($material['approval_status']); ?>">
                        <?php echo ucfirst($material['approval_status']); ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align:center;color:#6b7280;padding:20px;">No study materials yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="tables-row">
      <div class="table-card">
        <h3><i class="fas fa-user-clock"></i> My Leave Requests</h3>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Leave Type</th>
                <th>Date Range</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Requested At</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($coordinator_leaves_data) > 0): ?>
                <?php foreach($coordinator_leaves_data as $leave): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                    <td><?php echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d, Y', strtotime($leave['end_date'])); ?></td>
                    <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 40)) . (strlen($leave['reason']) > 40 ? '...' : ''); ?></td>
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
                  <td colspan="5" style="text-align:center;color:#6b7280;padding:20px;">No leave requests yet.</td>
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
    const coordinatorDashboardData = {
      attendanceTrend: <?php echo json_encode($attendance_trend_data); ?>,
      subjectLoad: <?php echo json_encode($subject_load_data); ?>,
      marksPerformance: <?php echo json_encode($marks_performance_data); ?>,
      materialUpload: <?php echo json_encode($material_trend_data); ?>,
      leavePattern: <?php echo json_encode($leave_pattern_data); ?>
    };
  </script>
  <script src="../js/coordinator_dashboard.js"></script>
</body>
</html>
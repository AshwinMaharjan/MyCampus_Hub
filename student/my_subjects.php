<?php
session_start();
include("connect.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$studentId = $_SESSION['uid'];

$getStudentInfoQuery = "
    SELECT 
        u.sem_id, 
        u.course_id, 
        u.full_name,
        u.id_number,
        c.course_name,
        s.sem_name
    FROM users u
    LEFT JOIN course c ON u.course_id = c.course_id
    LEFT JOIN semester s ON u.sem_id = s.sem_id
    WHERE u.user_id = ?
";

$stmt = $conn->prepare($getStudentInfoQuery);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$studentInfo = $result->fetch_assoc();
$stmt->close();

if (!$studentInfo) {
    echo "Student information not found.";
    exit();
}

$studentSemId = $studentInfo['sem_id'];
$studentCourseId = $studentInfo['course_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subjects</title>
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../css/admin_menu.css">
    <link rel="stylesheet" href="../css/student/my_subjects.css">
    <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
    <style>
        .subject-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .action-btn {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            font-size: 0.95em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .attendance-btn {
            background-color: #4CAF50;
            color: white;
        }

        .attendance-btn:hover {
            background-color: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
        }

        .marks-btn {
            background-color: #2196F3;
            color: white;
        }

        .marks-btn:hover {
            background-color: #0b7dda;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
        }

        .action-btn i {
            font-size: 1em;
        }
    </style>
</head>
<body>

<?php include("header.php"); ?>
  <div class="page-wrapper">
<?php include("menu.php"); ?>

<div class="main-content">
    <div class="student-info">
        <h3><?php echo htmlspecialchars($studentInfo['full_name']); ?></h3>
        <div class="student-details">
            <div>ID: <?php echo htmlspecialchars($studentInfo['id_number']); ?></div>
            <div>Course: <?php echo htmlspecialchars($studentInfo['course_name'] ?? 'Not Assigned'); ?></div>
            <div>Semester: <?php echo htmlspecialchars($studentInfo['sem_name'] ?? 'Not Assigned'); ?></div>
        </div>
    </div>

    <div class="subjects-container">
        <?php
$subjectsQuery = "
    SELECT 
        s.sub_id,
        s.sub_name,
        s.role_id AS teacher_id,
        u.full_name AS teacher_name,
        u.email AS teacher_email,
        u.contact_number AS teacher_contact
    FROM subject s
    LEFT JOIN users u ON s.role_id = u.user_id AND u.role_id = 3
    WHERE s.sem_id = ? AND s.course_id = ?
    ORDER BY s.sub_name
";
        $stmt = $conn->prepare($subjectsQuery);
        $stmt->bind_param("ii", $studentSemId, $studentCourseId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($subject = $result->fetch_assoc()) {
                ?>
                <div class="subject-card">
                    <div class="subject-header">
                        <div class="subject-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h2 class="subject-name"><?php echo htmlspecialchars($subject['sub_name']); ?></h2>
                    </div>

                    <div class="subject-details">
                        <div class="subject-detail">
                            <span class="detail-label">Subject Code:</span>
                            <span class="detail-value">SUB-<?php echo str_pad($subject['sub_id'], 3, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <div class="subject-detail">
                            <span class="detail-label">Course:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($studentInfo['course_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="subject-detail">
                            <span class="detail-label">Semester:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($studentInfo['sem_name'] ?? 'N/A'); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($subject['teacher_name'])): ?>
                    <div class="teacher-info">
                        <div class="teacher-name">
                            <i class="fas fa-chalkboard-teacher"></i> 
                            <?php echo htmlspecialchars($subject['teacher_name']); ?>
                        </div>
                        <?php if (!empty($subject['teacher_email'])): ?>
                        <div style="margin-top: 5px; font-size: 0.9em;">
                            <i class="fas fa-envelope"></i> 
                            <?php echo htmlspecialchars($subject['teacher_email']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($subject['teacher_contact'])): ?>
                        <div style="margin-top: 3px; font-size: 0.9em;">
                            <i class="fas fa-phone"></i> 
                            <?php echo htmlspecialchars($subject['teacher_contact']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="teacher-info">
                        <span style="color: #888; font-style: italic;">
                            <i class="fas fa-user-times"></i> No teacher assigned
                        </span>
                    </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="subject-actions">
                        <a href="student_view_attendance.php?subject_id=<?php echo $subject['sub_id']; ?>" class="action-btn attendance-btn">
                            <i class="fas fa-calendar-check"></i>
                            <span>View Attendance</span>
                        </a>
                        <a href="my_marks.php?subject_id=<?php echo $subject['sub_id']; ?>" class="action-btn marks-btn">
                            <i class="fas fa-chart-line"></i>
                            <span>View Marks</span>
                        </a>
                    </div>
                </div>
                <?php
            }
        } else {
            ?>
            <div class="no-subjects">
                <i class="fas fa-book-open"></i>
                <h3>No Subjects Found</h3>
                <p>No subjects are currently assigned to your course and semester.</p>
                <p>Please contact the administration if you believe this is an error.</p>
            </div>
            <?php
        }
        $stmt->close();
        ?>
    </div>
</div>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

</body>
</html>
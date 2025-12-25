<?php
session_start();
include("connect.php");
include("auth_check.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['uid'];
$errorMessage = null;

// Get user's course and semester information with better error handling
$userQuery = "
SELECT 
    u.user_id,
    u.full_name,
    u.email,
    u.role_id,
    u.course_id,
    u.sem_id,
    u.coordinator_for,
    c.course_name,
    sem.sem_name
FROM users u
LEFT JOIN course c ON u.coordinator_for = c.course_id
LEFT JOIN semester sem ON u.sem_id = sem.sem_id
WHERE u.user_id = ?
";

$userStmt = $conn->prepare($userQuery);
if (!$userStmt) {
    die("Prepare failed: " . $conn->error);
}

$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userData = $userResult->fetch_assoc();
$userStmt->close();

// Debug: Check what data we got
$userCourseId = $userData['coordinator_for'] ?? null;
if (!$userData) {
    $errorMessage = "User account not found. User ID: " . $userId;
} elseif (is_null($userCourseId) || empty($userCourseId)) {
    $errorMessage = "No course assigned to your account. Please contact administration to assign you to a course.";
}

$userSemId = $userData['sem_id'] ?? null;

// Get filter parameters
$filterSemester = isset($_GET['semester']) ? intval($_GET['semester']) : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all semesters for the filter dropdown
$semesterQuery = "SELECT DISTINCT sem_id, sem_name FROM semester ORDER BY sem_id";
$semesterResult = $conn->query($semesterQuery);
$semesters = [];
if ($semesterResult) {
    while ($row = $semesterResult->fetch_assoc()) {
        $semesters[] = $row;
    }
}

$subjects = [];

// Only fetch subjects if we have a valid course ID
if ($userCourseId) {
    // Build the main query with filters
    $query = "
        SELECT 
            s.sub_id,
            s.sub_name,
            s.course_id,
            s.sem_id,
            c.course_name,
            sem.sem_name,
            CONCAT(u.full_name) AS staff_name,
            u.profile_photo AS staff_photo
        FROM subject s
        LEFT JOIN course c ON s.course_id = c.course_id
        LEFT JOIN semester sem ON s.sem_id = sem.sem_id
        LEFT JOIN users u ON s.role_id = u.user_id AND u.role_id = 3
        WHERE s.course_id = ?
    ";

    $params = [$userCourseId];
    $types = "i";

    // Add semester filter
    if ($filterSemester !== '') {
        $query .= " AND s.sem_id = ?";
        $params[] = $filterSemester;
        $types .= "i";
    }

    // Add search filter
    if ($searchQuery !== '') {
        $query .= " AND s.sub_name LIKE ?";
        $params[] = "%$searchQuery%";
        $types .= "s";
    }

    $query .= " ORDER BY s.sem_id, s.sub_name";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    } else {
        $errorMessage = "Error preparing subject query: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subjects</title>
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../css/admin_menu.css">
    <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }

        .main-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: linear-gradient(135deg, #263576 0%, #4a5fa8 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(38, 53, 118, 0.2);
        }

        .page-header h1 {
            font-size: 2em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h1 i {
            font-size: 1.2em;
        }

        .course-info {
            display: flex;
            gap: 30px;
            margin-top: 15px;
            font-size: 1.05em;
        }

        .course-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .course-info-item i {
            opacity: 0.9;
        }

        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .filters-container {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr auto;
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 600;
            color: #263576;
            font-size: 0.95em;
        }

        .filter-group select,
        .filter-group input[type="text"] {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .filter-group select:focus,
        .filter-group input[type="text"]:focus {
            outline: none;
            border-color: #263576;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(38, 53, 118, 0.1);
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .search-box input {
            padding-left: 45px;
        }

        .btn-filter {
            padding: 12px 30px;
            background: linear-gradient(135deg, #263576 0%, #4a5fa8 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(38, 53, 118, 0.3);
        }

        .btn-reset {
            padding: 12px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-reset:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .subject-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            border-color: #263576;
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
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 26px;
            flex-shrink: 0;
        }

        .subject-title {
            flex: 1;
        }

        .subject-name {
            font-size: 1.3em;
            color: #263576;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .subject-code {
            font-size: 0.9em;
            color: #666;
            font-weight: 500;
        }

        .subject-details {
            display: grid;
            gap: 12px;
            margin-bottom: 20px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-label i {
            color: #263576;
            width: 18px;
        }

        .detail-value {
            color: #333;
            font-weight: 500;
            text-align: right;
        }

        .semester-badge {
            display: inline-block;
            padding: 4px 12px;
            background: linear-gradient(135deg, #263576 0%, #4a5fa8 100%);
            color: white;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .staff-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 15px;
        }

        .staff-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .staff-photo-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #263576 0%, #4a5fa8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3em;
            font-weight: 700;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .staff-details {
            flex: 1;
        }

        .staff-label {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 3px;
        }

        .subject-actions {
            display: flex;
            gap: 10px;
            flex-wrap: nowrap;
            justify-content: flex-start;
            margin-top: 10px;
        }

        .subject-actions .action-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 6px;
            border-radius: 6px;
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .subject-actions .action-btn i {
            font-size: 16px;
        }

        .subject-actions .marks-btn {
            background-color: #2196F3;
        }

        .subject-actions .attendance-btn {
            background-color: #4CAF50;
        }

        .subject-actions .students-btn {
            background-color: #FF9800;
        }

        .subject-actions .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .staff-name {
            font-weight: 600;
            color: #263576;
            font-size: 1em;
        }

        .no-subjects {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .no-subjects i {
            font-size: 5em;
            color: #ddd;
            margin-bottom: 20px;
        }

        .no-subjects h3 {
            color: #263576;
            font-size: 1.5em;
            margin-bottom: 10px;
        }

        .no-subjects p {
            color: #666;
            line-height: 1.6;
            max-width: 500px;
            margin: 0 auto;
        }

        .error-message {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .error-message i {
            font-size: 2em;
        }

        .error-content {
            flex: 1;
        }

        .error-content strong {
            display: block;
            margin-bottom: 5px;
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 0.9em;
        }

        .results-count {
            margin-bottom: 20px;
            padding: 15px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .results-count span {
            font-weight: 600;
            color: #263576;
        }

        @media (max-width: 1024px) {
            .filters-container {
                grid-template-columns: 1fr 1fr;
            }

            .search-box {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.5em;
            }

            .course-info {
                flex-direction: column;
                gap: 10px;
            }

            .filters-container {
                grid-template-columns: 1fr;
            }

            .subjects-grid {
                grid-template-columns: 1fr;
            }

            .btn-filter,
            .btn-reset {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
    <?php if ($errorMessage): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="error-content">
                <strong>Error:</strong> <?= htmlspecialchars($errorMessage) ?>
                
                <!-- Debug Information -->
                <?php if ($userData): ?>
                <div class="debug-info">
                    <strong>Debug Information:</strong><br>
                    User ID: <?= htmlspecialchars($userData['user_id'] ?? 'N/A') ?><br>
                    Name: <?= htmlspecialchars($userData['full_name'] ?? 'N/A') ?><br>
                    Email: <?= htmlspecialchars($userData['email'] ?? 'N/A') ?><br>
                    Role ID: <?= htmlspecialchars($userData['role_id'] ?? 'N/A') ?><br>
                    Course ID: <?= htmlspecialchars($userData['course_id'] ?? 'NULL') ?><br>
                    Semester ID: <?= htmlspecialchars($userData['sem_id'] ?? 'NULL') ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($userCourseId): ?>
        <div class="page-header">
            <h1>
                <i class="fas fa-book-open"></i>
                My Subjects
            </h1>
            <div class="course-info">
                <div class="course-info-item">
                    <i class="fas fa-graduation-cap"></i>
                    <strong>Course:</strong> <?= htmlspecialchars($userData['course_name'] ?? 'N/A') ?>
                </div>
                <?php if (!empty($userData['sem_name'])): ?>
                    <div class="course-info-item">
                        <i class="fas fa-calendar-alt"></i>
                        <strong>Current Semester:</strong> <?= htmlspecialchars($userData['sem_name']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-container">
                    <div class="filter-group">
                        <label for="semester">
                            <i class="fas fa-filter"></i> Filter by Semester
                        </label>
                        <select name="semester" id="semester">
                            <option value="">All Semesters</option>
                            <?php foreach ($semesters as $sem): ?>
                                <option value="<?= $sem['sem_id'] ?>" 
                                    <?= $filterSemester == $sem['sem_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sem['sem_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group search-box">
                        <label for="search">Search Subjects</label>
                        <input type="text" 
                               name="search" 
                               id="search" 
                               placeholder="Enter subject name..."
                               value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>

                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i>
                            Apply Filters
                        </button>
                    </div>

                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <a href="view_subjects.php" class="btn-reset">
                            <i class="fas fa-redo"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($subjects)): ?>
            <div class="results-count">
                <span>
                    <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
                    Showing <?= count($subjects) ?> subject<?= count($subjects) != 1 ? 's' : '' ?>
                </span>
            </div>

            <div class="subjects-grid">
                <?php foreach ($subjects as $subject): ?>
                    <div class="subject-card">
                        <div class="subject-header">
                            <div class="subject-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="subject-title">
                                <div class="subject-name">
                                    <?= htmlspecialchars($subject['sub_name']) ?>
                                </div>
                                <div class="subject-code">
                                    SUB-<?= str_pad($subject['sub_id'], 3, '0', STR_PAD_LEFT) ?>
                                </div>
                            </div>
                        </div>

                        <div class="subject-details">
                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="fas fa-graduation-cap"></i>
                                    Course
                                </span>
                                <span class="detail-value">
                                    <?= htmlspecialchars($subject['course_name']) ?>
                                </span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Semester
                                </span>
                                <span class="detail-value">
                                    <span class="semester-badge">
                                        <?= htmlspecialchars($subject['sem_name']) ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <?php if ($subject['staff_name']): ?>
                            <div class="staff-info">
                                <?php if ($subject['staff_photo']): ?>
                                    <img src="../uploads/<?= htmlspecialchars($subject['staff_photo']) ?>" 
                                         alt="Staff Photo" 
                                         class="staff-photo"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="staff-photo-placeholder" style="display:none;">
                                        <?= strtoupper(substr($subject['staff_name'], 0, 1)) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="staff-photo-placeholder">
                                        <?= strtoupper(substr($subject['staff_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="staff-details">
                                    <div class="staff-label">Instructor</div>
                                    <div class="staff-name">
                                        <?= htmlspecialchars($subject['staff_name']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="subject-actions">
                            <a href="view_marks.php?sub_id=<?= $subject['sub_id'] ?>" class="action-btn marks-btn">
                                <i class="fas fa-chart-line"></i>
                                <span>Marks</span>
                            </a>
                            <a href="view_attendance.php?sub_id=<?= $subject['sub_id'] ?>" class="action-btn attendance-btn">
                                <i class="fas fa-calendar-check"></i>
                                <span>Attendance</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="subjects-grid">
                <div class="no-subjects">
                    <i class="fas fa-search"></i>
                    <h3>No Subjects Found</h3>
                    <p>
                        <?php if ($searchQuery || $filterSemester): ?>
                            No subjects match your search criteria. Try adjusting your filters or search term.
                        <?php else: ?>
                            There are no subjects available for your course at this time.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

</body>
</html>
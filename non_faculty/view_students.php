<?php
session_start();
include("connect.php");
include("auth_check.php");

if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

$staff_id = $_SESSION['uid'];

// Check if a subject is selected
if (!isset($_GET['sub_id']) || intval($_GET['sub_id']) == 0) {
    header("Location: view_subjects.php");
    exit();
}

$selectedSubjectId = intval($_GET['sub_id']);

// Get subject details
$subjectQuery = "SELECT sub_id, sub_name, course_id, sem_id 
                 FROM subject 
                 WHERE sub_id = ?";
$stmt = $conn->prepare($subjectQuery);
$stmt->bind_param("i", $selectedSubjectId);
$stmt->execute();
$subjectResult = $stmt->get_result();

if ($subjectResult->num_rows === 0) {
    die("Subject not found.");
}

$subjectInfo = $subjectResult->fetch_assoc();
$stmt->close();

// Get course and semester names
$courseQuery = "SELECT course_name FROM course WHERE course_id = ?";
$stmt = $conn->prepare($courseQuery);
$stmt->bind_param("i", $subjectInfo['course_id']);
$stmt->execute();
$courseResult = $stmt->get_result();
$courseName = $courseResult->fetch_assoc()['course_name'] ?? 'Unknown Course';
$stmt->close();

$semesterQuery = "SELECT sem_name FROM semester WHERE sem_id = ?";
$stmt = $conn->prepare($semesterQuery);
$stmt->bind_param("i", $subjectInfo['sem_id']);
$stmt->execute();
$semesterResult = $stmt->get_result();
$semesterName = $semesterResult->fetch_assoc()['sem_name'] ?? 'Unknown Semester';
$stmt->close();

// Search functionality
$search_student = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination settings
$records_per_page = 15;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Build WHERE conditions
$whereConditions = [
    "u.course_id = ?",
    "u.sem_id = ?",
    "u.role_id = 2",// Assuming role_id 2 is for students
];
$params = [$subjectInfo['course_id'], $subjectInfo['sem_id']];
$paramTypes = "ii";

if (!empty($search_student)) {
    $whereConditions[] = "(u.full_name LIKE ? OR u.id_number LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%" . $search_student . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= "sss";
}

$whereClause = implode(" AND ", $whereConditions);

// Get total records for pagination
$countQuery = "SELECT COUNT(*) as total FROM users u WHERE $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param($paramTypes, ...$params);
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$total_pages = ceil($totalRecords / $records_per_page);

// Get students enrolled in this subject (matching course and semester)
$query = "SELECT 
    u.user_id,
    u.full_name,
    u.id_number,
    u.email,
    u.contact_number,
    u.gender,
    u.profile_photo,
    u.status
FROM users u
WHERE $whereClause
ORDER BY u.full_name ASC
LIMIT $records_per_page OFFSET $offset";

$stmt = $conn->prepare($query);
$stmt->bind_param($paramTypes, ...$params); // only for WHERE placeholders
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>View Students - <?= htmlspecialchars($subjectInfo['sub_name']) ?></title>
    <link rel="stylesheet" href="../css/all.min.css" />
    <link rel="stylesheet" href="../css/admin_menu.css" />
    <link rel="stylesheet" href="../css/notify_staff.css" />
    <link rel="stylesheet" href="../css/view_marks.css" />
    <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
    <style>
        .student-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e0e0;
        }
        .student-photo-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
            border: 2px solid #e0e0e0;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .subject-details {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 10px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .subject-details h3 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .subject-details p {
            margin: 5px 0;
            opacity: 0.9;
        }
        .no-students {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-students i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        .student-count {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .student-count i {
            color: #667eea;
            font-size: 24px;
        }
        .student-count .count {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .student-count .label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>

<body>

<?php include("header.php"); ?>
<?php include("menu.php"); ?>

<div class="main-content">
    <!-- Back button -->
    <a href="view_subjects.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
        <span>Back to My Subjects</span>
    </a>

    <!-- Subject Information -->
    <div class="subject-details">
        <h3><i class="fas fa-book"></i> <?= htmlspecialchars($subjectInfo['sub_name']) ?></h3>
        <p><strong>Course:</strong> <?= htmlspecialchars($courseName) ?></p>
        <p><strong>Semester:</strong> <?= htmlspecialchars($semesterName) ?></p>
    </div>

    <!-- Student Count -->
    <div class="student-count">
        <i class="fas fa-users"></i>
        <div>
            <div class="count"><?= $totalRecords ?></div>
            <div class="label">Enrolled Student<?= $totalRecords != 1 ? 's' : '' ?></div>
        </div>
    </div>

    <!-- Search Filter -->
    <div class="filter-container">
        <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; width: 100%;">
            <input type="hidden" name="sub_id" value="<?= $selectedSubjectId ?>">
            
            <div class="search-group" style="flex: 1;">
                <div class="filter-group" style="width: 100%;">
                    <label>Search Student</label>
                    <input type="text" name="search" id="search" placeholder="Name, ID number, or email..." value="<?= htmlspecialchars($search_student) ?>">
                </div>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>

            <?php if (!empty($search_student)): ?>
            <button type="button" class="clear-filters-btn" onclick="window.location='view_students.php?sub_id=<?= $selectedSubjectId ?>';">
                <i class="fas fa-times"></i> Clear
            </button>
            <?php endif; ?>
        </form>
    </div>

    <!-- Pagination Info -->
    <?php if ($totalRecords > 0): ?>
    <div class="pagination-info">
        Showing <?= $offset + 1 ?> to <?= min($offset + $records_per_page, $totalRecords) ?> of <?= $totalRecords ?> students
    </div>
    <?php endif; ?>

    <!-- Students Table -->
    <table>
        <thead>
            <tr>
                <th>Photo</th>
                <th>ID Number</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Gender</th>
                <th>Contact</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                
                // Photo column
                echo "<td style='text-align: center;'>";
                if (!empty($row['profile_photo']) && file_exists("../uploads/profile_photos/" . $row['profile_photo'])) {
                    echo "<img src='../uploads/profile_photos/" . htmlspecialchars($row['profile_photo']) . "' alt='Profile' class='student-photo'>";
                } else {
                    $initials = strtoupper(substr($row['full_name'], 0, 1));
                    echo "<div class='student-photo-placeholder'>" . htmlspecialchars($initials) . "</div>";
                }
                echo "</td>";
                
                echo "<td>" . htmlspecialchars($row['id_number'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($row['full_name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($row['email'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($row['gender'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($row['contact_number'] ?? 'N/A') . "</td>";
                
                // Status badge
                $status = $row['status'] ?? 'inactive';
                $statusClass = $status === 'active' ? 'status-active' : 'status-inactive';
                echo "<td><span class='status-badge $statusClass'>" . htmlspecialchars($status) . "</span></td>";
                
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='7' class='no-students'>";
            echo "<i class='fas fa-user-slash'></i><br>";
            if (!empty($search_student)) {
                echo "<strong>No students found matching your search.</strong><br>";
                echo "Try adjusting your search criteria.";
            } else {
                echo "<strong>No students enrolled in this subject yet.</strong><br>";
                echo "Students matching the course and semester will appear here.";
            }
            echo "</td></tr>";
        }
        
        $stmt->close();
        ?>
        </tbody>
    </table>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
        // Build query string for pagination links
        $query_params = ['sub_id' => $selectedSubjectId];
        if (!empty($search_student)) {
            $query_params['search'] = $search_student;
        }
        
        // Previous button
        if ($current_page > 1) {
            $query_params['page'] = $current_page - 1;
            echo '<a href="?'.http_build_query($query_params).'">Previous</a>';
        } else {
            echo '<span class="disabled">Previous</span>';
        }
        
        // Page numbers
        $range = 2;
        $start_page = max(1, $current_page - $range);
        $end_page = min($total_pages, $current_page + $range);
        
        // First page
        if ($start_page > 1) {
            $query_params['page'] = 1;
            echo '<a href="?'.http_build_query($query_params).'">1</a>';
            if ($start_page > 2) {
                echo '<span>...</span>';
            }
        }
        
        // Page numbers in range
        for ($i = $start_page; $i <= $end_page; $i++) {
            $query_params['page'] = $i;
            if ($i == $current_page) {
                echo '<span class="active">'.$i.'</span>';
            } else {
                echo '<a href="?'.http_build_query($query_params).'">'.$i.'</a>';
            }
        }
        
        // Last page
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<span>...</span>';
            }
            $query_params['page'] = $total_pages;
            echo '<a href="?'.http_build_query($query_params).'">'.$total_pages.'</a>';
        }
        
        // Next button
        if ($current_page < $total_pages) {
            $query_params['page'] = $current_page + 1;
            echo '<a href="?'.http_build_query($query_params).'">Next</a>';
        } else {
            echo '<span class="disabled">Next</span>';
        }
        ?>
    </div>
    <?php endif; ?>

</div>
</div>

<?php include("footer.php"); ?>
<?php include("lower_footer.php"); ?>

</body>
</html>
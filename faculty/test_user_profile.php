<?php
session_start();
include("connect.php"); // your DB connection
include("auth_check.php");

// Check if user is logged in
if (!isset($_SESSION['uid'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['uid']);

// Fetch student info
$query = "
    SELECT u.*, c.course_name, s.sem_name
    FROM users u
    LEFT JOIN course c ON u.course_id = c.course_id
    LEFT JOIN semester s ON u.sem_id = s.sem_id
    WHERE u.user_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found!";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link rel="stylesheet" href="profile.css">
    <style>
        body {
    font-family: Arial, sans-serif;
    background: #f5f6fa;
    margin: 0;
    padding: 0;
}

.profile-container {
    width: 90%;
    max-width: 700px;
    margin: 50px auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 30px;
}

.profile-header {
    text-align: center;
    margin-bottom: 30px;
}

.profile-photo img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid #667eea;
    margin-bottom: 15px;
}

.profile-header h2 {
    margin: 0;
    color: #333;
}

.profile-header p {
    color: #666;
    margin-top: 5px;
}

.profile-info {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.info-item {
    display: flex;
    gap: 10px;
    padding: 12px 15px;
    background: #f1f2f6;
    border-radius: 8px;
}

.label {
    font-weight: bold;
    color: #333;
    min-width: 120px;
}

    </style>
</head>
<body>
<?php include("menu.php"); ?>

    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-photo">
                <?php if (!empty($user['profile_photo'])): ?>
                    <img src="uploads/<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Photo">
                <?php else: ?>
                    <img src="uploads/default.png" alt="Default Photo">
                <?php endif; ?>
            </div>
            <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
            <p>Student ID: <?php echo htmlspecialchars($user['id_number']); ?></p>
        </div>

        <div class="profile-info">
            <div class="info-item">
                <span class="label">Email:</span> <?php echo htmlspecialchars($user['email']); ?>
            </div>
            <div class="info-item">
                <span class="label">Gender:</span> <?php echo htmlspecialchars($user['gender']); ?>
            </div>
            <div class="info-item">
                <span class="label">Date of Birth:</span> <?php echo htmlspecialchars($user['date_of_birth']); ?>
            </div>
            <div class="info-item">
                <span class="label">Contact Number:</span> <?php echo htmlspecialchars($user['contact_number']); ?>
            </div>
            <div class="info-item">
                <span class="label">Address:</span> <?php echo htmlspecialchars($user['address']); ?>
            </div>
            <div class="info-item">
                <span class="label">Course:</span> <?php echo htmlspecialchars($user['course_name']); ?>
            </div>
            <div class="info-item">
                <span class="label">Semester:</span> <?php echo htmlspecialchars($user['sem_name']); ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php
session_start();
include("connect.php");
include("auth_check.php");
// Check if student is logged in
if (!isset($_SESSION['uid'])) {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once('connect.php');

$student_id = $_SESSION['uid'];

// Fetch student details from users table
$student_query = "SELECT full_name, email, course_id FROM users WHERE user_id = '$student_id'";
$student_result = mysqli_query($conn, $student_query);
$student_data = mysqli_fetch_assoc($student_result);

$student_name = $student_data['full_name'];
$student_email = $student_data['email'];
$course_id = $student_data['course_id'];

// Fetch coordinator for the student's course
$coordinator_query = "SELECT user_id FROM users WHERE is_coordinator = 1 AND coordinator_for = '$course_id' LIMIT 1";
$coordinator_result = mysqli_query($conn, $coordinator_query);
$coordinator_data = mysqli_fetch_assoc($coordinator_result);
$coordinator_id = $coordinator_data['user_id'];

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_leave'])) {
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    
    // Validate dates
    if (strtotime($start_date) > strtotime($end_date)) {
        $error_message = "End date must be after or equal to start date!";
    } else {
        // Insert leave request
        $sql = "INSERT INTO studentLeaveReq (student_id, student_name, student_email, coordinator_id, leave_type, start_date, end_date, reason, status) 
                VALUES ('$student_id', '$student_name', '$student_email', '$coordinator_id', '$leave_type', '$start_date', '$end_date', '$reason', 'pending')";
        
        if (mysqli_query($conn, $sql)) {
            $success_message = "Leave request submitted successfully!";
        } else {
            $error_message = "Error: " . mysqli_error($conn);
        }
    }
}

// Fetch student's leave requests
$sql = "SELECT * FROM studentLeaveReq WHERE student_id = '$student_id' ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Leave Request</title>
    <link rel="stylesheet" href="../css/all.min.css" />
    <link rel="stylesheet" href="../css/admin_menu.css" />
    <link rel="stylesheet" href="../css/student_leave_requests.css" />
    <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">
</head>
<body>
    
    <div class="container">
        <h1>Leave Request System</h1>
        
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Leave Request Form -->
        <div class="form-section">
            <h2>Apply for Leave</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="leave_type">Leave Type:</label>
                    <select name="leave_type" id="leave_type" required>
                        <option value="">Select Leave Type</option>
                        <option value="Sick Leave">Sick Leave</option>
                        <option value="Casual Leave">Casual Leave</option>
                        <option value="Emergency Leave">Emergency Leave</option>
                        <option value="Personal Leave">Personal Leave</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" name="end_date" id="end_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="reason">Reason:</label>
                    <textarea name="reason" id="reason" rows="4" required placeholder="Please provide detailed reason for leave..."></textarea>
                </div>
                
                <button type="submit" name="submit_leave">Submit Leave Request</button>
            </form>
        </div>
        
        <!-- Leave Requests History -->
        <div class="history-section">
            <h2>My Leave Requests</h2>
            
            <?php if (mysqli_num_rows($result) > 0): ?>
                <table border="1" cellpadding="10" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Coordinator Remarks</th>
                            <th>Submitted On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                                <td><?php echo date('d-M-Y', strtotime($row['start_date'])); ?></td>
                                <td><?php echo date('d-M-Y', strtotime($row['end_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                <td>
                                    <span class="status-<?php echo $row['status']; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        echo $row['coordinator_remarks'] 
                                            ? htmlspecialchars($row['coordinator_remarks']) 
                                            : '-'; 
                                    ?>
                                </td>
                                <td><?php echo date('d-M-Y H:i', strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No leave requests found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
mysqli_close($conn);
?>
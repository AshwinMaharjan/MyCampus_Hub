<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu</title>
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../css/admin_menu.css">
    <link rel="icon" href="../Prime-College-Logo.ico" type="image/x-icon">

    <style>
      /* Logout Confirmation Modal */
      .logout-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 2000;
        animation: fadeIn 0.3s ease-in;
      }

      .logout-overlay.active {
        display: flex;
      }

      @keyframes fadeIn {
        from {
          opacity: 0;
        }
        to {
          opacity: 1;
        }
      }

      .logout-modal {
        background: white;
        border-radius: 12px;
        padding: 40px;
        max-width: 450px;
        width: 90%;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.4s ease-out;
        position: relative;
      }

      @keyframes slideUp {
        from {
          transform: translateY(30px);
          opacity: 0;
        }
        to {
          transform: translateY(0);
          opacity: 1;
        }
      }

      .logout-icon {
        font-size: 60px;
        color: #f59e0b;
        margin-bottom: 20px;
        animation: bounce 0.6s ease-out;
      }

      @keyframes bounce {
        0% {
          transform: scale(0);
        }
        50% {
          transform: scale(1.1);
        }
        100% {
          transform: scale(1);
        }
      }

      .logout-title {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 10px;
        color: #1f2937;
      }

      .logout-message {
        font-size: 16px;
        color: #6b7280;
        margin-bottom: 30px;
        line-height: 1.6;
      }

      .logout-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
      }

      .logout-button {
        padding: 10px 30px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        flex: 1;
        max-width: 150px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
      }

      .logout-button.confirm {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.2);
      }

      .logout-button.confirm:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
      }

      .logout-button.cancel {
        background: #e5e7eb;
        color: #374151;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      }

      .logout-button.cancel:hover {
        background: #d1d5db;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      }

      /* Logout Success Modal */
      .logout-success-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 2000;
        animation: fadeIn 0.3s ease-in;
      }

      .logout-success-overlay.active {
        display: flex;
      }

      .logout-success-modal {
        background: white;
        border-radius: 12px;
        padding: 40px;
        max-width: 450px;
        width: 90%;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.4s ease-out;
        position: relative;
        border-left: 5px solid #10b981;
      }

      .logout-success-icon {
        font-size: 60px;
        color: #10b981;
        margin-bottom: 20px;
        animation: bounce 0.6s ease-out;
      }

      .logout-success-title {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 10px;
        color: #1f2937;
      }

      .logout-success-message {
        font-size: 16px;
        color: #6b7280;
        margin-bottom: 30px;
        line-height: 1.6;
      }

      .logout-progress {
        height: 3px;
        background: #e5e7eb;
        border-radius: 3px;
        overflow: hidden;
        margin-top: 20px;
      }

      .logout-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #10b981, #059669);
        animation: progress linear 2s forwards;
        border-radius: 3px;
      }

      @keyframes progress {
        from {
          width: 100%;
        }
        to {
          width: 0%;
        }
      }
    </style>
</head>
<body>
<div class="page-wrapper">
  <div class="sidebar">
    <div class="sidebar-header">
    </div>
    <ul class="sidebar-menu">
  <li><a href="admin_homepage.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>

  <li class="dropdown">
    <a href="#" class="dropdown-toggle"><i class="fas fa-book"></i> Course <i class="fas fa-caret-down right"></i></a>
    <ul class="dropdown-content">
      <li><a href="add_course.php">Add Course</a></li>
      <li><a href="manage_courses.php">Manage Course</a></li>
    </ul>
  </li>

  <li class="dropdown">
    <a href="#" class="dropdown-toggle"><i class="fas fa-book-open"></i> Subject <i class="fas fa-caret-down right"></i></a>
    <ul class="dropdown-content">
      <li><a href="add_subject.php">Add Subject</a></li>
      <li><a href="manage_subjects.php">Manage Subject</a></li>
    </ul>
  </li>

  <li class="dropdown">
    <a href="#" class="dropdown-toggle"><i class="fas fa-calendar-alt"></i> Session <i class="fas fa-caret-down right"></i></a>
    <ul class="dropdown-content">
      <li><a href="add_session.php">Add Session</a></li>
      <li><a href="manage_sessions.php">Manage Session</a></li>
    </ul>
  </li>

  <li><a href="add_staff.php"><i class="fas fa-user-plus"></i> Add Staff</a></li>
  <li><a href="manage_staff.php"><i class="fas fa-users-cog"></i> Manage Staff</a></li>
  <li><a href="manage_student.php"><i class="fas fa-user-friends"></i> Manage Student</a></li>
  <li><a href="notify_staff.php"><i class="fas fa-bell"></i> Notify Staff</a></li>
  <li><a href="notify_student.php"><i class="fas fa-bell"></i> Notify Student</a></li>
  <li><a href="view_notification.php"><i class="fas fa-calendar-check"></i> View Notification</a></li>
  <li><a href="admin_view_attendance.php"><i class="fas fa-calendar-check"></i> View Attendance</a></li>
  <li><a href="view_academic_reports.php"><i class="fas fa-chart-line"></i> Academic Reports</a></li>
  <!-- <li><a href="staff_feedback.php"><i class="fas fa-comments"></i> Staff Feedback</a></li>
  <li><a href="student_feedback.php"><i class="fas fa-comment-dots"></i> Student Feedback</a></li> -->
  <!-- <li><a href="student_leave.php"><i class="fas fa-user-clock"></i> Student Leave</a></li> -->
  <li><a href="staff_leave_requests.php"><i class="fas fa-user-clock"></i> Staff Leave</a></li>
  <li><a href="study_material.php"><i class="fas fa-file-alt"></i> Study Material Sharing</a></li>
  <!-- <li><a href="internship_portal.php"><i class="fas fa-briefcase"></i> Internship/Job Portal</a></li> -->
  <li><a href="#" onclick="openLogoutConfirmation(event)"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
  </div>

  <!-- Logout Confirmation Modal -->
  <div class="logout-overlay" id="logoutConfirmationOverlay">
    <div class="logout-modal">
      <div class="logout-icon">
        <i class="fas fa-sign-out-alt"></i>
      </div>
      <div class="logout-title">Logout?</div>
      <div class="logout-message">Are you sure you want to logout? You will need to login again to access this system.</div>
      <div class="logout-buttons">
        <button class="logout-button cancel" onclick="closeLogoutConfirmation()">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button class="logout-button confirm" onclick="confirmLogout()">
          <i class="fas fa-check"></i> Logout
        </button>
      </div>
    </div>
  </div>

  <!-- Logout Success Modal -->
  <div class="logout-success-overlay" id="logoutSuccessOverlay">
    <div class="logout-success-modal">
      <div class="logout-success-icon">
        <i class="fas fa-check-circle"></i>
      </div>
      <div class="logout-success-title">Logged Out Successfully!</div>
      <div class="logout-success-message">You have been successfully logged out. Redirecting to login page...</div>
      <div class="logout-progress">
        <div class="logout-progress-bar"></div>
      </div>
    </div>
  </div>

  <script src="../js/admin_menu.js"></script>
  <script>
    function openLogoutConfirmation(event) {
      event.preventDefault();
      document.getElementById('logoutConfirmationOverlay').classList.add('active');
    }

    function closeLogoutConfirmation() {
      const overlay = document.getElementById('logoutConfirmationOverlay');
      overlay.style.animation = 'fadeIn 0.3s ease-in reverse';
      setTimeout(() => {
        overlay.classList.remove('active');
      }, 300);
    }

    function confirmLogout() {
      // Close confirmation modal
      document.getElementById('logoutConfirmationOverlay').classList.remove('active');
      
      // Show success modal
      document.getElementById('logoutSuccessOverlay').classList.add('active');
      
      // Redirect after 2 seconds
      setTimeout(() => {
        window.location.href = 'logout.php';
      }, 2000);
    }
  </script>
</body>
</html>
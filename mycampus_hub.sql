-- phpMyAdmin SQL Dump
-- MyCampus Hub - Database Schema (Structure Only)
-- All personal/sensitive data has been removed for public distribution.
-- --------------------------------------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Database: `mycampus_hub`
-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(400) NOT NULL,
  `module` enum('Login','Event','Internship','Complaint','Voting','Result','System','Others') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `details` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(400) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sub_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `sem_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Late','Absent') NOT NULL DEFAULT 'Absent',
  `remarks` varchar(255) DEFAULT NULL,
  `attendance_done_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `candidate_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `position` varchar(400) NOT NULL,
  `manifesto` text NOT NULL,
  `photo` varchar(400) NOT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT 1,
  `votes_received` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `certificate_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(400) NOT NULL,
  `event_id` int(11) NOT NULL,
  `result_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `file_path` varchar(400) NOT NULL,
  `generated_by` int(11) NOT NULL,
  `remarks` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint`
--

CREATE TABLE `complaint` (
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(400) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(400) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','In Progress','Resolved','Rejected') NOT NULL DEFAULT 'Pending',
  `resolved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_by` int(11) NOT NULL,
  `response_message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_response`
--

CREATE TABLE `complaint_response` (
  `response_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `responder_id` int(11) NOT NULL,
  `response_message` text NOT NULL,
  `response_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','In Progress','Resolved','Rejected') NOT NULL,
  `is_visible_to_user` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coordinators`
--

CREATE TABLE `coordinators` (
  `coordinator_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `coordinator_for` int(11) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

CREATE TABLE `course` (
  `course_id` int(11) NOT NULL,
  `course_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Reference data for table `course` (non-sensitive)
--

INSERT INTO `course` (`course_id`, `course_name`) VALUES
(1, 'BIM'),
(2, 'BBA'),
(3, 'BBM'),
(4, 'BCA'),
(5, 'BBS');

-- --------------------------------------------------------

--
-- Table structure for table `elections`
--

CREATE TABLE `elections` (
  `election_id` int(11) NOT NULL,
  `title` varchar(400) NOT NULL,
  `description` text NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Upcoming','Ongoing','Completed','Cancelled') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event`
--

CREATE TABLE `event` (
  `event_id` int(11) NOT NULL,
  `event_title` varchar(400) NOT NULL,
  `description` text NOT NULL,
  `event_type` enum('Individual','Team','','') NOT NULL,
  `category` varchar(400) NOT NULL,
  `location` varchar(400) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `organizer_id` int(11) NOT NULL,
  `max_participants` int(11) NOT NULL,
  `poster_image` varchar(400) NOT NULL,
  `qr_code` varchar(400) NOT NULL,
  `status` enum('Upcoming','Ongoing','Completed','Cancelled') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_attendance`
--

CREATE TABLE `event_attendance` (
  `attendance_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `marked_by` int(11) NOT NULL,
  `check_in_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Present','Absent','Late','') NOT NULL,
  `remarks` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `registration_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `team_name` varchar(400) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL,
  `remarks` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_types`
--

CREATE TABLE `exam_types` (
  `exam_type_id` int(11) NOT NULL,
  `exam_name` varchar(100) NOT NULL,
  `exam_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `weightage` decimal(5,2) DEFAULT 0.00,
  `display_order` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Reference data for table `exam_types` (non-sensitive)
--

INSERT INTO `exam_types` (`exam_type_id`, `exam_name`, `exam_code`, `description`, `weightage`, `display_order`, `is_active`) VALUES
(1, 'First Unit Test',       'UT1',        NULL, 5.00,  1, 1),
(2, 'Second Unit Test',      'UT2',        NULL, 5.00,  2, 1),
(3, 'Mid-term Examination',  'MIDTERM',    NULL, 20.00, 3, 1),
(4, 'Third Unit Test',       'UT3',        NULL, 5.00,  4, 1),
(5, 'Pre-board Examination', 'PREBOARD',   NULL, 15.00, 5, 1),
(6, 'Practical Assessment',  'PRACTICAL',  NULL, 20.00, 6, 1),
(7, 'Assignment/Project',    'ASSIGNMENT', NULL, 10.00, 7, 1);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('Event','Internship','System','Other') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `internship`
--

CREATE TABLE `internship` (
  `internship_id` int(11) NOT NULL,
  `title` varchar(400) NOT NULL,
  `description` text NOT NULL,
  `company_name` varchar(400) NOT NULL,
  `location` varchar(400) NOT NULL,
  `stipend` varchar(400) NOT NULL,
  `duration` varchar(400) NOT NULL,
  `requirements` text NOT NULL,
  `posted_by` int(11) NOT NULL,
  `application_deadline` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Open','Closed','','') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `internship_applications`
--

CREATE TABLE `internship_applications` (
  `application_id` int(11) NOT NULL,
  `internship_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cover_letter` text NOT NULL,
  `resume_file` varchar(400) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Accepted','Rejected','') NOT NULL DEFAULT 'Pending',
  `reviewed_by` int(11) NOT NULL,
  `remarks` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marks`
--

CREATE TABLE `marks` (
  `marks_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_number` varchar(400) NOT NULL,
  `sub_id` int(11) NOT NULL,
  `sem_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `full_marks` int(11) NOT NULL,
  `obtained_marks` decimal(5,2) NOT NULL,
  `remarks` varchar(100) NOT NULL,
  `entered_by_staff` int(11) NOT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `grade` varchar(5) DEFAULT NULL,
  `exam_type_id` int(11) DEFAULT NULL,
  `exam_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `date_sent` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `result_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('Academic','Event','Others','') NOT NULL,
  `title` varchar(400) NOT NULL,
  `description` text NOT NULL,
  `score` varchar(400) NOT NULL,
  `evaluated_by` int(11) NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remarks` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `semester`
--

CREATE TABLE `semester` (
  `sem_id` int(11) NOT NULL,
  `sem_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Reference data for table `semester` (non-sensitive)
--

INSERT INTO `semester` (`sem_id`, `sem_name`) VALUES
(1, '1st Semester'),
(2, '2nd Semester'),
(3, '3rd Semester'),
(4, '4th Semester'),
(5, '5th Semester'),
(6, '6th Semester'),
(7, '7th Semester'),
(8, '8th Semester');

-- --------------------------------------------------------

--
-- Table structure for table `session`
--

CREATE TABLE `session` (
  `session_id` int(11) NOT NULL,
  `sem_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Active','Inactive','','') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_leave_requests`
--

CREATE TABLE `staff_leave_requests` (
  `leave_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `admin_id` int(11) DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_teaching_assignments`
--

CREATE TABLE `staff_teaching_assignments` (
  `assignment_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `sem_id` int(11) NOT NULL,
  `sub_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_leave_requests`
--

CREATE TABLE `student_leave_requests` (
  `leave_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `sem_id` int(11) NOT NULL,
  `leave_type` enum('Sick','Casual','Personal','Other') DEFAULT 'Other',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `coordinator_id` int(11) DEFAULT NULL,
  `coordinator_remarks` text DEFAULT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `study_material`
--

CREATE TABLE `study_material` (
  `material_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `sem_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `material_title` varchar(255) NOT NULL,
  `material_description` text DEFAULT NULL,
  `material_type` enum('notes','assignment','previous_paper','reference_book','presentation','lab_manual','other') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `file_extension` varchar(10) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approval_status` enum('approved','pending','rejected') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subject`
--

CREATE TABLE `subject` (
  `sub_id` int(11) NOT NULL,
  `sub_name` varchar(400) NOT NULL,
  `role_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `sem_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subject_exam_config`
--

CREATE TABLE `subject_exam_config` (
  `config_id` int(11) NOT NULL,
  `sub_id` int(11) NOT NULL,
  `exam_type_id` int(11) NOT NULL,
  `full_marks` int(11) NOT NULL,
  `is_applicable` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(400) NOT NULL,
  `id_number` varchar(400) NOT NULL,
  `email` varchar(400) NOT NULL,
  `password` varchar(400) NOT NULL,
  `role_id` int(11) NOT NULL,
  `gender` enum('Male','Female','Others','Rather Not Say') NOT NULL,
  `date_of_birth` date NOT NULL,
  `contact_number` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `course_name` varchar(100) NOT NULL,
  `sem_id` int(11) DEFAULT NULL,
  `sem_name` varchar(255) DEFAULT NULL,
  `profile_photo` varchar(400) NOT NULL,
  `status` enum('Active','Inactive','','') NOT NULL,
  `is_coordinator` tinyint(1) DEFAULT 0,
  `coordinator_for` int(11) DEFAULT NULL,
  `is_teacher` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `vote_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `voter_id` int(11) NOT NULL,
  `cast_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_valid` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================================
-- PRIMARY KEYS & INDEXES
-- ========================================================

ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`);

ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`sub_id`,`attendance_date`),
  ADD KEY `fk_attendance_done_by` (`attendance_done_by`);

ALTER TABLE `candidates`
  ADD PRIMARY KEY (`candidate_id`);

ALTER TABLE `certificates`
  ADD PRIMARY KEY (`certificate_id`);

ALTER TABLE `complaint`
  ADD PRIMARY KEY (`complaint_id`);

ALTER TABLE `complaint_response`
  ADD PRIMARY KEY (`response_id`);

ALTER TABLE `coordinators`
  ADD PRIMARY KEY (`coordinator_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `coordinator_for` (`coordinator_for`);

ALTER TABLE `course`
  ADD PRIMARY KEY (`course_id`);

ALTER TABLE `elections`
  ADD PRIMARY KEY (`election_id`);

ALTER TABLE `event`
  ADD PRIMARY KEY (`event_id`);

ALTER TABLE `event_attendance`
  ADD PRIMARY KEY (`attendance_id`);

ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`registration_id`);

ALTER TABLE `exam_types`
  ADD PRIMARY KEY (`exam_type_id`),
  ADD UNIQUE KEY `exam_code` (`exam_code`);

ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`);

ALTER TABLE `internship`
  ADD PRIMARY KEY (`internship_id`);

ALTER TABLE `internship_applications`
  ADD PRIMARY KEY (`application_id`);

ALTER TABLE `marks`
  ADD PRIMARY KEY (`marks_id`),
  ADD UNIQUE KEY `unique_mark` (`user_id`,`sub_id`,`exam_type_id`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `results`
  ADD PRIMARY KEY (`result_id`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

ALTER TABLE `semester`
  ADD PRIMARY KEY (`sem_id`);

ALTER TABLE `session`
  ADD PRIMARY KEY (`session_id`);

ALTER TABLE `staff_leave_requests`
  ADD PRIMARY KEY (`leave_id`);

ALTER TABLE `staff_teaching_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `unique_assignment` (`staff_id`,`course_id`,`sem_id`,`sub_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `sem_id` (`sem_id`),
  ADD KEY `sub_id` (`sub_id`);

ALTER TABLE `student_leave_requests`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `idx_student_leave_status` (`status`),
  ADD KEY `idx_student_leave_student` (`student_id`),
  ADD KEY `idx_student_leave_coordinator` (`coordinator_id`);

ALTER TABLE `study_material`
  ADD PRIMARY KEY (`material_id`),
  ADD KEY `idx_user_uploads` (`user_id`,`upload_date`),
  ADD KEY `idx_subject_materials` (`subject_id`,`sem_id`,`approval_status`),
  ADD KEY `idx_course_semester` (`course_id`,`sem_id`),
  ADD KEY `sem_id` (`sem_id`);

ALTER TABLE `subject`
  ADD PRIMARY KEY (`sub_id`);

ALTER TABLE `subject_exam_config`
  ADD PRIMARY KEY (`config_id`),
  ADD UNIQUE KEY `unique_subject_exam` (`sub_id`,`exam_type_id`),
  ADD KEY `exam_type_id` (`exam_type_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `votes`
  ADD PRIMARY KEY (`vote_id`);

-- ========================================================
-- AUTO_INCREMENT VALUES
-- ========================================================

ALTER TABLE `activity_logs`   MODIFY `log_id`         int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `attendance`       MODIFY `attendance_id`   int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `candidates`       MODIFY `candidate_id`    int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `certificates`     MODIFY `certificate_id`  int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `complaint`        MODIFY `complaint_id`    int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `complaint_response` MODIFY `response_id`  int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `coordinators`     MODIFY `coordinator_id`  int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `course`           MODIFY `course_id`       int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `elections`        MODIFY `election_id`     int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `event`            MODIFY `event_id`        int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `event_attendance` MODIFY `attendance_id`   int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `event_registrations` MODIFY `registration_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `exam_types`       MODIFY `exam_type_id`    int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
ALTER TABLE `feedback`         MODIFY `feedback_id`     int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `internship`       MODIFY `internship_id`   int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `internship_applications` MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `marks`            MODIFY `marks_id`        int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `notifications`    MODIFY `id`              int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `results`          MODIFY `result_id`       int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `roles`            MODIFY `role_id`         int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `semester`         MODIFY `sem_id`          int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `session`          MODIFY `session_id`      int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `staff_leave_requests` MODIFY `leave_id`   int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `staff_teaching_assignments` MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `student_leave_requests` MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `study_material`   MODIFY `material_id`     int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `subject`          MODIFY `sub_id`          int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `subject_exam_config` MODIFY `config_id`   int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users`            MODIFY `user_id`         int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `votes`            MODIFY `vote_id`         int(11) NOT NULL AUTO_INCREMENT;

-- ========================================================
-- FOREIGN KEY CONSTRAINTS
-- ========================================================

ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_attendance_done_by` FOREIGN KEY (`attendance_done_by`) REFERENCES `users` (`user_id`);

ALTER TABLE `coordinators`
  ADD CONSTRAINT `coordinators_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `coordinators_ibfk_2` FOREIGN KEY (`coordinator_for`) REFERENCES `course` (`course_id`);

ALTER TABLE `staff_teaching_assignments`
  ADD CONSTRAINT `staff_teaching_assignments_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_teaching_assignments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_teaching_assignments_ibfk_3` FOREIGN KEY (`sem_id`) REFERENCES `semester` (`sem_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_teaching_assignments_ibfk_4` FOREIGN KEY (`sub_id`) REFERENCES `subject` (`sub_id`) ON DELETE CASCADE;

ALTER TABLE `study_material`
  ADD CONSTRAINT `study_material_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `study_material_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`course_id`),
  ADD CONSTRAINT `study_material_ibfk_3` FOREIGN KEY (`sem_id`) REFERENCES `semester` (`sem_id`),
  ADD CONSTRAINT `study_material_ibfk_4` FOREIGN KEY (`subject_id`) REFERENCES `subject` (`sub_id`);

ALTER TABLE `subject_exam_config`
  ADD CONSTRAINT `subject_exam_config_ibfk_1` FOREIGN KEY (`sub_id`) REFERENCES `subject` (`sub_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_exam_config_ibfk_2` FOREIGN KEY (`exam_type_id`) REFERENCES `exam_types` (`exam_type_id`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
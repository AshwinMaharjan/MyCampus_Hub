<<<<<<< HEAD
# MyCampus_Hub
MyCampus Hub is a web-based College Management System developed as an academic project to digitize and streamline core campus operations. The system provides role-based access for administrators, staff, and students, enabling efficient management of users, courses, subjects, notifications, and internal academic records.
=======
# MyCampus Hub - College Management System

A comprehensive web-based college management system for streamlining academic operations, built with PHP and MySQL.

## 🌐 Live Demo

https://my-campushub.great-site.net/?i=1

## Features

- **User Authentication**: Secure role-based login and profile management for all users
- **Student Management**: Enroll, update and manage student records and profiles
- **Faculty & Staff Management**: Manage faculty and non-faculty staff information and assignments
- **Course & Subject Management**: Create and organize courses, subjects and academic schedules
- **Attendance Tracking**: Record and monitor student attendance by subjects and dates
- **Grades & Results**: Enter, manage and publish student grades and academic results
- **Responsive Design**: Fully functional across desktop only

## User Roles

| Role | Access |
|------|--------|
| **Admin** | Full system control: manage users, courses, reports and settings |
| **Faculty** | Mark attendance, enter grades, view assigned courses and students |
| **Non-Faculty Staff** | Access relevant administrative modules based on assigned permissions |
| **Student** | View attendance, results, course details and personal profile |

## Technologies Used

- **Frontend**: HTML5, CSS3, JavaScript, jQuery, AJAX
- **Backend**: PHP
- **Database**: MySQL
- **Server**: XAMPP / WAMP (Localhost)

## Installation

### Prerequisites

- XAMPP or WAMP server installed
- PHP 7.0 or higher
- MySQL 5.7 or higher

### Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/AshwinMaharjan/MyCampus_Hub.git
   ```

2. **Move to your server root**
   - For XAMPP: place the folder inside `htdocs/`
   - For WAMP: place the folder inside `www/`

3. **Import the database**
   - Open phpMyAdmin
   - Create a new database named `mycampus_hub`
   - Import the SQL file from `mycampus_hub.sql`

4. **Configure database credentials**

   Open `connect.php` and update:
   ```php
   define('DB_SERVER', 'localhost');
   define('DB_USERNAME', 'root');
   define('DB_PASSWORD', '');
   define('DB_NAME', 'mycampus_hub');
   ```

5. **Run the application**
   - Start your Apache and MySQL servers from the XAMPP/WAMP control panel
   - Visit the app at: `http://localhost/mycampus_hub`

## Usage

### For Admin
- Log in with admin credentials
- Add and manage students, faculty, and non-faculty staff
- Create and assign courses and subjects
- Monitor attendance records and published results
- Manage system-wide settings and permissions

### For Faculty
- Log in to your faculty account
- View assigned courses and student lists
- Mark and update student attendance by subject
- Enter and manage student grades and results

### For Non-Faculty Staff
- Log in with staff credentials
- Access administrative modules based on assigned role permissions

### For Students
- Register or log in to your student account
- View enrolled courses and subject details
- Check your attendance records by subject
- View published grades and academic results
- Update your personal profile

## Project Structure

```
MyCampus_Hub/
├── admin/              # Admin panel pages and logic
├── faculty/            # Faculty dashboard and module pages
├── student/            # Student dashboard and module pages
├── non_faculty/        # Non-faculty dashboard and module pages
├── css/                # Stylesheets
├── js/                 # Scripts
├── webfonts/           # WebFonts
├── images/             # Site and profile images
├── uploads/            # Uploaded profile and document images
├── homepage.php        # Application entry point
└── mycampus_hub.sql    # Database schema and seed data
```

## Screenshots
- Landing Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/f656afea-145d-42c2-a9d7-83581f515869" />
- About Section: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/bb72d4bb-a9c1-4ec3-954e-cb16757c9f57" />
- Access Section: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/0fa54466-9972-44b9-9be8-faf029c0202a" />
- Features Section: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/55e99bf7-fe7b-4583-8712-8ca1cbae8051" />
- Login Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/a5313894-3f86-42ba-b2f3-6f30faff3cde" />
- Register Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/71db1670-9499-4a4e-800f-dfe0d407689e" />
- Admin Dashboard: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/16db3041-a830-4ea6-ada1-5f148038d065" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/a6cd2fb8-4a2f-45cd-b150-ea43b760c79f" />
- Manage Course: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/f89ed547-d904-4405-ad6a-06b8d871680e" />
- Manage Subject: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/e4809b7b-4317-428f-ae8e-511400c04fd5" />
- Manage Sessions: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/a57f11e3-8cd9-4a96-9be2-dc56d5c8a397" />
- Add Staff: <img width="1348" height="1154" alt="localhost_mycampus_hub_admin_add_staff php (1)" src="https://github.com/user-attachments/assets/9d2fcc12-23ac-4e5c-95c4-611f4f80871e" />
- Manage Staff: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/0ea58853-8a74-4f32-9b18-b03c50e4f147" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/3992f933-5386-4d05-b06e-4426b1ecbc64" />
- Manage Student: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/de582403-18eb-4445-aa9b-1664c46b9d85" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/6a6f8a7b-d087-4413-a04f-00c29027e163" />
- Notify Staff: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/feeb5296-a9fc-46b7-a96e-b68be2124169" />
- View Attendance: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/930e4a00-2142-40ec-a41e-608c9826baa4" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/eec0edc4-05b2-4233-a181-2cab3c10572d" />
- Academic Reports: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/82161f8d-2716-4577-ae1b-59310a8d274a" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/2be14a06-a95f-40f1-9d5f-e0798c555da5" />
- Staff Leave: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/392cf885-594c-4a08-8f8a-fa2889bff662" />
- Study Material Sharing: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/9553438e-0a01-475f-b5c9-41453480dee8" />
- Student Dashboard: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/cf144de3-7948-42ab-ad86-65aa88197d2f" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/50f9839a-510a-49e2-beeb-672b63f743a2" />
- My Subjects: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/201f34f0-93a4-4481-aa4e-7612ccb80242" />
- My Marks: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/75dd949b-46cc-4f0a-8411-5cf521d9f1a3" />
- Attendance: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/d4c5f527-a548-4233-bcbd-f0ec3378e9a7" />
- Leave Requests: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/0fbfdd87-9b9e-41e5-ba82-b3e1bf43557d" />
- Study Materials: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/4eeb467e-8470-468a-881b-1607650cf341" /> <img width="1147" height="479" alt="image" src="https://github.com/user-attachments/assets/1be3edf0-1c90-4d94-a909-f34e63e30aad" /> <img width="1147" height="487" alt="image" src="https://github.com/user-attachments/assets/3f30fe59-0a89-452d-b3d4-f86bfa703461" />
- Faculty Dashboard: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/05dc9003-c427-4a51-b96e-4336250d82fa" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/b61bfce7-c579-4cca-a724-188a0a9b5580" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/912f17d2-d16f-4995-9dd1-2e06d2ea6de9" />
- Faculty Subjects: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/67d845fb-20df-42d9-a707-648f7e8a3e8a" />
- Enter Marks: <img width="858" height="1043" alt="localhost_mycampus_hub_faculty_enter_marks php" src="https://github.com/user-attachments/assets/35bc9c4f-6b5f-4eb2-8402-85f3a12a29cf" />
- View Marks: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/abe68418-9d00-43b0-a612-68a867cbc805" />
- Mark Attendance: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/c8311bb3-cb6b-4ca4-bdc6-031ec076e959" />
- View Attendance: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/cb389d1e-db7d-4e60-9fd5-a01de89b49ec" />
- Attendance Reports:<img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/567dd232-0cca-4224-b834-097760cfd977" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/e2f984f5-0b90-4aa7-94ee-367c6b71622a" />
- Leave Requests: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/cd1e4ed6-e1e8-4dca-88ac-b6d460e2bb91" />
- View Leave Requests: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/e09dad0c-26e2-4fb8-9931-cc6316e882a8" />
- Study Material: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/588abd6a-ab89-46b9-94f3-cca4122ab9b2" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/8c74d57b-9b74-49a9-bfcf-bf606304f3e9" />
- Coordinator Dashboard: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/bb38b5d6-9428-4dd7-a386-fb46993052ab" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/352e31d7-e7d6-499c-93d4-87d79214ae49" />
- View Subjects: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/865e6ac3-182e-41ca-abbe-c0f9151e39fa" />
- View Marks: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/7169fe1b-56cf-46e2-a2cb-7e793036b852" />
- View Attendance: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/2242fe44-6947-433b-810e-694648cf0f41" />
- View My Leave Requests: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/08dbe6ea-5fac-4ad1-8b56-cbabc25a36b4" />
- View Student Leave Requests: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/d874ef72-f968-494f-903b-b342a0e0713f" />
- View Study Material: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/3c0f1cd5-8b7e-42c0-97a1-b79ba94c295b" />

## Roadmap

- [ ] Notice board & announcements module
- [ ] Fee and payment management
- [ ] Library management system
- [ ] Parent portal for monitoring student progress
- [ ] Email notifications for results and attendance alerts

## Contact

Have questions or want to contribute? Reach out:

- **GitHub**: https://github.com/AshwinMaharjan
- **Email**: maharjan.ashwin098@gmail.com

---

> Built with 🎓 and PHP. MyCampus Hub — one platform for your entire campus.
>>>>>>> 0dc93962c238aa94f92f2abe36a5fb6823d3485d

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prime College Management System</title>
  <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> -->
       <link rel="stylesheet" href="css/all.min.css" />
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: #333;
      overflow-x: hidden;
    }

    .mobile-menu {
      display: none;
      color: white;
      font-size: 1.5rem;
      cursor: pointer;
    }

    /* Hero Section */
    .hero {
      position: relative;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #263576 0%, #1a2659 100%);
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.05" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
      background-size: cover;
    }

    .hero-content {
      position: relative;
      text-align: center;
      color: white;
      z-index: 1;
      padding: 2rem;
      animation: fadeInUp 1s ease;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .hero h1 {
      font-size: 3.5rem;
      margin-bottom: 1rem;
      font-weight: 700;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    .hero .subtitle {
      font-size: 1.5rem;
      margin-bottom: 2rem;
      opacity: 0.95;
    }

    .hero-buttons {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn {
      padding: 12px 32px;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      display: inline-block;
      border: 2px solid transparent;
    }

    .btn-primary {
      background: white;
      color: #263576;
    }

    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(255, 255, 255, 0.3);
    }

    .btn-secondary {
      background: transparent;
      color: white;
      border-color: white;
    }

    .btn-secondary:hover {
      background: white;
      color: #263576;
      transform: translateY(-3px);
    }

    /* Section Styles */
    section {
      padding: 80px 5%;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .section-title {
      text-align: center;
      margin-bottom: 3rem;
    }

    .section-title h2 {
      font-size: 2.5rem;
      color: #263576;
      margin-bottom: 0.5rem;
      position: relative;
      display: inline-block;
    }

    .section-title h2::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 4px;
      background: #263576;
      border-radius: 2px;
    }

    .section-title p {
      color: #666;
      font-size: 1.1rem;
      margin-top: 1.5rem;
    }

    /* About Section */
    .about {
      background: #f8f9fa;
    }

    .about-content {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 3rem;
      align-items: center;
    }

    .about-text p {
      font-size: 1.1rem;
      line-height: 1.8;
      color: #555;
      margin-bottom: 1.5rem;
      text-align: justify;
    }

    .about-image {
      position: relative;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
    }

    .about-image img {
      width: 100%;
      height: auto;
      display: block;
    }

    .about-image::before {
      content: '';
      position: absolute;
      top: 20px;
      left: 20px;
      right: -20px;
      bottom: -20px;
      background: #263576;
      border-radius: 15px;
      z-index: -1;
    }

    /* Role Cards */
    .roles-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 2rem;
      margin-top: 3rem;
    }

    .role-card {
      background: white;
      padding: 2rem;
      border-radius: 15px;
      text-align: center;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }

    .role-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 15px 40px rgba(38, 53, 118, 0.15);
      border-color: #263576;
    }

    .role-icon {
      font-size: 3rem;
      color: #263576;
      margin-bottom: 1rem;
    }

    .role-card h3 {
      color: #263576;
      margin-bottom: 1rem;
      font-size: 1.5rem;
    }

    .role-card p {
      color: #666;
      line-height: 1.6;
    }

    /* Features Section */
    .features {
      background: linear-gradient(135deg, #263576 0%, #1a2659 100%);
      color: white;
    }

    .features .section-title h2 {
      color: white;
    }

    .features .section-title h2::after {
      background: white;
    }

    .features .section-title p {
      color: rgba(255, 255, 255, 0.9);
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
      margin-top: 3rem;
    }

    .feature-item {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 15px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      transition: all 0.3s ease;
    }

    .feature-item:hover {
      background: rgba(255, 255, 255, 0.15);
      transform: translateY(-5px);
    }

    .feature-item i {
      font-size: 2.5rem;
      margin-bottom: 1rem;
      margin-left: 150px;
      color: white;
    }

    .feature-item h3 {
      margin-bottom: 0.5rem;
      font-size: 1.3rem;
    }

    .feature-item p {
      opacity: 0.9;
      line-height: 1.6;
    }
   /* Smooth Scroll */
    html {
      scroll-behavior: smooth;
    }
  </style>
</head>
<body>
  <!-- Hero Section -->
  <section class="hero" id="home">
    <div class="hero-content">
      <h1>MyCampus Hub</h1>
      <p class="subtitle">A Digital Solution for Campus Life</p>
      <div class="hero-buttons">
        <a href="login.php" class="btn btn-primary">Login Now</a>
        <a href="#about" class="btn btn-secondary">Learn More</a>
      </div>
    </div>
  </section>

  <!-- About Section -->
  <section class="about" id="about">
    <div class="container">
      <div class="section-title">
        <h2>About PCMS</h2>
        <p>Empowering Education Through Technology</p>
      </div>
      <div class="about-content">
        <div class="about-text">
          <p>
            MyCampus Hub is a comprehensive digital platform that seamlessly connects students, faculty, and staff for efficient academic management. From attendance tracking, marks entry, leave requests, to subject tracking, PCMS streamlines every academic process for a smarter and more organized education experience. By integrating all key functions in one platform, Prime College ensures that students, teachers, and administrators can access real-time information, stay informed, and manage their academic activities effortlessly. With PCMS, the college embraces the future of education management, delivering a smooth and efficient experience for the entire academic community.
          </p>
        </div>
        <div class="about-image">
          <svg viewBox="0 0 500 400" xmlns="http://www.w3.org/2000/svg">
            <rect width="500" height="400" fill="#263576"/>
            <circle cx="250" cy="200" r="80" fill="#fbff29" opacity="0.3"/>
            <rect x="180" y="150" width="140" height="100" rx="10" fill="white" opacity="0.9"/>
            <line x1="200" y1="180" x2="280" y2="180" stroke="#263576" stroke-width="4"/>
            <line x1="200" y1="200" x2="280" y2="200" stroke="#263576" stroke-width="4"/>
            <line x1="200" y1="220" x2="260" y2="220" stroke="#263576" stroke-width="4"/>
            <circle cx="100" cy="100" r="20" fill="#fbff29" opacity="0.5"/>
            <circle cx="400" cy="300" r="30" fill="#fbff29" opacity="0.4"/>
          </svg>
        </div>
      </div>
    </div>
  </section>

  <!-- Role-Based Access Section -->
  <section class="roles" id="roles">
    <div class="container">
      <div class="section-title">
        <h2>Access for Every Role</h2>
        <p>Tailored dashboards for seamless management</p>
      </div>
      <div class="roles-grid">
        <div class="role-card">
          <div class="role-icon">
            <i class="fas fa-user-graduate"></i>
          </div>
          <h3>Student Portal</h3>
          <p>View courses, marks, attendance, and receive important notifications instantly.</p>
        </div>
        <div class="role-card">
          <div class="role-icon">
            <i class="fas fa-chalkboard-teacher"></i>
          </div>
          <h3>Faculty Portal</h3>
          <p>Manage subjects, enter marks, track student progress, and communicate efficiently.</p>
        </div>
        <div class="role-card">
          <div class="role-icon">
            <i class="fas fa-user-tie"></i>
          </div>
          <h3>Non-Faculty Portal</h3>
          <p>Handle administrative tasks, maintain records, and generate comprehensive reports.</p>
        </div>
        <div class="role-card">
          <div class="role-icon">
            <i class="fas fa-user-shield"></i>
          </div>
          <h3>Admin Portal</h3>
          <p>Manage users, handle approvals, configure settings, and view detailed analytics.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section class="features" id="features">
    <div class="container">
      <div class="section-title">
        <h2>Why Choose PCMS?</h2>
        <p>Smart Management for Smarter Learning</p>
      </div>
      <div class="features-grid">
        <div class="feature-item">
          <i class="fas fa-lock"></i>
          <h3>Secure Role-Based Login</h3>
          <p>Protected access with encrypted authentication for all user types.</p>
        </div>
        <div class="feature-item">
          <i class="fas fa-chart-line"></i>
          <h3>Real-Time Academic Insights</h3>
          <p>Live dashboards with up-to-date performance metrics and analytics.</p>
        </div>
        <div class="feature-item">
          <i class="fas fa-bell"></i>
          <h3>Notification System</h3>
          <p>Instant alerts for important updates, deadlines, and announcements.</p>
        </div>
        <div class="feature-item">
          <i class="fas fa-edit"></i>
          <h3>Easy Internal Marks Entry</h3>
          <p>Streamlined interface for quick and accurate grade submission.</p>
        </div>
<div class="feature-item">
  <i class="fas fa-user-check"></i>
  <h3>Attendance Management</h3>
  <p>Track student attendance efficiently with real-time updates and records.</p>
</div>
<div class="feature-item">
  <i class="fas fa-file-alt"></i>
  <h3>Leave Request System</h3>
  <p>Simple online process for students and staff to apply and manage leave requests.</p>
</div>
    </div>
  </section>

 <script>
    function toggleMenu() {
      const navLinks = document.getElementById('navLinks');
      navLinks.classList.toggle('active');
    }

    // Close mobile menu when clicking on a link
    document.querySelectorAll('.nav-links a').forEach(link => {
      link.addEventListener('click', () => {
        document.getElementById('navLinks').classList.remove('active');
      });
    });

    // Add scroll effect to navigation
    window.addEventListener('scroll', () => {
      const nav = document.querySelector('nav');
      if (window.scrollY > 100) {
        nav.style.background = 'rgba(38, 53, 118, 1)';
      } else {
        nav.style.background = 'rgba(38, 53, 118, 0.95)';
      }
    });
  </script>

</body>
</html>
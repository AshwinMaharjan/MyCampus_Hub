<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Header</title>
  <style>
    /* Reset default margins */
    body {
      margin: 0;
      font-family: Arial, sans-serif;
    }

    /* HEADER BASE */
    .main-header {
      background-color: #263576;
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 50px;
      height: 70px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 1000;
    }

    /* LOGO */
    .logo-container {
      display: flex;
      align-items: center;
    }

    .logo-container .logo {
      height: 50px;
      width: auto;
    }

    /* NAV LINKS */
    .nav-links {
      display: flex;
      align-items: center;
      gap: 25px;
    }

    .nav-btn {
      color: white;
      text-decoration: none;
      padding: 8px 16px;
      border: 2px solid white;
      border-radius: 5px;
      transition: 0.3s;
      font-size: 16px;
    }

    .nav-btn:hover {
      background-color: white;
      color: #263576;
    }

    /* Responsive tweaks */
    @media (max-width: 768px) {
      .main-header {
        flex-direction: column;
        height: auto;
        padding: 15px 30px;
      }

      .nav-links {
        flex-wrap: wrap;
        justify-content: center;
        margin-top: 10px;
        gap: 15px;
      }

      .nav-btn {
        padding: 6px 12px;
        font-size: 15px;
      }
    }
  </style>
</head>
<body>

  <header class="main-header">
    <div class="logo-container">
      <a href="homepage.php">
        <img src="images/prime_logo.jpg" alt="Prime Logo" class="logo">
      </a>
    </div>
    <nav class="nav-links">
      <a href="#home" class="nav-btn">Home</a>
      <a href="#about" class="nav-btn">About</a>
      <a href="#roles" class="nav-btn">Access</a>
      <a href="#features" class="nav-btn">Features</a>
      <a href="login.php" class="nav-btn">Login</a>
      <a href="register.php" class="nav-btn">Register</a>
    </nav>
  </header>
</body>
</html>

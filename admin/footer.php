<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Footer</title>
  <link rel="stylesheet" href="../css/footer.css" />
</head>
<body>

<footer class="main-footer">
  <div class="footer-content">
    <div class="footer-left">
      <a href="homepage.php" class="logo-link">
        <img src="images/prime_logo.jpg" alt="Prime Logo" class="logo" />
      </a>
      <p>Khusibun, Nayabazar, Kathmandu</p>
      <p>01-4961690, 01-4970072</p>
      <p>info@prime.edu.np, admissions@prime.edu.np</p>
      <p>You can visit Prime College between<br> 7:00 a.m. - 5:00 p.m. for more information.</p>

    </div>

    <div class="footer-right">
      <h2>Quick Links</h2>
      <ul class="quick-links">
        <li><a href="homepage.php">Home</a></li>
        <li><a href="about.php">About Us</a></li>
        <li><a href="courses.php">Courses</a></li>
        <li><a href="notice.php">Notices</a></li>
        <li><a href="contact.php">Contact</a></li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
  <h2>Join Our Community</h2>
      <div class="social-icons">
        <a href="https://www.facebook.com/primecollegenp" target="_blank" rel="noopener noreferrer">
          <img src="images/logo/facebook.png" alt="Facebook" />
        </a>
        <a href="https://www.youtube.com/user/primecollege1" target="_blank" rel="noopener noreferrer">
          <img src="images/logo/youtube.png" alt="YouTube" />
        </a>
        <a href="https://www.instagram.com/prime__college/?hl=en" target="_blank" rel="noopener noreferrer">
          <img src="images/logo/instagram.png" alt="Instagram" />
        </a>
        <a href="https://www.linkedin.com/school/prime-college-nepal/" target="_blank" rel="noopener noreferrer">
          <img src="images/logo/linkedin.png" alt="LinkedIn" />
        </a>
        <a href="https://www.tiktok.com/@prime_college" target="_blank" rel="noopener noreferrer">
          <img src="images/logo/tiktok.png" alt="TikTok" />
        </a>
      </div>

  </div>

  <button id="backToTopBtn" title="Back to top">↑</button>

  <script>
    const backToTopBtn = document.getElementById("backToTopBtn");

    window.onscroll = function () {
      backToTopBtn.style.display = (window.scrollY > 100) ? "block" : "none";
    };

    backToTopBtn.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  </script>
</footer>

</body>
</html>

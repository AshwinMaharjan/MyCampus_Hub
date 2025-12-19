<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Prime College Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            background: #f8f9fa;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #263576 0%, #1a2659 100%);
            color: white;
            padding: 100px 5% 60px;
            text-align: center;
            margin-top: 70px;
        }

        .page-header h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            animation: fadeInDown 0.8s ease;
        }

        .page-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: -40px auto 80px;
            padding: 0 5%;
            position: relative;
            z-index: 1;
        }

        /* Contact Grid */
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        /* Contact Form */
        .contact-form-section {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .contact-form-section h2 {
            color: #263576;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .contact-form-section .subtitle {
            color: #666;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #263576;
            box-shadow: 0 0 0 3px rgba(38, 53, 118, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .submit-btn {
            background: #263576;
            color: white;
            padding: 14px 40px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .submit-btn:hover {
            background: #1a2659;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(38, 53, 118, 0.3);
        }

        /* Contact Info */
        .contact-info-section {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .info-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .info-card h3 {
            color: #263576;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card h3 i {
            font-size: 1.8rem;
        }

        .info-item {
            display: flex;
            align-items: start;
            gap: 15px;
            margin-bottom: 1.5rem;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .info-item:hover {
            background: #e8eaf0;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        .info-item i {
            color: #263576;
            font-size: 1.5rem;
            margin-top: 3px;
        }

        .info-content h4 {
            color: #263576;
            margin-bottom: 0.3rem;
            font-size: 1.1rem;
        }

        .info-content p {
            color: #666;
            margin: 0;
        }

        .info-content a {
            color: #263576;
            text-decoration: none;
            transition: color 0.3s;
        }

        .info-content a:hover {
            color: #1a2659;
            text-decoration: underline;
        }

        /* Map Section */
        .map-section {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 3rem;
        }

        .map-section h2 {
            color: #263576;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            text-align: center;
        }

        .map-container {
            width: 100%;
            height: 400px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #e0e0e0;
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Social Links */
        .social-section {
            background: linear-gradient(135deg, #263576 0%, #1a2659 100%);
            padding: 3rem;
            border-radius: 15px;
            text-align: center;
            color: white;
        }

        .social-section h2 {
            margin-bottom: 1rem;
            font-size: 2rem;
        }

        .social-section p {
            opacity: 0.9;
            margin-bottom: 2rem;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            font-size: 1.8rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .social-link:hover {
            background: white;
            color: #263576;
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        /* Success Message */
        .success-message {
            display: none;
            background: #4CAF50;
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .success-message.show {
            display: block;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header {
                padding: 80px 5% 40px;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .contact-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .contact-form-section,
            .map-section {
                padding: 2rem;
            }

            .info-card {
                padding: 1.5rem;
            }

            .map-container {
                height: 300px;
            }

            .social-links {
                gap: 1.5rem;
            }

            .social-link {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <!-- Page Header -->
    <div class="page-header">
        <h1>Contact Us</h1>
        <p>We're here to help! Get in touch with Prime College Management System</p>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        
        <!-- Contact Grid -->
        <div class="contact-grid">
            
            <!-- Contact Form -->
            <div class="contact-form-section">
                <h2>Send us a Message</h2>
                <p class="subtitle">Fill out the form below and we'll get back to you as soon as possible</p>
                
                <div class="success-message" id="successMessage">
                    <i class="fas fa-check-circle"></i> Thank you! Your message has been sent successfully.
                </div>

                <form id="contactForm" method="POST" action="process_contact.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First Name *</label>
                            <input type="text" id="firstName" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name *</label>
                            <input type="text" id="lastName" name="last_name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <select id="subject" name="subject" required>
                            <option value="">Select a subject</option>
                            <option value="general">General Inquiry</option>
                            <option value="technical">Technical Support</option>
                            <option value="admission">Admission Information</option>
                            <option value="feedback">Feedback</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" required placeholder="Tell us how we can help you..."></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>

            <!-- Contact Info -->
            <div class="contact-info-section">
                
                <!-- Contact Details -->
                <div class="info-card">
                    <h3><i class="fas fa-address-card"></i> Contact Information</h3>
                    
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="info-content">
                            <h4>Address</h4>
                            <p>Prime College Campus<br>Kathmandu, Nepal</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <div class="info-content">
                            <h4>Phone</h4>
                            <p><a href="tel:+977-1-XXXXXXX">+977-1-XXXXXXX</a></p>
                            <p><a href="tel:+977-9XXXXXXXXX">+977-9XXXXXXXXX</a></p>
                        </div>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <div class="info-content">
                            <h4>Email</h4>
                            <p><a href="mailto:info@primecollege.edu.np">info@primecollege.edu.np</a></p>
                            <p><a href="mailto:support@primecollege.edu.np">support@primecollege.edu.np</a></p>
                        </div>
                    </div>
                </div>

                <!-- Office Hours -->
                <div class="info-card">
                    <h3><i class="fas fa-clock"></i> Office Hours</h3>
                    
                    <div class="info-item">
                        <i class="fas fa-calendar-day"></i>
                        <div class="info-content">
                            <h4>Weekdays</h4>
                            <p>Sunday - Friday: 7:00 AM - 5:00 PM</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-calendar-times"></i>
                        <div class="info-content">
                            <h4>Saturday</h4>
                            <p>Closed</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Map Section -->
        <div class="map-section">
            <h2><i class="fas fa-map-marked-alt"></i> Find Us on Map</h2>
            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3532.4857974748254!2d85.31681631506258!3d27.700769982791804!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39eb190f0c8e2b79%3A0x38c0d319f38be7c!2sKathmandu%2C%20Nepal!5e0!3m2!1sen!2s!4v1234567890123!5m2!1sen!2s" 
                    allowfullscreen="" 
                    loading="lazy">
                </iframe>
            </div>
        </div>

        <!-- Social Media Section -->
        <div class="social-section">
            <h2>Connect With Us</h2>
            <p>Follow us on social media for updates and news</p>
            <div class="social-links">
                <a href="#" class="social-link" title="Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="#" class="social-link" title="Twitter">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="#" class="social-link" title="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="#" class="social-link" title="LinkedIn">
                    <i class="fab fa-linkedin-in"></i>
                </a>
                <a href="#" class="social-link" title="YouTube">
                    <i class="fab fa-youtube"></i>
                </a>
            </div>
        </div>

    </div>

    <script>
        // Form submission handler
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show success message
            const successMessage = document.getElementById('successMessage');
            successMessage.classList.add('show');
            
            // Reset form
            this.reset();
            
            // Hide success message after 5 seconds
            setTimeout(() => {
                successMessage.classList.remove('show');
            }, 5000);
            
            // In production, you would send the form data to your PHP backend
            // const formData = new FormData(this);
            // fetch('process_contact.php', {
            //     method: 'POST',
            //     body: formData
            // }).then(response => response.json())
            //   .then(data => {
            //       if(data.success) {
            //           successMessage.classList.add('show');
            //           this.reset();
            //       }
            //   });
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.info-card, .contact-form-section, .map-section').forEach(el => {
            observer.observe(el);
        });
    </script>

</body>
</html>
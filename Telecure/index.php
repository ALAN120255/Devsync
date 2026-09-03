<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeleCure - Your Health, Anywhere</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/animations.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="home-page">

<!-- Floating particles background -->
<div class="particles-container" id="particles"></div>

<!-- Navigation -->
<nav class="navbar" id="navbar">
    <div class="nav-container">
        <div class="logo">
            <div class="logo-icon">
                <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="30" cy="30" r="28" fill="url(#logoGrad)" />
                    <rect x="25" y="15" width="10" height="30" rx="3" fill="white"/>
                    <rect x="15" y="25" width="30" height="10" rx="3" fill="white"/>
                    <circle cx="30" cy="30" r="4" fill="url(#logoGrad)"/>
                    <defs>
                        <linearGradient id="logoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#1565C0"/>
                            <stop offset="100%" style="stop-color:#42A5F5"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <span class="logo-text">Tele<span class="logo-accent">Cure</span></span>
        </div>
        <ul class="nav-links">
            <li><a href="#features">Features</a></li>
            <li><a href="#doctors">Doctors</a></li>
            <li><a href="#how-it-works">How It Works</a></li>
            <li><a href="#testimonials">Reviews</a></li>
        </ul>
        <div class="nav-actions">
            <a href="login.php" class="btn-outline">Sign In</a>
            <a href="register.php" class="btn-primary">Get Started</a>
        </div>
        <button class="hamburger" id="hamburger">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge animate-fade-in">
            <i class="fas fa-shield-virus"></i> Trusted by 50,000+ patients across South Africa
        </div>
        <h1 class="hero-title animate-slide-up">
            Healthcare<br>
            <span class="gradient-text">Without Boundaries</span>
        </h1>
        <p class="hero-subtitle animate-slide-up delay-1">
            Connect with certified doctors, book appointments, receive prescriptions,<br>
            and consult via video — all from the comfort of your home.
        </p>
        <div class="hero-actions animate-slide-up delay-2">
            <a href="register.php?role=patient" class="btn-hero-primary">
                <i class="fas fa-user-plus"></i> Register as Patient
            </a>
            <a href="register.php?role=doctor" class="btn-hero-secondary">
                <i class="fas fa-stethoscope"></i> Join as Doctor
            </a>
        </div>
        <div class="hero-stats animate-fade-in delay-3">
            <div class="stat-item">
                <span class="stat-number" data-target="500">0</span><span>+</span>
                <span class="stat-label">Doctors</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
                <span class="stat-number" data-target="50000">0</span><span>+</span>
                <span class="stat-label">Patients</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
                <span class="stat-number" data-target="98">0</span><span>%</span>
                <span class="stat-label">Satisfaction</span>
            </div>
        </div>
    </div>
    <div class="hero-visual animate-float">
        <div class="hero-card-wrapper">
            <div class="floating-card card-1">
                <i class="fas fa-video"></i>
                <span>Video Consult</span>
                <small>Now Available</small>
            </div>
            <div class="floating-card card-2">
                <i class="fas fa-prescription-bottle-medical"></i>
                <span>Prescription</span>
                <small>Issued instantly</small>
            </div>
            <div class="floating-card card-3">
                <i class="fas fa-calendar-check"></i>
                <span>Appointment</span>
                <small>Today 3:00 PM</small>
            </div>
            <div class="hero-phone-mockup">
                <div class="phone-inner">
                    <div class="phone-header">
                        <div class="phone-logo-mini">TC</div>
                        <span>TeleCure</span>
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="phone-chat">
                        <div class="chat-bubble doctor-bubble">
                            <img src="https://ui-avatars.com/api/?name=Dr+Sarah&background=1565C0&color=fff&size=32" alt="Doctor">
                            <div class="bubble">Good morning! How are you feeling today?</div>
                        </div>
                        <div class="chat-bubble patient-bubble">
                            <div class="bubble">Better thanks to the prescription!</div>
                        </div>
                        <div class="chat-bubble doctor-bubble">
                            <img src="https://ui-avatars.com/api/?name=Dr+Sarah&background=1565C0&color=fff&size=32" alt="Doctor">
                            <div class="bubble">Great! Let's schedule a follow-up. 🎉</div>
                        </div>
                    </div>
                    <div class="phone-actions">
                        <button class="phone-btn"><i class="fas fa-video"></i></button>
                        <div class="phone-input">Type a message...</div>
                        <button class="phone-btn send"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features" id="features">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">What We Offer</span>
            <h2>Everything You Need for<br><span class="gradient-text">Complete Healthcare</span></h2>
        </div>
        <div class="features-grid">
            <div class="feature-card" data-aos="fade-up" data-delay="0">
                <div class="feature-icon" style="--icon-color: #1565C0">
                    <i class="fas fa-video"></i>
                </div>
                <h3>Video Consultation</h3>
                <p>High-quality HD video calls with your doctor. No travel required — consult from your bedroom, office, or anywhere.</p>
                <a href="register.php" class="feature-link">Get Started <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="feature-card" data-aos="fade-up" data-delay="100">
                <div class="feature-icon" style="--icon-color: #0288D1">
                    <i class="fas fa-comments"></i>
                </div>
                <h3>Instant Messaging</h3>
                <p>Real-time chat with your doctor or patient. Send messages, images, and medical documents securely.</p>
                <a href="register.php" class="feature-link">Get Started <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="feature-card" data-aos="fade-up" data-delay="200">
                <div class="feature-icon" style="--icon-color: #0097A7">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3>Easy Appointments</h3>
                <p>Book appointments in seconds. Choose your doctor, pick a time slot, and get instant confirmation.</p>
                <a href="register.php" class="feature-link">Get Started <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="feature-card" data-aos="fade-up" data-delay="300">
                <div class="feature-icon" style="--icon-color: #1565C0">
                    <i class="fas fa-file-medical"></i>
                </div>
                <h3>Digital Prescriptions</h3>
                <p>Receive and manage your prescriptions digitally. Share with any pharmacy in South Africa instantly.</p>
                <a href="register.php" class="feature-link">Get Started <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="feature-card" data-aos="fade-up" data-delay="400">
                <div class="feature-icon" style="--icon-color: #0288D1">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>Specialist Access</h3>
                <p>Connect with specialists across multiple fields. Cardiologists, pediatricians, dermatologists and more.</p>
                <a href="register.php" class="feature-link">Get Started <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="feature-card" data-aos="fade-up" data-delay="500">
                <div class="feature-icon" style="--icon-color: #0097A7">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Secure & Private</h3>
                <p>All data encrypted end-to-end. Your health information stays private and fully POPIA compliant.</p>
                <a href="register.php" class="feature-link">Get Started <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- Doctors Section -->
<section class="doctors-section" id="doctors">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Our Team</span>
            <h2>Meet Our <span class="gradient-text">Top Doctors</span></h2>
        </div>
        <div class="doctors-grid">
            <?php
            $db = getDB();
            $doctors = $db->query("SELECT u.*, dp.specialization, dp.rating, dp.total_reviews, dp.consultation_fee 
                                   FROM users u 
                                   JOIN doctor_profiles dp ON u.id = dp.user_id 
                                   WHERE u.role='doctor' AND dp.is_verified=1 LIMIT 6")->fetchAll();
            
            $defaultDoctors = [
                ['full_name'=>'Dr. Sarah Johnson','specialization'=>'Cardiology','rating'=>4.8,'total_reviews'=>124,'consultation_fee'=>850,'profile_photo'=>'https://randomuser.me/api/portraits/women/68.jpg','role'=>'doctor'],
                ['full_name'=>'Dr. Michael Osei','specialization'=>'General Practice','rating'=>4.6,'total_reviews'=>98,'consultation_fee'=>600,'profile_photo'=>'https://randomuser.me/api/portraits/men/32.jpg','role'=>'doctor'],
                ['full_name'=>'Dr. Priya Naidoo','specialization'=>'Pediatrics','rating'=>4.9,'total_reviews'=>156,'consultation_fee'=>700,'profile_photo'=>'https://randomuser.me/api/portraits/women/44.jpg','role'=>'doctor'],
            ];
            
            $displayDoctors = !empty($doctors) ? $doctors : $defaultDoctors;
            
            foreach ($displayDoctors as $doc):
                $doc['role'] = 'doctor';
                $avatarUrl = avatarUrl($doc, 100);
            ?>
            <div class="doctor-card">
                <div class="doctor-avatar">
                    <img src="<?= $avatarUrl ?>" alt="<?= htmlspecialchars($doc['full_name']) ?>">
                    <div class="doctor-badge-verified"><i class="fas fa-check"></i></div>
                </div>
                <div class="doctor-info">
                    <h3><?= htmlspecialchars($doc['full_name']) ?></h3>
                    <span class="doctor-specialization"><?= htmlspecialchars($doc['specialization'] ?? 'General') ?></span>
                    <div class="doctor-rating">
                        <?php for($i=1;$i<=5;$i++): ?>
                            <i class="fas fa-star <?= $i <= round($doc['rating']) ? 'active' : '' ?>"></i>
                        <?php endfor; ?>
                        <span><?= number_format($doc['rating'], 1) ?> (<?= $doc['total_reviews'] ?? 0 ?> reviews)</span>
                    </div>
                    <div class="doctor-fee">
                        <i class="fas fa-coins"></i> From R<?= number_format($doc['consultation_fee'] ?? 0, 0) ?>/consult
                    </div>
                    <a href="register.php" class="btn-doctor-book">Book Appointment</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="how-it-works" id="how-it-works">
    <div class="container">
        <div class="section-header light">
            <span class="section-tag">Process</span>
            <h2>How <span>TeleCure</span> Works</h2>
        </div>
        <div class="steps-container">
            <div class="step-line"></div>
            <div class="step">
                <div class="step-num">01</div>
                <div class="step-icon"><i class="fas fa-user-plus"></i></div>
                <h3>Create Account</h3>
                <p>Register as a patient or doctor. Verify your identity and set up your profile in minutes.</p>
            </div>
            <div class="step">
                <div class="step-num">02</div>
                <div class="step-icon"><i class="fas fa-search"></i></div>
                <h3>Find a Doctor</h3>
                <p>Browse our network of verified doctors by specialty, rating, availability and consultation fee.</p>
            </div>
            <div class="step">
                <div class="step-num">03</div>
                <div class="step-icon"><i class="fas fa-calendar-check"></i></div>
                <h3>Book & Consult</h3>
                <p>Choose a time slot, book your appointment, then consult via chat, call, or video.</p>
            </div>
            <div class="step">
                <div class="step-num">04</div>
                <div class="step-icon"><i class="fas fa-prescription"></i></div>
                <h3>Get Prescription</h3>
                <p>Receive your digital prescription and medical notes directly to your dashboard.</p>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="testimonials" id="testimonials">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Reviews</span>
            <h2>What Patients <span class="gradient-text">Are Saying</span></h2>
        </div>
        <div class="testimonials-slider" id="testimonialSlider">
            <div class="testimonial active">
                <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                <p>"TeleCure changed my life. As someone with mobility issues, getting to a clinic was always a challenge. Now I can see my cardiologist from home every week."</p>
                <div class="testimonial-author">
                    <img src="https://ui-avatars.com/api/?name=Thabo+M&background=E3F2FD&color=1565C0&size=50" alt="Thabo">
                    <div>
                        <strong>Thabo Motsepe</strong>
                        <span>Patient, Johannesburg</span>
                    </div>
                </div>
            </div>
            <div class="testimonial">
                <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                <p>"As a doctor, TeleCure has allowed me to reach patients in rural areas I could never serve before. The platform is intuitive and the video quality is excellent."</p>
                <div class="testimonial-author">
                    <img src="https://ui-avatars.com/api/?name=Dr+Lindiwe&background=E3F2FD&color=1565C0&size=50" alt="Dr Lindiwe">
                    <div>
                        <strong>Dr. Lindiwe Dlamini</strong>
                        <span>General Practitioner, Durban</span>
                    </div>
                </div>
            </div>
            <div class="testimonial">
                <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                <p>"The prescription feature is fantastic! My doctor sent it digitally and I picked up my medication within the hour. No more waiting in queues."</p>
                <div class="testimonial-author">
                    <img src="https://ui-avatars.com/api/?name=Zanele+K&background=E3F2FD&color=1565C0&size=50" alt="Zanele">
                    <div>
                        <strong>Zanele Khumalo</strong>
                        <span>Patient, Cape Town</span>
                    </div>
                </div>
            </div>
            <div class="testimonial-dots">
                <span class="dot active" data-index="0"></span>
                <span class="dot" data-index="1"></span>
                <span class="dot" data-index="2"></span>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="cta-content">
        <h2>Ready to Transform Your Healthcare?</h2>
        <p>Join thousands of patients and doctors already using TeleCure</p>
        <div class="cta-actions">
            <a href="register.php?role=patient" class="btn-cta-white">
                <i class="fas fa-heartbeat"></i> I'm a Patient
            </a>
            <a href="register.php?role=doctor" class="btn-cta-outline">
                <i class="fas fa-stethoscope"></i> I'm a Doctor
            </a>
        </div>
    </div>
    <div class="cta-wave">
        <svg viewBox="0 0 1440 100" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,50 C360,100 1080,0 1440,50 L1440,100 L0,100 Z" fill="white"/>
        </svg>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="logo">
                    <div class="logo-icon small">
                        <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="30" cy="30" r="28" fill="url(#footerGrad)" />
                            <rect x="25" y="15" width="10" height="30" rx="3" fill="white"/>
                            <rect x="15" y="25" width="30" height="10" rx="3" fill="white"/>
                            <defs>
                                <linearGradient id="footerGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#1565C0"/>
                                    <stop offset="100%" style="stop-color:#42A5F5"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                    <span class="logo-text">Tele<span class="logo-accent">Cure</span></span>
                </div>
                <p>Modern telemedicine platform connecting patients with certified healthcare professionals across South Africa.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="register.php">Register</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#doctors">Our Doctors</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Services</h4>
                <ul>
                    <li><a href="#">Video Consultation</a></li>
                    <li><a href="#">Chat with Doctor</a></li>
                    <li><a href="#">Appointment Booking</a></li>
                    <li><a href="#">Digital Prescriptions</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contact</h4>
                <ul>
                    <li><i class="fas fa-phone"></i> +27 11 000 0000</li>
                    <li><i class="fas fa-envelope"></i> hello@telecure.co.za</li>
                    <li><i class="fas fa-map-marker-alt"></i> Johannesburg, SA</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> TeleCure. All rights reserved. | POPIA Compliant | <a href="#">Privacy Policy</a> | <a href="#">Terms</a></p>
        </div>
    </div>
</footer>

<script src="js/main.js"></script>
<script src="js/animations.js"></script>
</body>
</html>

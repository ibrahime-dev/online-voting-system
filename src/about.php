<?php
/**
 * About Page - Online Voting System
 * Wollo University - Integrated Project
 */
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Clean white background for about */
        body {
            background-color: #ffffff !important;
        }
        
        .hero {
            background: #f7fafc !important;
            color: #1a202c !important;
            border: 1px solid #e2e8f0 !important;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo-container">
                    <img src="../assets/images/logo.png" alt="<?php echo SITE_NAME; ?> Logo" class="nav-logo-img">
                </div>
                <h1 class="nav-logo"><?php echo SITE_NAME; ?></h1>
                <ul class="nav-menu">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php" class="active">About</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <h1>About Our Voting System</h1>
                <p>Secure, Transparent, and Efficient Electronic Voting Platform</p>
                <p class="subtitle">Wollo University - Informatics College</p>
            </div>
        </section>

        <!-- Project Overview -->
        <section class="features">
            <div class="container">
                <div class="dashboard-section">
                    <h2>📚 Project Overview</h2>
                    <div class="about-content">
                        <p>This Online Voting System is an integrated project developed by 3rd Year Information Technology students at Wollo University's Informatics College. The project combines three core courses:</p>
                        
                        <div class="course-grid">
                            <div class="course-card">
                                <h3>🌐 Internet Programming (IP)</h3>
                                <p>Dynamic web development using PHP, HTML5, CSS3, and JavaScript for creating interactive user interfaces and server-side functionality.</p>
                            </div>
                            <div class="course-card">
                                <h3>🗄️ Advanced Database (ADB)</h3>
                                <p>Normalized relational database design using MySQL with proper indexing, triggers, views, and stored procedures for data integrity.</p>
                            </div>
                            <div class="course-card">
                                <h3>📋 System Analysis & Design (SAD)</h3>
                                <p>Comprehensive software development documentation including SRS, system design, testing documentation, and project management.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Key Features -->
                <div class="dashboard-section">
                    <h2>🚀 Key Features</h2>
                    <div class="features-grid">
                        <div class="feature-card">
                            <h3>🔐 Secure Authentication</h3>
                            <p>Multi-level user authentication with role-based access control, password hashing, and session management.</p>
                        </div>
                        <div class="feature-card">
                            <h3>🗳️ Electronic Voting</h3>
                            <p>Secure ballot casting with encryption, vote verification, and tamper-proof vote storage.</p>
                        </div>
                        <div class="feature-card">
                            <h3>👥 User Management</h3>
                            <p>Comprehensive voter registration, verification system, and profile management with admin oversight.</p>
                        </div>
                        <div class="feature-card">
                            <h3>📊 Election Management</h3>
                            <p>Complete election lifecycle management from creation to result publication with candidate management.</p>
                        </div>
                        <div class="feature-card">
                            <h3>📈 Real-time Results</h3>
                            <p>Automated vote counting, real-time result generation, and comprehensive reporting system.</p>
                        </div>
                        <div class="feature-card">
                            <h3>🔍 Audit Trail</h3>
                            <p>Complete activity logging, audit trails, and transparency features for election integrity.</p>
                        </div>
                    </div>
                </div>

                <!-- Technology Stack -->
                <div class="dashboard-section">
                    <h2>💻 Technology Stack</h2>
                    <div class="tech-grid">
                        <div class="tech-category">
                            <h3>Backend Technologies</h3>
                            <ul>
                                <li><strong>PHP 7.4+:</strong> Server-side programming language</li>
                                <li><strong>MySQL 5.7+:</strong> Relational database management</li>
                                <li><strong>PDO:</strong> Database abstraction layer for secure queries</li>
                                <li><strong>BCrypt:</strong> Password hashing algorithm</li>
                            </ul>
                        </div>
                        <div class="tech-category">
                            <h3>Frontend Technologies</h3>
                            <ul>
                                <li><strong>HTML5:</strong> Modern semantic markup</li>
                                <li><strong>CSS3:</strong> Responsive design and animations</li>
                                <li><strong>JavaScript (ES6):</strong> Client-side interactivity</li>
                                <li><strong>Responsive Design:</strong> Mobile-first approach</li>
                            </ul>
                        </div>
                        <div class="tech-category">
                            <h3>Security Features</h3>
                            <ul>
                                <li><strong>HTTPS:</strong> Encrypted data transmission</li>
                                <li><strong>Session Security:</strong> Secure session management</li>
                                <li><strong>SQL Injection Prevention:</strong> Prepared statements</li>
                                <li><strong>CSRF Protection:</strong> Cross-site request forgery prevention</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Security & Integrity -->
                <div class="dashboard-section">
                    <h2>🛡️ Security & Vote Integrity</h2>
                    <div class="security-features">
                        <div class="security-item">
                            <h4>🔒 Data Protection</h4>
                            <p>All sensitive data is encrypted using industry-standard algorithms. Passwords are hashed using BCrypt, and vote data is protected with SHA-256 hashing for integrity verification.</p>
                        </div>
                        <div class="security-item">
                            <h4>👤 Access Control</h4>
                            <p>Role-based access control ensures users can only access appropriate features. Comprehensive audit logging tracks all activities for security monitoring.</p>
                        </div>
                        <div class="security-item">
                            <h4>🗳️ Vote Integrity</h4>
                            <p>Database constraints prevent duplicate voting, anonymous voting protects voter privacy, and receipt systems provide vote confirmation while maintaining ballot secrecy.</p>
                        </div>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="dashboard-section">
                    <h2>🎓 Academic Information</h2>
                    <div class="academic-info">
                        <div class="info-grid">
                            <div class="info-item">
                                <h4>Institution</h4>
                                <p>Wollo University<br>Informatics College</p>
                            </div>
                            <div class="info-item">
                                <h4>Department</h4>
                                <p>Information Technology</p>
                            </div>
                            <div class="info-item">
                                <h4>Course Level</h4>
                                <p>3rd Year IT Students</p>
                            </div>
                            <div class="info-item">
                                <h4>Submission Date</h4>
                                <p>December 25, 2018</p>
                            </div>
                        </div>
                        
                        <div class="project-objectives">
                            <h4>Project Objectives</h4>
                            <ol>
                                <li><strong>Internet Programming:</strong> Design and implement a dynamic website using modern web technologies</li>
                                <li><strong>Advanced Database:</strong> Implement a normalized relational database with proper indexing and constraints</li>
                                <li><strong>System Analysis & Design:</strong> Prepare comprehensive software development documentation</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- System Workflow -->
                <div class="dashboard-section">
                    <h2>🔄 System Workflow</h2>
                    <div class="workflow">
                        <div class="workflow-steps">
                            <div class="workflow-step">
                                <div class="step-number">1</div>
                                <h4>User Registration</h4>
                                <p>Citizens register with personal details and national ID verification</p>
                            </div>
                            <div class="workflow-arrow">→</div>
                            <div class="workflow-step">
                                <div class="step-number">2</div>
                                <h4>Admin Verification</h4>
                                <p>Administrators verify user credentials and approve voter accounts</p>
                            </div>
                            <div class="workflow-arrow">→</div>
                            <div class="workflow-step">
                                <div class="step-number">3</div>
                                <h4>Election Creation</h4>
                                <p>Admins create elections, add positions, and register candidates</p>
                            </div>
                            <div class="workflow-arrow">→</div>
                            <div class="workflow-step">
                                <div class="step-number">4</div>
                                <h4>Voting Period</h4>
                                <p>Verified voters cast their ballots during the active election period</p>
                            </div>
                            <div class="workflow-arrow">→</div>
                            <div class="workflow-step">
                                <div class="step-number">5</div>
                                <h4>Result Generation</h4>
                                <p>Automated vote counting and transparent result publication</p>
                            </div>
                            <div class="workflow-arrow">→</div>
                            <div class="workflow-step">
                                <div class="step-number">6</div>
                                <h4>Audit Review</h4>
                                <p>Comprehensive audit trails available for transparency and verification</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact & Support -->
                <div class="dashboard-section">
                    <h2>📞 Contact & Support</h2>
                    <div class="contact-info">
                        <p>For technical support or questions about this project:</p>
                        <div class="contact-details">
                            <div class="contact-item">
                                <strong>Institution:</strong> Wollo University - Informatics College
                            </div>
                            <div class="contact-item">
                                <strong>Department:</strong> Information Technology
                            </div>
                            <div class="contact-item">
                                <strong>Course:</strong> Integrated Project (IP, ADB, SAD)
                            </div>
                        </div>
                        
                        <div class="disclaimer">
                            <h4>⚠️ Academic Use Disclaimer</h4>
                            <p>This system is designed for educational purposes as part of an integrated academic project. For production use in real elections, additional security measures, legal compliance, and professional security audits would be required.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2018 Wollo University - Informatics College. All rights reserved.</p>
            <p>Integrated Project: Internet Programming, Advanced Database & System Analysis Design</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>
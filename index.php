<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SportsMeet Manager - Your Ultimate Sports Event Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Navbar Styling */
        .navbar {
            background: var(--primary-gradient) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.8rem;
            color: white !important;
        }

        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            color: white !important;
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero-section {
            background: var(--primary-gradient);
            color: white;
            padding: 100px 0;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="25" cy="25" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="3" fill="rgba(255,255,255,0.05)"/><circle cx="50" cy="10" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="2" fill="rgba(255,255,255,0.05)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1.4rem;
            font-weight: 300;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 3rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Buttons */
        .btn-hero {
            padding: 15px 35px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 0 10px;
        }

        .btn-hero-primary {
            background: linear-gradient(45deg, #fff, #f8f9fa);
            color: #667eea;
            box-shadow: 0 8px 25px rgba(255,255,255,0.3);
        }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(255,255,255,0.4);
            color: #667eea;
        }

        .btn-hero-outline {
            background: transparent;
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .btn-hero-outline:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-3px);
            color: white;
        }

        /* Features Section */
        .features-section {
            padding: 100px 0;
            background: white;
        }

        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
        }

        .feature-icon.icon-events {
            background: var(--secondary-gradient);
        }

        .feature-icon.icon-users {
            background: var(--success-gradient);
        }

        .feature-icon.icon-management {
            background: var(--warning-gradient);
        }

        /* Events Section */
        .events-section {
            padding: 100px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }

        .section-title p {
            font-size: 1.1rem;
            color: #6c757d;
        }

        .event-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }

        .event-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        .event-card-header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px;
            position: relative;
        }

        .event-card-body {
            padding: 25px;
        }

        .event-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .event-meta {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            color: #6c757d;
        }

        .event-meta i {
            width: 20px;
            margin-right: 10px;
        }

        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-upcoming {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .status-completed {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }

        .participants-info {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        /* Footer */
        .footer {
            background: var(--dark-gradient);
            color: white;
            padding: 60px 0 30px;
        }

        .footer h5 {
            font-weight: 600;
            margin-bottom: 20px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: white;
        }

        .social-icons a {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            margin-right: 10px;
            color: white;
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            background: var(--primary-gradient);
            transform: translateY(-3px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }

            .hero-stats {
                gap: 1.5rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .btn-hero {
                padding: 12px 25px;
                font-size: 1rem;
                margin: 5px;
            }
        }
    </style>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-trophy me-2"></i>SportsMeet Manager
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#features">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#events">Events</a>
                </li>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'user/dashboard.php'; ?>">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="auth/logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="auth/register.php">
                            <i class="fas fa-user-plus me-1"></i>Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center hero-content">
                <h1 class="hero-title">Welcome to SportsMeet Manager</h1>
                <p class="hero-subtitle">Your ultimate platform for discovering, organizing, and participating in amazing sports events</p>

                <?php if (!isLoggedIn()): ?>
                    <div class="mt-4">
                        <a href="auth/register.php" class="btn-hero btn-hero-primary">
                            <i class="fas fa-rocket me-2"></i>Get Started Today
                        </a>
                        <a href="auth/login.php" class="btn-hero btn-hero-outline">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </a>
                    </div>
                <?php else: ?>
                    <div class="mt-4">
                        <a href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'user/dashboard.php'; ?>" class="btn-hero btn-hero-primary">
                            <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                        </a>
                        <a href="#events" class="btn-hero btn-hero-outline">
                            <i class="fas fa-calendar-alt me-2"></i>View Events
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Hero Stats -->
                <div class="hero-stats">
                    <?php
                    $pdo = getDBConnection();
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
                    $total_events = $stmt->fetch()['count'];

                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
                    $total_users = $stmt->fetch()['count'];

                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM event_participants");
                    $total_registrations = $stmt->fetch()['count'];
                    ?>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $total_events; ?>+</span>
                        <span class="stat-label">Events</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $total_users; ?>+</span>
                        <span class="stat-label">Athletes</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $total_registrations; ?>+</span>
                        <span class="stat-label">Registrations</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section" id="features">
    <div class="container">
        <div class="section-title">
            <h2>Why Choose SportsMeet Manager?</h2>
            <p>Discover the features that make us the best choice for sports event management</p>
        </div>

        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="feature-card">
                    <div class="feature-icon icon-events">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h4>Easy Event Management</h4>
                    <p>Create, manage, and organize sports events with our intuitive dashboard. Set capacity limits, manage registrations, and track participants effortlessly.</p>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-4">
                <div class="feature-card">
                    <div class="feature-icon icon-users">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4>Smart Registration System</h4>
                    <p>Prevent duplicate registrations, enforce capacity limits, and get real-time updates on event participation. Join events with just one click!</p>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-4">
                <div class="feature-card">
                    <div class="feature-icon icon-management">
                        <i class="fas fa-search"></i>
                    </div>
                    <h4>Advanced Search & Filter</h4>
                    <p>Find events by sport, date, venue, or status. Our powerful search system helps you discover the perfect events for your interests.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Upcoming Events Section -->
<section class="events-section" id="events">
    <div class="container">
        <div class="section-title">
            <h2>Upcoming Events</h2>
            <p>Join these amazing sports events and be part of the action</p>
        </div>

        <div class="row">
            <?php
            $stmt = $pdo->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 6");
            $events = $stmt->fetchAll();

            if (empty($events)):
                ?>
                <div class="col-12 text-center">
                    <div class="py-5">
                        <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">No upcoming events</h4>
                        <p class="text-muted">Check back soon for exciting sports events!</p>
                        <?php if (isAdmin()): ?>
                            <a href="admin/events.php?action=create" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Create First Event
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event):
                    $participant_count = getParticipantCount($event['id']);
                    $status = getEventStatus($event['event_date'], $event['event_time']);
                    $is_full = $participant_count >= $event['max_participants'];
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="event-card">
                            <div class="event-card-header">
                                    <span class="status-badge status-<?php echo strtolower($status); ?>">
                                        <?php echo $status; ?>
                                    </span>
                                <h5 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <div class="event-meta">
                                    <i class="fas fa-futbol"></i>
                                    <span><?php echo htmlspecialchars($event['sport_name']); ?></span>
                                </div>
                            </div>

                            <div class="event-card-body">
                                <?php if ($event['description']): ?>
                                    <p class="text-muted mb-3"><?php echo htmlspecialchars(substr($event['description'], 0, 100)); ?>...</p>
                                <?php endif; ?>

                                <div class="event-meta">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($event['venue']); ?></span>
                                </div>

                                <div class="event-meta">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                                </div>

                                <div class="event-meta">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('h:i A', strtotime($event['event_time'])); ?></span>
                                </div>

                                <div class="participants-info">
                                    <span class="fw-bold">Participants</span>
                                    <span class="badge bg-<?php echo $is_full ? 'danger' : 'primary'; ?>">
                                            <?php echo $participant_count; ?>/<?php echo $event['max_participants']; ?>
                                        </span>
                                </div>

                                <?php if (isLoggedIn() && !isAdmin()): ?>
                                    <div class="mt-3">
                                        <?php if ($is_full): ?>
                                            <button class="btn btn-danger w-100" disabled>
                                                <i class="fas fa-users me-2"></i>Event Full
                                            </button>
                                        <?php elseif (hasUserJoined($event['id'], $_SESSION['user_id'])): ?>
                                            <button class="btn btn-success w-100" disabled>
                                                <i class="fas fa-check me-2"></i>Already Joined
                                            </button>
                                        <?php else: ?>
                                            <a href="user/events.php#event-<?php echo $event['id']; ?>" class="btn btn-primary w-100">
                                                <i class="fas fa-user-plus me-2"></i>Join Event
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="col-12 text-center mt-4">
                    <?php if (isLoggedIn()): ?>
                        <a href="user/events.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-calendar-alt me-2"></i>View All Events
                        </a>
                    <?php else: ?>
                        <p class="text-muted mb-3">Join SportsMeet Manager to participate in events!</p>
                        <a href="auth/register.php" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-user-plus me-2"></i>Register Now
                        </a>
                        <a href="auth/login.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4">
                <h5><i class="fas fa-trophy me-2"></i>SportsMeet Manager</h5>
                <p class="text-muted">Your ultimate platform for managing and participating in sports events. Join our community of athletes and sports enthusiasts today!</p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="col-lg-2 col-md-6 mb-4">
                <h5>Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="#features">Features</a></li>
                    <li><a href="#events">Events</a></li>
                    <li><a href="auth/register.php">Register</a></li>
                    <li><a href="auth/login.php">Login</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <h5>Sports Categories</h5>
                <ul class="footer-links">
                    <li><a href="#">Football</a></li>
                    <li><a href="#">Basketball</a></li>
                    <li><a href="#">Cricket</a></li>
                    <li><a href="#">Tennis</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <h5>Contact Info</h5>
                <ul class="footer-links">
                    <li><i class="fas fa-envelope me-2"></i>info@sportsmeet.com</li>
                    <li><i class="fas fa-phone me-2"></i>+1 (555) 123-4567</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i>Sports City, SC 12345</li>
                </ul>
            </div>
        </div>

        <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">

        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="mb-0 text-muted">&copy; 2024 SportsMeet Manager. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="#" class="text-muted me-3">Privacy Policy</a>
                <a href="#" class="text-muted">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add animation on scroll
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 100) {
            navbar.style.background = 'rgba(102, 126, 234, 0.95)';
        } else {
            navbar.style.background = 'var(--primary-gradient)';
        }
    });
</script>
</body>
</html>
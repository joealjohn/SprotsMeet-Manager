<?php
/**
 * Common Header for SportsMeet Manager
 *
 * This file contains the common HTML head and navigation elements
 * used across different pages of the application.
 */

// Include functions if not already included
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/functions.php';
}

// Get current page information
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$page_title = getPageTitle();
$current_user = getCurrentUser();
$notifications = [];

// Get notifications for logged-in users
if (isLoggedIn()) {
    $notifications = getUnreadNotifications($_SESSION['user_id'], 5);
}

// Determine navigation class based on user role
$nav_class = 'navbar-expand-lg';
$nav_bg = isAdmin() ? 'bg-dark' : 'navbar-primary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SportsMeet Manager - Your ultimate sports event management platform">
    <meta name="keywords" content="sports, events, management, registration, tournaments">
    <meta name="author" content="SportsMeet Team">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo getBaseUrl(); ?>/assets/images/favicon.ico">

    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?php echo getBaseUrl(); ?>/assets/css/style.css" rel="stylesheet">

    <!-- Page-specific CSS -->
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

        .navbar {
            background: var(--primary-gradient) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .navbar.admin {
            background: var(--dark-gradient) !important;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
        }

        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 5px;
            padding: 8px 15px !important;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white !important;
            transform: translateY(-2px);
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 10px;
            padding: 10px 0;
        }

        .dropdown-item {
            padding: 10px 20px;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            color: white;
            font-weight: 600;
        }

        .current-time {
            font-size: 0.8rem;
            opacity: 0.7;
            margin-left: 10px;
        }

        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1.2rem;
            }

            .current-time {
                display: none;
            }
        }
    </style>
</head>
<body>
<!-- Navigation Bar -->
<nav class="navbar <?php echo $nav_class; ?> <?php echo isAdmin() ? 'admin' : ''; ?>" id="mainNavbar">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand" href="<?php echo getBaseUrl(); ?>/index.php">
            <i class="fas fa-trophy me-2"></i>SportsMeet Manager
            <?php if (isAdmin()): ?>
                <small class="badge bg-warning text-dark ms-2">Admin</small>
            <?php endif; ?>
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navigation Menu -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <!-- Admin Navigation -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>"
                               href="<?php echo getBaseUrl(); ?>/admin/dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'events' ? 'active' : ''; ?>"
                               href="<?php echo getBaseUrl(); ?>/admin/events.php">
                                <i class="fas fa-calendar-alt me-1"></i>Manage Events
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- User Navigation -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>"
                               href="<?php echo getBaseUrl(); ?>/user/dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'events' ? 'active' : ''; ?>"
                               href="<?php echo getBaseUrl(); ?>/user/events.php">
                                <i class="fas fa-calendar-alt me-1"></i>Browse Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'my_events' ? 'active' : ''; ?>"
                               href="<?php echo getBaseUrl(); ?>/user/my_events.php">
                                <i class="fas fa-list-check me-1"></i>My Events
                            </a>
                        </li>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Guest Navigation -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'index' ? 'active' : ''; ?>"
                           href="<?php echo getBaseUrl(); ?>/index.php">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Right Side Navigation -->
            <ul class="navbar-nav">
                <!-- Current Time Display - Updated to current UTC time -->
                <li class="nav-item d-none d-lg-block">
                        <span class="nav-link current-time" id="currentTime">
                            <i class="fas fa-clock me-1"></i>
                            Aug 04, 2025 16:11:51 UTC
                        </span>
                </li>

                <?php if (isLoggedIn()): ?>
                    <!-- Notifications -->
                    <?php if (!empty($notifications)): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link position-relative" href="#" id="notificationDropdown"
                               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <span class="notification-badge"><?php echo count($notifications); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php foreach ($notifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $notification['action_url'] ?? '#'; ?>">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-<?php echo $notification['type'] === 'success' ? 'check-circle text-success' : 'info-circle text-info'; ?>"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-2">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                    <p class="mb-1 small text-muted"><?php echo htmlspecialchars(substr($notification['message'], 0, 50)) . '...'; ?></p>
                                                    <small class="text-muted"><?php echo formatDateTime(date('Y-m-d', strtotime($notification['created_at'])), date('H:i:s', strtotime($notification['created_at']))); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center" href="#">View All Notifications</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- User Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr(getCurrentUserDisplayName(), 0, 1)); ?>
                            </div>
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars(getCurrentUserDisplayName()); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><h6 class="dropdown-header"><?php echo htmlspecialchars(getCurrentUserDisplayName()); ?></h6></li>
                            <li><small class="dropdown-header text-muted">@<?php echo htmlspecialchars($current_user['username'] ?? 'joealjohn'); ?></small></li>
                            <li><hr class="dropdown-divider"></li>

                            <?php if (isAdmin()): ?>
                                <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>/admin/dashboard.php">
                                        <i class="fas fa-user-shield me-2"></i>Admin Dashboard
                                    </a></li>
                                <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>/user/dashboard.php">
                                        <i class="fas fa-eye me-2"></i>View as User
                                    </a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>/user/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i>My Dashboard
                                    </a></li>
                                <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>/user/my_events.php">
                                        <i class="fas fa-calendar-check me-2"></i>My Events
                                    </a></li>
                            <?php endif; ?>

                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>/index.php">
                                    <i class="fas fa-home me-2"></i>Back to Home
                                </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo getBaseUrl(); ?>/auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Guest Menu -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getBaseUrl(); ?>/auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getBaseUrl(); ?>/auth/register.php">
                            <i class="fas fa-user-plus me-1"></i>Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content Container -->
<main class="main-content">
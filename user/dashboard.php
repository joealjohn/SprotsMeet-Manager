<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in (not admin)
requireLogin();
if (isAdmin()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

// Get current IST time and user info for display
$current_ist_time = getCurrentDisplayTime();
$current_utc = '2025-08-04 15:31:43'; // Updated current UTC time
$current_user_data = getCurrentUser();
$current_user = $current_user_data['username']; // joealjohn
$display_name = getUserDisplayName($current_user_data); // Joe John
$user_initials = getUserInitials($current_user_data); // JO

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get user statistics
$stats_sql = "SELECT 
              (SELECT COUNT(*) FROM event_participants WHERE user_id = ? AND registration_status != 'cancelled') as total_events,
              (SELECT COUNT(*) FROM event_participants ep JOIN events e ON ep.event_id = e.id WHERE ep.user_id = ? AND ep.registration_status != 'cancelled' AND e.event_date >= CURDATE()) as upcoming_events,
              (SELECT COUNT(*) FROM event_participants ep JOIN events e ON ep.event_id = e.id WHERE ep.user_id = ? AND ep.registration_status != 'cancelled' AND e.event_date < CURDATE()) as completed_events";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$user_id, $user_id, $user_id]);
$user_stats = $stmt->fetch();

// Get upcoming events for user
$upcoming_events_sql = "SELECT e.*, ep.joined_at, ep.registration_status,
                        (SELECT COUNT(*) FROM event_participants ep2 WHERE ep2.event_id = e.id AND ep2.registration_status != 'cancelled') as participant_count
                        FROM events e 
                        JOIN event_participants ep ON e.id = ep.event_id 
                        WHERE ep.user_id = ? AND ep.registration_status != 'cancelled' AND e.event_date >= CURDATE()
                        ORDER BY e.event_date ASC, e.event_time ASC
                        LIMIT 3";
$stmt = $pdo->prepare($upcoming_events_sql);
$stmt->execute([$user_id]);
$upcoming_events = $stmt->fetchAll();

// Get recent activity
$activity_sql = "SELECT e.title, e.sport_name, ep.joined_at, e.event_date, e.event_time
                 FROM events e 
                 JOIN event_participants ep ON e.id = ep.event_id 
                 WHERE ep.user_id = ? AND ep.registration_status != 'cancelled'
                 ORDER BY ep.joined_at DESC
                 LIMIT 5";
$stmt = $pdo->prepare($activity_sql);
$stmt->execute([$user_id]);
$recent_activity = $stmt->fetchAll();

// Helper functions
function getSportIcon($sport_name) {
    $icons = [
        'Football' => 'fa-futbol',
        'Basketball' => 'fa-basketball-ball',
        'Tennis' => 'fa-table-tennis',
        'Cricket' => 'fa-cricket',
        'Swimming' => 'fa-swimmer',
        'Running' => 'fa-running',
        'Cycling' => 'fa-biking',
        'Volleyball' => 'fa-volleyball-ball'
    ];
    return $icons[$sport_name] ?? 'fa-calendar-alt';
}

function getSportColor($sport_name) {
    $colors = [
        'Football' => '#28a745',
        'Basketball' => '#dc3545',
        'Tennis' => '#007bff',
        'Cricket' => '#20c997',
        'Swimming' => '#17a2b8',
        'Running' => '#ffc107',
        'Cycling' => '#6f42c1',
        'Volleyball' => '#fd7e14'
    ];
    return $colors[$sport_name] ?? '#667eea';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SportsMeet Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --info-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .navbar {
            background: var(--primary-gradient) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .navbar-brand, .navbar-nav .nav-link {
            color: white !important;
            font-weight: 500;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .current-time {
            font-size: 0.85rem;
            opacity: 0.9;
            color: rgba(255,255,255,0.9) !important;
            border-left: 1px solid rgba(255,255,255,0.2);
            padding-left: 15px;
            margin-right: 15px;
            white-space: nowrap;
        }

        .navbar-collapse {
            justify-content: space-between;
        }

        .navbar-nav.ms-auto {
            margin-left: auto !important;
            display: flex;
            align-items: center;
        }

        /* FIXED: Hero Section with Better Contrast */
        .hero-section {
            background: var(--primary-gradient);
            color: white;
            padding: 60px 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }

        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: white !important; /* FIXED: Ensure white text */
        }

        .hero-section p {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 20px;
            color: rgba(255,255,255,0.9) !important; /* FIXED: Better contrast */
        }

        .hero-section small {
            opacity: 0.8;
            color: rgba(255,255,255,0.8) !important; /* FIXED: Better contrast */
        }

        /* FIXED: Stats Cards with Better Visibility */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: #2c3e50 !important; /* FIXED: Dark text for better visibility */
        }

        .stats-label {
            color: #6c757d !important; /* FIXED: Better contrast */
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* FIXED: Section Cards with Better Contrast */
        .section-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .section-header h5 {
            margin: 0;
            color: #2c3e50 !important; /* FIXED: Dark header text */
            font-weight: 600;
        }

        .section-body {
            padding: 25px;
        }

        /* FIXED: Event Cards with Better Visibility */
        .event-card {
            background: white;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            position: relative;
        }

        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .event-title {
            font-weight: 600;
            color: #2c3e50 !important; /* FIXED: Dark title text */
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .event-details {
            color: #6c757d !important; /* FIXED: Better contrast for details */
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .event-status {
            position: absolute;
            top: 15px;
            right: 15px;
        }

        /* FIXED: Activity Items with Better Contrast */
        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-title {
            font-weight: 600;
            color: #2c3e50 !important; /* FIXED: Dark text */
            margin-bottom: 5px;
        }

        .activity-details {
            color: #6c757d !important; /* FIXED: Better contrast */
            font-size: 0.85rem;
        }

        .sport-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea !important; /* FIXED: Better contrast */
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-right: 10px;
        }

        .sport-icon {
            width: 16px;
            height: 16px;
            margin-right: 6px;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 10px;
        }

        .dropdown-item:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        /* FIXED: View All Links */
        .view-all-link {
            color: #667eea !important;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .view-all-link:hover {
            color: #5a67d8 !important;
        }

        @media (max-width: 991px) {
            .current-time {
                border-left: none;
                padding-left: 0;
                margin-right: 0;
                text-align: center;
                padding: 0.5rem 1rem;
            }

            .hero-section {
                padding: 40px 20px;
            }

            .hero-section h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-trophy me-2"></i>SportsMeet Manager
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="events.php">
                        <i class="fas fa-calendar-alt me-1"></i>Browse Events
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my_events.php">
                        <i class="fas fa-list-check me-1"></i>My Events
                    </a>
                </li>
                <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../admin/dashboard.php">
                            <i class="fas fa-user-shield me-1"></i>Admin Panel
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item d-none d-lg-block">
                    <span class="nav-link current-time" id="currentTime">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo $current_ist_time; ?> IST
                    </span>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-2">
                                <?php echo $user_initials; ?>
                            </div>
                            <span><?php echo $display_name; ?></span>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?php echo $display_name; ?></h6></li>
                        <li><small class="dropdown-header text-muted">@<?php echo htmlspecialchars($current_user); ?></small></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="my_events.php"><i class="fas fa-calendar me-2"></i>My Events</a></li>
                        <?php if (isAdmin()): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../admin/dashboard.php"><i class="fas fa-user-shield me-2"></i>Admin Panel</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="stats-card text-center">
                <div class="stats-icon mx-auto" style="background: var(--primary-gradient);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stats-number"><?php echo $user_stats['total_events'] ?? 0; ?></div>
                <div class="stats-label">Total Events</div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stats-card text-center">
                <div class="stats-icon mx-auto" style="background: var(--success-gradient);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stats-number"><?php echo $user_stats['upcoming_events'] ?? 0; ?></div>
                <div class="stats-label">Upcoming Events</div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stats-card text-center">
                <div class="stats-icon mx-auto" style="background: var(--warning-gradient);">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stats-number"><?php echo $user_stats['completed_events'] ?? 0; ?></div>
                <div class="stats-label">Completed Events</div>
            </div>
        </div>
    </div>

    <!-- Content Sections -->
    <div class="row">
        <!-- Upcoming Events -->
        <div class="col-lg-6 mb-4">
            <div class="section-card">
                <div class="section-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-calendar-alt me-2"></i>My Upcoming Events</h5>
                    <a href="my_events.php" class="view-all-link">View All</a>
                </div>
                <div class="section-body">
                    <?php if (empty($upcoming_events)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No upcoming events found.</p>
                            <a href="events.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>Browse Events
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_events as $event):
                            $sport_color = getSportColor($event['sport_name']);
                            $sport_icon = getSportIcon($event['sport_name']);
                            ?>
                            <div class="event-card">
                                <div class="event-status">
                                    <span class="badge bg-success">Registered</span>
                                </div>
                                <div class="event-title">
                                    <i class="fas <?php echo $sport_icon; ?> me-2" style="color: <?php echo $sport_color; ?>;"></i>
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </div>
                                <div class="event-details">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($event['venue']); ?>
                                </div>
                                <div class="event-details">
                                    <i class="fas fa-clock me-1"></i>
                                    <span data-utc="<?php echo $event['event_date'] . ' ' . $event['event_time']; ?>">
                                        <?php echo convertUTCtoIST($event['event_date'] . ' ' . $event['event_time'], 'M d, Y - h:i A'); ?> IST
                                    </span>
                                </div>
                                <div class="event-details">
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo $event['participant_count']; ?>/<?php echo $event['max_participants']; ?> participants
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-6 mb-4">
            <div class="section-card">
                <div class="section-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-history me-2"></i>Recent Activity</h5>
                    <a href="my_events.php" class="view-all-link">View All</a>
                </div>
                <div class="section-body">
                    <?php if (empty($recent_activity)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No recent activity found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $activity):
                            $sport_color = getSportColor($activity['sport_name']);
                            $sport_icon = getSportIcon($activity['sport_name']);
                            ?>
                            <div class="activity-item">
                                <div class="activity-title">
                                    <span class="sport-badge">
                                        <i class="fas <?php echo $sport_icon; ?> sport-icon"></i>
                                        <?php echo htmlspecialchars($activity['sport_name']); ?>
                                    </span>
                                    <?php echo htmlspecialchars($activity['title']); ?>
                                </div>
                                <div class="activity-details">
                                    <i class="fas fa-calendar me-1"></i>
                                    Event: <span data-utc="<?php echo $activity['event_date'] . ' ' . $activity['event_time']; ?>">
                                        <?php echo convertUTCtoIST($activity['event_date'] . ' ' . $activity['event_time'], 'M d, Y'); ?>
                                    </span>
                                </div>
                                <div class="activity-details">
                                    <i class="fas fa-clock me-1"></i>
                                    Joined: <span data-utc="<?php echo $activity['joined_at']; ?>">
                                        <?php echo convertUTCtoIST($activity['joined_at'], 'M d, Y - h:i A'); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load time utilities -->
<script src="../assets/js/time-utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize time manager for IST conversion
        if (typeof timeManager !== 'undefined') {
            timeManager.startAutoUpdate();
        }

        // Update current time display
        function updateCurrentTime() {
            const now = new Date();
            const istTime = now.toLocaleString('en-IN', {
                timeZone: 'Asia/Kolkata',
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });

            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.innerHTML = `<i class="fas fa-clock me-1"></i>${istTime} IST`;
            }
        }

        updateCurrentTime();
        setInterval(updateCurrentTime, 1000);
    });

    console.log('Dashboard loaded successfully with IST timezone');
    console.log('Current User: <?php echo $display_name; ?>');
    console.log('Current Username: <?php echo $current_user; ?>');
    console.log('Current UTC: <?php echo $current_utc; ?>');
    console.log('Current IST: <?php echo $current_ist_time; ?>');
</script>
</body>
</html>
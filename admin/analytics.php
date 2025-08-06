<?php
$page_title = "Analytics";
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is admin
requireAdmin();

// Get current IST time and user info for display
$current_ist_time = getCurrentDisplayTime();
$current_utc = '2025-08-04 14:14:47';
$current_user_data = getCurrentUser();
$current_user = $current_user_data['username']; // Keep for logging
$display_name = getUserDisplayName($current_user_data);
$user_initials = getUserInitials($current_user_data);

$pdo = getDBConnection();

// Get date range from request (default to last 30 days)
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get general statistics
$general_stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_events' => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'published_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'published'")->fetchColumn(),
    'total_registrations' => $pdo->query("SELECT COUNT(*) FROM event_participants WHERE registration_status != 'cancelled'")->fetchColumn(),
    'new_users_this_month' => $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
    'events_this_month' => $pdo->query("SELECT COUNT(*) FROM events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
    'registrations_this_month' => $pdo->query("SELECT COUNT(*) FROM event_participants WHERE joined_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND registration_status != 'cancelled'")->fetchColumn()
];

// Get user growth data (last 30 days)
$user_growth_sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                    FROM users 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(created_at) 
                    ORDER BY date ASC";
$user_growth_data = $pdo->query($user_growth_sql)->fetchAll();

// Get event registration data (last 30 days)
$registration_growth_sql = "SELECT DATE(joined_at) as date, COUNT(*) as count 
                            FROM event_participants 
                            WHERE joined_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                            AND registration_status != 'cancelled'
                            GROUP BY DATE(joined_at) 
                            ORDER BY date ASC";
$registration_growth_data = $pdo->query($registration_growth_sql)->fetchAll();

// Get sport popularity
$sport_popularity_sql = "SELECT e.sport_name, COUNT(ep.id) as registrations
                         FROM events e
                         LEFT JOIN event_participants ep ON e.id = ep.event_id AND ep.registration_status != 'cancelled'
                         GROUP BY e.sport_name
                         ORDER BY registrations DESC
                         LIMIT 5";
$sport_popularity_data = $pdo->query($sport_popularity_sql)->fetchAll();

// Get top events by participation
$top_events_sql = "SELECT e.title, e.sport_name, e.venue, 
                   DATE(e.event_date) as event_date,
                   COUNT(ep.id) as participant_count,
                   e.max_participants
                   FROM events e
                   LEFT JOIN event_participants ep ON e.id = ep.event_id AND ep.registration_status != 'cancelled'
                   GROUP BY e.id
                   ORDER BY participant_count DESC
                   LIMIT 5";
$top_events_data = $pdo->query($top_events_sql)->fetchAll();

// Get recent activities (with enhanced user data)
$recent_activities_sql = "SELECT 
                          al.action,
                          al.created_at,
                          u.username,
                          u.first_name,
                          u.last_name
                          FROM activity_logs al
                          JOIN users u ON al.user_id = u.id
                          ORDER BY al.created_at DESC
                          LIMIT 8";
$recent_activities = $pdo->query($recent_activities_sql)->fetchAll();

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

function getActivityIcon($action) {
    $icons = [
        'user_login' => 'fa-sign-in-alt',
        'user_logout' => 'fa-sign-out-alt',
        'event_join' => 'fa-calendar-plus',
        'event_leave' => 'fa-calendar-minus',
        'event_create' => 'fa-plus',
        'event_update' => 'fa-edit',
        'event_delete' => 'fa-trash',
        'user_create' => 'fa-user-plus',
        'user_update' => 'fa-user-edit',
        'user_delete' => 'fa-user-times'
    ];
    return $icons[$action] ?? 'fa-info-circle';
}

// Helper function to get user display name from activity data
function getActivityUserDisplayName($activity) {
    if (!$activity['username']) return 'System';

    $firstName = trim($activity['first_name'] ?? '');
    $lastName = trim($activity['last_name'] ?? '');

    if (!empty($firstName) && !empty($lastName)) {
        return $firstName . ' ' . $lastName;
    } elseif (!empty($firstName)) {
        return $firstName;
    } elseif (!empty($lastName)) {
        return $lastName;
    } else {
        return $activity['username'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel - SportsMeet Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #718096;
            --success: #38a169;
            --warning: #d69e2e;
            --danger: #e53e3e;
            --info: #3182ce;
            --light: #f7fafc;
            --dark: #2d3748;
            --border-color: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: var(--dark);
            line-height: 1.6;
        }

        /* Dark Navigation to match other admin pages */
        .navbar {
            background: linear-gradient(135deg, #434343 0%, #000000 100%) !important;
            border-bottom: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            padding: 1rem 0;
        }

        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .navbar-nav .nav-link {
            color: white !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 20px;
            margin: 0 0.25rem;
            transition: all 0.2s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white !important;
        }

        .admin-badge {
            background: #dc3545;
            color: white;
            padding: 0.125rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
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
            color: rgba(255,255,255,0.8) !important;
            font-size: 0.875rem;
            font-weight: 500;
            border-left: 1px solid rgba(255,255,255,0.2);
            padding-left: 1rem;
            margin-left: 1rem;
        }

        /* Container & Layout */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--secondary);
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .time-info {
            background: #f1f5f9;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--secondary);
            display: inline-block;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 16px 16px 0 0;
        }

        .stat-card.users::before { background: var(--primary); }
        .stat-card.events::before { background: var(--success); }
        .stat-card.registrations::before { background: var(--warning); }
        .stat-card.active::before { background: var(--info); }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .stat-icon.users { background: var(--primary); }
        .stat-icon.events { background: var(--success); }
        .stat-icon.registrations { background: var(--warning); }
        .stat-icon.active { background: var(--info); }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-change {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--success);
            margin-top: 0.5rem;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-section {
            display: grid;
            gap: 2rem;
        }

        .sidebar {
            display: grid;
            gap: 2rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: #fafbfc;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            margin: 0;
        }

        .card-title i {
            margin-right: 0.75rem;
            color: var(--primary);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            padding: 1rem 0;
        }

        /* Sport Stats */
        .sport-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .sport-item:last-child {
            border-bottom: none;
        }

        .sport-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 1rem;
            font-size: 1rem;
        }

        .sport-info {
            flex: 1;
        }

        .sport-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .sport-progress {
            width: 100%;
            height: 6px;
            background: #f1f5f9;
            border-radius: 3px;
            overflow: hidden;
        }

        .sport-progress-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        .sport-count {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--secondary);
            margin-left: 1rem;
        }

        /* Activities */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            color: white;
            margin-right: 1rem;
            background: var(--primary);
        }

        .activity-content {
            flex: 1;
        }

        .activity-user {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .activity-action {
            color: var(--secondary);
            font-size: 0.8rem;
            margin-top: 0.125rem;
        }

        .activity-time {
            font-size: 0.75rem;
            color: var(--secondary);
            white-space: nowrap;
        }

        /* Events Table */
        .events-table {
            width: 100%;
            border-collapse: collapse;
        }

        .events-table th {
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid #f1f5f9;
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.875rem;
        }

        .events-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
        }

        .event-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .event-venue {
            color: var(--secondary);
            font-size: 0.8rem;
        }

        .sport-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }

        .capacity-bar {
            width: 60px;
            height: 6px;
            background: #f1f5f9;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.25rem;
        }

        .capacity-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                padding: 1.5rem;
            }

            .stat-card {
                padding: 1.5rem;
            }
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
<!-- Dark Navigation matching other admin pages -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-user-shield me-2"></i>Admin Panel
            <span class="admin-badge">ADMIN</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="events.php">
                        <i class="fas fa-calendar-alt me-1"></i>Events
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-1"></i>Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="activity.php">
                        <i class="fas fa-history me-1"></i>Activity
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="analytics.php">
                        <i class="fas fa-chart-bar me-1"></i>Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-1"></i>Settings
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav">
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
                        <li><small class="dropdown-header text-muted">@<?php echo $current_user; ?></small></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../index.php"><i class="fas fa-home me-2"></i>Back to Site</a></li>
                        <li><a class="dropdown-item" href="../user/dashboard.php"><i class="fas fa-eye me-2"></i>View as User</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="main-container">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-chart-line me-3"></i>Analytics Dashboard
        </h1>
        <p class="page-subtitle">Comprehensive insights and analytics for SportsMeet Manager</p>
        <div class="time-info">
            <i class="fas fa-clock me-2"></i>
            Report generated: <?php echo $current_ist_time; ?> IST
            <span class="ms-3">Admin: <?php echo $display_name; ?></span>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card users">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($general_stats['total_users']); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-icon users">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-change">
                <i class="fas fa-arrow-up me-1"></i>
                +<?php echo $general_stats['new_users_this_month']; ?> this month
            </div>
        </div>

        <div class="stat-card events">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($general_stats['total_events']); ?></div>
                    <div class="stat-label">Total Events</div>
                </div>
                <div class="stat-icon events">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>
            <div class="stat-change">
                <i class="fas fa-arrow-up me-1"></i>
                +<?php echo $general_stats['events_this_month']; ?> this month
            </div>
        </div>

        <div class="stat-card registrations">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($general_stats['total_registrations']); ?></div>
                    <div class="stat-label">Registrations</div>
                </div>
                <div class="stat-icon registrations">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
            <div class="stat-change">
                <i class="fas fa-arrow-up me-1"></i>
                +<?php echo $general_stats['registrations_this_month']; ?> this month
            </div>
        </div>

        <div class="stat-card active">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($general_stats['active_users']); ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat-icon active">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="stat-change">
                <i class="fas fa-percentage me-1"></i>
                <?php echo round(($general_stats['active_users'] / max($general_stats['total_users'], 1)) * 100, 1); ?>% of total
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="content-grid">
        <!-- Charts Section -->
        <div class="chart-section">
            <!-- User Growth Chart -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-plus"></i>
                        User Growth Trend
                    </h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Registration Growth Chart -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-calendar-plus"></i>
                        Event Registrations
                    </h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="registrationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sport Popularity -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-trophy"></i>
                        Popular Sports
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($sport_popularity_data)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <p>No sport data available</p>
                        </div>
                    <?php else: ?>
                        <?php
                        $max_registrations = max(array_column($sport_popularity_data, 'registrations'));
                        foreach ($sport_popularity_data as $sport):
                            $percentage = $max_registrations > 0 ? ($sport['registrations'] / $max_registrations) * 100 : 0;
                            $sport_color = getSportColor($sport['sport_name']);
                            $sport_icon = getSportIcon($sport['sport_name']);
                            ?>
                            <div class="sport-item">
                                <div class="sport-icon" style="background: <?php echo $sport_color; ?>;">
                                    <i class="fas <?php echo $sport_icon; ?>"></i>
                                </div>
                                <div class="sport-info">
                                    <div class="sport-name"><?php echo htmlspecialchars($sport['sport_name']); ?></div>
                                    <div class="sport-progress">
                                        <div class="sport-progress-bar" style="width: <?php echo $percentage; ?>%; background: <?php echo $sport_color; ?>;"></div>
                                    </div>
                                </div>
                                <div class="sport-count"><?php echo $sport['registrations']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history"></i>
                        Recent Activity
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activities)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity):
                            $user_display_name = getActivityUserDisplayName($activity);
                            ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas <?php echo getActivityIcon($activity['action']); ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-user"><?php echo htmlspecialchars($user_display_name); ?></div>
                                    <div class="activity-action"><?php echo ucwords(str_replace('_', ' ', $activity['action'])); ?></div>
                                </div>
                                <div class="activity-time" data-utc="<?php echo $activity['created_at']; ?>">
                                    <?php echo getTimeAgo($activity['created_at']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Events Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-star"></i>
                Top Events by Participation
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($top_events_data)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>No events found</p>
                </div>
            <?php else: ?>
                <table class="events-table">
                    <thead>
                    <tr>
                        <th>Event</th>
                        <th>Sport</th>
                        <th>Date</th>
                        <th>Participants</th>
                        <th>Capacity</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($top_events_data as $event):
                        $fill_percentage = ($event['participant_count'] / max($event['max_participants'], 1)) * 100;
                        $sport_color = getSportColor($event['sport_name']);
                        ?>
                        <tr>
                            <td>
                                <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                <div class="event-venue"><?php echo htmlspecialchars($event['venue']); ?></div>
                            </td>
                            <td>
                                        <span class="sport-badge" style="background: <?php echo $sport_color; ?>;">
                                            <?php echo htmlspecialchars($event['sport_name']); ?>
                                        </span>
                            </td>
                            <td data-utc="<?php echo $event['event_date']; ?>">
                                <?php echo convertUTCtoIST($event['event_date'], 'M d, Y'); ?>
                            </td>
                            <td><strong><?php echo $event['participant_count']; ?></strong></td>
                            <td>
                                <div><?php echo $event['max_participants']; ?></div>
                                <div class="capacity-bar">
                                    <div class="capacity-fill" style="width: <?php echo min($fill_percentage, 100); ?>%; background: <?php echo $sport_color; ?>;"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Load time utilities first -->
<script src="../assets/js/time-utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Clean Analytics with IST time management
    document.addEventListener('DOMContentLoaded', function() {
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

        // Initialize charts
        initializeCharts();
    });

    function initializeCharts() {
        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthData = <?php echo json_encode(array_map(function($item) {
            return ['x' => $item['date'], 'y' => $item['count']];
        }, $user_growth_data)); ?>;

        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'New Users',
                    data: userGrowthData,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: { unit: 'day' },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' }
                    }
                }
            }
        });

        // Registration Chart
        const registrationCtx = document.getElementById('registrationChart').getContext('2d');
        const registrationData = <?php echo json_encode(array_map(function($item) {
            return ['x' => $item['date'], 'y' => $item['count']];
        }, $registration_growth_data)); ?>;

        new Chart(registrationCtx, {
            type: 'bar',
            data: {
                datasets: [{
                    label: 'Registrations',
                    data: registrationData,
                    backgroundColor: 'rgba(56, 161, 105, 0.8)',
                    borderColor: '#38a169',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: { unit: 'day' },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' }
                    }
                }
            }
        });
    }

    console.log('Analytics Dashboard with Dark Navigation loaded successfully');
    console.log('Current Admin: <?php echo $display_name; ?>');
    console.log('Current Username: <?php echo $current_user; ?>');
    console.log('Current UTC: <?php echo $current_utc; ?>');
    console.log('Current IST: <?php echo $current_ist_time; ?>');
</script>
</body>
</html>
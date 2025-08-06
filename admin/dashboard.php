<?php
$page_title = "Dashboard";
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is admin
requireAdmin();

// Get current IST time and user info for display
$current_ist_time = getCurrentDisplayTime();
$current_utc = '2025-08-04 16:05:40'; // Updated current UTC time from user
$current_user_data = getCurrentUser();
$current_user = $current_user_data['username']; // joealjohn
$display_name = getUserDisplayName($current_user_data);
$user_initials = getUserInitials($current_user_data);

$pdo = getDBConnection();
$message = '';
$message_type = '';

// Get dashboard statistics
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_events' => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'published_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'published'")->fetchColumn(),
    'draft_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'draft'")->fetchColumn(),
    'upcoming_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE() AND status = 'published'")->fetchColumn(),
    'total_registrations' => $pdo->query("SELECT COUNT(*) FROM event_participants WHERE registration_status != 'cancelled'")->fetchColumn(),
    'pending_registrations' => $pdo->query("SELECT COUNT(*) FROM event_participants WHERE registration_status = 'pending'")->fetchColumn(),
    'new_users_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'new_users_week' => $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'events_today' => $pdo->query("SELECT COUNT(*) FROM events WHERE DATE(event_date) = CURDATE() AND status = 'published'")->fetchColumn(),
    'registrations_today' => $pdo->query("SELECT COUNT(*) FROM event_participants WHERE DATE(joined_at) = CURDATE() AND registration_status != 'cancelled'")->fetchColumn()
];

// Get recent events (with enhanced participant info)
$recent_events_sql = "SELECT e.*, 
                      (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.registration_status != 'cancelled') as participant_count,
                      u.username as created_by_username,
                      u.first_name as created_by_first_name,
                      u.last_name as created_by_last_name
                      FROM events e 
                      LEFT JOIN users u ON e.created_by = u.id
                      ORDER BY e.created_at DESC 
                      LIMIT 5";
$recent_events = $pdo->query($recent_events_sql)->fetchAll();

// Get recent users (with display names)
$recent_users_sql = "SELECT id, username, first_name, last_name, email, role, created_at, is_active, last_login
                     FROM users 
                     ORDER BY created_at DESC 
                     LIMIT 10";
$recent_users = $pdo->query($recent_users_sql)->fetchAll();

// Get recent activity (with enhanced user info)
$recent_activity_sql = "SELECT al.*, u.username, u.first_name, u.last_name
                        FROM activity_logs al 
                        LEFT JOIN users u ON al.user_id = u.id 
                        ORDER BY al.created_at DESC 
                        LIMIT 8";
$recent_activity = $pdo->query($recent_activity_sql)->fetchAll();

// Get upcoming events today and tomorrow
$upcoming_events_sql = "SELECT e.*, 
                        (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.registration_status != 'cancelled') as participant_count
                        FROM events e 
                        WHERE e.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
                        AND e.status = 'published'
                        ORDER BY e.event_date ASC, e.event_time ASC
                        LIMIT 5";
$upcoming_events = $pdo->query($upcoming_events_sql)->fetchAll();

// Get system alerts
$alerts = [];

// Check for events needing attention
if ($stats['draft_events'] > 0) {
    $alerts[] = [
        'type' => 'warning',
        'title' => 'Draft Events',
        'message' => $stats['draft_events'] . ' event(s) are still in draft status',
        'action' => 'events.php?status=draft',
        'icon' => 'fa-edit'
    ];
}

if ($stats['pending_registrations'] > 0) {
    $alerts[] = [
        'type' => 'info',
        'title' => 'Pending Registrations',
        'message' => $stats['pending_registrations'] . ' registration(s) need approval',
        'action' => 'events.php?registration_status=pending',
        'icon' => 'fa-clock'
    ];
}

if ($stats['events_today'] > 0) {
    $alerts[] = [
        'type' => 'success',
        'title' => 'Events Today',
        'message' => $stats['events_today'] . ' event(s) scheduled for today',
        'action' => 'events.php?date=' . date('Y-m-d'),
        'icon' => 'fa-calendar-day'
    ];
}

// Helper functions
function getEventStatusBadge($event) {
    $status = $event['status'];
    $badges = [
        'published' => 'bg-success',
        'draft' => 'bg-warning',
        'cancelled' => 'bg-danger',
        'completed' => 'bg-secondary'
    ];

    return '<span class="badge ' . ($badges[$status] ?? 'bg-primary') . '">' . ucfirst($status) . '</span>';
}

function getActivityIcon($action) {
    $icons = [
        'user_login' => 'fa-sign-in-alt',
        'user_logout' => 'fa-sign-out-alt',
        'user_create' => 'fa-user-plus',
        'user_update' => 'fa-user-edit',
        'user_delete' => 'fa-user-minus',
        'event_create' => 'fa-calendar-plus',
        'event_update' => 'fa-calendar-edit',
        'event_delete' => 'fa-calendar-minus',
        'event_join' => 'fa-user-check',
        'event_leave' => 'fa-user-times',
        'page_view' => 'fa-eye',
        'admin_login' => 'fa-shield-alt'
    ];

    foreach ($icons as $key => $icon) {
        if (strpos($action, $key) !== false) {
            return $icon;
        }
    }

    return 'fa-circle';
}

function getActivityColor($action) {
    $colors = [
        'login' => '#28a745',
        'logout' => '#6c757d',
        'create' => '#007bff',
        'update' => '#ffc107',
        'delete' => '#dc3545',
        'join' => '#20c997',
        'leave' => '#fd7e14',
        'page_view' => '#6f42c1'
    ];

    foreach ($colors as $key => $color) {
        if (strpos($action, $key) !== false) {
            return $color;
        }
    }

    return '#667eea';
}

// Helper function to get user display name from data
function getDataUserDisplayName($userData) {
    if (!$userData || !$userData['username']) return 'System';

    $firstName = trim($userData['first_name'] ?? '');
    $lastName = trim($userData['last_name'] ?? '');

    if (!empty($firstName) && !empty($lastName)) {
        return $firstName . ' ' . $lastName;
    } elseif (!empty($firstName)) {
        return $firstName;
    } elseif (!empty($lastName)) {
        return $lastName;
    } else {
        return $userData['username'];
    }
}

// Helper function to get user initials from data
function getDataUserInitials($userData) {
    if (!$userData || !$userData['username']) return 'S';

    $firstName = trim($userData['first_name'] ?? '');
    $lastName = trim($userData['last_name'] ?? '');
    $username = trim($userData['username'] ?? '');

    if (!empty($firstName) && !empty($lastName)) {
        return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
    } elseif (!empty($firstName)) {
        return strtoupper(substr($firstName, 0, 1));
    } elseif (!empty($username)) {
        return strtoupper(substr($username, 0, 1));
    } else {
        return 'U';
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --info-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --danger-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .navbar {
            background: var(--dark-gradient) !important;
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

        .admin-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: 8px;
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
            opacity: 0.8;
            border-left: 1px solid rgba(255,255,255,0.2);
            padding-left: 15px;
            margin-left: 15px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            border-radius: 15px 15px 0 0;
        }

        .stat-card.primary::before { background: var(--primary-gradient); }
        .stat-card.success::before { background: var(--success-gradient); }
        .stat-card.warning::before { background: var(--warning-gradient); }
        .stat-card.info::before { background: var(--info-gradient); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .stat-icon.primary { background: var(--primary-gradient); }
        .stat-icon.success { background: var(--success-gradient); }
        .stat-icon.warning { background: var(--warning-gradient); }
        .stat-icon.info { background: var(--info-gradient); }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #718096;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f1f3f4;
            background: #fafbfc;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 10px;
            color: #667eea;
        }

        .card-body {
            padding: 25px;
        }

        .alert-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .alert-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .alert-item:last-child {
            margin-bottom: 0;
        }

        .alert-item.warning {
            border-left: 4px solid #ffc107;
            background: #fff8e1;
        }

        .alert-item.info {
            border-left: 4px solid #17a2b8;
            background: #e3f2fd;
        }

        .alert-item.success {
            border-left: 4px solid #28a745;
            background: #e8f5e8;
        }

        .alert-header {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .alert-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 0.9rem;
            color: white;
        }

        .alert-icon.warning { background: #ffc107; }
        .alert-icon.info { background: #17a2b8; }
        .alert-icon.success { background: #28a745; }

        .alert-title {
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .alert-message {
            color: #718096;
            font-size: 0.9rem;
            margin-left: 42px;
        }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background: #f8f9fa;
            border: none;
            color: #495057;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 15px;
        }

        .table td {
            border: none;
            border-bottom: 1px solid #f1f3f4;
            padding: 15px;
            vertical-align: middle;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar-small {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
            margin-right: 10px;
            background: #6c757d;
        }

        .user-details h6 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .user-details small {
            color: #718096;
            font-size: 0.8rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
            font-size: 0.8rem;
        }

        .activity-content {
            flex-grow: 1;
        }

        .activity-user {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .activity-action {
            color: #718096;
            font-size: 0.8rem;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #718096;
            white-space: nowrap;
        }

        .event-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .event-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .event-card:last-child {
            margin-bottom: 0;
        }

        .event-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .event-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .event-meta {
            display: flex;
            gap: 15px;
            font-size: 0.8rem;
            color: #718096;
        }

        .event-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<!-- Admin Navigation -->
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
                    <a class="nav-link active" href="dashboard.php">
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
                    <a class="nav-link" href="analytics.php">
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
                <!-- Current Time Display - IST -->
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

<div class="container-fluid mt-4">
    <?php if ($message): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Stats Cards - Clean Start -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <small class="text-success">
                <i class="fas fa-arrow-up me-1"></i>
                +<?php echo $stats['new_users_week']; ?> this week
            </small>
        </div>

        <div class="stat-card success">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($stats['total_events']); ?></div>
                    <div class="stat-label">Total Events</div>
                </div>
                <div class="stat-icon success">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>
            <small class="text-info">
                <i class="fas fa-check me-1"></i>
                <?php echo $stats['published_events']; ?> published
            </small>
        </div>

        <div class="stat-card warning">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($stats['total_registrations']); ?></div>
                    <div class="stat-label">Total Registrations</div>
                </div>
                <div class="stat-icon warning">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
            <small class="text-success">
                <i class="fas fa-plus me-1"></i>
                +<?php echo $stats['registrations_today']; ?> today
            </small>
        </div>

        <div class="stat-card info">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo number_format($stats['events_today']); ?></div>
                    <div class="stat-label">Events Today</div>
                </div>
                <div class="stat-icon info">
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
            <small class="text-primary">
                <i class="fas fa-clock me-1"></i>
                Happening now
            </small>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="content-grid">
        <!-- Left Column -->
        <div>
            <!-- System Alerts -->
            <?php if (!empty($alerts)): ?>
                <div class="dashboard-card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            System Alerts
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($alerts as $alert): ?>
                            <div class="alert-item <?php echo $alert['type']; ?>" onclick="window.location.href='<?php echo $alert['action']; ?>'">
                                <div class="alert-header">
                                    <div class="alert-icon <?php echo $alert['type']; ?>">
                                        <i class="fas <?php echo $alert['icon']; ?>"></i>
                                    </div>
                                    <h6 class="alert-title"><?php echo $alert['title']; ?></h6>
                                </div>
                                <div class="alert-message"><?php echo $alert['message']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Events -->
            <div class="dashboard-card mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-calendar-alt"></i>
                        Recent Events
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_events)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No recent events found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_events as $event):
                            $created_by_display = getDataUserDisplayName([
                                'username' => $event['created_by_username'],
                                'first_name' => $event['created_by_first_name'],
                                'last_name' => $event['created_by_last_name']
                            ]);
                            ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <div class="flex-grow-1">
                                        <h6 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h6>
                                        <div class="event-meta">
                                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['venue']); ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo convertUTCtoIST($event['event_date'], 'M d, Y'); ?></span>
                                            <span><i class="fas fa-users"></i> <?php echo $event['participant_count']; ?>/<?php echo $event['max_participants']; ?></span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <?php echo getEventStatusBadge($event); ?>
                                        <small class="d-block text-muted mt-1">by <?php echo htmlspecialchars($created_by_display); ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-plus"></i>
                        Recent Users
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($recent_users)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3">
                                        <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">No users found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_users as $user):
                                    $user_display_name = getDataUserDisplayName($user);
                                    $user_initials_small = getDataUserInitials($user);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar-small">
                                                    <?php echo $user_initials_small; ?>
                                                </div>
                                                <div class="user-details">
                                                    <h6><?php echo htmlspecialchars($user_display_name); ?></h6>
                                                    <small>@<?php echo htmlspecialchars($user['username']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                        </td>
                                        <td>
                                                    <span data-utc="<?php echo $user['created_at']; ?>">
                                                        <?php echo convertUTCtoIST($user['created_at'], 'M d, Y'); ?>
                                                    </span>
                                        </td>
                                        <td>
                                                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Upcoming Events -->
            <div class="dashboard-card mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock"></i>
                        Upcoming Events
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_events)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-calendar-day fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No upcoming events</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_events as $event): ?>
                            <div class="event-card">
                                <h6 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h6>
                                <div class="event-meta">
                                    <span><i class="fas fa-calendar"></i> <?php echo convertUTCtoIST($event['event_date'] . ' ' . $event['event_time'], 'M d - h:i A'); ?></span>
                                    <span><i class="fas fa-users"></i> <?php echo $event['participant_count']; ?> registered</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history"></i>
                        Recent Activity
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activity)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-history fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No recent activity</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $activity):
                            $activity_color = getActivityColor($activity['action']);
                            $activity_icon = getActivityIcon($activity['action']);
                            $activity_user_display = getDataUserDisplayName($activity);
                            ?>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: <?php echo $activity_color; ?>;">
                                    <i class="fas <?php echo $activity_icon; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-user"><?php echo htmlspecialchars($activity_user_display); ?></div>
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
</div>

<!-- Load time utilities first -->
<script src="../assets/js/time-utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    // Dashboard specific IST time management
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize time manager
        if (typeof timeManager !== 'undefined') {
            timeManager.startAutoUpdate();
        } else {
            // Fallback time update for navbar
            function updateCurrentTime() {
                const now = new Date();
                const options = {
                    timeZone: 'Asia/Kolkata',
                    year: 'numeric',
                    month: 'short',
                    day: '2-digit',
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                };

                const istTime = now.toLocaleString('en-IN', options);
                const timeElement = document.getElementById('currentTime');
                if (timeElement) {
                    timeElement.innerHTML = `<i class="fas fa-clock me-1"></i>${istTime} IST`;
                }
            }

            updateCurrentTime();
            setInterval(updateCurrentTime, 1000);
        }

        // Auto-refresh dashboard every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    });

    console.log('Admin Dashboard loaded successfully with IST timezone');
    console.log('Current Admin: <?php echo $display_name; ?>');
    console.log('Current Username: <?php echo $current_user; ?>');
    console.log('UTC Time: <?php echo $current_utc; ?>');
    console.log('IST Time: <?php echo $current_ist_time; ?>');
</script>
</body>
</html>
<?php
$page_title = "Activity Monitor";
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is admin
requireAdmin();

// Get current IST time and user info for display
$current_ist_time = getCurrentDisplayTime();
$current_utc = '2025-08-04 14:11:27';
$current_user_data = getCurrentUser();
$current_user = $current_user_data['username']; // Keep for logging
$display_name = getUserDisplayName($current_user_data);
$user_initials = getUserInitials($current_user_data);

$pdo = getDBConnection();
$message = '';
$message_type = '';

// Get filter parameters
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user_id'] ?? '';
$date_filter = $_GET['date'] ?? '';
$table_filter = $_GET['table'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE conditions
$conditions = ['1=1'];
$params = [];

if (!empty($action_filter)) {
    $conditions[] = "activity_logs.action LIKE ?";
    $params[] = "%$action_filter%";
}

if (!empty($user_filter)) {
    $conditions[] = "activity_logs.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($date_filter)) {
    $conditions[] = "DATE(activity_logs.created_at) = ?";
    $params[] = $date_filter;
}

if (!empty($table_filter)) {
    $conditions[] = "activity_logs.table_name = ?";
    $params[] = $table_filter;
}

if (!empty($search)) {
    $conditions[] = "(activity_logs.action LIKE ? OR users.username LIKE ? OR users.first_name LIKE ? OR users.last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) 
              FROM activity_logs 
              LEFT JOIN users ON activity_logs.user_id = users.id 
              WHERE $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get activity logs with enhanced details
$sql = "SELECT 
        activity_logs.id,
        activity_logs.user_id,
        activity_logs.action,
        activity_logs.table_name,
        activity_logs.record_id,
        activity_logs.old_values,
        activity_logs.new_values,
        activity_logs.ip_address,
        activity_logs.user_agent,
        activity_logs.created_at,
        users.username,
        users.first_name,
        users.last_name,
        users.role
        FROM activity_logs 
        LEFT JOIN users ON activity_logs.user_id = users.id 
        WHERE $where_clause
        ORDER BY activity_logs.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$per_page, $offset]));
$activities = $stmt->fetchAll();

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total_activities,
              COUNT(DISTINCT user_id) as unique_users,
              COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_count,
              COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as last_hour,
              COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h
              FROM activity_logs";
$stats = $pdo->query($stats_sql)->fetch();

// Get top actions
$top_actions_sql = "SELECT action, COUNT(*) as count 
                    FROM activity_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY action 
                    ORDER BY count DESC 
                    LIMIT 5";
$top_actions = $pdo->query($top_actions_sql)->fetchAll();

// Get unique users for filter dropdown (with display names)
$users_sql = "SELECT DISTINCT u.id, u.username, u.first_name, u.last_name 
              FROM users u 
              INNER JOIN activity_logs al ON u.id = al.user_id 
              ORDER BY u.username";
$users = $pdo->query($users_sql)->fetchAll();

// Get unique tables for filter dropdown
$tables_sql = "SELECT DISTINCT table_name FROM activity_logs WHERE table_name IS NOT NULL ORDER BY table_name";
$tables = $pdo->query($tables_sql)->fetchAll();

// Helper functions
function getActivityIcon($action) {
    $icons = [
        'login' => 'fa-sign-in-alt',
        'logout' => 'fa-sign-out-alt',
        'user_create' => 'fa-user-plus',
        'user_update' => 'fa-user-edit',
        'user_delete' => 'fa-user-minus',
        'event_create' => 'fa-calendar-plus',
        'event_update' => 'fa-calendar-edit',
        'event_delete' => 'fa-calendar-minus',
        'event_join' => 'fa-user-check',
        'event_leave' => 'fa-user-times',
        'page_view' => 'fa-eye',
        'admin_login' => 'fa-shield-alt',
        'database_setup' => 'fa-database',
        'settings_update' => 'fa-cog'
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
        'page_view' => '#6f42c1',
        'admin' => '#e83e8c',
        'database' => '#17a2b8',
        'settings' => '#495057'
    ];

    foreach ($colors as $key => $color) {
        if (strpos($action, $key) !== false) {
            return $color;
        }
    }

    return '#667eea';
}

function formatActivityData($data) {
    if (empty($data)) return '';

    $decoded = json_decode($data, true);
    if ($decoded === null) return htmlspecialchars($data);

    $output = '';
    foreach ($decoded as $key => $value) {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        $output .= '<span class="badge bg-light text-dark me-1 mb-1">' .
            htmlspecialchars($key) . ': ' . htmlspecialchars($value) . '</span>';
    }

    return $output;
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

// Helper function to get user initials from activity data
function getActivityUserInitials($activity) {
    if (!$activity['username']) return 'S';

    $firstName = trim($activity['first_name'] ?? '');
    $lastName = trim($activity['last_name'] ?? '');
    $username = trim($activity['username'] ?? '');

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

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            margin-bottom: 10px;
        }

        .stats-icon.total { background: var(--primary-gradient); }
        .stats-icon.users { background: var(--success-gradient); }
        .stats-icon.today { background: var(--warning-gradient); }
        .stats-icon.hour { background: var(--info-gradient); }

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

        .activity-stream-widget {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .activity-stream-widget::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: 1;
        }

        .widget-header {
            padding: 25px 30px 20px;
            color: white;
            position: relative;
            z-index: 2;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .widget-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .widget-title i {
            margin-right: 12px;
            font-size: 1.3rem;
        }

        .activity-count {
            background: #fff;
            color: #667eea;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-left: auto;
        }

        .activity-container {
            padding: 10px;
            background: #2c3e50;
            border-radius: 0 0 20px 20px;
            max-height: 600px;
            overflow-y: auto;
        }

        .activity-container::-webkit-scrollbar {
            width: 6px;
        }

        .activity-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .activity-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .activity-item {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 12px;
            position: relative;
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
            cursor: pointer;
        }

        .activity-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        .activity-header {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
            font-size: 0.9rem;
        }

        .activity-user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
            margin-right: 12px;
            background: #6c757d;
        }

        .activity-info {
            flex-grow: 1;
        }

        .activity-user {
            font-size: 1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .activity-username {
            font-size: 0.8rem;
            color: #6c757d;
            margin: 0;
        }

        .activity-action {
            font-size: 0.9rem;
            color: #6c757d;
            margin: 0;
        }

        .activity-time {
            color: #667eea;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 3px 10px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            white-space: nowrap;
        }

        .activity-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.85rem;
        }

        .activity-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f1f3f4;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .search-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .pagination-wrapper {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .no-activities {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.8);
        }

        .no-activities i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.6;
        }

        .no-activities h5 {
            color: white;
            margin-bottom: 10px;
        }

        .table-name-badge {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .ip-address {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        @media (max-width: 768px) {
            .activity-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .activity-time {
                margin-top: 8px;
            }

            .activity-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
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
                    <a class="nav-link active" href="activity.php">
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
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-history me-2"></i>Activity Monitor</h2>
                    <p class="text-muted mb-0">Real-time system activity tracking and monitoring</p>
                    <small class="text-muted">Current Admin: <?php echo $display_name; ?> | Current Time: <?php echo $current_ist_time; ?> IST</small>
                </div>
                <div>
                    <a href="activity.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </a>
                    <button class="btn btn-primary" onclick="exportActivityLog()">
                        <i class="fas fa-download me-1"></i>Export Log
                    </button>
                </div>
            </div>
        </div>
    </div>

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

    <!-- Activity Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-icon total">
                    <i class="fas fa-list"></i>
                </div>
                <h4 class="mb-0"><?php echo number_format($stats['total_activities']); ?></h4>
                <small class="text-muted">Total Activities</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <h4 class="mb-0"><?php echo $stats['unique_users']; ?></h4>
                <small class="text-muted">Active Users</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-icon today">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <h4 class="mb-0"><?php echo $stats['today_count']; ?></h4>
                <small class="text-muted">Today's Activities</small>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-icon hour">
                    <i class="fas fa-clock"></i>
                </div>
                <h4 class="mb-0"><?php echo $stats['last_hour']; ?></h4>
                <small class="text-muted">Last Hour</small>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Filters and Search -->
        <div class="col-lg-4 mb-4">
            <div class="search-section">
                <h5 class="mb-3"><i class="fas fa-search me-2"></i>Search Activities</h5>
                <form method="GET" action="activity.php">
                    <div class="mb-3">
                        <input type="text" class="form-control" name="search"
                               placeholder="Search actions, users, or data..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="mb-3">
                        <select class="form-select" name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user):
                                $user_display_name = getUserDisplayName($user);
                                ?>
                                <option value="<?php echo $user['id']; ?>"
                                    <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user_display_name); ?>
                                    <?php if ($user_display_name !== $user['username']): ?>
                                        (@<?php echo htmlspecialchars($user['username']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <select class="form-select" name="table">
                            <option value="">All Tables</option>
                            <?php foreach ($tables as $table): ?>
                                <option value="<?php echo htmlspecialchars($table['table_name']); ?>"
                                    <?php echo $table_filter == $table['table_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($table['table_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <input type="date" class="form-control" name="date"
                               value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                    <div class="mb-3">
                        <input type="text" class="form-control" name="action"
                               placeholder="Filter by action..."
                               value="<?php echo htmlspecialchars($action_filter); ?>">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i>Apply Filters
                        </button>
                        <a href="activity.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear All
                        </a>
                    </div>
                </form>
            </div>

            <!-- Top Actions -->
            <div class="filter-section">
                <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Top Actions (7 days)</h5>
                <?php if (empty($top_actions)): ?>
                    <p class="text-muted">No activity data available</p>
                <?php else: ?>
                    <?php foreach ($top_actions as $action): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small"><?php echo htmlspecialchars($action['action']); ?></span>
                            <span class="badge bg-primary"><?php echo $action['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Stream -->
        <div class="col-lg-8 mb-4">
            <div class="activity-stream-widget">
                <div class="widget-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="widget-title">
                            <i class="fas fa-stream"></i>
                            Activity Stream
                        </h3>
                        <span class="activity-count"><?php echo count($activities); ?></span>
                    </div>
                </div>

                <div class="activity-container">
                    <?php if (empty($activities)): ?>
                        <div class="no-activities">
                            <i class="fas fa-history"></i>
                            <h5>No Activities Found</h5>
                            <p>No activities match your current filters</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activities as $activity):
                            $activity_color = getActivityColor($activity['action']);
                            $activity_icon = getActivityIcon($activity['action']);
                            $user_display_name = getActivityUserDisplayName($activity);
                            $user_initials = getActivityUserInitials($activity);
                            ?>
                            <div class="activity-item" style="border-left-color: <?php echo $activity_color; ?>;">
                                <div class="activity-header">
                                    <div class="activity-user-avatar" style="background: <?php echo $activity_color; ?>;">
                                        <?php echo $user_initials; ?>
                                    </div>
                                    <div class="activity-icon" style="background: <?php echo $activity_color; ?>;">
                                        <i class="fas <?php echo $activity_icon; ?>"></i>
                                    </div>
                                    <div class="activity-info">
                                        <h6 class="activity-user"><?php echo htmlspecialchars($user_display_name); ?></h6>
                                        <?php if ($user_display_name !== $activity['username'] && $activity['username']): ?>
                                            <p class="activity-username">@<?php echo htmlspecialchars($activity['username']); ?></p>
                                        <?php endif; ?>
                                        <p class="activity-action">performed <strong><?php echo htmlspecialchars($activity['action']); ?></strong></p>
                                    </div>
                                    <div class="activity-time" data-time-ago="<?php echo $activity['created_at']; ?>">
                                        <?php echo getTimeAgo($activity['created_at']); ?>
                                    </div>
                                </div>

                                <?php if (!empty($activity['new_values'])): ?>
                                    <div class="activity-details">
                                        <strong>Details:</strong><br>
                                        <?php echo formatActivityData($activity['new_values']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="activity-meta">
                                    <div>
                                        <i class="fas fa-clock me-1"></i>
                                        <span data-utc="<?php echo $activity['created_at']; ?>">
                                                <?php echo convertUTCtoIST($activity['created_at'], 'M d, Y - h:i:s A'); ?>
                                            </span>

                                        <?php if ($activity['table_name']): ?>
                                            <span class="table-name-badge ms-2"><?php echo htmlspecialchars($activity['table_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="ip-address"><?php echo htmlspecialchars($activity['ip_address']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
            <nav aria-label="Activity pagination">
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);

                    for ($i = $start; $i <= $end; $i++):
                        ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="text-center mt-3">
                <small class="text-muted">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?>
                    of <?php echo number_format($total_records); ?> activities
                </small>
            </div>
        </div>
    <?php endif; ?>

</div> <!-- End container-fluid -->

<!-- Load time utilities first -->
<script src="../assets/js/time-utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    // Activity page specific IST time management
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
    });

    // Export activity log function
    function exportActivityLog() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'csv');
        window.open('activity.php?' + params.toString());
    }

    // Auto-refresh activities every 30 seconds
    function refreshActivities() {
        setTimeout(() => {
            location.reload();
        }, 30000);
    }

    // Add click handlers for activity items
    document.querySelectorAll('.activity-item').forEach(item => {
        item.addEventListener('click', function() {
            console.log('Activity item clicked');
        });
    });

    console.log('Activity Monitor initialized with IST timezone');
    console.log('Current admin: <?php echo $display_name; ?>');
    console.log('UTC Time: <?php echo $current_utc; ?>');
    console.log('IST Time: <?php echo $current_ist_time; ?>');
    console.log('Total activities displayed: <?php echo count($activities); ?>');
</script>
</body>
</html>
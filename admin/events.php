<?php
$page_title = "Manage Events";
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is admin
requireAdmin();

// Get current IST time and user info for display
$current_ist_time = getCurrentDisplayTime();
$current_utc = '2025-08-04 14:26:33';
$current_user_data = getCurrentUser();
$current_user = $current_user_data['username']; // Keep for logging
$display_name = getUserDisplayName($current_user_data);
$user_initials = getUserInitials($current_user_data);

$pdo = getDBConnection();
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'create' || $action === 'edit') {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $sport_name = sanitize($_POST['sport_name']);
        $event_date = $_POST['event_date'];
        $event_time = $_POST['event_time'];
        $venue = sanitize($_POST['venue']);
        $max_participants = (int)$_POST['max_participants'];
        $registration_deadline = $_POST['registration_deadline'];
        $status = sanitize($_POST['status']);

        if ($action === 'create') {
            // Validation
            if (empty($title) || empty($sport_name) || empty($event_date) || empty($event_time) || empty($venue)) {
                $message = 'Please fill in all required fields.';
                $message_type = 'danger';
            } else {
                // Create new event
                $stmt = $pdo->prepare("INSERT INTO events (title, description, sport_name, event_date, event_time, venue, max_participants, registration_deadline, status, created_by, created_at, updated_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $description, $sport_name, $event_date, $event_time, $venue, $max_participants, $registration_deadline, $status, $_SESSION['user_id'], getCurrentUTCTime(), getCurrentUTCTime()])) {
                    $message = "Event '$title' created successfully!";
                    $message_type = 'success';

                    // Log activity
                    logActivity('event_create', ['title' => $title, 'sport' => $sport_name, 'date' => $event_date], $_SESSION['user_id']);
                } else {
                    $message = 'Error creating event.';
                    $message_type = 'danger';
                }
            }
        } else {
            // Edit event
            $event_id = (int)$_POST['event_id'];

            $stmt = $pdo->prepare("UPDATE events SET title=?, description=?, sport_name=?, event_date=?, event_time=?, venue=?, max_participants=?, registration_deadline=?, status=?, updated_at=? WHERE id=?");
            if ($stmt->execute([$title, $description, $sport_name, $event_date, $event_time, $venue, $max_participants, $registration_deadline, $status, getCurrentUTCTime(), $event_id])) {
                $message = "Event '$title' updated successfully!";
                $message_type = 'success';

                // Log activity
                logActivity('event_update', ['event_id' => $event_id, 'title' => $title], $_SESSION['user_id']);
            } else {
                $message = 'Error updating event.';
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'bulk_delete') {
        $event_ids = $_POST['event_ids'] ?? [];
        if (!empty($event_ids)) {
            $placeholders = implode(',', array_fill(0, count($event_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM events WHERE id IN ($placeholders)");

            if ($stmt->execute($event_ids)) {
                $count = $stmt->rowCount();
                $message = "Successfully deleted $count event(s).";
                $message_type = 'success';

                // Log activity
                logActivity('bulk_event_delete', ['count' => $count, 'event_ids' => $event_ids], $_SESSION['user_id']);
            } else {
                $message = 'Error deleting events.';
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'bulk_publish') {
        $event_ids = $_POST['event_ids'] ?? [];
        if (!empty($event_ids)) {
            $placeholders = implode(',', array_fill(0, count($event_ids), '?'));
            $stmt = $pdo->prepare("UPDATE events SET status = 'published', updated_at = ? WHERE id IN ($placeholders)");

            if ($stmt->execute(array_merge([getCurrentUTCTime()], $event_ids))) {
                $count = $stmt->rowCount();
                $message = "Successfully published $count event(s).";
                $message_type = 'success';
            }
        }
    } elseif ($action === 'bulk_draft') {
        $event_ids = $_POST['event_ids'] ?? [];
        if (!empty($event_ids)) {
            $placeholders = implode(',', array_fill(0, count($event_ids), '?'));
            $stmt = $pdo->prepare("UPDATE events SET status = 'draft', updated_at = ? WHERE id IN ($placeholders)");

            if ($stmt->execute(array_merge([getCurrentUTCTime()], $event_ids))) {
                $count = $stmt->rowCount();
                $message = "Successfully moved $count event(s) to draft.";
                $message_type = 'success';
            }
        }
    }
}

// Handle single event delete
if (isset($_GET['delete'])) {
    $event_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    if ($stmt->execute([$event_id])) {
        $message = "Event deleted successfully!";
        $message_type = "success";

        // Log activity
        logActivity('event_delete', ['event_id' => $event_id], $_SESSION['user_id']);
    } else {
        $message = "Error deleting event.";
        $message_type = "danger";
    }
}

// Get current action
$current_action = $_GET['action'] ?? 'list';
$edit_event = null;

if ($current_action === 'edit' && isset($_GET['id'])) {
    // Get event details with participant count and creator info
    $stmt = $pdo->prepare("SELECT e.*, 
                           (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.registration_status != 'cancelled') as participant_count,
                           u.username as created_by_username,
                           u.first_name as created_by_first_name,
                           u.last_name as created_by_last_name
                           FROM events e 
                           LEFT JOIN users u ON e.created_by = u.id 
                           WHERE e.id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $edit_event = $stmt->fetch();
    if (!$edit_event) {
        $current_action = 'list';
    }
}

if ($current_action === 'participants' && isset($_GET['id'])) {
    $event_id = (int)$_GET['id'];

    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event_details = $stmt->fetch();

    if (!$event_details) {
        $current_action = 'list';
    } else {
        // Get participants with enhanced user info
        $stmt = $pdo->prepare("SELECT ep.*, u.username, u.first_name, u.last_name, u.email, u.phone 
                               FROM event_participants ep 
                               JOIN users u ON ep.user_id = u.id 
                               WHERE ep.event_id = ? AND ep.registration_status != 'cancelled'
                               ORDER BY ep.joined_at ASC");
        $stmt->execute([$event_id]);
        $participants = $stmt->fetchAll();
    }
}

// Get all events with enhanced details
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sport_filter = $_GET['sport'] ?? '';

$conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $conditions[] = "(title LIKE ? OR description LIKE ? OR venue LIKE ? OR sport_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($sport_filter)) {
    $conditions[] = "sport_name = ?";
    $params[] = $sport_filter;
}

$where_clause = implode(' AND ', $conditions);

$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.registration_status != 'cancelled') as participant_count,
        u.username as created_by_username,
        u.first_name as created_by_first_name,
        u.last_name as created_by_last_name
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.id 
        WHERE $where_clause
        ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Get event statistics
$stats_sql = "SELECT 
              COUNT(*) as total_events,
              COUNT(CASE WHEN status = 'published' THEN 1 END) as published_count,
              COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_count,
              COUNT(CASE WHEN event_date >= CURDATE() THEN 1 END) as upcoming_count,
              COUNT(CASE WHEN event_date < CURDATE() THEN 1 END) as completed_count,
              COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
              FROM events";
$event_stats = $pdo->query($stats_sql)->fetch();

// Get unique sports for filter
$sports_sql = "SELECT DISTINCT sport_name FROM events ORDER BY sport_name";
$sports = $pdo->query($sports_sql)->fetchAll();

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

// Helper function to get user display name from event data
function getEventUserDisplayName($eventData, $prefix = 'created_by_') {
    if (!$eventData || !$eventData[$prefix . 'username']) return 'System';

    $firstName = trim($eventData[$prefix . 'first_name'] ?? '');
    $lastName = trim($eventData[$prefix . 'last_name'] ?? '');

    if (!empty($firstName) && !empty($lastName)) {
        return $firstName . ' ' . $lastName;
    } elseif (!empty($firstName)) {
        return $firstName;
    } elseif (!empty($lastName)) {
        return $lastName;
    } else {
        return $eventData[$prefix . 'username'];
    }
}

// Helper function to get participant display name
function getParticipantDisplayName($participant) {
    $firstName = trim($participant['first_name'] ?? '');
    $lastName = trim($participant['last_name'] ?? '');

    if (!empty($firstName) && !empty($lastName)) {
        return $firstName . ' ' . $lastName;
    } elseif (!empty($firstName)) {
        return $firstName;
    } elseif (!empty($lastName)) {
        return $lastName;
    } else {
        return $participant['username'];
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
        .stats-icon.published { background: var(--success-gradient); }
        .stats-icon.upcoming { background: var(--warning-gradient); }
        .stats-icon.new { background: var(--info-gradient); }

        .search-filter-widget {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .events-list-widget {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .widget-header {
            padding: 25px 30px 20px;
            color: white;
            position: relative;
            z-index: 2;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .events-container {
            padding: 10px;
            background: #2c3e50;
            border-radius: 0 0 20px 20px;
            max-height: 600px;
            overflow-y: auto;
        }

        .event-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 12px;
            position: relative;
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
            cursor: pointer;
        }

        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .event-datetime {
            color: #667eea;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .event-actions {
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .event-card:hover .event-actions {
            opacity: 1;
        }

        .participant-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 3px solid #667eea;
        }

        .participant-avatar {
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
                    <a class="nav-link active" href="events.php">
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
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-calendar-alt me-2"></i>Event Management</h2>
                    <p class="text-muted mb-0">Create, edit, and manage sports events</p>
                    <small class="text-muted">Current Admin: <?php echo $display_name; ?> | Current Time: <?php echo $current_ist_time; ?> IST</small>
                </div>
                <?php if ($current_action === 'list'): ?>
                    <a href="events.php?action=create" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Create New Event
                    </a>
                <?php else: ?>
                    <a href="events.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to List
                    </a>
                <?php endif; ?>
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

    <?php if ($current_action === 'list'): ?>
        <!-- Event Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon total">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <h4 class="mb-0"><?php echo $event_stats['total_events']; ?></h4>
                    <small class="text-muted">Total Events</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon published">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h4 class="mb-0"><?php echo $event_stats['published_count']; ?></h4>
                    <small class="text-muted">Published</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon upcoming">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h4 class="mb-0"><?php echo $event_stats['upcoming_count']; ?></h4>
                    <small class="text-muted">Upcoming</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon new">
                        <i class="fas fa-plus"></i>
                    </div>
                    <h4 class="mb-0"><?php echo $event_stats['new_this_week']; ?></h4>
                    <small class="text-muted">New This Week</small>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filter-widget">
            <h5 class="mb-3"><i class="fas fa-search me-2"></i>Search Events</h5>
            <form method="GET" class="row align-items-end">
                <div class="col-md-4 mb-3">
                    <input type="text" class="form-control" name="search"
                           placeholder="Search by title, venue, or description..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <select class="form-select" name="sport">
                        <option value="">All Sports</option>
                        <?php foreach ($sports as $sport): ?>
                            <option value="<?php echo htmlspecialchars($sport['sport_name']); ?>"
                                <?php echo $sport_filter === $sport['sport_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sport['sport_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Events List -->
        <div class="events-list-widget">
            <div class="widget-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="widget-title">
                        <i class="fas fa-list"></i>
                        All Events
                    </h3>
                    <span class="badge bg-light text-dark"><?php echo count($events); ?></span>
                </div>
            </div>

            <div class="events-container">
                <?php if (empty($events)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-4x text-white mb-3 opacity-50"></i>
                        <h5 class="text-white">No events found</h5>
                        <p class="text-white opacity-75">Create your first event to get started!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event):
                        $sport_color = getSportColor($event['sport_name']);
                        $sport_icon = getSportIcon($event['sport_name']);
                        $is_upcoming = strtotime($event['event_date']) >= strtotime(date('Y-m-d'));
                        $created_by_display = getEventUserDisplayName($event);
                        ?>
                        <div class="event-card" style="border-left-color: <?php echo $sport_color; ?>;">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($event['title']); ?></h6>
                                        <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-<?php echo $event['status'] === 'published' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($event['status']); ?>
                                                </span>
                                            <?php if ($is_upcoming): ?>
                                                <span class="badge bg-primary">Upcoming</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Completed</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center mb-2">
                                        <div style="background: <?php echo $sport_color; ?>; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; margin-right: 8px;">
                                            <i class="fas <?php echo $sport_icon; ?>" style="font-size: 0.7rem;"></i>
                                        </div>
                                        <span class="text-muted"><?php echo htmlspecialchars($event['sport_name']); ?></span>
                                        <span class="text-muted ms-2">• <?php echo htmlspecialchars($event['venue']); ?></span>
                                    </div>

                                    <div class="event-datetime" data-utc="<?php echo $event['event_date'] . ' ' . $event['event_time']; ?>">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo convertUTCtoIST($event['event_date'] . ' ' . $event['event_time'], 'M d, Y - h:i A'); ?>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo $event['participant_count']; ?>/<?php echo $event['max_participants']; ?> participants
                                        </small>
                                        <small class="text-muted">
                                            Created by: <?php echo htmlspecialchars($created_by_display); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="event-actions ms-3">
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewParticipants(<?php echo $event['id']; ?>)" title="View Participants">
                                        <i class="fas fa-users"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="editEvent(<?php echo $event['id']; ?>)" title="Edit Event">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteEvent(<?php echo $event['id']; ?>)" title="Delete Event">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($current_action === 'participants'): ?>
        <!-- Participants View -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            Participants - <?php echo htmlspecialchars($event_details['title']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Event Details:</strong><br>
                            <span class="text-muted">
                                    <?php echo htmlspecialchars($event_details['sport_name']); ?> •
                                    <?php echo htmlspecialchars($event_details['venue']); ?> •
                                    <span data-utc="<?php echo $event_details['event_date'] . ' ' . $event_details['event_time']; ?>">
                                        <?php echo convertUTCtoIST($event_details['event_date'] . ' ' . $event_details['event_time'], 'M d, Y - h:i A'); ?>
                                    </span>
                                </span>
                        </div>

                        <?php if (empty($participants)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h6>No Participants Yet</h6>
                                <p class="text-muted">No one has registered for this event yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($participants as $participant):
                                    $participant_display_name = getParticipantDisplayName($participant);
                                    $participant_initials = getUserInitials($participant);
                                    ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="participant-card">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="d-flex align-items-center">
                                                    <div class="participant-avatar">
                                                        <?php echo $participant_initials; ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($participant_display_name); ?></h6>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($participant['username']); ?></small><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($participant['email']); ?></small>
                                                        <?php if ($participant['phone']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($participant['phone']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted" data-utc="<?php echo $participant['joined_at']; ?>">
                                                        Joined:<br><?php echo convertUTCtoIST($participant['joined_at'], 'M d, h:i A'); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Create/Edit Event Form -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $current_action === 'create' ? 'plus' : 'edit'; ?> me-2"></i>
                            <?php echo $current_action === 'create' ? 'Create New Event' : 'Edit Event'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $current_action; ?>">
                            <?php if ($edit_event): ?>
                                <input type="hidden" name="event_id" value="<?php echo $edit_event['id']; ?>">
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Event Title *</label>
                                    <input type="text" class="form-control" id="title" name="title"
                                           value="<?php echo $edit_event ? htmlspecialchars($edit_event['title']) : ''; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="sport_name" class="form-label">Sport *</label>
                                    <select class="form-select" id="sport_name" name="sport_name" required>
                                        <option value="">Select Sport</option>
                                        <option value="Football" <?php echo ($edit_event && $edit_event['sport_name'] === 'Football') ? 'selected' : ''; ?>>Football</option>
                                        <option value="Basketball" <?php echo ($edit_event && $edit_event['sport_name'] === 'Basketball') ? 'selected' : ''; ?>>Basketball</option>
                                        <option value="Tennis" <?php echo ($edit_event && $edit_event['sport_name'] === 'Tennis') ? 'selected' : ''; ?>>Tennis</option>
                                        <option value="Cricket" <?php echo ($edit_event && $edit_event['sport_name'] === 'Cricket') ? 'selected' : ''; ?>>Cricket</option>
                                        <option value="Swimming" <?php echo ($edit_event && $edit_event['sport_name'] === 'Swimming') ? 'selected' : ''; ?>>Swimming</option>
                                        <option value="Running" <?php echo ($edit_event && $edit_event['sport_name'] === 'Running') ? 'selected' : ''; ?>>Running</option>
                                        <option value="Cycling" <?php echo ($edit_event && $edit_event['sport_name'] === 'Cycling') ? 'selected' : ''; ?>>Cycling</option>
                                        <option value="Volleyball" <?php echo ($edit_event && $edit_event['sport_name'] === 'Volleyball') ? 'selected' : ''; ?>>Volleyball</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo $edit_event ? htmlspecialchars($edit_event['description']) : ''; ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="event_date" class="form-label">Event Date *</label>
                                    <input type="date" class="form-control" id="event_date" name="event_date"
                                           value="<?php echo $edit_event ? $edit_event['event_date'] : ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="event_time" class="form-label">Event Time *</label>
                                    <input type="time" class="form-control" id="event_time" name="event_time"
                                           value="<?php echo $edit_event ? $edit_event['event_time'] : ''; ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="venue" class="form-label">Venue *</label>
                                    <input type="text" class="form-control" id="venue" name="venue"
                                           value="<?php echo $edit_event ? htmlspecialchars($edit_event['venue']) : ''; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="max_participants" class="form-label">Max Participants *</label>
                                    <input type="number" class="form-control" id="max_participants" name="max_participants"
                                           value="<?php echo $edit_event ? $edit_event['max_participants'] : '50'; ?>" min="1" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="registration_deadline" class="form-label">Registration Deadline</label>
                                    <input type="date" class="form-control" id="registration_deadline" name="registration_deadline"
                                           value="<?php echo $edit_event ? $edit_event['registration_deadline'] : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="draft" <?php echo (!$edit_event || $edit_event['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                        <option value="published" <?php echo ($edit_event && $edit_event['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    <?php echo $current_action === 'create' ? 'Create Event' : 'Update Event'; ?>
                                </button>
                                <a href="events.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($edit_event): ?>
                <!-- Event Statistics Sidebar -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Event Statistics</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <h5 class="text-primary"><?php echo $edit_event['participant_count']; ?></h5>
                                    <small class="text-muted">Participants</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <h5 class="text-info"><?php echo $edit_event['max_participants']; ?></h5>
                                    <small class="text-muted">Max Capacity</small>
                                </div>
                            </div>

                            <hr>

                            <div class="mb-2">
                                <strong>Created:</strong><br>
                                <small class="text-muted" data-utc="<?php echo $edit_event['created_at']; ?>">
                                    <?php echo convertUTCtoIST($edit_event['created_at'], 'F d, Y \a\t h:i A'); ?>
                                </small>
                            </div>

                            <div class="mb-2">
                                <strong>Last Updated:</strong><br>
                                <small class="text-muted" data-utc="<?php echo $edit_event['updated_at']; ?>">
                                    <?php echo convertUTCtoIST($edit_event['updated_at'], 'F d, Y \a\t h:i A'); ?>
                                </small>
                            </div>

                            <?php if ($edit_event['created_by_username']): ?>
                                <div class="mb-2">
                                    <strong>Created By:</strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars(getEventUserDisplayName($edit_event)); ?></small>
                                </div>
                            <?php endif; ?>

                            <hr>

                            <a href="events.php?action=participants&id=<?php echo $edit_event['id']; ?>" class="btn btn-outline-info btn-sm w-100">
                                <i class="fas fa-users me-1"></i>View Participants
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div> <!-- End container-fluid -->

<!-- Load time utilities first -->
<script src="../assets/js/time-utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    // Events admin specific IST time management
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

    function editEvent(eventId) {
        window.location.href = `events.php?action=edit&id=${eventId}`;
    }

    function viewParticipants(eventId) {
        window.location.href = `events.php?action=participants&id=${eventId}`;
    }

    function deleteEvent(eventId) {
        if (confirm('Are you sure you want to delete this event? This will also remove all participant registrations.')) {
            window.location.href = `events.php?delete=${eventId}`;
        }
    }

    // Auto-submit search form when filters change
    document.querySelectorAll('select[name="status"], select[name="sport"]').forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

    console.log('Events Admin loaded successfully with IST timezone');
    console.log('Current Admin: <?php echo $display_name; ?>');
    console.log('Current Username: <?php echo $current_user; ?>');
    console.log('UTC Time: <?php echo $current_utc; ?>');
    console.log('IST Time: <?php echo $current_ist_time; ?>');
</script>
</body>
</html>
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
$current_utc = '2025-08-04 15:25:37'; // Current UTC time
$current_user_data = getCurrentUser();
$current_user = $current_user_data['username']; // joealjohn
$display_name = getUserDisplayName($current_user_data); // Joe John
$user_initials = getUserInitials($current_user_data); // JO

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle join event action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_event'])) {
    $event_id = (int)$_POST['event_id'];

    // Check if event exists and is available
    $stmt = $pdo->prepare("SELECT e.*, 
                           (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.registration_status != 'cancelled') as participant_count
                           FROM events e WHERE e.id = ? AND e.status = 'published' AND e.event_date >= CURDATE()");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        $message = 'Event not found or no longer available.';
        $message_type = 'danger';
    } elseif ($event['participant_count'] >= $event['max_participants']) {
        $message = 'Sorry, this event is full.';
        $message_type = 'warning';
    } elseif (hasUserJoined($event_id, $user_id)) {
        $message = 'You have already joined this event.';
        $message_type = 'info';
    } else {
        // Join the event
        $stmt = $pdo->prepare("INSERT INTO event_participants (event_id, user_id, registration_status, joined_at) VALUES (?, ?, 'registered', ?)");
        if ($stmt->execute([$event_id, $user_id, getCurrentUTCTime()])) {
            $message = 'Successfully joined the event: ' . htmlspecialchars($event['title']) . '!';
            $message_type = 'success';

            // Log activity
            logActivity('event_join', ['event_id' => $event_id, 'event_title' => $event['title']], $user_id);
        } else {
            $message = 'Failed to join the event. Please try again.';
            $message_type = 'danger';
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$sport_filter = $_GET['sport'] ?? '';
$venue_filter = $_GET['venue'] ?? '';
$date_filter = $_GET['date'] ?? '';
$status_filter = $_GET['status'] ?? 'upcoming';

// Build query conditions
$conditions = ["e.status = 'published'"];
$params = [];

if (!empty($search)) {
    $conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.sport_name LIKE ? OR e.venue LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($sport_filter)) {
    $conditions[] = "e.sport_name = ?";
    $params[] = $sport_filter;
}

if (!empty($venue_filter)) {
    $conditions[] = "e.venue LIKE ?";
    $params[] = "%$venue_filter%";
}

if (!empty($date_filter)) {
    $conditions[] = "e.event_date = ?";
    $params[] = $date_filter;
}

if ($status_filter === 'upcoming') {
    $conditions[] = "e.event_date >= CURDATE()";
} elseif ($status_filter === 'past') {
    $conditions[] = "e.event_date < CURDATE()";
}

$where_clause = implode(' AND ', $conditions);

// Get events with participant counts
$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.registration_status != 'cancelled') as participant_count,
        (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.user_id = ? AND ep.registration_status != 'cancelled') as user_joined
        FROM events e 
        WHERE $where_clause
        ORDER BY e.event_date ASC, e.event_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$user_id], $params));
$events = $stmt->fetchAll();

// Get available sports for filter
$stmt = $pdo->query("SELECT DISTINCT sport_name FROM events WHERE status = 'published' ORDER BY sport_name");
$sports = $stmt->fetchAll();

// Get available venues for filter
$stmt = $pdo->query("SELECT DISTINCT venue FROM events WHERE status = 'published' ORDER BY venue");
$venues = $stmt->fetchAll();

// Helper functions for sport icons and colors
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
    <title>Browse Events - SportsMeet Manager</title>
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            padding-right: 10px;
            margin-right: 5px;
            white-space: nowrap;
        }

        .navbar-collapse {
            justify-content: space-between;
        }

        .navbar-nav.ms-auto {
            margin-left: auto !important;
            display: flex;
            align-items: center;
            gap: 0;
        }

        .search-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .stats-summary {
            background: var(--primary-gradient);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }

        .event-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
            height: 100%;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }

        .event-card-header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px;
            position: relative;
        }

        .event-status-badge {
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

        .status-past {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }

        .status-joined {
            background: rgba(0, 123, 255, 0.2);
            color: #007bff;
        }

        .event-card-body {
            padding: 25px;
        }

        .event-meta {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .event-meta i {
            width: 20px;
            margin-right: 10px;
        }

        .participants-bar {
            background: #e9ecef;
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            margin: 15px 0;
        }

        .participants-fill {
            height: 100%;
            background: var(--primary-gradient);
            transition: width 0.3s ease;
        }

        .btn-join {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-join:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .sport-icon-header {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-size: 0.8rem;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 10px;
        }

        .dropdown-item:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        @media (max-width: 991px) {
            .current-time {
                border-left: none;
                padding-left: 0;
                margin-right: 0;
                text-align: center;
                padding: 0.5rem 1rem;
            }
        }
    </style>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-trophy me-2"></i>SportsMeet Manager
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="events.php">
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
                        <li><a class="dropdown-item" href="my_events.php"><i class="fas fa-calendar me-2"></i>My Events</a></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
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

<div class="container mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-calendar-alt me-2"></i>Browse Events</h2>
                    <p class="text-muted mb-0">Discover and join exciting sports events happening near you</p>
                    <small class="text-muted">Welcome, <?php echo $display_name; ?> | Current Time: <?php echo $current_ist_time; ?> IST</small>
                </div>
                <a href="events.php" class="btn btn-outline-secondary">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($message): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'info-circle'); ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search and Filter Section -->
    <div class="search-section">
        <form method="GET" id="filterForm">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="search" class="form-label">Search Events</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="search" name="search"
                               placeholder="Search by title, sport, or venue" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>

                <div class="col-md-2 mb-3">
                    <label for="sport" class="form-label">Sport</label>
                    <select class="form-select" id="sport" name="sport">
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
                    <label for="venue" class="form-label">Venue</label>
                    <select class="form-select" id="venue" name="venue">
                        <option value="">All Venues</option>
                        <?php foreach ($venues as $venue): ?>
                            <option value="<?php echo htmlspecialchars($venue['venue']); ?>"
                                <?php echo $venue_filter === $venue['venue'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($venue['venue']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 mb-3">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>

                <div class="col-md-2 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="past" <?php echo $status_filter === 'past' ? 'selected' : ''; ?>>Past Events</option>
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Events</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <a href="events.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear Filters
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Summary -->
    <?php if (!empty($events)): ?>
        <div class="stats-summary">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Found <?php echo count($events); ?> event<?php echo count($events) !== 1 ? 's' : ''; ?>
                <?php if (!empty($search) || !empty($sport_filter) || !empty($venue_filter) || !empty($date_filter)): ?>
                    matching your search criteria
                <?php endif; ?>
            </h5>
        </div>
    <?php endif; ?>

    <!-- Events Grid -->
    <div class="row">
        <?php if (empty($events)): ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h4 class="text-muted">No events found</h4>
                    <p class="text-muted">
                        <?php if (!empty($search) || !empty($sport_filter) || !empty($venue_filter) || !empty($date_filter)): ?>
                            Try adjusting your search criteria or <a href="events.php">browse all events</a>.
                        <?php else: ?>
                            There are no events available at the moment. Check back later!
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($events as $event):
                $status = getEventStatus($event['event_date'], $event['event_time']);
                $is_full = $event['participant_count'] >= $event['max_participants'];
                $user_joined = $event['user_joined'] > 0;
                $fill_percentage = ($event['participant_count'] / $event['max_participants']) * 100;
                $sport_color = getSportColor($event['sport_name']);
                $sport_icon = getSportIcon($event['sport_name']);
                ?>
                <div class="col-lg-4 col-md-6 mb-4" id="event-<?php echo $event['id']; ?>">
                    <div class="event-card">
                        <div class="event-card-header">
                            <div class="event-status-badge status-<?php echo strtolower($status); ?>">
                                <?php echo $status; ?>
                            </div>
                            <?php if ($user_joined): ?>
                                <div class="event-status-badge status-joined" style="top: 50px;">
                                    <i class="fas fa-check me-1"></i>Joined
                                </div>
                            <?php endif; ?>

                            <h5 class="mb-2"><?php echo htmlspecialchars($event['title']); ?></h5>
                            <p class="mb-0 opacity-75">
                                <span class="sport-icon-header" style="background: <?php echo $sport_color; ?>;">
                                    <i class="fas <?php echo $sport_icon; ?>"></i>
                                </span>
                                <?php echo htmlspecialchars($event['sport_name']); ?>
                            </p>
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
                                <span data-utc="<?php echo $event['event_date']; ?>">
                                    <?php echo convertUTCtoIST($event['event_date'], 'l, F d, Y'); ?>
                                </span>
                            </div>

                            <div class="event-meta">
                                <i class="fas fa-clock"></i>
                                <span data-utc="<?php echo $event['event_date'] . ' ' . $event['event_time']; ?>">
                                    <?php echo convertUTCtoIST($event['event_date'] . ' ' . $event['event_time'], 'h:i A'); ?> IST
                                </span>
                            </div>

                            <div class="participants-bar">
                                <div class="participants-fill" style="width: <?php echo $fill_percentage; ?>%"></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo $event['participant_count']; ?>/<?php echo $event['max_participants']; ?> participants
                                </span>
                                <span class="badge bg-<?php echo $is_full ? 'danger' : ($fill_percentage > 80 ? 'warning' : 'success'); ?>">
                                    <?php echo $is_full ? 'FULL' : ($fill_percentage > 80 ? 'Almost Full' : 'Available'); ?>
                                </span>
                            </div>

                            <div class="text-center">
                                <?php if ($status === 'Completed'): ?>
                                    <button class="btn btn-outline-secondary" disabled>
                                        <i class="fas fa-flag-checkered me-1"></i>Event Completed
                                    </button>
                                <?php elseif ($user_joined): ?>
                                    <button class="btn btn-success" disabled>
                                        <i class="fas fa-check me-1"></i>Already Joined
                                    </button>
                                <?php elseif ($is_full): ?>
                                    <button class="btn btn-outline-danger" disabled>
                                        <i class="fas fa-users me-1"></i>Event Full
                                    </button>
                                <?php else: ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirmJoin('<?php echo htmlspecialchars($event['title']); ?>')">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" name="join_event" class="btn btn-join">
                                            <i class="fas fa-plus me-1"></i>Join Event
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($events)): ?>
        <div class="text-center mt-4">
            <p class="text-muted">
                Showing <?php echo count($events); ?> event<?php echo count($events) !== 1 ? 's' : ''; ?>
                <?php if ($status_filter === 'upcoming'): ?>
                    • <a href="?status=all">View all events including past ones</a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Load time utilities -->
<script src="../assets/js/time-utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

        // Initialize time manager for IST conversion
        if (typeof timeManager !== 'undefined') {
            timeManager.startAutoUpdate();
        }
    });

    function confirmJoin(eventTitle) {
        return confirm(`Are you sure you want to join "${eventTitle}"?`);
    }

    // Auto-submit form when filters change
    document.querySelectorAll('#filterForm select').forEach(select => {
        select.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });

    console.log('Events page loaded successfully with IST timezone');
    console.log('Current User: <?php echo $display_name; ?>');
    console.log('Current Username: <?php echo $current_user; ?>');
    console.log('Current UTC: <?php echo $current_utc; ?>');
    console.log('Current IST: <?php echo $current_ist_time; ?>');
</script>
</body>
</html>
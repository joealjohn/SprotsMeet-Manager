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
$current_utc = '2025-08-04 15:28:45'; // Current UTC time
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

// Get event statistics for display
$total_events = count($events);
$upcoming_events = count(array_filter($events, function($event) {
    return strtotime($event['event_date']) >= strtotime(date('Y-m-d'));
}));
$available_events = count(array_filter($events, function($event) {
    return $event['participant_count'] < $event['max_participants'] && $event['user_joined'] == 0;
}));

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
            --danger-gradient: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
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

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .event-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .event-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, transparent 0%, rgba(102, 126, 234, 0.05) 100%);
            border-radius: 0 15px 0 40px;
        }

        .event-time {
            color: #667eea;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .event-status {
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .event-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
            position: absolute;
            bottom: 15px;
            right: 15px;
        }

        .event-card:hover .event-actions {
            opacity: 1;
        }

        .sport-badge {
            display: inline-flex;
            align-items: center;
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #495057;
            margin-right: 10px;
        }

        .sport-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 8px;
            font-size: 0.7rem;
        }

        .participants-progress {
            background: #e9ecef;
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            margin: 10px 0;
        }

        .participants-fill {
            height: 100%;
            background: var(--primary-gradient);
            transition: width 0.3s ease;
        }

        .join-btn {
            background: var(--success-gradient);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .join-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
            color: white;
        }

        .joined-btn {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .full-btn {
            background: var(--danger-gradient);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
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
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-calendar-alt me-2"></i>Browse Events</h2>
                    <p class="text-muted mb-0">Discover and join exciting sports events happening near you</p>
                    <small class="text-muted">Current User: <?php echo $display_name; ?> | Current Time: <?php echo $current_ist_time; ?> IST</small>
                </div>
                <a href="my_events.php" class="btn btn-primary">
                    <i class="fas fa-calendar-check me-1"></i>My Events
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

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon me-3" style="background: var(--primary-gradient); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div>
                        <h4 class="mb-0"><?php echo $total_events; ?></h4>
                        <small class="text-muted">Total Events</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon me-3" style="background: var(--success-gradient); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <h4 class="mb-0"><?php echo $upcoming_events; ?></h4>
                        <small class="text-muted">Upcoming Events</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stats-card">
                <div class="d-flex align-items-center">
                    <div class="stats-icon me-3" style="background: var(--warning-gradient); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div>
                        <h4 class="mb-0"><?php echo $available_events; ?></h4>
                        <small class="text-muted">Available to Join</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <form method="GET" class="row align-items-end">
            <div class="col-md-3 mb-3">
                <label for="search" class="form-label">Search Events</label>
                <input type="text" class="form-control" id="search" name="search"
                       placeholder="Search by title, venue..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2 mb-3">
                <label for="sport" class="form-label">Sport Filter</label>
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
                <label for="venue" class="form-label">Venue Filter</label>
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
                <label for="date" class="form-label">Date Filter</label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
            </div>
            <div class="col-md-2 mb-3">
                <label for="status" class="form-label">Status Filter</label>
                <select class="form-select" id="status" name="status">
                    <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="past" <?php echo $status_filter === 'past' ? 'selected' : ''; ?>>Past Events</option>
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Events</option>
                </select>
            </div>
            <div class="col-md-1 mb-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Events List -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($events)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                        <h4>No Events Found</h4>
                        <p class="text-muted mb-4">
                            <?php if (!empty($search) || !empty($sport_filter) || !empty($venue_filter) || !empty($date_filter)): ?>
                                Try adjusting your filters or search criteria.
                            <?php else: ?>
                                There are no events available at the moment. Check back later!
                            <?php endif; ?>
                        </p>
                        <a href="events.php" class="btn btn-primary">
                            <i class="fas fa-sync me-1"></i>Refresh
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event):
                    $sport_color = getSportColor($event['sport_name']);
                    $sport_icon = getSportIcon($event['sport_name']);
                    $is_upcoming = strtotime($event['event_date']) >= strtotime(date('Y-m-d'));
                    $user_joined = $event['user_joined'] > 0;
                    $is_full = $event['participant_count'] >= $event['max_participants'];
                    $fill_percentage = ($event['participant_count'] / $event['max_participants']) * 100;
                    ?>
                    <div class="event-card" style="border-left-color: <?php echo $sport_color; ?>;">
                        <div class="event-status">
                            <?php if ($is_upcoming): ?>
                                <span class="badge bg-success">Upcoming</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Completed</span>
                            <?php endif; ?>
                        </div>

                        <div class="event-actions">
                            <?php if ($is_upcoming && !$user_joined && !$is_full): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirmJoin('<?php echo htmlspecialchars($event['title']); ?>')">
                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <button type="submit" name="join_event" class="join-btn">
                                        <i class="fas fa-plus me-1"></i>Join
                                    </button>
                                </form>
                            <?php elseif ($user_joined): ?>
                                <button class="joined-btn" disabled>
                                    <i class="fas fa-check me-1"></i>Joined
                                </button>
                            <?php elseif ($is_full): ?>
                                <button class="full-btn" disabled>
                                    <i class="fas fa-users me-1"></i>Full
                                </button>
                            <?php endif; ?>
                        </div>

                        <h5 class="mb-3"><?php echo htmlspecialchars($event['title']); ?></h5>

                        <div class="sport-badge">
                            <div class="sport-icon" style="background: <?php echo $sport_color; ?>;">
                                <i class="fas <?php echo $sport_icon; ?>"></i>
                            </div>
                            <?php echo htmlspecialchars($event['sport_name']); ?>
                        </div>

                        <div class="mb-2">
                            <i class="fas fa-map-marker-alt text-muted me-1"></i>
                            <span class="text-muted"><?php echo htmlspecialchars($event['venue']); ?></span>
                        </div>

                        <div class="event-time">
                            <i class="fas fa-clock me-1"></i>
                            <span data-utc="<?php echo $event['event_date'] . ' ' . $event['event_time']; ?>">
                                <?php echo convertUTCtoIST($event['event_date'] . ' ' . $event['event_time'], 'M d, Y - h:i A'); ?> IST
                            </span>
                        </div>

                        <div class="participants-progress">
                            <div class="participants-fill" style="width: <?php echo $fill_percentage; ?>%"></div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo $event['participant_count']; ?>/<?php echo $event['max_participants']; ?> participants
                                </small>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="badge bg-<?php echo $is_full ? 'danger' : ($fill_percentage > 80 ? 'warning' : 'success'); ?>">
                                    <?php echo $is_full ? 'FULL' : ($fill_percentage > 80 ? 'Almost Full' : 'Available'); ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($event['description'])): ?>
                            <div class="mt-2">
                                <p class="text-muted mb-0" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($event['description']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

    function confirmJoin(eventTitle) {
        return confirm(`Are you sure you want to join "${eventTitle}"?`);
    }

    // Auto-submit form when filters change
    document.querySelectorAll('#sport, #venue, #status').forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

    console.log('Browse Events loaded successfully with IST timezone');
    console.log('Current User: <?php echo $display_name; ?>');
    console.log('Current Username: <?php echo $current_user; ?>');
    console.log('Current UTC: <?php echo $current_utc; ?>');
    console.log('Current IST: <?php echo $current_ist_time; ?>');
</script>
</body>
</html>
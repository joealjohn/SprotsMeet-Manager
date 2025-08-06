<?php
$page_title = "System Settings";
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is admin
requireAdmin();

// Get current IST time and user info for display
$current_ist_time = getCurrentDisplayTime();
$current_utc = '2025-08-04 14:40:17';
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

    if ($action === 'update_settings') {
        $settings = $_POST['settings'] ?? [];

        $updated_count = 0;
        foreach ($settings as $key => $value) {
            if (setAppSetting($key, $value)) {
                $updated_count++;
            }
        }

        if ($updated_count > 0) {
            $message = "Successfully updated $updated_count setting(s).";
            $message_type = 'success';

            // Log activity
            logActivity('settings_update', ['count' => $updated_count, 'settings' => array_keys($settings)], $_SESSION['user_id']);
        } else {
            $message = 'No settings were updated.';
            $message_type = 'warning';
        }
    } elseif ($action === 'test_email') {
        // Test email functionality
        $test_email = sanitize($_POST['test_email']);
        if (validateEmail($test_email)) {
            $subject = 'SportsMeet Manager - Test Email';
            $message_body = "This is a test email sent from SportsMeet Manager.\n\nSent by: " . $display_name . "\nTime: " . $current_ist_time . " IST";

            if (sendEmail($test_email, $subject, $message_body)) {
                $message = "Test email sent successfully to " . $test_email;
                $message_type = 'success';
            } else {
                $message = "Failed to send test email to " . $test_email;
                $message_type = 'danger';
            }
        } else {
            $message = "Invalid email address.";
            $message_type = 'danger';
        }
    } elseif ($action === 'clear_cache') {
        // Clear application cache
        $cache_dir = '../cache/';
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*.cache');
            $cleared = 0;
            foreach ($files as $file) {
                if (unlink($file)) {
                    $cleared++;
                }
            }
            $message = "Cleared $cleared cache file(s).";
            $message_type = 'success';

            // Log activity
            logActivity('cache_clear', ['files_cleared' => $cleared], $_SESSION['user_id']);
        } else {
            $message = "Cache directory not found.";
            $message_type = 'warning';
        }
    }
}

// Get all current settings
$stmt = $pdo->query("SELECT * FROM settings ORDER BY setting_key");
$all_settings = $stmt->fetchAll();

// Organize settings by category
$settings_categories = [
    'General' => [
        'site_name' => 'Application Name',
        'site_description' => 'Application Description',
        'site_url' => 'Site URL',
        'maintenance_mode' => 'Maintenance Mode'
    ],
    'User Management' => [
        'allow_registration' => 'Allow User Registration',
        'require_email_verification' => 'Require Email Verification',
        'max_events_per_user' => 'Max Events Per User',
        'session_timeout' => 'Session Timeout (minutes)'
    ],
    'Event Settings' => [
        'auto_approve_events' => 'Auto-approve Events',
        'default_event_capacity' => 'Default Event Capacity',
        'allow_event_cancellation' => 'Allow Event Cancellation',
        'registration_deadline_days' => 'Default Registration Deadline (days)'
    ],
    'Notifications' => [
        'email_notifications' => 'Email Notifications',
        'sms_notifications' => 'SMS Notifications',
        'admin_email' => 'Admin Email Address',
        'notification_frequency' => 'Notification Frequency'
    ],
    'System' => [
        'analytics_enabled' => 'Analytics Tracking',
        'debug_mode' => 'Debug Mode',
        'backup_frequency' => 'Backup Frequency',
        'max_file_upload_size' => 'Max File Upload Size (MB)'
    ]
];

// Get current settings values
$current_settings = [];
foreach ($all_settings as $setting) {
    $current_settings[$setting['setting_key']] = $setting['setting_value'];
}

// Set default values for missing settings
$default_values = [
    'site_name' => 'SportsMeet Manager',
    'site_description' => 'Sports Event Management System',
    'site_url' => 'http://localhost/sportsmeet',
    'maintenance_mode' => 'false',
    'allow_registration' => 'true',
    'require_email_verification' => 'false',
    'max_events_per_user' => '10',
    'session_timeout' => '30',
    'auto_approve_events' => 'false',
    'default_event_capacity' => '50',
    'allow_event_cancellation' => 'true',
    'registration_deadline_days' => '1',
    'email_notifications' => 'true',
    'sms_notifications' => 'false',
    'admin_email' => 'admin@sportsmeet.com',
    'notification_frequency' => 'immediate',
    'analytics_enabled' => 'true',
    'debug_mode' => 'false',
    'backup_frequency' => 'daily',
    'max_file_upload_size' => '5'
];

foreach ($default_values as $key => $default_value) {
    if (!isset($current_settings[$key])) {
        $current_settings[$key] = $default_value;
    }
}

// Get system statistics
$system_stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_events' => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'total_registrations' => $pdo->query("SELECT COUNT(*) FROM event_participants WHERE registration_status != 'cancelled'")->fetchColumn(),
    'database_size' => formatBytes(1024 * 1024 * 15), // Placeholder
    'last_backup' => 'Never', // Placeholder
    'app_version' => APP_VERSION ?? '2.1.0'
];
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

        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border: none;
            transition: all 0.3s ease;
        }

        .settings-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .settings-card h5 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }

        .system-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .system-info-card h5 {
            border-bottom: 2px solid rgba(255,255,255,0.2);
            padding-bottom: 10px;
        }

        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-outline-secondary {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .btn-danger {
            background: var(--danger-gradient);
            border: none;
            border-radius: 10px;
        }

        .btn-success {
            background: var(--success-gradient);
            border: none;
            border-radius: 10px;
        }

        .btn-warning {
            background: var(--warning-gradient);
            border: none;
            border-radius: 10px;
            color: #333;
        }

        .system-actions {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .badge-custom {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        @media (max-width: 768px) {
            .settings-card {
                padding: 20px;
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
                    <a class="nav-link active" href="settings.php">
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

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-cog me-2"></i>System Settings</h2>
                    <p class="text-muted mb-0">Configure application settings and preferences</p>
                    <small class="text-muted">Current Admin: <?php echo $display_name; ?> | Current Time: <?php echo $current_ist_time; ?> IST</small>
                </div>
                <div>
                    <button class="btn btn-outline-info me-2" data-bs-toggle="modal" data-bs-target="#systemInfoModal">
                        <i class="fas fa-info-circle me-1"></i>System Info
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

    <form method="POST" id="settingsForm">
        <input type="hidden" name="action" value="update_settings">

        <div class="row">
            <?php foreach ($settings_categories as $category => $category_settings): ?>
                <div class="col-lg-6 mb-4">
                    <div class="settings-card">
                        <h5>
                            <i class="fas fa-<?php echo $category === 'General' ? 'home' : ($category === 'User Management' ? 'users' : ($category === 'Event Settings' ? 'calendar' : ($category === 'Notifications' ? 'bell' : 'server'))); ?> me-2"></i>
                            <?php echo $category; ?>
                        </h5>

                        <?php foreach ($category_settings as $key => $label): ?>
                            <?php
                            $value = $current_settings[$key] ?? '';
                            $setting_info = array_filter($all_settings, function($s) use ($key) { return $s['setting_key'] === $key; });
                            $setting_info = reset($setting_info);
                            $type = $setting_info['setting_type'] ?? 'string';
                            ?>

                            <div class="mb-3">
                                <label for="<?php echo $key; ?>" class="form-label fw-semibold">
                                    <?php echo $label; ?>
                                    <?php if ($setting_info && !empty($setting_info['description'])): ?>
                                        <i class="fas fa-info-circle text-muted ms-1" title="<?php echo htmlspecialchars($setting_info['description']); ?>"></i>
                                    <?php endif; ?>
                                </label>

                                <?php if (strpos($key, '_mode') !== false || strpos($key, 'allow_') !== false || strpos($key, 'require_') !== false || strpos($key, 'auto_') !== false || strpos($key, '_enabled') !== false || strpos($key, '_notifications') !== false): ?>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="<?php echo $key; ?>"
                                               name="settings[<?php echo $key; ?>]" value="true"
                                            <?php echo filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="<?php echo $key; ?>">
                                            Enable <?php echo str_replace(['_', 'mode'], [' ', ''], $label); ?>
                                        </label>
                                    </div>
                                <?php elseif (strpos($key, 'max_') !== false || strpos($key, 'session_timeout') !== false || strpos($key, '_days') !== false || strpos($key, '_size') !== false): ?>
                                    <input type="number" class="form-control" id="<?php echo $key; ?>"
                                           name="settings[<?php echo $key; ?>]" value="<?php echo htmlspecialchars($value); ?>"
                                           min="1" required>
                                <?php elseif ($key === 'backup_frequency'): ?>
                                    <select class="form-select" id="<?php echo $key; ?>" name="settings[<?php echo $key; ?>]">
                                        <option value="hourly" <?php echo $value === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                        <option value="daily" <?php echo $value === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                        <option value="weekly" <?php echo $value === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                        <option value="monthly" <?php echo $value === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    </select>
                                <?php elseif ($key === 'notification_frequency'): ?>
                                    <select class="form-select" id="<?php echo $key; ?>" name="settings[<?php echo $key; ?>]">
                                        <option value="immediate" <?php echo $value === 'immediate' ? 'selected' : ''; ?>>Immediate</option>
                                        <option value="hourly" <?php echo $value === 'hourly' ? 'selected' : ''; ?>>Hourly Digest</option>
                                        <option value="daily" <?php echo $value === 'daily' ? 'selected' : ''; ?>>Daily Digest</option>
                                        <option value="weekly" <?php echo $value === 'weekly' ? 'selected' : ''; ?>>Weekly Digest</option>
                                    </select>
                                <?php elseif ($key === 'admin_email'): ?>
                                    <input type="email" class="form-control" id="<?php echo $key; ?>"
                                           name="settings[<?php echo $key; ?>]" value="<?php echo htmlspecialchars($value); ?>"
                                           required>
                                <?php elseif ($key === 'site_description'): ?>
                                    <textarea class="form-control" id="<?php echo $key; ?>" rows="3"
                                              name="settings[<?php echo $key; ?>]" required><?php echo htmlspecialchars($value); ?></textarea>
                                <?php else: ?>
                                    <input type="text" class="form-control" id="<?php echo $key; ?>"
                                           name="settings[<?php echo $key; ?>]" value="<?php echo htmlspecialchars($value); ?>"
                                           required>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- System Actions -->
        <div class="row">
            <div class="col-12">
                <div class="settings-card">
                    <h5><i class="fas fa-tools me-2"></i>System Actions</h5>
                    <div class="system-actions">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-1"></i>Save Settings
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button type="reset" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-undo me-1"></i>Reset Form
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button type="button" class="btn btn-warning w-100" onclick="clearCache()">
                                    <i class="fas fa-broom me-1"></i>Clear Cache
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#testEmailModal">
                                    <i class="fas fa-envelope me-1"></i>Test Email
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- System Info Modal -->
<div class="modal fade" id="systemInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-server me-2"></i>System Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="system-info-card">
                    <h5><i class="fas fa-chart-bar me-2"></i>System Statistics</h5>
                    <div class="stat-item">
                        <span>Application Version:</span>
                        <span class="badge-custom"><?php echo $system_stats['app_version']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Total Users:</span>
                        <span class="badge-custom"><?php echo number_format($system_stats['total_users']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Total Events:</span>
                        <span class="badge-custom"><?php echo number_format($system_stats['total_events']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Total Registrations:</span>
                        <span class="badge-custom"><?php echo number_format($system_stats['total_registrations']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Database Size:</span>
                        <span class="badge-custom"><?php echo $system_stats['database_size']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Last Backup:</span>
                        <span class="badge-custom"><?php echo $system_stats['last_backup']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Current Admin:</span>
                        <span class="badge-custom"><?php echo $display_name; ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Server Time (IST):</span>
                        <span class="badge-custom" id="modalCurrentTime"><?php echo $current_ist_time; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="test_email">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Send Test Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="test_email" name="test_email"
                               value="<?php echo htmlspecialchars($current_settings['admin_email'] ?? ''); ?>" required>
                        <div class="form-text">Enter the email address to send a test email to.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane me-1"></i>Send Test Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden form for cache clearing -->
<form id="cacheForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="clear_cache">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    // Settings page specific IST time management
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

            // Update navbar time
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.innerHTML = `<i class="fas fa-clock me-1"></i>${istTime} IST`;
            }

            // Update modal time
            const modalTimeElement = document.getElementById('modalCurrentTime');
            if (modalTimeElement) {
                modalTimeElement.textContent = istTime;
            }
        }

        updateCurrentTime();
        setInterval(updateCurrentTime, 1000);

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // Clear cache function
    function clearCache() {
        if (confirm('Are you sure you want to clear the application cache? This may temporarily slow down the system.')) {
            document.getElementById('cacheForm').submit();
        }
    }

    // Form validation
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        const requiredFields = this.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });

    console.log('Settings page initialized successfully with IST timezone');
    console.log('Current admin: <?php echo $display_name; ?>');
    console.log('Current username: <?php echo $current_user; ?>');
    console.log('Current UTC: <?php echo $current_utc; ?>');
    console.log('Current IST: <?php echo $current_ist_time; ?>');
</script>
</body>
</html>
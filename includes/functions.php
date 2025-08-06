<?php
/**
 * SportsMeet Manager - Enhanced Helper Functions with Display Names
 *
 * This file contains reusable functions for the SportsMeet Manager application.
 *
 * @author SportsMeet Team
 * @version 2.1
 * @since 2025-08-04
 *
 * Current Date: 2025-08-04 19:37:54 IST (UTC+5:30)
 * Current User: joealjohn
 */

require_once __DIR__ . '/../config/database.php';

// Application Constants (only define if not already defined)
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.1.0');
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', true); // Set to false in production
}
if (!defined('CURRENT_TIME_IST')) {
    define('CURRENT_TIME_IST', '2025-08-04 07:37:54 PM');
}
if (!defined('CURRENT_TIME_UTC')) {
    define('CURRENT_TIME_UTC', '2025-08-04 14:07:54');
}
if (!defined('CURRENT_USER')) {
    define('CURRENT_USER', 'joealjohn');
}

// Set default timezone to IST
date_default_timezone_set('Asia/Kolkata');

// ================================
// TIME & DATE FUNCTIONS (UPDATED FOR IST)
// ================================

// Get current application time in IST
function getCurrentISTTime() {
    return date('Y-m-d h:i:s A', time());
}

// Get current time in 12-hour format for display
function getCurrentDisplayTime() {
    return date('M d, Y - h:i A', time());
}

// Get current application time (UTC) - for database storage
function getCurrentUTCTime() {
    return gmdate('Y-m-d H:i:s', time());
}

// Get current time as DateTime object in IST
function getCurrentDateTime() {
    $dt = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    return $dt;
}

// Convert UTC time to IST display format
function convertUTCtoIST($utc_datetime, $format = 'M d, Y - h:i A') {
    if (empty($utc_datetime)) return '';

    try {
        $utc = new DateTime($utc_datetime, new DateTimeZone('UTC'));
        $utc->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $utc->format($format);
    } catch (Exception $e) {
        error_log("Error converting UTC to IST: " . $e->getMessage());
        return $utc_datetime;
    }
}

// Convert IST time to UTC for database storage
function convertISTtoUTC($ist_datetime) {
    if (empty($ist_datetime)) return '';

    try {
        $ist = new DateTime($ist_datetime, new DateTimeZone('Asia/Kolkata'));
        $ist->setTimezone(new DateTimeZone('UTC'));
        return $ist->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("Error converting IST to UTC: " . $e->getMessage());
        return $ist_datetime;
    }
}

// Get event status based on date and time (IST)
function getEventStatus($event_date, $event_time) {
    try {
        // Create event datetime in IST
        $event_datetime = new DateTime($event_date . ' ' . $event_time, new DateTimeZone('Asia/Kolkata'));
        $current_datetime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

        if ($event_datetime > $current_datetime) {
            return 'Upcoming';
        } else {
            return 'Completed';
        }
    } catch (Exception $e) {
        error_log("Error getting event status: " . $e->getMessage());
        return 'Unknown';
    }
}

// Get time remaining until event (IST)
function getTimeRemaining($event_date, $event_time) {
    try {
        $event_datetime = new DateTime($event_date . ' ' . $event_time, new DateTimeZone('Asia/Kolkata'));
        $current_datetime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

        if ($event_datetime <= $current_datetime) {
            return null; // Event has passed
        }

        $diff = $current_datetime->diff($event_datetime);

        if ($diff->days == 0) {
            if ($diff->h == 0) {
                return $diff->i . ' minute' . ($diff->i != 1 ? 's' : '') . ' remaining';
            }
            return $diff->h . ' hour' . ($diff->h != 1 ? 's' : '') . ' ' . $diff->i . ' minute' . ($diff->i != 1 ? 's' : '') . ' remaining';
        } elseif ($diff->days == 1) {
            return 'Tomorrow at ' . $event_datetime->format('h:i A');
        } else {
            return $diff->days . ' day' . ($diff->days != 1 ? 's' : '') . ' remaining';
        }
    } catch (Exception $e) {
        error_log("Error calculating time remaining: " . $e->getMessage());
        return null;
    }
}

// Format date for display (IST)
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) return '';
    try {
        return convertUTCtoIST($date, $format);
    } catch (Exception $e) {
        return date($format, strtotime($date));
    }
}

// Format time for display (IST 12-hour)
function formatTime($time, $format = 'h:i A') {
    if (empty($time)) return '';
    try {
        // If it's just time, add today's date for conversion
        if (strlen($time) <= 8) {
            $time = date('Y-m-d') . ' ' . $time;
        }
        return convertUTCtoIST($time, $format);
    } catch (Exception $e) {
        return date($format, strtotime($time));
    }
}

// Format datetime for display (IST 12-hour)
function formatDateTime($date, $time = null, $format = 'M d, Y - h:i A') {
    if (empty($date)) return '';

    try {
        if ($time) {
            $datetime = $date . ' ' . $time;
        } else {
            $datetime = $date;
        }
        return convertUTCtoIST($datetime, $format);
    } catch (Exception $e) {
        return date($format, strtotime($date . ' ' . $time));
    }
}

// Get user's timezone (IST by default)
function getUserTimezone($user_id = null) {
    // For now, return IST. In future versions, this could be user-configurable
    return 'Asia/Kolkata';
}

// Get time ago in IST context
function getTimeAgo($datetime) {
    if (empty($datetime)) return '';

    try {
        // Convert UTC datetime to IST for comparison
        $utc = new DateTime($datetime, new DateTimeZone('UTC'));
        $utc->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $past_time = $utc->getTimestamp();

        $current_time = time(); // Current IST time
        $time_diff = $current_time - $past_time;

        if ($time_diff < 60) return 'Just now';
        if ($time_diff < 3600) return floor($time_diff/60) . 'm ago';
        if ($time_diff < 86400) return floor($time_diff/3600) . 'h ago';
        if ($time_diff < 2592000) return floor($time_diff/86400) . 'd ago';

        return $utc->format('M d, Y');
    } catch (Exception $e) {
        error_log("Error calculating time ago: " . $e->getMessage());
        return date('M d, Y', strtotime($datetime));
    }
}

// Get current IST timestamp for JavaScript
function getCurrentISTTimestamp() {
    return time();
}

// Get IST offset information
function getISTOffset() {
    return '+05:30';
}

// ================================
// AUTHENTICATION & USER FUNCTIONS (ENHANCED WITH DISPLAY NAMES)
// ================================

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Get current user data (Enhanced with all name fields)
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, username, email, role, first_name, last_name, phone, is_active, email_verified, created_at, last_login FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

// Get user display name (First Name + Last Name or Username fallback)
function getUserDisplayName($user = null) {
    if ($user === null) {
        $user = getCurrentUser();
    }

    if (!$user) {
        return 'Unknown User';
    }

    // Try to build full name
    $firstName = trim($user['first_name'] ?? '');
    $lastName = trim($user['last_name'] ?? '');

    if (!empty($firstName) && !empty($lastName)) {
        return $firstName . ' ' . $lastName;
    } elseif (!empty($firstName)) {
        return $firstName;
    } elseif (!empty($lastName)) {
        return $lastName;
    } else {
        return $user['username'];
    }
}

// Get user initials for avatar
function getUserInitials($user = null) {
    if ($user === null) {
        $user = getCurrentUser();
    }

    if (!$user) {
        return 'U';
    }

    $firstName = trim($user['first_name'] ?? '');
    $lastName = trim($user['last_name'] ?? '');
    $username = trim($user['username'] ?? '');

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

// Get current user's display name (shortcut function)
function getCurrentUserDisplayName() {
    if (!isLoggedIn()) {
        return 'Guest';
    }

    $user = getCurrentUser();
    return getUserDisplayName($user);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $current_url = $_SERVER['REQUEST_URI'];
        header('Location: ' . getBaseUrl() . '/auth/login.php?redirect=' . urlencode($current_url));
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . getBaseUrl() . '/user/dashboard.php');
        exit();
    }
}

// Check if user has permission
function hasPermission($permission, $user_id = null) {
    if (!$user_id && isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
    }

    // Enhanced permission system
    switch ($permission) {
        case 'admin':
        case 'admin_access':
            return isAdmin();
        case 'user_management':
        case 'create_user':
        case 'edit_user':
        case 'delete_user':
            return isAdmin();
        case 'event_management':
        case 'create_event':
        case 'edit_event':
        case 'delete_event':
            return isAdmin();
        case 'view_analytics':
        case 'system_settings':
        case 'activity_monitoring':
            return isAdmin();
        case 'join_event':
        case 'leave_event':
            return isLoggedIn();
        case 'view_events':
        case 'browse_events':
            return true; // Everyone can view events
        default:
            return false;
    }
}

// ================================
// UTILITY & HELPER FUNCTIONS
// ================================

// Get base URL of the application
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname(dirname($script)); // Go up one level from current script

    // Clean up path
    $path = str_replace('\\', '/', $path);
    $path = rtrim($path, '/');

    return $protocol . '://' . $host . $path;
}

// Sanitize input data
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Enhanced input validation
function validateInput($data, $type = 'string', $options = []) {
    $data = sanitize($data);

    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL);

        case 'int':
        case 'integer':
            $min = $options['min'] ?? null;
            $max = $options['max'] ?? null;
            $int = filter_var($data, FILTER_VALIDATE_INT);
            if ($int === false) return false;
            if ($min !== null && $int < $min) return false;
            if ($max !== null && $int > $max) return false;
            return $int;

        case 'date':
            $date = DateTime::createFromFormat('Y-m-d', $data);
            return $date && $date->format('Y-m-d') === $data ? $data : false;

        case 'datetime':
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $data);
            return $datetime && $datetime->format('Y-m-d H:i:s') === $data ? $data : false;

        case 'time':
            $time = DateTime::createFromFormat('H:i', $data);
            return $time && $time->format('H:i') === $data ? $data : false;

        case 'username':
            return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $data) ? $data : false;

        case 'phone':
            return preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $data) ? $data : false;

        case 'url':
            return filter_var($data, FILTER_VALIDATE_URL);

        default:
            $minLength = $options['min_length'] ?? 0;
            $maxLength = $options['max_length'] ?? 1000;
            $length = strlen($data);
            return ($length >= $minLength && $length <= $maxLength) ? $data : false;
    }
}

// ================================
// EVENT FUNCTIONS
// ================================

// Get participant count for event
function getParticipantCount($event_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_participants WHERE event_id = ? AND registration_status != 'cancelled'");
        $stmt->execute([$event_id]);
        return $stmt->fetch()['count'];
    } catch (Exception $e) {
        error_log("Error getting participant count: " . $e->getMessage());
        return 0;
    }
}

// Check if user already joined event
function hasUserJoined($event_id, $user_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM event_participants WHERE event_id = ? AND user_id = ? AND registration_status != 'cancelled'");
        $stmt->execute([$event_id, $user_id]);
        return $stmt->fetch()['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking user participation: " . $e->getMessage());
        return false;
    }
}

// Get event by ID with participant info
function getEventById($event_id, $user_id = null) {
    try {
        $pdo = getDBConnection();
        $sql = "SELECT e.*, 
                (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.registration_status != 'cancelled') as participant_count";

        if ($user_id) {
            $sql .= ", (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.user_id = ? AND ep.registration_status != 'cancelled') as user_joined";
            $stmt = $pdo->prepare($sql . " WHERE e.id = ?");
            $stmt->execute([$user_id, $event_id]);
        } else {
            $stmt = $pdo->prepare($sql . " WHERE e.id = ?");
            $stmt->execute([$event_id]);
        }

        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting event by ID: " . $e->getMessage());
        return false;
    }
}

// Check if event is full
function isEventFull($event_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT 
                               e.max_participants,
                               (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.registration_status != 'cancelled') as current_count
                               FROM events e WHERE e.id = ?");
        $stmt->execute([$event_id]);
        $result = $stmt->fetch();

        if (!$result) return false;

        return $result['current_count'] >= $result['max_participants'];
    } catch (Exception $e) {
        error_log("Error checking if event is full: " . $e->getMessage());
        return false;
    }
}

// Enhanced can join event function
function canJoinEvent($event_id, $user_id) {
    $pdo = getDBConnection();

    // Check if event exists and is published
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'published'");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        return ['can_join' => false, 'reason' => 'Event not found or not published'];
    }

    // Check if event is in the future
    $event_datetime = new DateTime($event['event_date'] . ' ' . $event['event_time'], new DateTimeZone('UTC'));
    $now = new DateTime('now', new DateTimeZone('UTC'));

    if ($event_datetime <= $now) {
        return ['can_join' => false, 'reason' => 'Event has already started or ended'];
    }

    // Check registration deadline
    if ($event['registration_deadline'] && $event['registration_deadline'] < date('Y-m-d')) {
        return ['can_join' => false, 'reason' => 'Registration deadline has passed'];
    }

    // Check if user is already registered
    $stmt = $pdo->prepare("SELECT registration_status FROM event_participants WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    $existing = $stmt->fetch();

    if ($existing && $existing['registration_status'] !== 'cancelled') {
        return ['can_join' => false, 'reason' => 'Already registered for this event'];
    }

    // Check if event is full
    $stmt = $pdo->prepare("SELECT COUNT(*) as participant_count FROM event_participants WHERE event_id = ? AND registration_status != 'cancelled'");
    $stmt->execute([$event_id]);
    $count = $stmt->fetch()['participant_count'];

    if ($count >= $event['max_participants']) {
        return ['can_join' => false, 'reason' => 'Event is full'];
    }

    return ['can_join' => true, 'reason' => ''];
}

// ================================
// USER FUNCTIONS (ENHANCED)
// ================================

// Get user by ID with display name
function getUserById($user_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting user by ID: " . $e->getMessage());
        return false;
    }
}

// Get user display name by ID
function getUserDisplayNameById($user_id) {
    $user = getUserById($user_id);
    return getUserDisplayName($user);
}

// Get user initials by ID
function getUserInitialsById($user_id) {
    $user = getUserById($user_id);
    return getUserInitials($user);
}

// Check if username exists
function usernameExists($username, $exclude_user_id = null) {
    try {
        $pdo = getDBConnection();
        if ($exclude_user_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $exclude_user_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
            $stmt->execute([$username]);
        }
        return $stmt->fetch()['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking username existence: " . $e->getMessage());
        return false;
    }
}

// Check if email exists
function emailExists($email, $exclude_user_id = null) {
    try {
        $pdo = getDBConnection();
        if ($exclude_user_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $exclude_user_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
            $stmt->execute([$email]);
        }
        return $stmt->fetch()['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking email existence: " . $e->getMessage());
        return false;
    }
}

// ================================
// NOTIFICATION FUNCTIONS
// ================================

// Send notification to user
function sendNotification($user_id, $title, $message, $type = 'info', $action_url = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, action_url, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$user_id, $title, $message, $type, $action_url, getCurrentUTCTime()]);

        if ($result) {
            logActivity('notification_sent', [
                'user_id' => $user_id,
                'title' => $title,
                'type' => $type
            ]);
        }

        return $result;
    } catch (Exception $e) {
        error_log("Error sending notification: " . $e->getMessage());
        return false;
    }
}

// Get unread notifications for user
function getUnreadNotifications($user_id, $limit = 10) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM notifications 
                               WHERE user_id = ? AND is_read = 0 
                               AND (expires_at IS NULL OR expires_at > ?) 
                               ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$user_id, getCurrentUTCTime(), $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

// Get all notifications for user
function getAllNotifications($user_id, $limit = 50) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM notifications 
                               WHERE user_id = ?
                               ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting all notifications: " . $e->getMessage());
        return [];
    }
}

// Mark notification as read
function markNotificationRead($notification_id, $user_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notification_id, $user_id]);
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

// Mark all notifications as read for user
function markAllNotificationsRead($user_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

// ================================
// SETTINGS FUNCTIONS
// ================================

// Get application setting with enhanced error handling
function getAppSetting($key, $default = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value, setting_type FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $setting = $stmt->fetch();

        if (!$setting) {
            return $default;
        }

        // Convert based on type
        switch ($setting['setting_type']) {
            case 'boolean':
                return in_array(strtolower($setting['setting_value']), ['true', '1', 'yes', 'on']);
            case 'integer':
                return (int)$setting['setting_value'];
            case 'json':
                $decoded = json_decode($setting['setting_value'], true);
                return $decoded !== null ? $decoded : $default;
            default:
                return $setting['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Error getting app setting '$key': " . $e->getMessage());
        return $default;
    }
}

// Set application setting with enhanced error handling
function setAppSetting($key, $value, $type = null) {
    try {
        $pdo = getDBConnection();

        // Auto-detect type if not provided
        if ($type === null) {
            if (is_bool($value)) {
                $type = 'boolean';
                $value = $value ? 'true' : 'false';
            } elseif (is_int($value)) {
                $type = 'integer';
                $value = (string)$value;
            } elseif (is_array($value) || is_object($value)) {
                $type = 'json';
                $value = json_encode($value);
            } else {
                $type = 'string';
                $value = (string)$value;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?, updated_at = ?");
        return $stmt->execute([$key, $value, $type, $value, $type, getCurrentUTCTime()]);
    } catch (Exception $e) {
        error_log("Error setting app setting '$key': " . $e->getMessage());
        return false;
    }
}

// Get all settings
function getAllSettings() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY setting_key");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting all settings: " . $e->getMessage());
        return [];
    }
}

// ================================
// ACTIVITY LOGGING FUNCTIONS
// ================================

// Enhanced activity logging function
function logActivity($action, $details = [], $user_id = null, $table_name = 'general', $record_id = null) {
    if (!$user_id && isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
    }

    if (!$user_id) {
        return false;
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, ?)");
        return $stmt->execute([$user_id, $action, getCurrentUTCTime()]);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

// Get user's activity logs
function getUserActivity($user_id, $limit = 10, $offset = 0) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$user_id, $limit, $offset]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting user activity: " . $e->getMessage());
        return [];
    }
}

// Get recent activity for all users
function getRecentActivity($limit = 20) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT al.*, u.username 
                               FROM activity_logs al 
                               LEFT JOIN users u ON al.user_id = u.id 
                               ORDER BY al.created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting recent activity: " . $e->getMessage());
        return [];
    }
}

// ================================
// UI HELPER FUNCTIONS
// ================================

// Generate breadcrumb navigation
function generateBreadcrumbs($items) {
    $breadcrumbs = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';

    foreach ($items as $index => $item) {
        $isLast = ($index === count($items) - 1);

        if ($isLast) {
            $breadcrumbs .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($item['title']) . '</li>';
        } else {
            $breadcrumbs .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['title']) . '</a></li>';
        }
    }

    $breadcrumbs .= '</ol></nav>';
    return $breadcrumbs;
}

// Format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
}

// Generate secure token
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Get current page title
function getPageTitle($default = 'SportsMeet Manager') {
    $page = basename($_SERVER['PHP_SELF'], '.php');

    $titles = [
        'index' => 'Home - SportsMeet Manager',
        'dashboard' => 'Dashboard - SportsMeet Manager',
        'events' => 'Events - SportsMeet Manager',
        'my_events' => 'My Events - SportsMeet Manager',
        'users' => 'User Management - SportsMeet Manager',
        'activity' => 'Activity Monitor - SportsMeet Manager',
        'analytics' => 'Analytics Dashboard - SportsMeet Manager',
        'settings' => 'System Settings - SportsMeet Manager',
        'login' => 'Login - SportsMeet Manager',
        'register' => 'Register - SportsMeet Manager'
    ];

    return $titles[$page] ?? $default;
}

// ================================
// MAINTENANCE & SYSTEM FUNCTIONS
// ================================

// Check if maintenance mode is enabled
function isMaintenanceMode() {
    return getAppSetting('maintenance_mode', false);
}

// Display maintenance page if enabled
function checkMaintenanceMode() {
    if (isMaintenanceMode() && !isAdmin()) {
        http_response_code(503);
        include __DIR__ . '/maintenance.php';
        exit();
    }
}

// Save user preferences (Enhanced with display names)
function saveUserPreferences($preferences = []) {
    if (!isLoggedIn()) return false;

    $defaultPreferences = [
        'user' => getCurrentUserDisplayName(),
        'username' => $_SESSION['username'] ?? 'user',
        'theme' => 'light',
        'notifications' => true,
        'language' => 'en',
        'timezone' => 'Asia/Kolkata',
        'lastVisited' => getCurrentUTCTime()
    ];

    $preferences = array_merge($defaultPreferences, $preferences);

    try {
        $key = 'user_preferences_' . $_SESSION['user_id'];
        return setAppSetting($key, $preferences, 'json');
    } catch (Exception $e) {
        error_log("Error saving user preferences: " . $e->getMessage());
        return false;
    }
}

// Get user preferences
function getUserPreferences($user_id = null) {
    if (!$user_id && isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
    }

    if (!$user_id) return [];

    $key = 'user_preferences_' . $user_id;
    return getAppSetting($key, []);
}

// Safe redirect function
function safeRedirect($url, $statusCode = 302) {
    // Validate URL to prevent open redirects
    $parsed = parse_url($url);

    if ($parsed === false || (isset($parsed['host']) && $parsed['host'] !== $_SERVER['HTTP_HOST'])) {
        $url = getBaseUrl();
    }

    header('Location: ' . $url, true, $statusCode);
    exit();
}

// ================================
// DEBUG & DEVELOPMENT FUNCTIONS
// ================================

// Debug function (only works in debug mode)
function debug($data, $label = '') {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo '<div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; margin: 10px 0; border-radius: 5px;">';
        if ($label) {
            echo '<strong>' . htmlspecialchars($label) . ':</strong><br>';
        }
        echo '<pre>' . print_r($data, true) . '</pre>';
        echo '<small class="text-muted">Debug output at ' . getCurrentDisplayTime() . ' IST by ' . getCurrentUserDisplayName() . '</small>';
        echo '</div>';
    }
}

// Enhanced error logging
function logError($message, $context = []) {
    $logMessage = '[' . getCurrentDisplayTime() . ' IST] ' . $message;
    if (!empty($context)) {
        $logMessage .= ' Context: ' . json_encode($context);
    }
    $logMessage .= ' User: ' . (isLoggedIn() ? getCurrentUserDisplayName() : 'Guest');

    error_log($logMessage);

    // Also log to activity if user is logged in
    if (isLoggedIn()) {
        logActivity('error_logged', [
            'message' => $message,
            'context' => $context,
            'time' => getCurrentDisplayTime()
        ]);
    }
}

// ================================
// STATISTICS & ANALYTICS FUNCTIONS
// ================================

// Get system statistics
function getSystemStats() {
    try {
        $pdo = getDBConnection();

        $stats = [];

        // User statistics
        $stats['users'] = [
            'total' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
            'admins' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
            'new_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
            'new_week' => $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
        ];

        // Event statistics
        $stats['events'] = [
            'total' => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
            'published' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'published'")->fetchColumn(),
            'upcoming' => $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE() AND status = 'published'")->fetchColumn(),
            'completed' => $pdo->query("SELECT COUNT(*) FROM events WHERE event_date < CURDATE() AND status = 'published'")->fetchColumn(),
        ];

        // Registration statistics
        $stats['registrations'] = [
            'total' => $pdo->query("SELECT COUNT(*) FROM event_participants WHERE registration_status != 'cancelled'")->fetchColumn(),
            'today' => $pdo->query("SELECT COUNT(*) FROM event_participants WHERE DATE(joined_at) = CURDATE() AND registration_status != 'cancelled'")->fetchColumn(),
            'week' => $pdo->query("SELECT COUNT(*) FROM event_participants WHERE joined_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND registration_status != 'cancelled'")->fetchColumn(),
        ];

        // Activity statistics
        $stats['activity'] = [
            'total' => $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn(),
            'today' => $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
            'week' => $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
        ];

        return $stats;
    } catch (Exception $e) {
        error_log("Error getting system stats: " . $e->getMessage());
        return [];
    }
}

// ================================
// FILE UPLOAD FUNCTIONS
// ================================

// Handle file upload
function handleFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 2048000) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error occurred'];
    }

    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_name = $file['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }

    if ($file_size > $max_size) {
        return ['success' => false, 'message' => 'File size too large'];
    }

    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $new_filename = uniqid() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file_tmp, $upload_path)) {
        return ['success' => true, 'filename' => $new_filename, 'path' => $upload_path];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

// ================================
// EMAIL FUNCTIONS
// ================================

// Send email (basic implementation)
function sendEmail($to, $subject, $message) {
    // Basic mail function - in production, use proper email service
    $headers = "From: noreply@sportsmeet.com\r\n";
    $headers .= "Reply-To: noreply@sportsmeet.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($to, $subject, $message, $headers);
}

// ================================
// CACHE FUNCTIONS
// ================================

// Set cache (basic file-based cache)
function setCache($key, $data, $expiration = 3600) {
    $cache_dir = '../cache/';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    $cache_file = $cache_dir . md5($key) . '.cache';
    $cache_data = [
        'data' => $data,
        'expiration' => time() + $expiration
    ];

    return file_put_contents($cache_file, serialize($cache_data)) !== false;
}

// Get cache
function getCache($key) {
    $cache_dir = '../cache/';
    $cache_file = $cache_dir . md5($key) . '.cache';

    if (!file_exists($cache_file)) {
        return null;
    }

    $cache_data = unserialize(file_get_contents($cache_file));

    if (time() > $cache_data['expiration']) {
        unlink($cache_file);
        return null;
    }

    return $cache_data['data'];
}

// ================================
// SESSION MANAGEMENT
// ================================

// Regenerate session
function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

// Destroy session
function destroySession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
}

// ================================
// VALIDATION FUNCTIONS
// ================================

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone
function validatePhone($phone) {
    // Basic phone validation - adjust as needed
    return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $phone);
}

// Validate password
function validatePassword($password) {
    // At least 6 characters, contains letter and number
    return strlen($password) >= 6 && preg_match('/[A-Za-z]/', $password) && preg_match('/\d/', $password);
}

// Generate random string
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

// Format bytes
function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// ================================
// INITIALIZATION
// ================================

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check maintenance mode on every page load (skip on admin pages)
if (!isAdmin()) {
    checkMaintenanceMode();
}

// Log page view for logged-in users (only if not already defined to prevent duplicates)
if (isLoggedIn() && !defined('SKIP_PAGE_LOG')) {
    $page = basename($_SERVER['PHP_SELF'], '.php');
    $path = $_SERVER['REQUEST_URI'];

    // Only log if we haven't logged this page view in this session already
    $session_key = 'logged_' . $page . '_' . md5($path);
    if (!isset($_SESSION[$session_key])) {
        logActivity('page_view', [
            'page' => $page,
            'path' => $path,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
            'timestamp' => getCurrentDisplayTime(),
            'user' => CURRENT_USER
        ]);
        $_SESSION[$session_key] = true;
    }
}

// Set current user context constants if logged in
if (isLoggedIn() && !defined('CURRENT_USER_CONTEXT')) {
    define('CURRENT_USER_CONTEXT', getCurrentUserDisplayName());
    define('CURRENT_USER_INITIALS', getUserInitials());
}

// Log successful functions.php initialization
if (defined('APP_DEBUG') && APP_DEBUG) {
    error_log("[" . getCurrentDisplayTime() . " IST] SportsMeet Manager functions.php loaded successfully for user: " . (isLoggedIn() ? getCurrentUserDisplayName() : 'Guest'));
}

?>
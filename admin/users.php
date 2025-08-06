<?php
$page_title = "User Management";
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is admin
requireAdmin();

// Get current IST time and user info for display
$current_ist_time = getCurrentDisplayTime();
$current_utc = '2025-08-04 14:33:23';
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
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $phone = sanitize($_POST['phone']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($action === 'create') {
            $password = $_POST['password'];

            // Validation
            if (empty($username) || empty($email) || empty($password)) {
                $message = 'Username, email, and password are required.';
                $message_type = 'danger';
            } else {
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);

                if ($stmt->fetch()['count'] > 0) {
                    $message = 'Username or email already exists.';
                    $message_type = 'danger';
                } else {
                    // Create new user
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone, is_active, email_verified, created_at, updated_at) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
                    if ($stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role, $first_name, $last_name, $phone, $is_active, getCurrentUTCTime(), getCurrentUTCTime()])) {
                        $message = "User '$username' created successfully!";
                        $message_type = 'success';

                        // Log activity
                        logActivity('user_create', ['username' => $username, 'email' => $email, 'role' => $role], $_SESSION['user_id']);
                    } else {
                        $message = 'Error creating user.';
                        $message_type = 'danger';
                    }
                }
            }
        } else {
            // Edit user
            $user_id = (int)$_POST['user_id'];
            $update_password = !empty($_POST['password']);

            if ($update_password) {
                $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, password=?, role=?, first_name=?, last_name=?, phone=?, is_active=?, updated_at=? WHERE id=?");
                $params = [$username, $email, password_hash($_POST['password'], PASSWORD_DEFAULT), $role, $first_name, $last_name, $phone, $is_active, getCurrentUTCTime(), $user_id];
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, role=?, first_name=?, last_name=?, phone=?, is_active=?, updated_at=? WHERE id=?");
                $params = [$username, $email, $role, $first_name, $last_name, $phone, $is_active, getCurrentUTCTime(), $user_id];
            }

            if ($stmt->execute($params)) {
                $message = "User '$username' updated successfully!";
                $message_type = 'success';

                // Log activity
                logActivity('user_update', ['user_id' => $user_id, 'username' => $username], $_SESSION['user_id']);
            } else {
                $message = 'Error updating user.';
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'bulk_delete') {
        $user_ids = $_POST['user_ids'] ?? [];
        if (!empty($user_ids)) {
            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND id != ?");
            $params = array_merge($user_ids, [$_SESSION['user_id']]); // Don't delete current admin

            if ($stmt->execute($params)) {
                $count = $stmt->rowCount();
                $message = "Successfully deleted $count user(s).";
                $message_type = 'success';

                // Log activity
                logActivity('bulk_user_delete', ['count' => $count, 'user_ids' => $user_ids], $_SESSION['user_id']);
            } else {
                $message = 'Error deleting users.';
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'bulk_activate') {
        $user_ids = $_POST['user_ids'] ?? [];
        if (!empty($user_ids)) {
            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1, updated_at = ? WHERE id IN ($placeholders)");

            if ($stmt->execute(array_merge([getCurrentUTCTime()], $user_ids))) {
                $count = $stmt->rowCount();
                $message = "Successfully activated $count user(s).";
                $message_type = 'success';
            }
        }
    } elseif ($action === 'bulk_deactivate') {
        $user_ids = $_POST['user_ids'] ?? [];
        if (!empty($user_ids)) {
            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0, updated_at = ? WHERE id IN ($placeholders) AND id != ?");
            $params = array_merge([getCurrentUTCTime()], $user_ids, [$_SESSION['user_id']]); // Don't deactivate current admin

            if ($stmt->execute($params)) {
                $count = $stmt->rowCount();
                $message = "Successfully deactivated $count user(s).";
                $message_type = 'success';
            }
        }
    }
}

// Handle single user delete
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    if ($user_id !== $_SESSION['user_id']) { // Don't allow admin to delete themselves
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $message = "User deleted successfully!";
            $message_type = "success";

            // Log activity
            logActivity('user_delete', ['user_id' => $user_id], $_SESSION['user_id']);
        } else {
            $message = "Error deleting user.";
            $message_type = "danger";
        }
    } else {
        $message = "You cannot delete your own account.";
        $message_type = "warning";
    }
}

// Get current action
$current_action = $_GET['action'] ?? 'list';
$edit_user = null;

if ($current_action === 'edit' && isset($_GET['id'])) {
    // Enhanced query for edit user to include statistics
    $stmt = $pdo->prepare("SELECT u.*, 
                           COALESCE(ep_count.events_joined, 0) as events_joined,
                           COALESCE(al_count.activity_count, 0) as activity_count
                           FROM users u 
                           LEFT JOIN (
                               SELECT user_id, COUNT(*) as events_joined 
                               FROM event_participants 
                               WHERE registration_status != 'cancelled'
                               GROUP BY user_id
                           ) ep_count ON u.id = ep_count.user_id
                           LEFT JOIN (
                               SELECT user_id, COUNT(*) as activity_count 
                               FROM activity_logs 
                               GROUP BY user_id
                           ) al_count ON u.id = al_count.user_id
                           WHERE u.id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $edit_user = $stmt->fetch();
    if (!$edit_user) {
        $current_action = 'list';
    }
}

// Get all users with statistics (fixed query)
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $conditions[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($role_filter)) {
    $conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter === 'active') {
    $conditions[] = "is_active = 1";
} elseif ($status_filter === 'inactive') {
    $conditions[] = "is_active = 0";
}

$where_clause = implode(' AND ', $conditions);

// Fixed SQL query with proper LEFT JOINs and COALESCE for null values
$sql = "SELECT u.*, 
        COALESCE(ep_count.events_joined, 0) as events_joined,
        COALESCE(al_count.activity_count, 0) as activity_count
        FROM users u 
        LEFT JOIN (
            SELECT user_id, COUNT(*) as events_joined 
            FROM event_participants 
            WHERE registration_status != 'cancelled'
            GROUP BY user_id
        ) ep_count ON u.id = ep_count.user_id
        LEFT JOIN (
            SELECT user_id, COUNT(*) as activity_count 
            FROM activity_logs 
            GROUP BY user_id
        ) al_count ON u.id = al_count.user_id
        WHERE $where_clause
        ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user statistics
$stats_sql = "SELECT 
              COUNT(*) as total_users,
              COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
              COUNT(CASE WHEN role = 'user' THEN 1 END) as user_count,
              COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_count,
              COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_count,
              COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
              FROM users";
$user_stats = $pdo->query($stats_sql)->fetch();

// Helper function to get user display name
function getUserCardDisplayName($user) {
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

// Helper function to get user initials
function getUserCardInitials($user) {
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
        .stats-icon.admins { background: var(--info-gradient); }
        .stats-icon.active { background: var(--success-gradient); }
        .stats-icon.new { background: var(--warning-gradient); }

        /* ENHANCED SEARCH AND FILTER SECTION */
        .search-filter-widget {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .search-filter-widget .widget-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .search-filter-widget .widget-title i {
            margin-right: 10px;
            color: #667eea;
        }

        .search-input {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 12px 20px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.1);
            background: white;
        }

        .filter-select {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 10px 15px;
            font-size: 0.9rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.1);
            background: white;
        }

        .search-btn {
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            padding: 12px 25px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .clear-btn {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 10px 20px;
            background: white;
            color: #6c757d;
            transition: all 0.3s ease;
        }

        .clear-btn:hover {
            border-color: #dc3545;
            color: #dc3545;
            background: #fff5f5;
        }

        /* ENHANCED USERS LIST WIDGET */
        .users-list-widget {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .users-list-widget::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -15%;
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

        .users-count {
            background: #fff;
            color: #667eea;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .bulk-actions-section {
            padding: 0 30px 15px;
            position: relative;
            z-index: 2;
        }

        .bulk-actions {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 15px;
            display: none;
            backdrop-filter: blur(10px);
        }

        .bulk-actions.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .bulk-action-btn {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 8px;
            transition: all 0.3s ease;
        }

        .bulk-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .bulk-action-btn.success { color: #28a745; }
        .bulk-action-btn.warning { color: #ffc107; }
        .bulk-action-btn.danger { color: #dc3545; }

        .users-container {
            padding: 10px;
            background: #2c3e50;
            border-radius: 0 0 20px 20px;
            max-height: 600px;
            overflow-y: auto;
        }

        .users-container::-webkit-scrollbar {
            width: 6px;
        }

        .users-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .users-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .user-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 12px;
            position: relative;
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
            cursor: pointer;
            overflow: hidden;
        }

        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .user-card:last-child {
            margin-bottom: 0;
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, transparent 0%, rgba(102, 126, 234, 0.05) 100%);
            border-radius: 0 15px 0 40px;
        }

        .user-card.admin {
            border-left-color: #dc3545;
        }

        .user-card.inactive {
            opacity: 0.7;
            border-left-color: #6c757d;
        }

        .user-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .user-avatar-large {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            margin-right: 15px;
            background: var(--primary-gradient);
            flex-shrink: 0;
        }

        .user-avatar-large.admin {
            background: var(--danger-gradient);
        }

        .user-info {
            flex-grow: 1;
        }

        .user-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 3px 0;
            display: flex;
            align-items: center;
        }

        .user-username {
            color: #6c757d;
            font-size: 0.85rem;
            margin: 0 0 3px 0;
        }

        .user-email {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }

        .user-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }

        .user-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .user-badge.role-admin {
            background: #dc3545;
            color: white;
        }

        .user-badge.role-user {
            background: #007bff;
            color: white;
        }

        .user-badge.status-active {
            background: #28a745;
            color: white;
        }

        .user-badge.status-inactive {
            background: #6c757d;
            color: white;
        }

        .user-badge.unverified {
            background: #ffc107;
            color: #212529;
        }

        .user-badge.current-user {
            background: var(--info-gradient);
            color: white;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .user-card:hover .user-actions {
            opacity: 1;
        }

        .user-select-checkbox {
            margin-right: 10px;
            transform: scale(1.2);
        }

        .user-stats {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px;
            margin-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-item {
            text-align: center;
            flex: 1;
        }

        .stat-number {
            font-size: 1.1rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 2px;
        }

        .user-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f1f3f4;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .last-login {
            display: flex;
            align-items: center;
        }

        .online-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .online-indicator.online {
            background: #28a745;
            animation: pulse 2s infinite;
        }

        .online-indicator.offline {
            background: #6c757d;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .action-btn {
            background: none;
            border: 1px solid #dee2e6;
            color: #6c757d;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .action-btn.edit:hover {
            background: #007bff;
            border-color: #007bff;
            color: white;
        }

        .action-btn.activity:hover {
            background: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }

        .action-btn.delete:hover {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .no-users {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.8);
        }

        .no-users i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.6;
        }

        .no-users h5 {
            color: white;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .user-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-actions {
                margin-top: 10px;
                opacity: 1;
            }

            .user-stats {
                flex-direction: column;
                gap: 10px;
            }

            .stat-item {
                text-align: left;
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
                    <a class="nav-link active" href="users.php">
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
                    <h2><i class="fas fa-users me-2"></i>User Management</h2>
                    <p class="text-muted mb-0">Create, edit, and manage user accounts</p>
                    <small class="text-muted">
                        Current Admin: <?php echo $display_name; ?> |
                        Current Time: <span id="currentDisplayTime"><?php echo $current_ist_time; ?> IST</span>
                    </small>
                </div>
                <?php if ($current_action === 'list'): ?>
                    <a href="users.php?action=create" class="btn btn-primary">
                        <i class="fas fa-user-plus me-1"></i>Create New User
                    </a>
                <?php else: ?>
                    <a href="users.php" class="btn btn-outline-secondary">
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
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'info-circle'); ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($current_action === 'list'): ?>
        <!-- User Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon total">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4 class="mb-0"><?php echo $user_stats['total_users']; ?></h4>
                    <small class="text-muted">Total Users</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon admins">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h4 class="mb-0"><?php echo $user_stats['admin_count']; ?></h4>
                    <small class="text-muted">Administrators</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon active">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h4 class="mb-0"><?php echo $user_stats['active_count']; ?></h4>
                    <small class="text-muted">Active Users</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-icon new">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h4 class="mb-0"><?php echo $user_stats['new_this_week']; ?></h4>
                    <small class="text-muted">New This Week</small>
                </div>
            </div>
        </div>

        <!-- Enhanced Search and Filters -->
        <div class="search-filter-widget">
            <h5 class="widget-title">
                <i class="fas fa-search"></i>
                Search Users
            </h5>
            <form method="GET" class="row align-items-end">
                <div class="col-md-5 mb-3">
                    <label for="search" class="form-label fw-semibold">Search by name, username, or email</label>
                    <input type="text" class="form-control search-input" id="search" name="search"
                           placeholder="Type to search users..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="role" class="form-label fw-semibold">Role</label>
                    <select class="form-select filter-select" id="role" name="role">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="status" class="form-label fw-semibold">Status</label>
                    <select class="form-select filter-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="submit" class="btn search-btn me-2">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <a href="users.php" class="btn clear-btn">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Enhanced Users List -->
        <div class="users-list-widget">
            <div class="widget-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="widget-title">
                        <i class="fas fa-list"></i>
                        All Users
                    </h3>
                    <span class="users-count"><?php echo count($users); ?></span>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="bulk-actions-section">
                <div class="bulk-actions" id="bulkActions">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-white"><strong id="selectedCount">0</strong> user(s) selected</span>
                        <div>
                            <button type="button" class="bulk-action-btn success" onclick="bulkAction('activate')">
                                <i class="fas fa-check me-1"></i>Activate
                            </button>
                            <button type="button" class="bulk-action-btn warning" onclick="bulkAction('deactivate')">
                                <i class="fas fa-pause me-1"></i>Deactivate
                            </button>
                            <button type="button" class="bulk-action-btn danger" onclick="bulkAction('delete')">
                                <i class="fas fa-trash me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="users-container">
                <?php if (empty($users)): ?>
                    <div class="no-users">
                        <i class="fas fa-users"></i>
                        <h5>No users found</h5>
                        <p>
                            <?php if (!empty($search) || !empty($role_filter) || !empty($status_filter)): ?>
                                Try adjusting your search criteria.
                            <?php else: ?>
                                Create your first user to get started!
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($users as $user):
                        $is_current_user = $user['id'] == $_SESSION['user_id'];
                        $last_activity = $user['last_login'] ? time() - strtotime($user['last_login']) : null;
                        $is_online = $last_activity && $last_activity < 300; // 5 minutes
                        $user_display_name = getUserCardDisplayName($user);
                        $user_initial = getUserCardInitials($user);
                        ?>
                        <div class="user-card <?php echo $user['role'] === 'admin' ? 'admin' : ''; ?> <?php echo !$user['is_active'] ? 'inactive' : ''; ?>"
                             onclick="<?php if (!$is_current_user): ?>toggleUserSelect(<?php echo $user['id']; ?>)<?php endif; ?>">

                            <div class="user-header">
                                <?php if (!$is_current_user): ?>
                                    <input type="checkbox" class="user-select-checkbox" value="<?php echo $user['id']; ?>"
                                           onchange="updateBulkActions()" onclick="event.stopPropagation()">
                                <?php endif; ?>

                                <div class="user-avatar-large <?php echo $user['role'] === 'admin' ? 'admin' : ''; ?>">
                                    <?php echo $user_initial; ?>
                                </div>

                                <div class="user-info">
                                    <h5 class="user-name">
                                        <?php echo htmlspecialchars($user_display_name); ?>
                                        <?php if ($is_current_user): ?>
                                            <span class="user-badge current-user ms-2">You</span>
                                        <?php endif; ?>
                                    </h5>
                                    <?php if ($user_display_name !== $user['username']): ?>
                                        <p class="user-username">@<?php echo htmlspecialchars($user['username']); ?></p>
                                    <?php endif; ?>
                                    <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>

                                    <div class="user-badges">
                                            <span class="user-badge role-<?php echo $user['role']; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        <span class="user-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        <?php if (!$user['email_verified']): ?>
                                            <span class="user-badge unverified">Unverified</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="user-actions">
                                    <button class="action-btn edit" onclick="event.stopPropagation(); editUser(<?php echo $user['id']; ?>)" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn activity" onclick="event.stopPropagation(); viewActivity(<?php echo $user['id']; ?>)" title="View Activity">
                                        <i class="fas fa-history"></i>
                                    </button>
                                    <?php if (!$is_current_user): ?>
                                        <button class="action-btn delete" onclick="event.stopPropagation(); deleteUser(<?php echo $user['id']; ?>)" title="Delete User">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="user-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $user['events_joined']; ?></div>
                                    <div class="stat-label">Events</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $user['activity_count']; ?></div>
                                    <div class="stat-label">Activities</div>
                                </div>
                                <?php if ($user['phone']): ?>
                                    <div class="stat-item">
                                        <div class="stat-number"><i class="fas fa-phone"></i></div>
                                        <div class="stat-label"><?php echo htmlspecialchars($user['phone']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="user-meta">
                                <div class="last-login">
                                    <span class="online-indicator <?php echo $is_online ? 'online' : 'offline'; ?>"></span>
                                    <span data-utc="<?php echo $user['last_login']; ?>" data-format="time-ago">
                                            <?php if ($user['last_login']): ?>
                                                Last: <?php echo convertUTCtoIST($user['last_login'], 'M d, h:i A'); ?>
                                            <?php else: ?>
                                                Never logged in
                                            <?php endif; ?>
                                        </span>
                                </div>
                                <div class="joined-date">
                                        <span data-utc="<?php echo $user['created_at']; ?>">
                                            Joined: <?php echo convertUTCtoIST($user['created_at'], 'M d, Y'); ?>
                                        </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Create/Edit User Form -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $current_action === 'create' ? 'user-plus' : 'user-edit'; ?> me-2"></i>
                            <?php echo $current_action === 'create' ? 'Create New User' : 'Edit User'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $current_action; ?>">
                            <?php if ($edit_user): ?>
                                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username"
                                           value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>"
                                           required <?php echo $edit_user ? 'readonly' : ''; ?>>
                                    <?php if ($edit_user): ?>
                                        <div class="form-text">Username cannot be changed after creation.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo $edit_user ? htmlspecialchars($edit_user['email']) : ''; ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name"
                                           value="<?php echo $edit_user ? htmlspecialchars($edit_user['first_name']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name"
                                           value="<?php echo $edit_user ? htmlspecialchars($edit_user['last_name']) : ''; ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone" name="phone"
                                           value="<?php echo $edit_user ? htmlspecialchars($edit_user['phone']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Role *</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="user" <?php echo ($edit_user && $edit_user['role'] === 'user') || !$edit_user ? 'selected' : ''; ?>>User</option>
                                        <option value="admin" <?php echo $edit_user && $edit_user['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    Password <?php echo $current_action === 'create' ? '*' : '(leave blank to keep current)'; ?>
                                </label>
                                <input type="password" class="form-control" id="password" name="password"
                                    <?php echo $current_action === 'create' ? 'required' : ''; ?>>
                                <div class="form-text">Password must be at least 6 characters long.</div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                        <?php echo !$edit_user || $edit_user['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Active User
                                    </label>
                                    <div class="form-text">Inactive users cannot log in to the system.</div>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    <?php echo $current_action === 'create' ? 'Create User' : 'Update User'; ?>
                                </button>
                                <a href="users.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($edit_user): ?>
                <!-- User Statistics Sidebar -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>User Statistics</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <h5 class="text-primary"><?php echo isset($edit_user['events_joined']) ? $edit_user['events_joined'] : 0; ?></h5>
                                    <small class="text-muted">Events Joined</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <h5 class="text-info"><?php echo isset($edit_user['activity_count']) ? $edit_user['activity_count'] : 0; ?></h5>
                                    <small class="text-muted">Activities</small>
                                </div>
                            </div>

                            <hr>

                            <div class="mb-2">
                                <strong>Display Name:</strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars(getUserCardDisplayName($edit_user)); ?></small>
                            </div>

                            <div class="mb-2">
                                <strong>Account Created:</strong><br>
                                <small class="text-muted" data-utc="<?php echo $edit_user['created_at']; ?>">
                                    <?php echo convertUTCtoIST($edit_user['created_at'], 'F d, Y \a\t h:i A'); ?>
                                </small>
                            </div>

                            <?php if ($edit_user['last_login']): ?>
                                <div class="mb-2">
                                    <strong>Last Login:</strong><br>
                                    <small class="text-muted" data-utc="<?php echo $edit_user['last_login']; ?>">
                                        <?php echo convertUTCtoIST($edit_user['last_login'], 'F d, Y \a\t h:i A'); ?>
                                    </small>
                                </div>
                            <?php endif; ?>

                            <div class="mb-2">
                                <strong>Email Verified:</strong><br>
                                <span class="badge bg-<?php echo $edit_user['email_verified'] ? 'success' : 'warning'; ?>">
                                        <?php echo $edit_user['email_verified'] ? 'Verified' : 'Pending'; ?>
                                    </span>
                            </div>

                            <hr>

                            <a href="activity.php?user_id=<?php echo $edit_user['id']; ?>" class="btn btn-outline-info btn-sm w-100">
                                <i class="fas fa-history me-1"></i>View Activity Log
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div> <!-- End container-fluid -->

<!-- Bulk Action Forms (Hidden) -->
<form id="bulkActionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="bulkActionType">
    <div id="bulkUserIds"></div>
</form>

<!-- Load time utilities first -->
<script src="../assets/js/time-utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
    // User management specific IST time management
    document.addEventListener('DOMContentLoaded', function() {
        // Enhanced IST time display and management
        function updateAllTimes() {
            // Update navbar time to IST
            const now = new Date();
            const istOptions = {
                timeZone: 'Asia/Kolkata',
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };

            const istTime = now.toLocaleString('en-IN', istOptions);

            // Update navbar time
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.innerHTML = `<i class="fas fa-clock me-1"></i>${istTime} IST`;
            }

            // Update current display time in header
            const displayTimeElement = document.getElementById('currentDisplayTime');
            if (displayTimeElement) {
                displayTimeElement.textContent = `${istTime} IST`;
            }

            // Update all time elements with data-utc attributes
            document.querySelectorAll('[data-utc]').forEach(element => {
                const utcTime = element.getAttribute('data-utc');
                if (utcTime && utcTime !== '') {
                    const utcDate = new Date(utcTime + 'Z'); // Ensure UTC
                    const istDate = new Date(utcDate.getTime() + (5.5 * 60 * 60 * 1000)); // Add 5.5 hours
                    const format = element.getAttribute('data-format') || 'default';

                    if (format === 'time-ago') {
                        const diffMs = now.getTime() - istDate.getTime();
                        const diffMinutes = Math.floor(diffMs / (1000 * 60));
                        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

                        if (diffMinutes < 1) element.textContent = 'Just now';
                        else if (diffMinutes < 60) element.textContent = `${diffMinutes}m ago`;
                        else if (diffHours < 24) element.textContent = `${diffHours}h ago`;
                        else if (diffDays < 30) element.textContent = `${diffDays}d ago`;
                        else element.textContent = istDate.toLocaleDateString('en-IN');
                    } else {
                        element.textContent = istDate.toLocaleString('en-IN', istOptions);
                    }
                }
            });
        }

        // Initialize and update times
        updateAllTimes();
        setInterval(updateAllTimes, 1000);
    });

    // Bulk Actions JavaScript
    function updateBulkActions() {
        const checkboxes = document.querySelectorAll('.user-select-checkbox:checked');
        const count = checkboxes.length;
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');

        selectedCount.textContent = count;

        if (count > 0) {
            bulkActions.classList.add('show');
        } else {
            bulkActions.classList.remove('show');
        }
    }

    function toggleUserSelect(userId) {
        const checkbox = document.querySelector(`input[value="${userId}"]`);
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            updateBulkActions();
        }
    }

    function bulkAction(action) {
        const checkboxes = document.querySelectorAll('.user-select-checkbox:checked');

        if (checkboxes.length === 0) {
            alert('Please select at least one user.');
            return;
        }

        let confirmMessage = '';
        switch (action) {
            case 'delete':
                confirmMessage = `Are you sure you want to delete ${checkboxes.length} user(s)? This action cannot be undone.`;
                break;
            case 'activate':
                confirmMessage = `Are you sure you want to activate ${checkboxes.length} user(s)?`;
                break;
            case 'deactivate':
                confirmMessage = `Are you sure you want to deactivate ${checkboxes.length} user(s)?`;
                break;
        }

        if (confirm(confirmMessage)) {
            const form = document.getElementById('bulkActionForm');
            const actionInput = document.getElementById('bulkActionType');
            const idsContainer = document.getElementById('bulkUserIds');

            actionInput.value = 'bulk_' + action;
            idsContainer.innerHTML = '';

            checkboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = cb.value;
                idsContainer.appendChild(input);
            });

            form.submit();
        }
    }

    // User action functions
    function editUser(userId) {
        window.location.href = `users.php?action=edit&id=${userId}`;
    }

    function viewActivity(userId) {
        window.location.href = `activity.php?user_id=${userId}`;
    }

    function deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user? This will also remove all their event registrations.')) {
            window.location.href = `users.php?delete=${userId}`;
        }
    }

    // Auto-submit search form when filters change
    document.querySelectorAll('#role, #status').forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

    console.log('User management page loaded successfully with IST timezone');
    console.log('Current admin: <?php echo $display_name; ?>');
    console.log('Current username: <?php echo $current_user; ?>');
    console.log('Current UTC Time: <?php echo $current_utc; ?>');
    console.log('Current IST Time: <?php echo $current_ist_time; ?>');
    console.log('Total users displayed: <?php echo count($users); ?>');
</script>
</body>
</html>
<?php
/**
 * SportsMeet Manager - Database Configuration
 *
 * This file contains database connection settings and helper functions
 * for the SportsMeet Manager application.
 *
 * @author SportsMeet Team
 * @version 1.0
 * @since 2025-08-04
 */

// Prevent direct access
if (!defined('SPORTSMEET_ACCESS')) {
    define('SPORTSMEET_ACCESS', true);
}

// Database Configuration Constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sportsmeet_manager');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// Application Configuration
define('APP_NAME', 'SportsMeet Manager');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', true); // Set to false in production
define('APP_TIMEZONE', 'UTC');

// Security Configuration
define('SESSION_LIFETIME', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds

// File Upload Configuration
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', '../uploads/');

// Set default timezone
date_default_timezone_set(APP_TIMEZONE);

// Global database connection variable
$pdo = null;

/**
 * Get database connection using PDO
 *
 * @return PDO Database connection object
 * @throws Exception If connection fails
 */
function getDBConnection() {
    global $pdo;

    // Return existing connection if available
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // Create PDO connection string
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        // PDO options for better security and performance
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_COLLATION,
            PDO::ATTR_TIMEOUT            => 30, // Connection timeout in seconds
        ];

        // Create new PDO instance
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Log successful connection in debug mode
        if (APP_DEBUG) {
            error_log("Database connection established successfully at " . date('Y-m-d H:i:s'));
        }

        return $pdo;

    } catch (PDOException $e) {
        // Log the error
        error_log("Database connection failed: " . $e->getMessage());

        // Show user-friendly error in debug mode, generic error in production
        if (APP_DEBUG) {
            die("Database Connection Error: " . $e->getMessage());
        } else {
            die("Database connection failed. Please try again later.");
        }
    }
}

/**
 * Test database connection
 *
 * @return bool True if connection successful, false otherwise
 */
function testDBConnection() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Initialize database tables if they don't exist
 *
 * @return bool True if initialization successful, false otherwise
 */
function initializeDatabase() {
    try {
        $pdo = getDBConnection();

        // Check if tables exist
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            return true; // Tables already exist
        }

        // Create tables
        $sql = file_get_contents(__DIR__ . '/../sql/database.sql');
        if ($sql === false) {
            throw new Exception("Could not read database schema file");
        }

        // Execute SQL commands
        $pdo->exec($sql);

        if (APP_DEBUG) {
            error_log("Database tables initialized successfully");
        }

        return true;

    } catch (Exception $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get database statistics
 *
 * @return array Database statistics
 */
function getDatabaseStats() {
    try {
        $pdo = getDBConnection();
        $stats = [];

        // Get table statistics
        $tables = ['users', 'events', 'event_participants'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
            $stats[$table] = $stmt->fetch()['count'];
        }

        // Get database size
        $stmt = $pdo->prepare("SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
            FROM information_schema.tables 
            WHERE table_schema = ?");
        $stmt->execute([DB_NAME]);
        $stats['size_mb'] = $stmt->fetch()['size_mb'] ?? 0;

        return $stats;

    } catch (Exception $e) {
        error_log("Failed to get database stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Execute a prepared statement with error handling
 *
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters for the query
 * @return PDOStatement|false Statement object or false on failure
 */
function executeQuery($sql, $params = []) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;

    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage() . " | Query: " . $sql);
        return false;
    }
}

/**
 * Begin database transaction
 *
 * @return bool True if transaction started successfully
 */
function beginTransaction() {
    try {
        $pdo = getDBConnection();
        return $pdo->beginTransaction();
    } catch (Exception $e) {
        error_log("Failed to begin transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Commit database transaction
 *
 * @return bool True if transaction committed successfully
 */
function commitTransaction() {
    try {
        $pdo = getDBConnection();
        return $pdo->commit();
    } catch (Exception $e) {
        error_log("Failed to commit transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Rollback database transaction
 *
 * @return bool True if transaction rolled back successfully
 */
function rollbackTransaction() {
    try {
        $pdo = getDBConnection();
        return $pdo->rollBack();
    } catch (Exception $e) {
        error_log("Failed to rollback transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Sanitize input for database operations
 *
 * @param mixed $input Input to sanitize
 * @return mixed Sanitized input
 */
function sanitizeInput($input) {
    if (is_string($input)) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    } elseif (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return $input;
}

/**
 * Log database activity
 *
 * @param string $action Action performed
 * @param string $table Table affected
 * @param int $user_id User ID performing the action
 * @param array $details Additional details
 */
function logDatabaseActivity($action, $table, $user_id = null, $details = []) {
    if (!APP_DEBUG) {
        return; // Only log in debug mode
    }

    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'table' => $table,
        'user_id' => $user_id,
        'details' => $details
    ];

    error_log("DB Activity: " . json_encode($log_entry));
}

// Start session management
if (session_status() == PHP_SESSION_NONE) {
    // Configure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

    // Start session
    session_start();

    // Session security checks
    if (isset($_SESSION['created']) && (time() - $_SESSION['created']) > SESSION_LIFETIME) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    } elseif (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    }
}

/**
 * Clean up old sessions and temporary data
 */
function cleanupDatabase() {
    try {
        $pdo = getDBConnection();

        // Clean up expired sessions (if you have a sessions table)
        // $pdo->exec("DELETE FROM sessions WHERE expires_at < NOW()");

        // Add other cleanup operations as needed

        if (APP_DEBUG) {
            error_log("Database cleanup completed");
        }

    } catch (Exception $e) {
        error_log("Database cleanup failed: " . $e->getMessage());
    }
}

/**
 * Get current database schema version
 *
 * @return string Schema version
 */
function getSchemaVersion() {
    try {
        $pdo = getDBConnection();

        // Try to get version from a version table (create if needed)
        $stmt = $pdo->query("SELECT version FROM schema_version ORDER BY id DESC LIMIT 1");
        if ($stmt && $row = $stmt->fetch()) {
            return $row['version'];
        }

        return '1.0.0'; // Default version

    } catch (Exception $e) {
        return '1.0.0'; // Default version if table doesn't exist
    }
}

/**
 * Check if database needs migration
 *
 * @return bool True if migration needed
 */
function needsMigration() {
    $current_version = getSchemaVersion();
    return version_compare($current_version, APP_VERSION, '<');
}

// Initialize error reporting based on debug mode
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// Create logs directory if it doesn't exist
$logs_dir = __DIR__ . '/../logs';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

// Test database connection on include
if (!testDBConnection()) {
    if (APP_DEBUG) {
        error_log("Warning: Database connection test failed during config load");
    }
}

// Schedule cleanup (you might want to do this with a cron job in production)
if (random_int(1, 100) === 1) { // 1% chance
    cleanupDatabase();
}

// Define a constant to indicate config is loaded
define('SPORTSMEET_CONFIG_LOADED', true);

?>
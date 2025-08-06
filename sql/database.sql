-- SportsMeet Manager Database Schema
-- Version: 1.0.0
-- Created: 2025-08-04

CREATE DATABASE IF NOT EXISTS sportsmeet_manager;
USE sportsmeet_manager;

-- Set charset and collation
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Users table
CREATE TABLE IF NOT EXISTS users (
                                     id INT PRIMARY KEY AUTO_INCREMENT,
                                     username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    first_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NULL,
    phone VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    profile_image VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Events table
CREATE TABLE IF NOT EXISTS events (
                                      id INT PRIMARY KEY AUTO_INCREMENT,
                                      title VARCHAR(200) NOT NULL,
    description TEXT,
    sport_name VARCHAR(100) NOT NULL,
    venue VARCHAR(200) NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    max_participants INT NOT NULL DEFAULT 50,
    registration_deadline DATETIME NULL,
    event_image VARCHAR(255) NULL,
    entry_fee DECIMAL(10,2) DEFAULT 0.00,
    contact_email VARCHAR(100) NULL,
    contact_phone VARCHAR(20) NULL,
    status ENUM('draft', 'published', 'cancelled', 'completed') DEFAULT 'published',
    is_featured BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_event_date (event_date),
    INDEX idx_sport_name (sport_name),
    INDEX idx_venue (venue),
    INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event participants table
CREATE TABLE IF NOT EXISTS event_participants (
                                                  id INT PRIMARY KEY AUTO_INCREMENT,
                                                  event_id INT NOT NULL,
                                                  user_id INT NOT NULL,
                                                  registration_status ENUM('registered', 'confirmed', 'cancelled', 'attended') DEFAULT 'registered',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    special_requirements TEXT NULL,
    emergency_contact VARCHAR(100) NULL,
    emergency_phone VARCHAR(20) NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participation (event_id, user_id),
    INDEX idx_event_id (event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_registration_status (registration_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_logs (
                                             id INT PRIMARY KEY AUTO_INCREMENT,
                                             user_id INT NULL,
                                             action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schema version table
CREATE TABLE IF NOT EXISTS schema_version (
                                              id INT PRIMARY KEY AUTO_INCREMENT,
                                              version VARCHAR(20) NOT NULL,
    description TEXT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table for application configuration
CREATE TABLE IF NOT EXISTS settings (
                                        id INT PRIMARY KEY AUTO_INCREMENT,
                                        setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
                                             id INT PRIMARY KEY AUTO_INCREMENT,
                                             user_id INT NOT NULL,
                                             title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(255) NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial schema version
INSERT INTO schema_version (version, description) VALUES ('1.0.0', 'Initial schema creation');

-- Insert default admin user
INSERT INTO users (username, email, password, role, first_name, last_name, is_active, email_verified) VALUES
    ('admin', 'admin@sportsmeet.com', 'admin123', 'admin', 'System', 'Administrator', TRUE, TRUE);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES
                                                                                            ('app_name', 'SportsMeet Manager', 'string', 'Application name', TRUE),
                                                                                            ('app_description', 'Your ultimate sports event management platform', 'string', 'Application description', TRUE),
                                                                                            ('max_participants_default', '50', 'integer', 'Default maximum participants for events', FALSE),
                                                                                            ('registration_enabled', 'true', 'boolean', 'Whether user registration is enabled', TRUE),
                                                                                            ('email_notifications', 'true', 'boolean', 'Whether email notifications are enabled', FALSE),
                                                                                            ('maintenance_mode', 'false', 'boolean', 'Whether the site is in maintenance mode', FALSE);

-- Insert sample events data
INSERT INTO events (title, description, sport_name, venue, event_date, event_time, max_participants, created_by, registration_deadline) VALUES
                                                                                                                                            ('Summer Football Championship', 'Join us for an exciting football tournament featuring teams from across the region. Prizes for top 3 teams!', 'Football', 'Main Stadium Complex', '2025-08-15', '09:00:00', 22, 1, '2025-08-13 23:59:59'),
                                                                                                                                            ('Basketball League Finals', 'The ultimate showdown between the best basketball teams. Fast-paced action guaranteed!', 'Basketball', 'Sports Center Court A', '2025-08-20', '14:00:00', 10, 1, '2025-08-18 23:59:59'),
                                                                                                                                            ('Cricket T20 Tournament', 'Experience the thrill of T20 cricket with multiple teams competing for the championship.', 'Cricket', 'Cricket Ground East', '2025-08-25', '10:00:00', 22, 1, '2025-08-23 23:59:59'),
                                                                                                                                            ('Tennis Open Championship', 'Individual tennis tournament open to all skill levels. Professional coaching available.', 'Tennis', 'Tennis Courts Complex', '2025-08-30', '08:00:00', 32, 1, '2025-08-28 23:59:59'),
                                                                                                                                            ('Swimming Competition', 'Multi-category swimming event including freestyle, backstroke, and relay races.', 'Swimming', 'Aquatic Center Pool', '2025-09-05', '07:00:00', 40, 1, '2025-09-03 23:59:59');

-- Create views for common queries
CREATE VIEW event_summary AS
SELECT
    e.id,
    e.title,
    e.sport_name,
    e.venue,
    e.event_date,
    e.event_time,
    e.max_participants,
    COUNT(ep.id) as registered_participants,
    (e.max_participants - COUNT(ep.id)) as available_spots,
    CASE
        WHEN CONCAT(e.event_date, ' ', e.event_time) > NOW() THEN 'Upcoming'
        ELSE 'Completed'
        END as status,
    e.created_at
FROM events e
         LEFT JOIN event_participants ep ON e.id = ep.event_id AND ep.registration_status != 'cancelled'
WHERE e.status = 'published'
GROUP BY e.id, e.title, e.sport_name, e.venue, e.event_date, e.event_time, e.max_participants, e.created_at;

CREATE VIEW user_participation_summary AS
SELECT
    u.id as user_id,
    u.username,
    u.email,
    COUNT(ep.id) as total_events_joined,
    COUNT(CASE WHEN e.event_date < CURDATE() THEN 1 END) as completed_events,
    COUNT(CASE WHEN e.event_date >= CURDATE() THEN 1 END) as upcoming_events
FROM users u
         LEFT JOIN event_participants ep ON u.id = ep.user_id AND ep.registration_status != 'cancelled'
LEFT JOIN events e ON ep.event_id = e.id
WHERE u.role = 'user' AND u.is_active = TRUE
GROUP BY u.id, u.username, u.email;

-- Create stored procedures for common operations
DELIMITER //

CREATE PROCEDURE GetEventStats(IN event_id INT)
BEGIN
SELECT
    e.title,
    e.max_participants,
    COUNT(ep.id) as registered_count,
    (e.max_participants - COUNT(ep.id)) as available_spots,
    ROUND((COUNT(ep.id) / e.max_participants) * 100, 2) as fill_percentage
FROM events e
         LEFT JOIN event_participants ep ON e.id = ep.event_id AND ep.registration_status != 'cancelled'
WHERE e.id = event_id
GROUP BY e.id, e.title, e.max_participants;
END //

CREATE PROCEDURE GetUserEventHistory(IN user_id INT)
BEGIN
SELECT
    e.id,
    e.title,
    e.sport_name,
    e.venue,
    e.event_date,
    e.event_time,
    ep.registration_status,
    ep.joined_at,
    CASE
        WHEN e.event_date < CURDATE() THEN 'Completed'
        ELSE 'Upcoming'
        END as event_status
FROM event_participants ep
         JOIN events e ON ep.event_id = e.id
WHERE ep.user_id = user_id
ORDER BY e.event_date DESC;
END //

DELIMITER ;

-- Create triggers for activity logging
DELIMITER //

CREATE TRIGGER user_activity_log AFTER UPDATE ON users
    FOR EACH ROW
BEGIN
    IF OLD.last_login != NEW.last_login THEN
        INSERT INTO activity_logs (user_id, action, table_name, record_id, new_values)
        VALUES (NEW.id, 'login', 'users', NEW.id, JSON_OBJECT('last_login', NEW.last_login));
END IF;
END //

CREATE TRIGGER event_participation_log AFTER INSERT ON event_participants
    FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, table_name, record_id, new_values)
    VALUES (NEW.user_id, 'event_join', 'event_participants', NEW.id,
            JSON_OBJECT('event_id', NEW.event_id, 'joined_at', NEW.joined_at));
END //

DELIMITER ;

-- Create indexes for better performance
CREATE INDEX idx_events_date_status ON events(event_date, status);
CREATE INDEX idx_participants_status ON event_participants(registration_status);
CREATE INDEX idx_users_active ON users(is_active);

-- Set up database optimization
ANALYZE TABLE users, events, event_participants;

-- Final commit
COMMIT;
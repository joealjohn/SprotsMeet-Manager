<?php
/**
 * Common Footer for SportsMeet Manager
 *
 * This file contains the common footer elements and JavaScript
 * used across different pages of the application.
 */

// Get some statistics for the footer
$footer_stats = [
    'total_events' => 0,
    'total_users' => 0,
    'total_registrations' => 0
];

try {
    $pdo = getDBConnection();

    // Get total events
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE status = 'published'");
    $footer_stats['total_events'] = $stmt->fetch()['count'];

    // Get total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND is_active = TRUE");
    $footer_stats['total_users'] = $stmt->fetch()['count'];

    // Get total registrations
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM event_participants WHERE registration_status != 'cancelled'");
    $footer_stats['total_registrations'] = $stmt->fetch()['count'];

} catch (Exception $e) {
    error_log("Error getting footer stats: " . $e->getMessage());
}

// Get current year
$current_year = date('Y');
?>

</main> <!-- End main content -->

<!-- Footer -->
<footer class="footer mt-5">
    <div class="container">
        <div class="row">
            <!-- Brand Section -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="footer-brand">
                    <h5><i class="fas fa-trophy me-2"></i>SportsMeet Manager</h5>
                    <p class="text-muted">Your ultimate platform for managing and participating in sports events. Join our community of athletes and sports enthusiasts today!</p>

                    <!-- Live Stats -->
                    <div class="footer-stats">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stat-number"><?php echo $footer_stats['total_events']; ?></div>
                                <div class="stat-label">Events</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-number"><?php echo $footer_stats['total_users']; ?></div>
                                <div class="stat-label">Athletes</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-number"><?php echo $footer_stats['total_registrations']; ?></div>
                                <div class="stat-label">Registrations</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6 mb-4">
                <h5>Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="<?php echo getBaseUrl(); ?>/index.php">Home</a></li>
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li><a href="<?php echo getBaseUrl(); ?>/admin/dashboard.php">Admin Dashboard</a></li>
                            <li><a href="<?php echo getBaseUrl(); ?>/admin/events.php">Manage Events</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo getBaseUrl(); ?>/user/dashboard.php">Dashboard</a></li>
                            <li><a href="<?php echo getBaseUrl(); ?>/user/events.php">Browse Events</a></li>
                            <li><a href="<?php echo getBaseUrl(); ?>/user/my_events.php">My Events</a></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li><a href="<?php echo getBaseUrl(); ?>/auth/login.php">Login</a></li>
                        <li><a href="<?php echo getBaseUrl(); ?>/auth/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Sports Categories -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5>Popular Sports</h5>
                <ul class="footer-links">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT sport_name, COUNT(*) as count FROM events WHERE status = 'published' GROUP BY sport_name ORDER BY count DESC LIMIT 6");
                        $sports = $stmt->fetchAll();

                        foreach ($sports as $sport):
                            ?>
                            <li>
                                <a href="<?php echo getBaseUrl(); ?>/user/events.php?sport=<?php echo urlencode($sport['sport_name']); ?>">
                                    <?php echo htmlspecialchars($sport['sport_name']); ?>
                                    <span class="text-muted">(<?php echo $sport['count']; ?>)</span>
                                </a>
                            </li>
                        <?php
                        endforeach;
                    } catch (Exception $e) {
                        // Default sports if query fails
                        $default_sports = ['Football', 'Basketball', 'Cricket', 'Tennis', 'Swimming', 'Volleyball'];
                        foreach ($default_sports as $sport):
                            ?>
                            <li><a href="<?php echo getBaseUrl(); ?>/user/events.php?sport=<?php echo urlencode($sport); ?>"><?php echo $sport; ?></a></li>
                        <?php endforeach; } ?>
                </ul>
            </div>

            <!-- Contact & Info -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5>Connect With Us</h5>
                <ul class="footer-links">
                    <li><i class="fas fa-envelope me-2"></i>info@sportsmeet.com</li>
                    <li><i class="fas fa-phone me-2"></i>+1 (555) 123-4567</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i>Sports City, SC 12345</li>
                    <li><i class="fas fa-clock me-2"></i>24/7 Support</li>
                </ul>

                <!-- Social Media Links -->
                <div class="social-icons mt-3">
                    <a href="#" class="social-link" title="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-link" title="Twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="social-link" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="social-link" title="LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">

        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="mb-0 text-muted">
                    &copy; <?php echo $current_year; ?> SportsMeet Manager. All rights reserved.
                    <small class="d-block d-md-inline ms-md-2">
                        Version <?php echo APP_VERSION; ?> |
                        Current User: <?php echo isLoggedIn() ? htmlspecialchars(getCurrentUserDisplayName()) : 'Guest'; ?>
                    </small>
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="footer-legal-links">
                    <a href="#" class="text-muted me-3">Privacy Policy</a>
                    <a href="#" class="text-muted me-3">Terms of Service</a>
                    <a href="#" class="text-muted">Help</a>
                </div>

                <!-- Live Time Display -->
                <div class="live-time mt-2">
                    <small class="text-muted">
                        <i class="fas fa-globe me-1"></i>
                        Server Time: <span id="serverTime"><?php echo getCurrentUTCTime(); ?> UTC</span>
                    </small>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<button class="btn-back-to-top" id="backToTop" title="Back to Top">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- JavaScript Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script>
    // Global JavaScript for SportsMeet Manager

    // Update current time every second
    function updateTime() {
        const now = new Date();
        const utcTime = now.toISOString().slice(0, 19).replace('T', ' ');

        // Update current time in navbar
        const currentTimeElement = document.getElementById('currentTime');
        if (currentTimeElement) {
            const options = {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                timeZone: 'UTC'
            };
            currentTimeElement.innerHTML =
                '<i class="fas fa-clock me-1"></i>' +
                now.toLocaleDateString('en-US', options) + ' UTC';
        }

        // Update server time in footer
        const serverTimeElement = document.getElementById('serverTime');
        if (serverTimeElement) {
            serverTimeElement.textContent = utcTime + ' UTC';
        }
    }

    // Update time immediately and then every second
    updateTime();
    setInterval(updateTime, 1000);

    // Back to top button functionality
    const backToTopBtn = document.getElementById('backToTop');

    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopBtn.style.display = 'flex';
        } else {
            backToTopBtn.style.display = 'none';
        }
    });

    backToTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Mark notifications as read when clicked
    document.querySelectorAll('#notificationDropdown + .dropdown-menu .dropdown-item').forEach(item => {
        item.addEventListener('click', function() {
            // This would normally make an AJAX call to mark notification as read
            console.log('Notification clicked');
        });
    });

    // Add loading states to buttons
    document.querySelectorAll('button[type="submit"], .btn-submit').forEach(button => {
        button.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';

            // Re-enable button after 3 seconds (fallback)
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = originalText;
            }, 3000);
        });
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Console welcome message for developers
    console.log('%c🏆 SportsMeet Manager', 'font-size: 20px; color: #667eea; font-weight: bold;');
    console.log('%cVersion: <?php echo APP_VERSION; ?>', 'color: #6c757d;');
    console.log('%cCurrent User: <?php echo isLoggedIn() ? htmlspecialchars(getCurrentUserDisplayName()) : 'Guest'; ?>', 'color: #6c757d;');
    console.log('%cCurrent Time: <?php echo getCurrentUTCTime(); ?> UTC', 'color: #6c757d;');

    <?php if (APP_DEBUG): ?>
    console.log('%cDEBUG MODE ENABLED', 'background: #dc3545; color: white; padding: 2px 5px; border-radius: 3px;');
    <?php endif; ?>
</script>

<!-- Additional Styles for Footer -->
<style>
    .footer {
        background: var(--dark-gradient);
        color: white;
        padding: 60px 0 30px;
        margin-top: auto;
    }

    .footer h5 {
        font-weight: 600;
        margin-bottom: 20px;
        color: white;
    }

    .footer-links {
        list-style: none;
        padding: 0;
    }

    .footer-links li {
        margin-bottom: 10px;
    }

    .footer-links a {
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        transition: color 0.3s ease;
        font-size: 0.9rem;
    }

    .footer-links a:hover {
        color: white;
    }

    .footer-stats {
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
        padding: 15px;
        margin-top: 20px;
    }

    .footer-stats .stat-number {
        font-size: 1.2rem;
        font-weight: 700;
        color: white;
    }

    .footer-stats .stat-label {
        font-size: 0.8rem;
        color: rgba(255,255,255,0.7);
    }

    .social-icons {
        display: flex;
        gap: 10px;
    }

    .social-link {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
        color: white;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .social-link:hover {
        background: var(--primary-gradient);
        transform: translateY(-3px);
        color: white;
    }

    .footer-legal-links a {
        font-size: 0.9rem;
    }

    .live-time {
        font-family: 'Courier New', monospace;
    }

    .btn-back-to-top {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        background: var(--primary-gradient);
        color: white;
        border: none;
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        transition: all 0.3s ease;
        z-index: 1000;
    }

    .btn-back-to-top:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        color: white;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .footer {
            padding: 40px 0 20px;
        }

        .footer-stats {
            margin-top: 15px;
        }

        .footer-stats .row > div {
            margin-bottom: 10px;
        }

        .btn-back-to-top {
            bottom: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
        }
    }
</style>

<?php if (APP_DEBUG): ?>
    <!-- Debug Information (only shown in debug mode) -->
    <div id="debugInfo" style="position: fixed; bottom: 10px; left: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-size: 11px; z-index: 9999; max-width: 300px;">
        <strong>Debug Info:</strong><br>
        Page: <?php echo basename($_SERVER['PHP_SELF']); ?><br>
        User: <?php echo isLoggedIn() ? htmlspecialchars(getCurrentUserDisplayName()) . ' (' . $_SESSION['role'] . ')' : 'Guest'; ?><br>
        Time: <?php echo getCurrentUTCTime(); ?> UTC<br>
        Memory: <?php echo formatFileSize(memory_get_usage(true)); ?><br>
        <button onclick="document.getElementById('debugInfo').style.display='none'" style="background: none; border: none; color: white; float: right; cursor: pointer;">×</button>
    </div>
<?php endif; ?>

</body>
</html>
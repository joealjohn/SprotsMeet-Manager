<?php
require_once '../config/database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Store username for goodbye message
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Destroy all session data
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - SportsMeet Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="30" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="70" r="3" fill="rgba(255,255,255,0.05)"/><circle cx="50" cy="10" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="2" fill="rgba(255,255,255,0.05)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .logout-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 450px;
            margin: auto;
            text-align: center;
        }

        .logout-card {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .logout-icon {
            width: 80px;
            height: 80px;
            background: var(--success-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 2rem;
            animation: checkmark 0.6s ease-in-out;
        }

        @keyframes checkmark {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .logout-title {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }

        .logout-message {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn-group-logout {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-logout {
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 25px;
            border: none;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary-logout {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-primary-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-outline-logout {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-outline-logout:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .countdown {
            margin-top: 30px;
            padding: 15px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 10px;
            font-size: 0.9rem;
            color: #667eea;
        }

        @media (max-width: 576px) {
            .logout-card {
                margin: 20px;
                padding: 40px 25px;
            }

            .logout-title {
                font-size: 1.6rem;
            }

            .btn-group-logout {
                flex-direction: column;
                align-items: center;
            }

            .btn-logout {
                width: 100%;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="logout-container">
        <div class="logout-card">
            <div class="logout-icon">
                <i class="fas fa-check"></i>
            </div>

            <h2 class="logout-title">Successfully Logged Out</h2>

            <p class="logout-message">
                <?php if ($username): ?>
                    Thank you for using SportsMeet Manager, <strong><?php echo htmlspecialchars($username); ?></strong>!
                    <br>We hope to see you again soon.
                <?php else: ?>
                    Thank you for using SportsMeet Manager!<br>
                    We hope to see you again soon.
                <?php endif; ?>
            </p>

            <div class="btn-group-logout">
                <a href="../index.php" class="btn-logout btn-primary-logout">
                    <i class="fas fa-home"></i>
                    Back to Home
                </a>
                <a href="login.php" class="btn-logout btn-outline-logout">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In Again
                </a>
            </div>

            <div class="countdown">
                <i class="fas fa-clock me-1"></i>
                Redirecting to homepage in <span id="countdown">10</span> seconds...
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Countdown and auto-redirect
    let timeLeft = 10;
    const countdownElement = document.getElementById('countdown');

    const countdown = setInterval(function() {
        timeLeft--;
        countdownElement.textContent = timeLeft;

        if (timeLeft <= 0) {
            clearInterval(countdown);
            window.location.href = '../index.php';
        }
    }, 1000);

    // Add some confetti effect (optional)
    function createConfetti() {
        const colors = ['#667eea', '#764ba2', '#4facfe', '#00f2fe'];
        const confettiCount = 50;

        for (let i = 0; i < confettiCount; i++) {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.width = '10px';
            confetti.style.height = '10px';
            confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.top = '-10px';
            confetti.style.borderRadius = '50%';
            confetti.style.pointerEvents = 'none';
            confetti.style.zIndex = '1000';
            confetti.style.animation = `confetti-fall ${Math.random() * 2 + 2}s linear forwards`;

            document.body.appendChild(confetti);

            setTimeout(() => {
                confetti.remove();
            }, 4000);
        }
    }

    // Add CSS for confetti animation
    const style = document.createElement('style');
    style.textContent = `
            @keyframes confetti-fall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                }
            }
        `;
    document.head.appendChild(style);

    // Trigger confetti on page load
    setTimeout(createConfetti, 500);
</script>
</body>
</html>
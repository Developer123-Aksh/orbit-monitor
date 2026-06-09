<?php
// login.php - Secure login interface

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (!empty($username) && !empty($password)) {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT * FROM `users` WHERE `username` = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred. Please try again later.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCADA Login - Access Control</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 40px;
            text-align: center;
        }

        .login-header {
            margin-bottom: 30px;
        }

        .login-header h1 {
            font-size: 1.75rem;
            margin-bottom: 8px;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .login-header p {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .error-alert {
            background: rgba(255, 23, 68, 0.15);
            border: 1px solid rgba(255, 23, 68, 0.3);
            border-radius: var(--radius-sm);
            color: #ff5252;
            padding: 12px;
            font-size: 0.875rem;
            margin-bottom: 20px;
            text-align: left;
        }

        .credentials-hint {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid var(--border-glass);
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="glass-card login-card fade-in">
            <div class="login-header">
                <h1 class="digital-font">SCADA Engine</h1>
                <p>Industrial Control System Access</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-alert fade-in">
                    <strong>Authentication Failed:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username">USERNAME</label>
                    <input type="text" id="username" name="username" class="form-control" autocomplete="username" required autofocus>
                </div>
                
                <div class="form-group" style="margin-bottom: 30px;">
                    <label for="password">PASSWORD</label>
                    <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%;">
                    SECURE SIGN IN
                </button>
            </form>

            <div class="credentials-hint">
                <strong>Default Access Roles:</strong><br>
                Admin: <code>admin</code> / <code>admin123</code><br>
                Operator: <code>operator</code> / <code>operator123</code>
            </div>
        </div>
    </div>
</body>
</html>

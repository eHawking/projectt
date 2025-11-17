<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if (is_logged_in()) {
    header('Location: /admin/dashboard');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM admin_users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_id'] = (int)$user['id'];
            $_SESSION['admin_username'] = $username;
            header('Location: /admin/dashboard');
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            /* Light theme (default) – dark navy CRM style */
            --bg-gradient: linear-gradient(135deg, #051637 0%, #020817 40%, #020314 100%);
            --card-bg: #071a35;
            --accent: #fb7185; /* neon pink */
            --accent-strong: #fb82a0;
            --text-main: #e5e7eb;
            --text-muted: #94a3b8;
            --border-subtle: rgba(15, 23, 42, 0.85);
            --field-bg: #020b26;
        }

        :root[data-theme="dark"] {
            /* Dark theme – slightly deeper variant */
            --bg-gradient: linear-gradient(135deg, #020617 0%, #020012 40%, #000000 100%);
            --card-bg: #061327;
            --accent: #fb7185;
            --accent-strong: #fb82a0;
            --text-main: #e5e7eb;
            --text-muted: #9ca3af;
            --border-subtle: rgba(15, 23, 42, 0.9);
            --field-bg: #020617;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: var(--text-main);
        }

        .login-box {
            width: 100%;
            max-width: 380px;
            background: var(--card-bg);
            padding: 26px 22px 28px;
            border-radius: 22px;
            box-shadow:
                0 22px 80px rgba(15, 23, 42, 1),
                0 0 0 1px rgba(15, 23, 42, 0.9);
        }

        h1 {
            margin: 0 0 6px 0;
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: -0.02em;
        }

        .sub {
            margin: 0 0 18px 0;
            text-align: center;
            font-size: 0.86rem;
            color: var(--text-muted);
        }

        label {
            font-size: 0.86rem;
            display: block;
            margin-bottom: 4px;
            color: var(--text-muted);
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 9px 10px;
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
            margin-bottom: 14px;
            box-sizing: border-box;
            background: var(--field-bg);
            color: var(--text-main);
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.4);
        }

        button {
            width: 100%;
            padding: 10px;
            border-radius: 999px;
            border: none;
            background: var(--accent);
            color: #022c22;
            font-size: 0.95rem;
            cursor: pointer;
            font-weight: 500;
            box-shadow: 0 12px 26px rgba(34, 197, 94, 0.25);
            transition: background-color 0.15s ease-out, transform 0.12s ease-out, box-shadow 0.15s ease-out;
        }

        button:hover {
            background: var(--accent-strong);
            transform: translateY(-1px);
        }

        button:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .error {
            color: #f97373;
            font-size: 0.85rem;
            margin-bottom: 10px;
            text-align: center;
        }

        .theme-toggle {
            position: fixed;
            top: 12px;
            right: 12px;
            border-radius: 999px;
            border: 1px solid var(--border-subtle);
            background: rgba(148, 163, 184, 0.12);
            color: var(--text-muted);
            font-size: 0.75rem;
            padding: 4px 10px;
            cursor: pointer;
        }

        .theme-toggle span {
            font-weight: 500;
            margin-right: 4px;
        }

        @media (max-width: 480px) {
            .login-box {
                padding: 22px 18px 24px;
            }
        }
    </style>
</head>
<body>
<button type="button" class="theme-toggle" data-theme-toggle>
    <span data-theme-toggle-label>Light</span> mode
</button>
<div class="login-box">
    <h1>Admin Login</h1>
    <p class="sub">Sign in to view your visitor analytics dashboard.</p>
    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <label for="username">Username</label>
        <input type="text" name="username" id="username" autocomplete="username">

        <label for="password">Password</label>
        <input type="password" name="password" id="password" autocomplete="current-password">

        <button type="submit">Log in</button>
    </form>
</div>
<script src="/assets/js/theme.js"></script>
</body>
</html>

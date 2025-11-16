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
        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f3f4f6;
        }
        .login-box {
            max-width: 360px;
            margin: 80px auto;
            background: #ffffff;
            padding: 24px 20px 28px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        h1 {
            margin-top: 0;
            font-size: 1.4rem;
            text-align: center;
        }
        label {
            font-size: 0.9rem;
            display: block;
            margin-bottom: 4px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            margin-bottom: 12px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 9px;
            border-radius: 6px;
            border: none;
            background: #2563eb;
            color: #ffffff;
            font-size: 0.95rem;
            cursor: pointer;
        }
        .error {
            color: #b91c1c;
            font-size: 0.85rem;
            margin-bottom: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="login-box">
    <h1>Admin Login</h1>
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
</body>
</html>

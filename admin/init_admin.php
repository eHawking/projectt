<?php
// One-time script to create the first admin user.
// IMPORTANT: After you successfully create an admin, delete this file from the server.

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$pdo = db();

// If an admin already exists, do not allow creating another here.
$count = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($count > 0) {
    echo '<p>An admin user already exists. For security, please delete init_admin.php from the server.</p>';
    echo '<p><a href="/admin/login">Go to login</a></p>';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)');
        try {
            $stmt->execute([':u' => $username, ':p' => $hash]);
            $success = 'Admin user created. You can now log in.';
        } catch (Throwable $e) {
            $error = 'Failed to create admin user (maybe username already exists).';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Init Admin User</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f3f4f6; margin:0; }
        .box { max-width: 380px; margin: 60px auto; background:#fff; padding:20px 18px 24px; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.08); }
        h1 { margin-top:0; font-size:1.3rem; text-align:center; }
        label { display:block; font-size:0.9rem; margin-bottom:4px; }
        input[type="text"], input[type="password"] { width:100%; padding:8px; margin-bottom:12px; border-radius:6px; border:1px solid #d1d5db; box-sizing:border-box; }
        button { width:100%; padding:9px; border-radius:6px; border:none; background:#16a34a; color:#fff; font-size:0.95rem; cursor:pointer; }
        .msg { font-size:0.85rem; margin-bottom:10px; text-align:center; }
        .msg.error { color:#b91c1c; }
        .msg.success { color:#166534; }
        .note { font-size:0.75rem; color:#6b7280; margin-top:10px; text-align:center; }
        a { color:#2563eb; }
    </style>
</head>
<body>
<div class="box">
    <h1>Create Admin User</h1>
    <?php if ($error !== ''): ?>
        <div class="msg error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="msg success"><?= h($success) ?></div>
        <p class="note">Now go to <a href="/admin/login">/admin/login</a>. After that, delete <code>init_admin.php</code> from the server.</p>
    <?php else: ?>
        <form method="post" action="">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" required>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>

            <button type="submit">Create admin</button>
        </form>
        <p class="note">Use this page only once to create your first admin. Then delete <code>init_admin.php</code> from the server.</p>
    <?php endif; ?>
</div>
</body>
</html>

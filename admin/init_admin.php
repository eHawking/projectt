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
        :root {
            --bg-gradient: radial-gradient(circle at top left, #111827 0, #020617 40%, #020617 100%);
            --card-bg: rgba(15, 23, 42, 0.96);
            --accent: #22c55e;
            --accent-strong: #16a34a;
            --text-main: #e5e7eb;
            --text-muted: #9ca3af;
            --border-subtle: rgba(148, 163, 184, 0.35);
        }

        * {
            box-sizing: border-box;
        }

        body { 
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            background: var(--bg-gradient); 
            margin: 0; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: var(--text-main);
        }
        .box { 
            width: 100%;
            max-width: 380px; 
            margin: 0 auto; 
            background: var(--card-bg); 
            padding: 22px 18px 24px; 
            border-radius: 18px; 
            box-shadow:0 18px 60px rgba(15,23,42,0.85), 0 0 0 1px rgba(148,163,184,0.35); 
        }
        h1 { 
            margin-top:0; 
            font-size:1.3rem; 
            text-align:center; 
            letter-spacing:-0.02em;
        }
        label { 
            display:block; 
            font-size:0.9rem; 
            margin-bottom:4px; 
            color: var(--text-muted);
        }
        input[type="text"], input[type="password"] { 
            width:100%; 
            padding:9px 10px; 
            margin-bottom:12px; 
            border-radius:10px; 
            border:1px solid var(--border-subtle); 
            box-sizing:border-box; 
            background: rgba(15,23,42,0.9);
            color: var(--text-main);
        }
        input[type="text"]:focus, input[type="password"]:focus {
            outline:none;
            border-color: var(--accent);
            box-shadow: 0 0 0 1px rgba(34,197,94,0.4);
        }
        button { 
            width:100%; 
            padding:9px; 
            border-radius:999px; 
            border:none; 
            background:var(--accent); 
            color:#022c22; 
            font-size:0.95rem; 
            cursor:pointer; 
            font-weight:500;
            box-shadow:0 12px 26px rgba(34,197,94,0.25);
        }
        button:hover {
            background: var(--accent-strong);
        }
        .msg { font-size:0.85rem; margin-bottom:10px; text-align:center; }
        .msg.error { color:#f97373; }
        .msg.success { color:#22c55e; }
        .note { font-size:0.75rem; color:var(--text-muted); margin-top:10px; text-align:center; }
        a { color:#22c55e; }
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

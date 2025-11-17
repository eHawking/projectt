<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_login();

$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    echo 'Visit not found.';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM visits WHERE id = :id');
$stmt->execute([':id' => $id]);
$visit = $stmt->fetch();

if (!$visit) {
    http_response_code(404);
    echo 'Visit not found.';
    exit;
}
// Total visits from this IP
$ipVisitCount = null;
if (!empty($visit['ip'])) {
    $cstmt = $pdo->prepare('SELECT COUNT(*) FROM visits WHERE ip = :ip');
    $cstmt->execute([':ip' => $visit['ip']]);
    $ipVisitCount = (int)$cstmt->fetchColumn();
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Visit #<?= (int)$visit['id'] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            /* Light theme (default) – dark navy CRM style */
            --bg-gradient: linear-gradient(135deg, #051637 0%, #020817 40%, #020314 100%);
            --header-bg: #051327;
            --header-fg: #f9fafb;
            --card-bg: #071a35;
            --border-subtle: rgba(15, 23, 42, 0.85);
            --text-main: #e5e7eb;
            --text-muted: #94a3b8;
            --accent: #fb7185; /* neon pink */
            --table-row-alt-bg: #051426;
        }

        :root[data-theme="dark"] {
            /* Dark theme – slightly deeper variant */
            --bg-gradient: linear-gradient(135deg, #020617 0%, #020012 40%, #000000 100%);
            --header-bg: #030b1e;
            --header-fg: #f9fafb;
            --card-bg: #061327;
            --border-subtle: rgba(15, 23, 42, 0.9);
            --text-main: #e5e7eb;
            --text-muted: #9ca3af;
            --accent: #fb7185;
            --table-row-alt-bg: #050b18;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            padding-left: 220px;
            min-height: 100vh;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 220px;
            background: #020b26;
            border-right: 1px solid var(--border-subtle);
            padding: 18px 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            z-index: 30;
        }
        .sidebar-logo-main {
            font-size: 1rem;
            font-weight: 600;
            color: #f9fafb;
        }
        .sidebar-logo-sub {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .sidebar-nav {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .sidebar-link {
            display: block;
            padding: 8px 10px;
            border-radius: 999px;
            font-size: 0.86rem;
            color: var(--text-muted);
            text-decoration: none;
        }
        .sidebar-link:hover {
            background: #020b35;
            color: #f9fafb;
        }
        .sidebar-link-active {
            background: var(--accent) ;
            color: #f9fafb;
        }
        .sidebar-user {
            margin-top: auto;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .sidebar-user a {
            display: inline-block;
            margin-top: 6px;
            color: var(--accent);
            text-decoration: none;
            font-size: 0.82rem;
        }
        .sidebar-user a:hover {
            text-decoration: underline;
        }
        .bottom-nav {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: 52px;
            background: #020b26;
            border-top: 1px solid var(--border-subtle);
            display: none;
            align-items: center;
            justify-content: space-around;
            z-index: 40;
        }
        .bottom-nav-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.75rem;
        }
        .bottom-nav-link-active {
            color: var(--accent);
        }
        header {
            background: var(--header-bg);
            color: var(--header-fg);
            padding: 12px 20px;
            border-bottom: 1px solid var(--border-subtle);
        }
        header a {
            color: var(--header-fg);
            text-decoration: none;
            font-size: 0.9rem;
        }
        .container {
            max-width: 860px;
            margin: 20px auto;
            padding: 0 16px 32px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 18px;
            overflow: hidden;
            box-shadow:
                0 18px 60px rgba(15, 23, 42, 0.9),
                0 0 0 1px rgba(15, 23, 42, 0.9);
            font-size: 0.86rem;
        }
        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid rgba(30, 64, 175, 0.35);
            text-align: left;
        }
        th {
            width: 160px;
            background: rgba(15, 23, 42, 0.96);
            color: var(--text-muted);
        }
        tr:nth-child(even) td {
            background: var(--table-row-alt-bg);
        }
        td {
            max-width: 520px;
            overflow-wrap: anywhere;
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

        @media (max-width: 900px) {
            body {
                padding-left: 0;
                padding-bottom: 60px;
            }
            .sidebar {
                display: none;
            }
            .container {
                padding: 0 12px 24px;
            }
            .bottom-nav {
                display: flex;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 12px 24px;
            }
            table {
                font-size: 0.8rem;
            }
            th, td {
                padding: 6px 8px;
            }
        }
    </style>
</head>
<body>
<button type="button" class="theme-toggle" data-theme-toggle>
    <span data-theme-toggle-label>Light</span> mode
</button>
<div class="sidebar">
    <div class="sidebar-logo-main">DS Analytics</div>
    <div class="sidebar-logo-sub">Admin panel</div>
    <nav class="sidebar-nav">
        <a href="/admin/dashboard" class="sidebar-link sidebar-link-active">Dashboard</a>
        <a href="/admin/share_links" class="sidebar-link">Share links</a>
    </nav>
    <div class="sidebar-user">
        <div>Logged in as <?= h($_SESSION['admin_username'] ?? 'admin') ?></div>
        <a href="/admin/logout">Logout</a>
    </div>
</div>
<header>
    <h1>Visit details</h1>
</header>
<div class="container">
    <p style="margin: 0 0 10px 0;"><a href="/admin/dashboard" style="color: var(--accent); text-decoration: none;">&larr; Back to dashboard</a></p>
    <h2>Visit #<?= (int)$visit['id'] ?></h2>
    <table>
        <tr><th>Date</th><td><?= h($visit['created_at']) ?></td></tr>
        <tr><th>IP</th><td><?= h($visit['ip']) ?></td></tr>
        <tr><th>Country</th><td><?= h($visit['country'] ?? '') ?></td></tr>
        <tr><th>Region</th><td><?= h($visit['region'] ?? '') ?></td></tr>
        <tr><th>City</th><td><?= h($visit['city'] ?? '') ?></td></tr>
        <tr><th>Latitude</th><td><?= h($visit['latitude'] !== null ? (string)$visit['latitude'] : '') ?></td></tr>
        <tr><th>Longitude</th><td><?= h($visit['longitude'] !== null ? (string)$visit['longitude'] : '') ?></td></tr>
        <tr><th>Duration</th><td><?= $visit['duration_seconds'] !== null ? h((string)$visit['duration_seconds']) . ' s' : '' ?></td></tr>
        <tr><th>Total visits from this IP</th><td><?= $ipVisitCount !== null ? (int)$ipVisitCount : '' ?></td></tr>
        <?php
        $mapUrl = '';
        if ($visit['latitude'] !== null && $visit['longitude'] !== null) {
            $mapUrl = 'https://www.google.com/maps?q=' . rawurlencode((string)$visit['latitude'] . ',' . (string)$visit['longitude']);
        } elseif (!empty($visit['ip'])) {
            $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string)$visit['ip']);
        }
        ?>
        <tr><th>Map</th><td><?php if ($mapUrl !== ''): ?><a href="<?= h($mapUrl) ?>" target="_blank">Open map</a><?php endif; ?></td></tr>
        <tr><th>ISP</th><td><?= h($visit['isp'] ?? '') ?></td></tr>
        <tr><th>Browser</th><td><?= h($visit['browser_name'] ?? '') . ' ' . h($visit['browser_version'] ?? '') ?></td></tr>
        <tr><th>OS</th><td><?= h($visit['os_name'] ?? '') . ' ' . h($visit['os_version'] ?? '') ?></td></tr>
        <tr><th>Device type</th><td><?= h($visit['device_type'] ?? '') ?></td></tr>
        <tr><th>Language</th><td><?= h($visit['language'] ?? '') ?></td></tr>
        <tr><th>Screen</th><td><?= h((string)$visit['screen_width']) . ' x ' . h((string)$visit['screen_height']) ?></td></tr>
        <tr><th>URL</th><td><?= h($visit['url'] ?? '') ?></td></tr>
        <tr><th>Referrer</th><td><?= h($visit['referer'] ?? '') ?></td></tr>
        <tr><th>User agent</th><td><?= h($visit['user_agent'] ?? '') ?></td></tr>
    </table>
</div>
<script src="/assets/js/theme.js"></script>
<div class="bottom-nav">
    <a href="/admin/dashboard" class="bottom-nav-link bottom-nav-link-active">Dashboard</a>
    <a href="/admin/share_links" class="bottom-nav-link">Share</a>
    <a href="/admin/logout" class="bottom-nav-link">Logout</a>
</div>
</body>
</html>

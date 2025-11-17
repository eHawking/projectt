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
            /* Light theme (default) */
            --bg-gradient: radial-gradient(circle at top left, #e5f0ff 0, #f9fafb 45%, #f3f4f6 100%);
            --header-bg: #ffffff;
            --header-fg: #0f172a;
            --card-bg: #ffffff;
            --border-subtle: rgba(148, 163, 184, 0.35);
            --text-main: #0f172a;
            --text-muted: #6b7280;
            --accent: #22c55e;
            --table-row-alt-bg: #f9fafb;
        }

        :root[data-theme="dark"] {
            /* Dark theme */
            --bg-gradient: radial-gradient(circle at top left, #020617 0, #020617 50%, #000000 100%);
            --header-bg: rgba(15, 23, 42, 0.98);
            --header-fg: #e5e7eb;
            --card-bg: rgba(15, 23, 42, 0.96);
            --border-subtle: rgba(148, 163, 184, 0.35);
            --text-main: #e5e7eb;
            --text-muted: #9ca3af;
            --accent: #22c55e;
            --table-row-alt-bg: rgba(15, 23, 42, 0.9);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
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
            border-radius: 14px;
            overflow: hidden;
            box-shadow:
                0 14px 40px rgba(15, 23, 42, 0.7),
                0 0 0 1px rgba(148, 163, 184, 0.35);
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
    </style>
</head>
<body>
<button type="button" class="theme-toggle" data-theme-toggle>
    <span data-theme-toggle-label>Light</span> mode
</button>
<header>
    <a href="/admin/dashboard">&larr; Back to dashboard</a>
</header>
<div class="container">
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
</body>
</html>

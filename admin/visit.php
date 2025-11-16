<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    echo 'Visit not found.';
    exit;
}

$stmt = db()->prepare('SELECT * FROM visits WHERE id = :id');
$stmt->execute([':id' => $id]);
$visit = $stmt->fetch();

if (!$visit) {
    http_response_code(404);
    echo 'Visit not found.';
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Visit #<?= (int)$visit['id'] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f3f4f6;
        }
        header {
            background: #1f2937;
            color: #ffffff;
            padding: 12px 20px;
        }
        header a {
            color: #e5e7eb;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 16px 32px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            font-size: 0.86rem;
        }
        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        th {
            width: 160px;
            background: #f9fafb;
        }
        td {
            max-width: 520px;
            overflow-wrap: anywhere;
        }
    </style>
</head>
<body>
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
</body>
</html>

<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_login();

$pdo = db();

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$country = trim($_GET['country'] ?? '');
$deviceType = trim($_GET['device_type'] ?? '');

$where = [];
$params = [];

if ($dateFrom !== '') {
    $where[] = 'created_at >= :df';
    $params[':df'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = 'created_at <= :dt';
    $params[':dt'] = $dateTo . ' 23:59:59';
}
if ($country !== '') {
    $where[] = 'country = :country';
    $params[':country'] = $country;
}
if ($deviceType !== '') {
    $where[] = 'device_type = :dtype';
    $params[':dtype'] = $deviceType;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totalVisits = (int)$pdo->query('SELECT COUNT(*) FROM visits')->fetchColumn();
$todayVisits = (int)$pdo->query('SELECT COUNT(*) FROM visits WHERE DATE(created_at) = CURDATE()')->fetchColumn();

$topCountriesStmt = $pdo->query('SELECT country, COUNT(*) AS c FROM visits GROUP BY country ORDER BY c DESC LIMIT 5');
$topCountries = $topCountriesStmt->fetchAll();

$topDevicesStmt = $pdo->query('SELECT device_type, COUNT(*) AS c FROM visits GROUP BY device_type ORDER BY c DESC');
$topDevices = $topDevicesStmt->fetchAll();

$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM visits $whereSql");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();

$sql = "SELECT id, created_at, ip, country, city, browser_name, os_name, device_type, url
        FROM visits
        $whereSql
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$visits = $stmt->fetchAll();

$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --bg-gradient: radial-gradient(circle at top left, #020617 0, #020617 50%, #000000 100%);
            --header-bg: rgba(15, 23, 42, 0.98);
            --card-bg: rgba(15, 23, 42, 0.96);
            --card-elevated: rgba(15, 23, 42, 0.98);
            --accent: #22c55e;
            --accent-soft: rgba(34, 197, 94, 0.15);
            --border-subtle: rgba(148, 163, 184, 0.35);
            --text-main: #e5e7eb;
            --text-muted: #9ca3af;
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
            color: #e5e7eb;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(148, 163, 184, 0.35);
        }
        header h1 {
            margin: 0;
            font-size: 1.2rem;
            letter-spacing: -0.02em;
        }
        header a {
            color: #e5e7eb;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .container {
            max-width: 1160px;
            margin: 20px auto 28px;
            padding: 0 16px 32px;
        }
        .cards {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .card {
            flex: 1 1 200px;
            background: var(--card-bg);
            border-radius: 14px;
            padding: 10px 12px;
            box-shadow:
                0 14px 40px rgba(15, 23, 42, 0.7),
                0 0 0 1px rgba(148, 163, 184, 0.35);
        }
        .card-title {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.11em;
        }
        .card-value {
            font-size: 1.3rem;
            font-weight: 600;
        }
        .filters {
            background: var(--card-elevated);
            border-radius: 14px;
            padding: 10px 12px;
            box-shadow:
                0 14px 40px rgba(15, 23, 42, 0.7),
                0 0 0 1px rgba(148, 163, 184, 0.35);
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .filters label {
            margin-right: 6px;
            color: var(--text-muted);
        }
        .filters input,
        .filters select {
            margin-right: 8px;
            margin-bottom: 4px;
            background: rgba(15, 23, 42, 0.9);
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
            color: var(--text-main);
            padding: 4px 6px;
            font-size: 0.86rem;
        }
        .filters button {
            padding: 6px 12px;
            border-radius: 999px;
            border: none;
            background: var(--accent);
            color: #022c22;
            font-size: 0.86rem;
            cursor: pointer;
            font-weight: 500;
        }
        .filters a {
            font-size: 0.85rem;
            color: var(--accent);
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
            font-size: 0.85rem;
        }
        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid rgba(30, 64, 175, 0.35);
            text-align: left;
        }
        th {
            background: rgba(15, 23, 42, 0.96);
            font-weight: 600;
            color: var(--text-muted);
        }
        tr:nth-child(even) td {
            background: rgba(15, 23, 42, 0.9);
        }
        tr:hover td {
            background: rgba(15, 118, 110, 0.22);
        }
        td a {
            color: var(--accent);
            text-decoration: none;
        }
        td a:hover {
            text-decoration: underline;
        }
        .pagination {
            margin-top: 10px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .pagination a {
            margin-right: 4px;
            text-decoration: none;
            color: var(--accent);
        }
        .pagination span.current {
            margin-right: 4px;
            font-weight: 600;
            color: var(--text-main);
        }
        .top-list {
            list-style: none;
            padding-left: 0;
            margin: 4px 0 0 0;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
<header>
    <h1>Tracking Dashboard</h1>
    <div>
        <span style="margin-right: 10px; font-size: 0.9rem;">Logged in as <?= h($_SESSION['admin_username'] ?? 'admin') ?></span>
        <a href="/admin/logout">Logout</a>
    </div>
</header>
<div class="container">
    <div class="cards">
        <div class="card">
            <div class="card-title">Total visits</div>
            <div class="card-value"><?= $totalVisits ?></div>
        </div>
        <div class="card">
            <div class="card-title">Visits today</div>
            <div class="card-value"><?= $todayVisits ?></div>
        </div>
        <div class="card">
            <div class="card-title">Top countries</div>
            <ul class="top-list">
                <?php foreach ($topCountries as $row): ?>
                    <li><?= h($row['country'] ?? 'Unknown') ?> (<?= (int)$row['c'] ?>)</li>
                <?php endforeach; ?>
                <?php if (!$topCountries): ?>
                    <li>–</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="card">
            <div class="card-title">Devices</div>
            <ul class="top-list">
                <?php foreach ($topDevices as $row): ?>
                    <li><?= h($row['device_type'] ?? 'Unknown') ?> (<?= (int)$row['c'] ?>)</li>
                <?php endforeach; ?>
                <?php if (!$topDevices): ?>
                    <li>–</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="filters">
        <form method="get" action="">
            <label for="date_from">From</label>
            <input type="date" name="date_from" id="date_from" value="<?= h($dateFrom) ?>">

            <label for="date_to">To</label>
            <input type="date" name="date_to" id="date_to" value="<?= h($dateTo) ?>">

            <label for="country">Country</label>
            <input type="text" name="country" id="country" value="<?= h($country) ?>" placeholder="BD, US, ...">

            <label for="device_type">Device</label>
            <select name="device_type" id="device_type">
                <option value="">Any</option>
                <option value="desktop" <?= $deviceType === 'desktop' ? 'selected' : '' ?>>Desktop</option>
                <option value="mobile" <?= $deviceType === 'mobile' ? 'selected' : '' ?>>Mobile</option>
                <option value="tablet" <?= $deviceType === 'tablet' ? 'selected' : '' ?>>Tablet</option>
            </select>

            <button type="submit">Apply</button>
            <a href="/admin/export?<?= http_build_query(['date_from' => $dateFrom, 'date_to' => $dateTo, 'country' => $country, 'device_type' => $deviceType]) ?>" style="margin-left: 10px;">Export CSV</a>
        </form>
    </div>

    <table>
        <thead>
        <tr>
            <th>Date</th>
            <th>IP</th>
            <th>Country/City</th>
            <th>Browser</th>
            <th>OS</th>
            <th>Device</th>
            <th>URL</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($visits as $v): ?>
            <tr>
                <td><?= h($v['created_at']) ?></td>
                <td><a href="/admin/visit?id=<?= (int)$v['id'] ?>"><?= h($v['ip']) ?></a></td>
                <td><?= h(trim(($v['country'] ?? '') . ' / ' . ($v['city'] ?? ''), ' /')) ?></td>
                <td><?= h($v['browser_name'] ?? '') ?></td>
                <td><?= h($v['os_name'] ?? '') ?></td>
                <td><?= h($v['device_type'] ?? '') ?></td>
                <td style="max-width: 260px; overflow-wrap: anywhere;"><?= h($v['url'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$visits): ?>
            <tr><td colspan="7">No visits found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        Page
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <?php if ($p === $page): ?>
                <span class="current"><?= $p ?></span>
            <?php else: ?>
                <a href="?<?= http_build_query(['page' => $p, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'country' => $country, 'device_type' => $deviceType]) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        (<?= $totalFiltered ?> result<?= $totalFiltered === 1 ? '' : 's' ?>)
    </div>
</div>
</body>
</html>

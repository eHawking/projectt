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

$sql = "SELECT id, created_at, ip, country, city, browser_name, os_name, device_type, url, latitude, longitude, duration_seconds, visit_count
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
$currentQuery = http_build_query([
    'page' => $page,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'country' => $country,
    'device_type' => $deviceType,
]);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            /* Light theme (default) */
            --bg-gradient: radial-gradient(circle at top left, #e5f0ff 0, #f9fafb 45%, #f3f4f6 100%);
            --header-bg: #ffffff;
            --header-fg: #0f172a;
            --card-bg: #ffffff;
            --card-elevated: #f9fafb;
            --accent: #22c55e;
            --accent-soft: rgba(34, 197, 94, 0.12);
            --border-subtle: rgba(148, 163, 184, 0.35);
            --text-main: #0f172a;
            --text-muted: #6b7280;
            --field-bg: #f9fafb;
            --table-row-alt-bg: #f9fafb;
        }

        :root[data-theme="dark"] {
            /* Dark theme */
            --bg-gradient: radial-gradient(circle at top left, #020617 0, #020617 50%, #000000 100%);
            --header-bg: rgba(15, 23, 42, 0.98);
            --header-fg: #e5e7eb;
            --card-bg: rgba(15, 23, 42, 0.96);
            --card-elevated: rgba(15, 23, 42, 0.98);
            --accent: #22c55e;
            --accent-soft: rgba(34, 197, 94, 0.15);
            --border-subtle: rgba(148, 163, 184, 0.35);
            --text-main: #e5e7eb;
            --text-muted: #9ca3af;
            --field-bg: rgba(15, 23, 42, 0.9);
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
            color: var(--header-fg);
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
            background: var(--field-bg);
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
            background: var(--table-row-alt-bg);
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
        .bulk-actions {
            margin-top: 10px;
            font-size: 0.85rem;
        }
        .bulk-actions button {
            padding: 6px 14px;
            border-radius: 999px;
            border: none;
            background: #ef4444;
            color: #fef2f2;
            cursor: pointer;
            font-weight: 500;
        }
        .bulk-actions button:hover {
            background: #dc2626;
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
    <h1>Tracking Dashboard</h1>
    <div>
        <a href="/admin/share_links" style="margin-right: 10px; font-size: 0.9rem;">Share links</a>
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

    <form method="post" action="/admin/delete_visits" id="bulk-delete-form">
        <input type="hidden" name="redirect_query" value="<?= h($currentQuery) ?>">
        <table>
            <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Date</th>
                <th>IP</th>
                <th>Country/City</th>
                <th>Browser</th>
                <th>OS</th>
                <th>Device</th>
                <th>Duration</th>
                <th>Visits</th>
                <th>URL</th>
                <th>Map</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($visits as $v): ?>
                <?php
                $mapUrl = '';
                if ($v['latitude'] !== null && $v['longitude'] !== null) {
                    $mapUrl = 'https://www.google.com/maps?q=' . rawurlencode((string)$v['latitude'] . ',' . (string)$v['longitude']);
                } elseif (!empty($v['ip'])) {
                    $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string)$v['ip']);
                }
                ?>
                <tr>
                    <td><input type="checkbox" class="visit-checkbox" name="ids[]" value="<?= (int)$v['id'] ?>"></td>
                    <td><?= h($v['created_at']) ?></td>
                    <td><a href="/admin/visit?id=<?= (int)$v['id'] ?>"><?= h($v['ip']) ?></a></td>
                    <td><?= h(trim(($v['country'] ?? '') . ' / ' . ($v['city'] ?? ''), ' /')) ?></td>
                    <td><?= h($v['browser_name'] ?? '') ?></td>
                    <td><?= h($v['os_name'] ?? '') ?></td>
                    <td><?= h($v['device_type'] ?? '') ?></td>
                    <td><?= $v['duration_seconds'] !== null ? h((string)$v['duration_seconds']) . ' s' : '' ?></td>
                    <td><?= $v['visit_count'] !== null ? (int)$v['visit_count'] : '' ?></td>
                    <td style="max-width: 260px; overflow-wrap: anywhere;"><?= h($v['url'] ?? '') ?></td>
                    <td>
                        <?php if ($mapUrl !== ''): ?>
                            <a href="<?= h($mapUrl) ?>" target="_blank">Map</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$visits): ?>
                <tr><td colspan="9">No visits found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <div class="bulk-actions">
            <button type="submit" onclick="return confirm('Delete selected visits? This cannot be undone.');">Delete selected</button>
        </div>
    </form>

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
    <script src="/assets/js/theme.js"></script>
    <script>
    (function () {
        var selectAll = document.getElementById('select-all');
        if (!selectAll) return;
        selectAll.addEventListener('change', function () {
            var boxes = document.querySelectorAll('.visit-checkbox');
            for (var i = 0; i < boxes.length; i++) {
                boxes[i].checked = selectAll.checked;
            }
        });
    })();
    </script>
</div>
</body>
</html>

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

$sql = "SELECT id, created_at, ip, country, city, isp, browser_name, os_name, device_type, url, latitude, longitude, duration_seconds, visit_count
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            /* Light theme (default) – dark navy CRM style */
            --bg-gradient: linear-gradient(135deg, #f9fafb 0%, #e5e7eb 40%, #e5e7eb 100%);
            --header-bg: #ffffff;
            --header-fg: #0f172a;
            --card-bg: #ffffff;
            --card-elevated: #f9fafb;
            --accent: #fb7185; /* neon pink */
            --accent-soft: rgba(251, 113, 133, 0.16);
            --border-subtle: rgba(148, 163, 184, 0.6);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --field-bg: #f1f5f9;
            --table-row-alt-bg: #f8fafc;
        }

        :root[data-theme="dark"] {
            /* Dark theme – slightly deeper variant */
            --bg-gradient: linear-gradient(135deg, #020617 0%, #020012 40%, #000000 100%);
            --header-bg: #030b1e;
            --header-fg: #f9fafb;
            --card-bg: #061327;
            --card-elevated: #050f22;
            --accent: #fb7185;
            --accent-soft: rgba(251, 113, 133, 0.24);
            --border-subtle: rgba(15, 23, 42, 0.9);
            --text-main: #e5e7eb;
            --text-muted: #9ca3af;
            --field-bg: #020617;
            --table-row-alt-bg: #050b18;
        }

        * {
            box-sizing: border-box;
        }

        .icon-inline {
            margin-right: 6px;
            font-size: 1rem;
            vertical-align: -0.1em;
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
            background: var(--accent-soft);
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
            border-radius: 18px;
            padding: 12px 14px;
            box-shadow:
                0 18px 60px rgba(15, 23, 42, 0.9),
                0 0 0 1px rgba(15, 23, 42, 0.9);
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
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }
        .records-mobile {
            display: none;
            margin-top: 16px;
        }
        .record-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 10px 12px;
            box-shadow:
                0 14px 40px rgba(15, 23, 42, 0.85),
                0 0 0 1px rgba(15, 23, 42, 0.85);
        }
        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            font-size: 0.8rem;
        }
        .record-date {
            color: var(--text-muted);
        }
        .record-ip a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
        .record-ip a:hover {
            text-decoration: underline;
        }
        .record-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 3px;
            font-size: 0.8rem;
        }
        .record-label {
            color: var(--text-muted);
            min-width: 110px;
        }
        .record-value {
            text-align: right;
            max-width: 60%;
            overflow-wrap: anywhere;
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
            .cards {
                flex-direction: column;
            }
            .bottom-nav {
                display: flex;
            }
        }

        @media (max-width: 768px) {
            header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .filters {
                overflow-x: auto;
            }
            .table-wrapper {
                display: none;
            }
            .records-mobile {
                display: flex;
                flex-direction: column;
                gap: 10px;
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
        <a href="/admin/dashboard" class="sidebar-link sidebar-link-active"><i class="bi bi-speedometer2 icon-inline"></i>Dashboard</a>
        <a href="/admin/share_links" class="sidebar-link"><i class="bi bi-link-45deg icon-inline"></i>Share links</a>
    </nav>
    <div class="sidebar-user">
        <div>Logged in as <?= h($_SESSION['admin_username'] ?? 'admin') ?></div>
        <a href="/admin/logout">Logout</a>
    </div>
</div>
<header>
    <h1><i class="bi bi-graph-up-arrow icon-inline"></i>Tracking Dashboard</h1>
</header>
<div class="container">
    <div class="cards">
        <div class="card">
            <div class="card-title"><i class="bi bi-people icon-inline"></i>Total visits</div>
            <div class="card-value"><?= $totalVisits ?></div>
        </div>
        <div class="card">
            <div class="card-title"><i class="bi bi-sun icon-inline"></i>Visits today</div>
            <div class="card-value"><?= $todayVisits ?></div>
        </div>
        <div class="card">
            <div class="card-title"><i class="bi bi-geo-alt icon-inline"></i>Top countries</div>
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
            <div class="card-title"><i class="bi bi-phone-laptop icon-inline"></i>Devices</div>
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
        <div class="table-wrapper">
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
                <th>VPN?</th>
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

                $vpnSuspected = false;
                $isp = strtolower((string)($v['isp'] ?? ''));
                if ($isp !== '') {
                    $vpnKeywords = ['vpn', 'proxy', 'hosting', 'data center', 'datacenter', 'colo', 'digitalocean', 'ovh', 'm247'];
                    foreach ($vpnKeywords as $kw) {
                        if (strpos($isp, $kw) !== false) {
                            $vpnSuspected = true;
                            break;
                        }
                    }
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
                    <td><?= $vpnSuspected ? 'Yes' : '' ?></td>
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
        </div>
        <div class="records-mobile">
            <?php foreach ($visits as $v): ?>
                <?php
                $mapUrl = '';
                if ($v['latitude'] !== null && $v['longitude'] !== null) {
                    $mapUrl = 'https://www.google.com/maps?q=' . rawurlencode((string)$v['latitude'] . ',' . (string)$v['longitude']);
                } elseif (!empty($v['ip'])) {
                    $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string)$v['ip']);
                }

                $vpnSuspected = false;
                $isp = strtolower((string)($v['isp'] ?? ''));
                if ($isp !== '') {
                    $vpnKeywords = ['vpn', 'proxy', 'hosting', 'data center', 'datacenter', 'colo', 'digitalocean', 'ovh', 'm247'];
                    foreach ($vpnKeywords as $kw) {
                        if (strpos($isp, $kw) !== false) {
                            $vpnSuspected = true;
                            break;
                        }
                    }
                }
                ?>
                <div class="record-card">
                    <div class="record-header">
                        <div class="record-date"><?= h($v['created_at']) ?></div>
                        <div class="record-ip">
                            <input type="checkbox" class="visit-checkbox" name="ids[]" value="<?= (int)$v['id'] ?>" style="margin-right:4px;">
                            <a href="/admin/visit?id=<?= (int)$v['id'] ?>"><?= h($v['ip']) ?></a>
                        </div>
                    </div>
                    <div class="record-row">
                        <div class="record-label">Country/City</div>
                        <div class="record-value"><?= h(trim(($v['country'] ?? '') . ' / ' . ($v['city'] ?? ''), ' /')) ?></div>
                    </div>
                    <div class="record-row">
                        <div class="record-label">Browser</div>
                        <div class="record-value"><?= h($v['browser_name'] ?? '') ?></div>
                    </div>
                    <div class="record-row">
                        <div class="record-label">OS</div>
                        <div class="record-value"><?= h($v['os_name'] ?? '') ?></div>
                    </div>
                    <div class="record-row">
                        <div class="record-label">Device</div>
                        <div class="record-value"><?= h($v['device_type'] ?? '') ?></div>
                    </div>
                    <div class="record-row">
                        <div class="record-label">VPN?</div>
                        <div class="record-value"><?= $vpnSuspected ? 'Yes' : '' ?></div>
                    </div>
                    <div class="record-row">
                        <div class="record-label">Duration</div>
                        <div class="record-value"><?= $v['duration_seconds'] !== null ? h((string)$v['duration_seconds']) . ' s' : '' ?></div>
                    </div>
                    <div class="record-row">
                        <div class="record-label">Visits</div>
                        <div class="record-value"><?= $v['visit_count'] !== null ? (int)$v['visit_count'] : '' ?></div>
                    </div>
                    <div class="record-row">
                        <div class="record-label">URL</div>
                        <div class="record-value"><?= h($v['url'] ?? '') ?></div>
                    </div>
                    <?php if ($mapUrl !== ''): ?>
                        <div class="record-row">
                            <div class="record-label">Map</div>
                            <div class="record-value"><a href="<?= h($mapUrl) ?>" target="_blank">Open</a></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$visits): ?>
                <div class="record-card">
                    <div class="record-row">
                        <div class="record-label">Info</div>
                        <div class="record-value">No visits found.</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
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
<div class="bottom-nav">
    <a href="/admin/dashboard" class="bottom-nav-link bottom-nav-link-active"><i class="bi bi-speedometer2"></i></a>
    <a href="/admin/share_links" class="bottom-nav-link"><i class="bi bi-link-45deg"></i></a>
    <a href="/admin/logout" class="bottom-nav-link"><i class="bi bi-box-arrow-right"></i></a>
</div>
</body>
</html>

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

$sql = "SELECT created_at, ip, country, region, city, latitude, longitude, isp, browser_name, browser_version, os_name, os_version, device_type, url, referer, language, screen_width, screen_height, duration_seconds, visit_count FROM visits $whereSql ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="visits_export.csv"');

$out = fopen('php://output', 'w');

fputcsv($out, ['created_at','ip','country','region','city','latitude','longitude','isp','browser','browser_version','os','os_version','device_type','url','referer','language','screen_width','screen_height','duration_seconds','visit_count']);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['created_at'],
        $r['ip'],
        $r['country'],
        $r['region'],
        $r['city'],
        $r['latitude'],
        $r['longitude'],
        $r['isp'],
        $r['browser_name'],
        $r['browser_version'],
        $r['os_name'],
        $r['os_version'],
        $r['device_type'],
        $r['url'],
        $r['referer'],
        $r['language'],
        $r['screen_width'],
        $r['screen_height'],
        $r['duration_seconds'],
        $r['visit_count'],
    ]);
}

fclose($out);

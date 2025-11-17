<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$pdo = db();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$visitId = isset($data['visit_id']) ? (int)$data['visit_id'] : 0;
$lat = array_key_exists('latitude', $data) ? (float)$data['latitude'] : null;
$lon = array_key_exists('longitude', $data) ? (float)$data['longitude'] : null;
$durationSeconds = array_key_exists('duration_seconds', $data) ? (int)$data['duration_seconds'] : null;

// Update duration for an existing visit
if ($visitId > 0 && $durationSeconds !== null) {
    if ($durationSeconds < 0) {
        $durationSeconds = 0;
    }
    try {
        $stmt = $pdo->prepare('UPDATE visits SET duration_seconds = :dur WHERE id = :id');
        $stmt->execute([
            ':dur' => $durationSeconds,
            ':id'  => $visitId,
        ]);
        echo json_encode(['ok' => true, 'updated' => true]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'update_failed']);
    }
    exit;
}

// Update precise location for an existing visit
if ($visitId > 0 && ($lat !== null || $lon !== null)) {
    try {
        $stmt = $pdo->prepare('UPDATE visits SET latitude = :lat, longitude = :lon WHERE id = :id');
        $stmt->execute([
            ':lat' => $lat,
            ':lon' => $lon,
            ':id'  => $visitId,
        ]);
        echo json_encode(['ok' => true, 'updated' => true]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'update_failed']);
    }
    exit;
}

// Insert new visit
$ip = get_client_ip();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ref = $_SERVER['HTTP_REFERER'] ?? null;
$url = $data['url'] ?? ($ref ?? '');
$lang = $data['language'] ?? ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null);
$sw = isset($data['screen_width']) ? (int)$data['screen_width'] : null;
$sh = isset($data['screen_height']) ? (int)$data['screen_height'] : null;

$uaInfo = parse_ua($ua);
$geo = geo_ip($ip);

// Basic VPN / proxy heuristic: use explicit proxy/hosting flags if the IP API provides them,
// and fall back to checking common VPN / hosting keywords in the ISP name.
$vpnSuspected = false;
$proxyFlag = !empty($geo['proxy']);
$hostingFlag = !empty($geo['hosting']);
if ($proxyFlag || $hostingFlag) {
    $vpnSuspected = true;
}

$ispLower = strtolower((string)($geo['isp'] ?? ''));
if ($ispLower !== '') {
    $vpnKeywords = [
        'vpn', 'proxy', 'tor',
        'hosting', 'cloud', 'data center', 'datacenter', 'colo',
        'digitalocean', 'ovh', 'm247', 'hetzner', 'linode', 'leaseweb', 'contabo', 'vultr', 'aws', 'amazon', 'google', 'gcp', 'azure', 'cloudflare',
        'nordvpn', 'nord vpn', 'surfshark', 'expressvpn', 'cyberghost', 'private internet access', 'pia', 'tunnelbear', 'protonvpn', 'windscribe', 'purevpn', 'hidemyass', 'hma'
    ];
    foreach ($vpnKeywords as $kw) {
        if (strpos($ispLower, $kw) !== false) {
            $vpnSuspected = true;
            break;
        }
    }
}

if ($lat === null && isset($geo['latitude'])) {
    $lat = $geo['latitude'];
}
if ($lon === null && isset($geo['longitude'])) {
    $lon = $geo['longitude'];
}

$visitCount = 1;
try {
    $cstmt = $pdo->prepare('SELECT COUNT(*) FROM visits WHERE ip = :ip');
    $cstmt->execute([':ip' => $ip]);
    $visitCount = ((int)$cstmt->fetchColumn()) + 1;
} catch (Throwable $e) {
    $visitCount = 1;
}

try {
    $sql = 'INSERT INTO visits (
        ip, country, region, city,
        latitude, longitude, isp,
        user_agent, browser_name, browser_version,
        os_name, os_version, device_type,
        referer, url, language,
        screen_width, screen_height,
        duration_seconds, visit_count,
        created_at
    ) VALUES (
        :ip, :country, :region, :city,
        :lat, :lon, :isp,
        :ua, :bname, :bver,
        :os, :osver, :dtype,
        :ref, :url, :lang,
        :sw, :sh,
        NULL, :vcount,
        NOW()
    )';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ip' => $ip,
        ':country' => $geo['country'] ?? null,
        ':region' => $geo['region'] ?? null,
        ':city' => $geo['city'] ?? null,
        ':lat' => $lat,
        ':lon' => $lon,
        ':isp' => $geo['isp'] ?? null,
        ':ua' => $ua,
        ':bname' => $uaInfo['browser_name'] ?? null,
        ':bver' => $uaInfo['browser_version'] ?? null,
        ':os' => $uaInfo['os_name'] ?? null,
        ':osver' => $uaInfo['os_version'] ?? null,
        ':dtype' => $uaInfo['device_type'] ?? null,
        ':ref' => $ref,
        ':url' => $url,
        ':lang' => $lang,
        ':sw' => $sw,
        ':sh' => $sh,
        ':vcount' => $visitCount,
    ]);

    $id = (int)$pdo->lastInsertId();
    echo json_encode(['ok' => true, 'id' => $id, 'vpn_suspected' => $vpnSuspected]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'insert_failed']);
}

function parse_ua(string $ua): array
{
    $browser = 'Unknown';
    $bver = '';
    $os = 'Unknown';
    $osver = '';
    $dtype = 'desktop';

    $ual = strtolower($ua);

    if (strpos($ual, 'mobile') !== false || strpos($ual, 'android') !== false || strpos($ual, 'iphone') !== false) {
        $dtype = 'mobile';
    } elseif (strpos($ual, 'ipad') !== false || strpos($ual, 'tablet') !== false) {
        $dtype = 'tablet';
    }

    if (preg_match('/Edg\\/([\\d\\.]+)/', $ua, $m)) {
        $browser = 'Edge';
        $bver = $m[1];
    } elseif (preg_match('/OPR\\/([\\d\\.]+)/', $ua, $m)) {
        $browser = 'Opera';
        $bver = $m[1];
    } elseif (preg_match('/Chrome\\/([\\d\\.]+)/', $ua, $m) && strpos($ua, 'Chromium') === false) {
        $browser = 'Chrome';
        $bver = $m[1];
    } elseif (preg_match('/Firefox\\/([\\d\\.]+)/', $ua, $m)) {
        $browser = 'Firefox';
        $bver = $m[1];
    } elseif (preg_match('/Version\\/([\\d\\.]+).*Safari/', $ua, $m)) {
        $browser = 'Safari';
        $bver = $m[1];
    } elseif (preg_match('/MSIE\\s([\\d\\.]+)/', $ua, $m)) {
        $browser = 'Internet Explorer';
        $bver = $m[1];
    } elseif (preg_match('/Trident\\/.*rv:([\\d\\.]+)/', $ua, $m)) {
        $browser = 'Internet Explorer';
        $bver = $m[1];
    }

    if (preg_match('/Windows NT 10\.0/', $ua)) {
        $os = 'Windows';
        $osver = '10';
    } elseif (preg_match('/Windows NT 6\.3/', $ua)) {
        $os = 'Windows';
        $osver = '8.1';
    } elseif (preg_match('/Windows NT 6\.2/', $ua)) {
        $os = 'Windows';
        $osver = '8';
    } elseif (preg_match('/Windows NT 6\.1/', $ua)) {
        $os = 'Windows';
        $osver = '7';
    } elseif (stripos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'iOS') !== false) {
        $os = 'iOS';
    } elseif (stripos($ua, 'Mac OS X') !== false) {
        $os = 'macOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }

    return [
        'browser_name' => $browser,
        'browser_version' => $bver,
        'os_name' => $os,
        'os_version' => $osver,
        'device_type' => $dtype,
    ];
}

function geo_ip(string $ip): array
{
    $out = [
        'country' => null,
        'region' => null,
        'city' => null,
        'latitude' => null,
        'longitude' => null,
        'isp' => null,
        'proxy' => false,
        'hosting' => false,
    ];

    if ($ip === '') {
        return $out;
    }

    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,regionName,city,lat,lon,isp,proxy,hosting';

    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
        ],
    ]);

    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        return $out;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return $out;
    }

    $out['country'] = $data['country'] ?? null;
    $out['region'] = $data['regionName'] ?? null;
    $out['city'] = $data['city'] ?? null;
    $out['latitude'] = isset($data['lat']) ? (float)$data['lat'] : null;
    $out['longitude'] = isset($data['lon']) ? (float)$data['lon'] : null;
    $out['isp'] = $data['isp'] ?? null;
    $out['proxy'] = $data['proxy'] ?? false;
    $out['hosting'] = $data['hosting'] ?? false;

    return $out;
}

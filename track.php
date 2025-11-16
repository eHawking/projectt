<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

// CONFIGURE THESE VALUES
$targetUrl        = 'https://www.dailysokalersomoy.online/news/138822';    // where you finally redirect the user
$previewImageUrl  = 'https://dailysokalersomoy.online/preview-image.jpg';  // full URL to image used in WhatsApp preview
$previewTitle     = 'Daily Sokalersomoy tracked link';                    // title shown in preview
$previewDescription = 'Click to open Daily Sokalersomoy (tracked link).'; // description shown in preview

ensureTrackingTableExists();
ensureSettingsTableExists();
$settings = getTrackerSettings();
if (!empty($settings['target_url'])) {
    $targetUrl = (string)$settings['target_url'];
}
if (!empty($settings['preview_image_url'])) {
    $previewImageUrl = (string)$settings['preview_image_url'];
}
if (!empty($settings['preview_title'])) {
    $previewTitle = (string)$settings['preview_title'];
}
if (!empty($settings['preview_description'])) {
    $previewDescription = (string)$settings['preview_description'];
}

function getClientIp(): string
{
    $keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_REAL_IP',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ipList = explode(',', (string)$_SERVER[$key]);
            foreach ($ipList as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }

    return '0.0.0.0';
}

function parseDevice(string $userAgent): array
{
    $ua = strtolower($userAgent);

    $deviceType = 'desktop';
    if (preg_match('/mobile|android|iphone|ipod|blackberry|phone/', $ua)) {
        $deviceType = 'mobile';
    } elseif (preg_match('/tablet|ipad/', $ua)) {
        $deviceType = 'tablet';
    } elseif (preg_match('/bot|crawler|spider|crawling/', $ua)) {
        $deviceType = 'bot';
    }

    $os = 'unknown';
    if (strpos($ua, 'windows nt 10') !== false) {
        $os = 'Windows 10';
    } elseif (strpos($ua, 'windows nt 6.3') !== false) {
        $os = 'Windows 8.1';
    } elseif (strpos($ua, 'windows nt 6.2') !== false) {
        $os = 'Windows 8';
    } elseif (strpos($ua, 'windows nt 6.1') !== false) {
        $os = 'Windows 7';
    } elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false || strpos($ua, 'ipod') !== false) {
        $os = 'iOS';
    } elseif (strpos($ua, 'android') !== false) {
        $os = 'Android';
    } elseif (strpos($ua, 'mac os x') !== false) {
        $os = 'macOS';
    } elseif (strpos($ua, 'linux') !== false) {
        $os = 'Linux';
    }

    $browser = 'unknown';
    if (strpos($ua, 'edg/') !== false) {
        $browser = 'Edge';
    } elseif (strpos($ua, 'chrome/') !== false && strpos($ua, 'safari/') !== false) {
        $browser = 'Chrome';
    } elseif (strpos($ua, 'safari/') !== false && strpos($ua, 'chrome/') === false) {
        $browser = 'Safari';
    } elseif (strpos($ua, 'firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($ua, 'msie') !== false || strpos($ua, 'trident/') !== false) {
        $browser = 'Internet Explorer';
    } elseif (strpos($ua, 'opera') !== false || strpos($ua, 'opr/') !== false) {
        $browser = 'Opera';
    }

    return [$deviceType, $os, $browser];
}

function getGeoFromIp(string $ip): array
{
    $result = [
        'country'   => null,
        'region'    => null,
        'city'      => null,
        'latitude'  => null,
        'longitude' => null,
    ];

    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === '0.0.0.0') {
        return $result;
    }

    $url  = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,regionName,city,lat,lon';
    $json = @file_get_contents($url);

    if ($json === false) {
        return $result;
    }

    $data = json_decode($json, true);

    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return $result;
    }

    $result['country']   = $data['country']     ?? null;
    $result['region']    = $data['regionName']  ?? null;
    $result['city']      = $data['city']        ?? null;
    $result['latitude']  = $data['lat']         ?? null;
    $result['longitude'] = $data['lon']         ?? null;

    return $result;
}

$ip             = getClientIp();
$userAgent      = $_SERVER['HTTP_USER_AGENT']      ?? '';
$referrer       = $_SERVER['HTTP_REFERER']         ?? null;
$acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;

[$deviceType, $os, $browser] = parseDevice($userAgent);
$geo = getGeoFromIp($ip);

$db = getDb();

$stmt = $db->prepare(
    'INSERT INTO tracking_logs (
        clicked_at, ip, country, region, city, latitude, longitude,
        device_type, os, browser, user_agent, referrer, accept_language, target_url
     ) VALUES (
        NOW(), :ip, :country, :region, :city, :lat, :lon,
        :device_type, :os, :browser, :ua, :ref, :lang, :target
     )'
);

$stmt->execute([
    ':ip'          => $ip,
    ':country'     => $geo['country'],
    ':region'      => $geo['region'],
    ':city'        => $geo['city'],
    ':lat'         => $geo['latitude'],
    ':lon'         => $geo['longitude'],
    ':device_type' => $deviceType,
    ':os'          => $os,
    ':browser'     => $browser,
    ':ua'          => $userAgent,
    ':ref'         => $referrer,
    ':lang'        => $acceptLanguage,
    ':target'      => $targetUrl,
]);

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$currentUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
$ogImage = $previewImageUrl;
if ($ogImage !== '' && $ogImage[0] === '/') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $ogImage = $scheme . '://' . $host . $ogImage;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($previewTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($previewDescription, ENT_QUOTES, 'UTF-8'); ?>">

    <meta property="og:title" content="<?php echo htmlspecialchars($previewTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($previewDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8'); ?>">

    <meta http-equiv="refresh" content="1;url=<?php echo htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <p>Redirecting...</p>
    <p>If you are not redirected automatically, <a href="<?php echo htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8'); ?>">click here</a>.</p>
</body>
</html>

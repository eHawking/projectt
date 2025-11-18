<?php
declare(strict_types=1);

/**
 * Lightweight MaxMind GeoIP + VPN/proxy detection helper.
 *
 * This uses MaxMind's GeoIP2 Precision: Insights (or similar) web service if
 * MAXMIND_ACCOUNT_ID and MAXMIND_LICENSE_KEY are defined in config.php.
 *
 * If the credentials are missing or a network/API error occurs, this returns null
 * and callers should gracefully fall back to other GeoIP sources.
 */
function maxmind_geo_ip(string $ip): ?array
{
    $ip = trim($ip);
    if ($ip === '') {
        return null;
    }

    if (!defined('MAXMIND_ACCOUNT_ID') || !defined('MAXMIND_LICENSE_KEY')) {
        return null;
    }

    $accountId = (string)MAXMIND_ACCOUNT_ID;
    $licenseKey = (string)MAXMIND_LICENSE_KEY;
    if ($accountId === '' || $licenseKey === '') {
        return null;
    }

    $url = 'https://geoip.maxmind.com/geoip/v2.1/insights/' . rawurlencode($ip);
    $auth = base64_encode($accountId . ':' . $licenseKey);

    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Basic {$auth}\r\nAccept: application/json\r\n",
            'timeout' => 3,
        ],
        'https' => [
            'method' => 'GET',
            'header' => "Authorization: Basic {$auth}\r\nAccept: application/json\r\n",
            'timeout' => 3,
        ],
    ];

    $context = stream_context_create($opts);
    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    $countryIso = $data['country']['iso_code'] ?? null;
    $countryName = $data['country']['names']['en'] ?? null;
    $region = null;
    if (!empty($data['subdivisions']) && isset($data['subdivisions'][0]['names']['en'])) {
        $region = $data['subdivisions'][0]['names']['en'];
    }
    $city = $data['city']['names']['en'] ?? null;

    $lat = isset($data['location']['latitude']) ? (float)$data['location']['latitude'] : null;
    $lon = isset($data['location']['longitude']) ? (float)$data['location']['longitude'] : null;

    $traits = is_array($data['traits'] ?? null) ? $data['traits'] : [];

    $isp = $traits['isp']
        ?? $traits['organization']
        ?? $traits['autonomous_system_organization']
        ?? null;

    return [
        'country' => $countryName ?: $countryIso,
        'country_iso2' => $countryIso,
        'region' => $region,
        'city' => $city,
        'latitude' => $lat,
        'longitude' => $lon,
        'isp' => $isp,
        'is_anonymous' => (bool)($traits['is_anonymous'] ?? false),
        'is_anonymous_vpn' => (bool)($traits['is_anonymous_vpn'] ?? false),
        'is_public_proxy' => (bool)($traits['is_public_proxy'] ?? false),
        'is_tor_exit_node' => (bool)($traits['is_tor_exit_node'] ?? false),
        'is_hosting_provider' => (bool)($traits['is_hosting_provider'] ?? false),
        'is_legitimate_proxy' => (bool)($traits['is_legitimate_proxy'] ?? false),
    ];
}

function maxmind_ip_is_vpn_or_proxy(?array $info): bool
{
    if (!$info) {
        return false;
    }

    if (!empty($info['is_anonymous_vpn'])) {
        return true;
    }
    if (!empty($info['is_public_proxy'])) {
        return true;
    }
    if (!empty($info['is_tor_exit_node'])) {
        return true;
    }
    if (!empty($info['is_anonymous'])) {
        return true;
    }
    if (!empty($info['is_hosting_provider'])) {
        return true;
    }

    return false;
}

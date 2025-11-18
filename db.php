<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    return $pdo;
}

function get_client_ip(): string
{
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $value = $_SERVER[$key];
            $parts = explode(',', $value);
            return trim($parts[0]);
        }
    }

    return '';
}

function detect_vpn_proxy(string $ip, ?string $isp = null, ?bool $proxyFlag = null, ?bool $hostingFlag = null): bool
{
    if ($ip === '') {
        return false;
    }

    $maxmindResult = detect_vpn_proxy_maxmind($ip);
    if ($maxmindResult !== null) {
        return $maxmindResult;
    }

    if ($proxyFlag === null || $hostingFlag === null || $isp === null) {
        $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,isp,proxy,hosting';
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
            ],
        ]);

        $json = @file_get_contents($url, false, $context);
        if ($json !== false) {
            $data = json_decode($json, true);
            if (is_array($data) && ($data['status'] ?? '') === 'success') {
                if ($isp === null && isset($data['isp'])) {
                    $isp = (string)$data['isp'];
                }
                if ($proxyFlag === null && array_key_exists('proxy', $data)) {
                    $proxyFlag = (bool)$data['proxy'];
                }
                if ($hostingFlag === null && array_key_exists('hosting', $data)) {
                    $hostingFlag = (bool)$data['hosting'];
                }
            }
        }
    }

    if ($proxyFlag === true || $hostingFlag === true) {
        return true;
    }

    $ispLower = strtolower((string)$isp);
    if ($ispLower === '') {
        return false;
    }

    $vpnKeywords = ['vpn', 'proxy', 'hosting', 'data center', 'datacenter', 'colo', 'digitalocean', 'ovh', 'm247'];
    foreach ($vpnKeywords as $kw) {
        if (strpos($ispLower, $kw) !== false) {
            return true;
        }
    }

    return false;
}

function detect_vpn_proxy_info(string $ip, ?string $isp = null, ?bool $proxyFlag = null, ?bool $hostingFlag = null): array
{
    $result = [
        'detected' => false,
        'method' => null,
    ];

    if ($ip === '') {
        return $result;
    }

    $maxmindResult = detect_vpn_proxy_maxmind($ip);
    if ($maxmindResult !== null) {
        if ($maxmindResult === true) {
            $result['detected'] = true;
            $result['method'] = 'MaxMind';
        }
        return $result;
    }

    if ($proxyFlag === null || $hostingFlag === null || $isp === null) {
        $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,isp,proxy,hosting';
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
            ],
        ]);

        $json = @file_get_contents($url, false, $context);
        if ($json !== false) {
            $data = json_decode($json, true);
            if (is_array($data) && ($data['status'] ?? '') === 'success') {
                if ($isp === null && isset($data['isp'])) {
                    $isp = (string)$data['isp'];
                }
                if ($proxyFlag === null && array_key_exists('proxy', $data)) {
                    $proxyFlag = (bool)$data['proxy'];
                }
                if ($hostingFlag === null && array_key_exists('hosting', $data)) {
                    $hostingFlag = (bool)$data['hosting'];
                }
            }
        }
    }

    if ($proxyFlag === true || $hostingFlag === true) {
        $result['detected'] = true;
        $result['method'] = 'ip-api';
        return $result;
    }

    $ispLower = strtolower((string)$isp);
    if ($ispLower === '') {
        return $result;
    }

    $vpnKeywords = ['vpn', 'proxy', 'hosting', 'data center', 'datacenter', 'colo', 'digitalocean', 'ovh', 'm247'];
    foreach ($vpnKeywords as $kw) {
        if (strpos($ispLower, $kw) !== false) {
            $result['detected'] = true;
            $result['method'] = 'ISP keywords';
            return $result;
        }
    }

    return $result;
}

function detect_vpn_proxy_maxmind(string $ip): ?bool
{
    if ($ip === '') {
        return null;
    }

    $accountId = defined('MAXMIND_ACCOUNT_ID') ? MAXMIND_ACCOUNT_ID : '';
    $licenseKey = defined('MAXMIND_LICENSE_KEY') ? MAXMIND_LICENSE_KEY : '';

    if ($accountId === '' || $licenseKey === '') {
        return null;
    }

    $url = 'https://geoip.maxmind.com/geoip/v2.1/insights/' . urlencode($ip);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Basic ' . base64_encode($accountId . ':' . $licenseKey) . "\r\n",
            'timeout' => 2,
        ],
    ]);

    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    $traits = $data['traits'] ?? null;
    if (!is_array($traits)) {
        return null;
    }

    $fields = [
        'is_anonymous',
        'is_anonymous_vpn',
        'is_hosting_provider',
        'is_public_proxy',
        'is_residential_proxy',
        'is_tor_exit_node',
    ];

    foreach ($fields as $field) {
        if (!empty($traits[$field])) {
            return true;
        }
    }

    return false;
}

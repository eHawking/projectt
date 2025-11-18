<?php
declare(strict_types=1);

$lifetime = 60 * 60 * 24 * 30; // 30 days
$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.gc_maxlifetime', (string)$lifetime);

session_start();

require __DIR__ . '/../db.php';

function is_logged_in(): bool
{
    return isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /admin/login');
        exit;
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

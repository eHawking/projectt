<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_login();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/dashboard');
    exit;
}

$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) {
    $ids = [];
}
$filtered = [];
foreach ($ids as $id) {
    $id = (int)$id;
    if ($id > 0) {
        $filtered[] = $id;
    }
}

if ($filtered) {
    $placeholders = implode(',', array_fill(0, count($filtered), '?'));
    $stmt = $pdo->prepare("DELETE FROM visits WHERE id IN ($placeholders)");
    $stmt->execute($filtered);
}

$redirect = '/admin/dashboard';
if (!empty($_POST['redirect_query'])) {
    $q = (string)$_POST['redirect_query'];
    if ($q !== '') {
        $redirect .= '?' . $q;
    }
}

header('Location: ' . $redirect);
exit;

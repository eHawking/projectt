<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/config.php';

$adminPassword = 'changeme'; // CHANGE THIS PASSWORD

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';

        if (hash_equals($adminPassword, $password)) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin.php');
            exit;
        }

        $error = 'Invalid password';
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Tracker Admin Login</title>
    </head>
    <body>
    <?php if ($error !== null): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <form method="post">
        <label>
            Password:
            <input type="password" name="password" required>
        </label>
        <button type="submit">Login</button>
    </form>
    </body>
    </html>
    <?php
    exit;
}

ensureTrackingTableExists();
ensureSettingsTableExists();

$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $previewTitle = trim((string)($_POST['preview_title'] ?? ''));
    $previewDescription = trim((string)($_POST['preview_description'] ?? ''));
    $targetUrl = trim((string)($_POST['target_url'] ?? ''));

    $settings = getTrackerSettings();
    $imageUrl = (string)($settings['preview_image_url'] ?? '');

    if (isset($_FILES['preview_image']) && is_array($_FILES['preview_image']) && ($_FILES['preview_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && ($_FILES['preview_image']['size'] ?? 0) > 0) {
        $originalName = (string)$_FILES['preview_image']['name'];
        $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed, true)) {
            $uploadDir = __DIR__ . '/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = 'preview_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $targetPath = $uploadDir . '/' . $fileName;
            if (move_uploaded_file($_FILES['preview_image']['tmp_name'], $targetPath)) {
                $imageUrl = '/uploads/' . $fileName;
            }
        }
    }

    $stmtUpdate = $db->prepare('UPDATE tracker_settings SET preview_image_url = :img, preview_title = :title, preview_description = :descr, target_url = :target WHERE id = 1');
    $stmtUpdate->execute([
        ':img'    => $imageUrl !== '' ? $imageUrl : null,
        ':title'  => $previewTitle !== '' ? $previewTitle : null,
        ':descr'  => $previewDescription !== '' ? $previewDescription : null,
        ':target' => $targetUrl !== '' ? $targetUrl : null,
    ]);
}

$settings = getTrackerSettings();

$previewImageUrlCurrent = (string)($settings['preview_image_url'] ?? '');
$previewTitleCurrent = (string)($settings['preview_title'] ?? '');
$previewDescriptionCurrent = (string)($settings['preview_description'] ?? '');
$targetUrlCurrent = (string)($settings['target_url'] ?? '');

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$previewImageFullUrl = '';
if ($previewImageUrlCurrent !== '') {
    if ($previewImageUrlCurrent[0] === '/') {
        $previewImageFullUrl = $scheme . '://' . $host . $previewImageUrlCurrent;
    } else {
        $previewImageFullUrl = $previewImageUrlCurrent;
    }
}

$limit = 200;

$stmt = $db->prepare('SELECT * FROM tracking_logs ORDER BY clicked_at DESC LIMIT :limit');
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tracking Admin</title>
    <style>
        table { border-collapse: collapse; width: 100%; font-size: 12px; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: left; }
        th { background-color: #f0f0f0; }
        tr:nth-child(even) { background-color: #fafafa; }
        .settings-form { border: 1px solid #ccc; padding: 10px; margin-bottom: 20px; }
        .settings-form label { display: block; margin-bottom: 6px; }
        .settings-form input[type="text"],
        .settings-form input[type="url"],
        .settings-form textarea { width: 100%; max-width: 600px; }
    </style>
</head>
<body>
<div class="settings-form">
    <h1>Preview settings</h1>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_settings">
        <label>
            Target URL
            <input type="url" name="target_url" value="<?php echo htmlspecialchars($targetUrlCurrent, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Preview title
            <input type="text" name="preview_title" value="<?php echo htmlspecialchars($previewTitleCurrent, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            Preview description
            <textarea name="preview_description" rows="2"><?php echo htmlspecialchars($previewDescriptionCurrent, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>
        <label>
            Preview image file (JPEG/PNG/GIF/WEBP)
            <input type="file" name="preview_image" accept=".jpg,.jpeg,.png,.gif,.webp">
        </label>
        <?php if ($previewImageFullUrl !== ''): ?>
            <p>Current image:</p>
            <p><img src="<?php echo htmlspecialchars($previewImageFullUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="max-width: 200px; height: auto;"></p>
        <?php endif; ?>
        <button type="submit">Save settings</button>
    </form>
</div>
<h2>Tracking Logs (latest <?php echo (int)$limit; ?>)</h2>
<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Time</th>
        <th>IP</th>
        <th>Country</th>
        <th>Region</th>
        <th>City</th>
        <th>Lat</th>
        <th>Lon</th>
        <th>Device</th>
        <th>OS</th>
        <th>Browser</th>
        <th>Referrer</th>
        <th>Accept-Lang</th>
        <th>Target URL</th>
        <th>User-Agent</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td><?php echo (int)$row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['clicked_at'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($row['ip'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['country'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['region'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['city'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['latitude'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['longitude'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['device_type'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['os'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['browser'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['referrer'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['accept_language'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['target_url'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['user_agent'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>

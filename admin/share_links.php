<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_login();

$pdo = db();

function share_fetch_url_content(string $url): ?string
{
    $opts = [
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
        ],
        'https' => [
            'method' => 'GET',
            'timeout' => 5,
        ],
    ];
    $context = stream_context_create($opts);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return null;
    }
    return $body;
}

function share_extract_metadata(string $html): array
{
    $title = null;
    $description = null;
    $image = null;

    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
        $title = trim($m[1]);
    } elseif (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m)
        || preg_match('/<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m)) {
        $description = trim($m[1]);
    }

    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
        $image = trim($m[1]);
    }

    return [
        'title' => $title,
        'description' => $description,
        'image' => $image,
    ];
}

function share_download_remote_image(string $url, string $slug): ?string
{
    if (stripos($url, 'http://') !== 0 && stripos($url, 'https://') !== 0) {
        return null;
    }

    $opts = [
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
        ],
        'https' => [
            'method' => 'GET',
            'timeout' => 8,
        ],
    ];
    $context = stream_context_create($opts);
    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        return null;
    }

    if (strlen($data) > 5 * 1024 * 1024) {
        return null;
    }

    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if ($ext === '' || !in_array($ext, $allowed, true)) {
        $ext = 'jpg';
    }

    $uploadDir = dirname(__DIR__) . '/uploads/share';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $safeSlug = $slug !== '' ? $slug : 'auto';
    $safeSlug = preg_replace('/[^A-Za-z0-9_-]/', '_', $safeSlug);
    $fileName = 'share_' . $safeSlug . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . '/' . $fileName;

    if (file_put_contents($destPath, $data) === false) {
        return null;
    }

    return 'uploads/share/' . $fileName;
}

$errors = [];
$success = '';

$slug = trim($_POST['slug'] ?? '');
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$targetUrl = trim($_POST['target_url'] ?? '');
$isActive = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (isset($_POST['is_active']) ? 1 : 0)
    : 1;
$remoteImageUrl = trim($_POST['remote_image_url'] ?? '');
$action = $_POST['action'] ?? 'save';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'fetch') {
        if ($targetUrl === '') {
            $errors[] = 'Target URL is required to fetch metadata.';
        } else {
            $html = share_fetch_url_content($targetUrl);
            if ($html === null) {
                $errors[] = 'Could not fetch metadata from the target URL.';
            } else {
                $meta = share_extract_metadata($html);
                if ($meta['title'] !== null && $title === '') {
                    $title = $meta['title'];
                }
                if ($meta['description'] !== null && $description === '') {
                    $description = $meta['description'];
                }
                if ($meta['image'] !== null) {
                    $remoteImageUrl = $meta['image'];
                }
                if ($slug === ''
                    && preg_match('#^https?://(?:www\.)?dailysokalersomoy\.com/news/(\d+)#i', $targetUrl, $mNews)) {
                    $slug = 'news-' . $mNews[1];
                }
                if (!$errors) {
                    $success = 'Metadata fetched. Review and adjust before saving.';
                }
            }
        }
    } else {
        if ($slug === '') {
            $errors[] = 'Slug is required.';
        } elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $slug)) {
            $errors[] = 'Slug may only contain letters, numbers, hyphen and underscore.';
        }

        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if ($targetUrl === '') {
            $errors[] = 'Target URL is required.';
        }

        $imagePath = null;

        if ($remoteImageUrl !== '') {
            $downloaded = share_download_remote_image($remoteImageUrl, $slug);
            if ($downloaded !== null) {
                $imagePath = $downloaded;
            } else {
                $errors[] = 'Failed to download remote preview image.';
            }
        }

        if ($imagePath === null && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['image']['tmp_name'];
                $origName = $_FILES['image']['name'] ?? 'image';
                $size = (int)($_FILES['image']['size'] ?? 0);

                if ($size > 5 * 1024 * 1024) {
                    $errors[] = 'Image must be smaller than 5MB.';
                } else {
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (!in_array($ext, $allowed, true)) {
                        $errors[] = 'Image must be JPG, PNG, GIF or WEBP.';
                    } else {
                        $uploadDir = dirname(__DIR__) . '/uploads/share';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0777, true);
                        }
                        $fileName = 'share_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $slug) . '_' . time() . '.' . $ext;
                        $destPath = $uploadDir . '/' . $fileName;
                        if (move_uploaded_file($tmpName, $destPath)) {
                            $imagePath = 'uploads/share/' . $fileName;
                        } else {
                            $errors[] = 'Failed to save uploaded image.';
                        }
                    }
                }
            } else {
                $errors[] = 'Error during image upload.';
            }
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare('INSERT INTO share_links (slug, title, description, image_path, target_url, is_active) VALUES (:slug, :title, :description, :image_path, :target_url, :is_active)');
                $stmt->execute([
                    ':slug' => $slug,
                    ':title' => $title,
                    ':description' => $description !== '' ? $description : null,
                    ':image_path' => $imagePath,
                    ':target_url' => $targetUrl,
                    ':is_active' => $isActive,
                ]);

                $success = 'Share link created successfully.';
                $slug = '';
                $title = '';
                $description = '';
                $targetUrl = '';
                $remoteImageUrl = '';
                $isActive = 1;
            } catch (Throwable $e) {
                if ((int)$e->getCode() === 23000) {
                    $errors[] = 'Slug already exists. Please choose another.';
                } else {
                    $errors[] = 'Failed to save share link.';
                }
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM share_links WHERE id = :id');
            $stmt->execute([':id' => $id]);
        } catch (Throwable $e) {
        }
        header('Location: /admin/share_links');
        exit;
    }
}

$stmt = $pdo->query('SELECT id, slug, title, description, image_path, target_url, is_active, created_at FROM share_links ORDER BY created_at DESC');
$links = $stmt->fetchAll();

$baseShareUrl = rtrim(BASE_URL, '/') . '/share/';
$baseNewsUrl = rtrim(BASE_URL, '/') . '/news/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manage Share Links</title>
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
            --accent-soft: rgba(251, 113, 133, 0.26);
            --border-subtle: rgba(15, 23, 42, 0.9);
            --text-main: #e5e7eb;
            --text-muted: #9ca3af;
            --field-bg: #020617;
            --table-row-alt-bg: #050b18;
        }
        * { box-sizing: border-box; }

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
            margin-left: 10px;
        }
        .container {
            max-width: 900px;
            margin: 20px auto 28px;
            padding: 0 16px 32px;
        }
        .card {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 16px 18px;
            box-shadow: 0 18px 60px rgba(15, 23, 42, 0.9),
                        0 0 0 1px rgba(15, 23, 42, 0.9);
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 0.95rem;
            margin-bottom: 12px;
        }
        label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        input[type="text"],
        input[type="url"],
        textarea {
            width: 100%;
            padding: 6px 8px;
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
            background: var(--field-bg);
            color: var(--text-main);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        textarea {
            min-height: 60px;
            resize: vertical;
        }
        input[type="file"] {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        .field-inline {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        button {
            border: none;
            border-radius: 999px;
            padding: 8px 18px;
            font-size: 0.9rem;
            cursor: pointer;
            font-weight: 500;
            background: var(--accent);
            color: #022c22;
        }
        .messages {
            margin-bottom: 12px;
            font-size: 0.86rem;
        }
        .messages .error {
            color: #fca5a5;
        }
        .messages .success {
            color: #4ade80;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        th, td {
            padding: 6px 8px;
            border-bottom: 1px solid rgba(30, 64, 175, 0.35);
            text-align: left;
        }
        th {
            color: var(--text-muted);
        }
        tr:nth-child(even) td {
            background: var(--table-row-alt-bg);
        }
        td a {
            color: var(--accent);
            text-decoration: none;
        }
        td a:hover {
            text-decoration: underline;
        }
        .badge-active {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.75rem;
        }
        .badge-inactive {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: rgba(127, 29, 29, 0.4);
            color: #fecaca;
            font-size: 0.75rem;
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
            .bottom-nav {
                display: flex;
            }
        }

        @media (max-width: 768px) {
            table {
                font-size: 0.78rem;
            }
            th, td {
                padding: 6px 8px;
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
        <a href="/admin/dashboard" class="sidebar-link"><i class="bi bi-speedometer2 icon-inline"></i>Dashboard</a>
        <a href="/admin/share_links" class="sidebar-link sidebar-link-active"><i class="bi bi-link-45deg icon-inline"></i>Share links</a>
    </nav>
    <div class="sidebar-user">
        <div>Logged in as <?= h($_SESSION['admin_username'] ?? 'admin') ?></div>
        <a href="/admin/logout">Logout</a>
    </div>
</div>
<header>
    <h1><i class="bi bi-link-45deg icon-inline"></i>Share Links</h1>
</header>
<div class="container">
    <div class="card">
        <div class="card-title"><i class="bi bi-plus-circle icon-inline"></i>Create new share link</div>
        <div class="messages">
            <?php foreach ($errors as $e): ?>
                <div class="error">&bull; <?= h($e) ?></div>
            <?php endforeach; ?>
            <?php if ($success): ?>
                <div class="success"><?= h($success) ?></div>
            <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data">
            <label for="slug">Slug (for /share/slug)</label>
            <input type="text" name="slug" id="slug" value="<?= h($slug) ?>" placeholder="e.g. campaign1">

            <label for="title">Title (for WhatsApp / Open Graph)</label>
            <input type="text" name="title" id="title" value="<?= h($title) ?>">

            <label for="description">Description (optional)</label>
            <textarea name="description" id="description" placeholder="Optional message shown under title."><?= h($description) ?></textarea>

            <label for="target_url">Target URL (where visitor goes after opening link)</label>
            <input type="url" name="target_url" id="target_url" value="<?= h($targetUrl) ?>" placeholder="https://www.dailysokalersomoy.com/news/123" required>

            <input type="hidden" name="remote_image_url" id="remote_image_url" value="<?= h($remoteImageUrl) ?>">

            <label for="image">Preview image (JPG/PNG/GIF/WEBP, optional)</label>
            <input type="file" name="image" id="image" accept="image/*">

            <?php if ($remoteImageUrl !== ''): ?>
                <div class="field-inline" style="margin: 6px 0 10px 0;">
                    <span>Fetched image:</span>
                    <a href="<?= h($remoteImageUrl) ?>" target="_blank">Open</a>
                </div>
            <?php endif; ?>

            <div class="field-inline" style="margin-top: 6px; margin-bottom: 14px;">
                <input type="checkbox" id="is_active" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <label for="is_active" style="margin: 0;">Active</label>
            </div>

            <div class="field-inline" style="margin-top: 4px; gap: 10px;">
                <button type="submit" name="action" value="fetch" style="background: #020b26; color: var(--text-main); border: 1px solid var(--border-subtle);">
                    <i class="bi bi-magic icon-inline"></i>Fetch metadata
                </button>
                <button type="submit" name="action" value="save">
                    <i class="bi bi-save icon-inline"></i>Save share link
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-title"><i class="bi bi-list-ul icon-inline"></i>Existing share links</div>
        <table>
            <thead>
            <tr>
                <th>Slug</th>
                <th>Title</th>
                <th>Share URL</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($links as $row): ?>
                <?php
                $shareUrl = $baseShareUrl . $row['slug'];
                $target = (string)($row['target_url'] ?? '');
                if ($target !== '' && preg_match('#^https?://(?:www\.)?dailysokalersomoy\.com/news/(\d+)#i', $target, $mNews)) {
                    $shareUrl = $baseNewsUrl . $mNews[1];
                }
                ?>
                <tr>
                    <td><?= h($row['slug']) ?></td>
                    <td><?= h($row['title']) ?></td>
                    <td style="max-width: 260px; overflow-wrap: anywhere;">
                        <?= h($shareUrl) ?>
                    </td>
                    <td>
                        <?php if ((int)$row['is_active'] === 1): ?>
                            <span class="badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= h($shareUrl) ?>" target="_blank"><i class="bi bi-box-arrow-up-right icon-inline"></i>Open</a>
                        |
                        <a href="/admin/share_links?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Delete this share link?');"><i class="bi bi-trash3 icon-inline"></i>Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$links): ?>
                <tr><td colspan="5">No share links yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="/assets/js/theme.js"></script>
<div class="bottom-nav">
    <a href="/admin/dashboard" class="bottom-nav-link"><i class="bi bi-speedometer2"></i></a>
    <a href="/admin/share_links" class="bottom-nav-link bottom-nav-link-active"><i class="bi bi-link-45deg"></i></a>
    <a href="/admin/logout" class="bottom-nav-link"><i class="bi bi-box-arrow-right"></i></a>
</div>
</body>
</html>

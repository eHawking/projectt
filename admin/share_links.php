<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_login();

$pdo = db();

$errors = [];
$success = '';

$slug = trim($_POST['slug'] ?? '');
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$targetUrl = trim($_POST['target_url'] ?? '');
$isActive = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (isset($_POST['is_active']) ? 1 : 0)
    : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manage Share Links</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            /* Light theme (default) – dark navy CRM style */
            --bg-gradient: linear-gradient(135deg, #051637 0%, #020817 40%, #020314 100%);
            --header-bg: #051327;
            --header-fg: #f9fafb;
            --card-bg: #071a35;
            --card-elevated: #071f3f;
            --accent: #fb7185; /* neon pink */
            --accent-soft: rgba(251, 113, 133, 0.2);
            --border-subtle: rgba(15, 23, 42, 0.85);
            --text-main: #e5e7eb;
            --text-muted: #94a3b8;
            --field-bg: #020b26;
            --table-row-alt-bg: #051426;
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
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
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
            .container {
                padding: 0 12px 24px;
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
<header>
    <h1>Share Links</h1>
    <div>
        <a href="/admin/dashboard">Dashboard</a>
        <a href="/admin/logout">Logout</a>
    </div>
</header>
<div class="container">
    <div class="card">
        <div class="card-title">Create new share link</div>
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
            <input type="text" name="slug" id="slug" value="<?= h($slug) ?>" placeholder="e.g. campaign1" required>

            <label for="title">Title (for WhatsApp / Open Graph)</label>
            <input type="text" name="title" id="title" value="<?= h($title) ?>" required>

            <label for="description">Description (optional)</label>
            <textarea name="description" id="description" placeholder="Optional message shown under title."><?= h($description) ?></textarea>

            <label for="target_url">Target URL (where visitor goes after opening link)</label>
            <input type="url" name="target_url" id="target_url" value="<?= h($targetUrl) ?>" placeholder="https://www.dailysokalersomoy.com/news/123" required>

            <label for="image">Preview image (JPG/PNG/GIF/WEBP, optional)</label>
            <input type="file" name="image" id="image" accept="image/*">

            <div class="field-inline" style="margin-top: 6px; margin-bottom: 14px;">
                <input type="checkbox" id="is_active" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                <label for="is_active" style="margin: 0;">Active</label>
            </div>

            <button type="submit">Save share link</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Existing share links</div>
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
                <tr>
                    <td><?= h($row['slug']) ?></td>
                    <td><?= h($row['title']) ?></td>
                    <td style="max-width: 260px; overflow-wrap: anywhere;">
                        <?= h($baseShareUrl . $row['slug']) ?>
                    </td>
                    <td>
                        <?php if ((int)$row['is_active'] === 1): ?>
                            <span class="badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= h($baseShareUrl . $row['slug']) ?>" target="_blank">Open</a>
                        |
                        <a href="/admin/share_links?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Delete this share link?');">Delete</a>
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
</body>
</html>

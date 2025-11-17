<?php
declare(strict_types=1);

function index_fetch_url_content(string $url): ?string
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

function index_extract_metadata(string $html): array
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

$iframeUrl = 'https://dailysokalersomoy.com';

$title = 'Daily Sokalersomoy';
$description = 'Latest news and updates from Daily Sokalersomoy.';
$imageUrl = $iframeUrl . '/favicon.ico';
$pageUrl = 'https://dailysokalersomoy.online';

$html = index_fetch_url_content($iframeUrl);
if ($html !== null) {
    $meta = index_extract_metadata($html);
    if ($meta['title'] !== null) {
        $title = $meta['title'];
    }
    if ($meta['description'] !== null) {
        $description = $meta['description'];
    }
    if ($meta['image'] !== null) {
        $imageUrl = $meta['image'];
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
        }
        body {
            background: #020617;
        }
        .frame-wrapper {
            width: 100%;
            height: 100%;
        }
        .frame-wrapper iframe {
            border: 0;
            width: 100%;
            height: 100%;
            display: block;
        }
    </style>
</head>
<body>
<div class="frame-wrapper">
    <iframe src="https://dailysokalersomoy.com" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
</div>
<script src="/assets/js/tracker.js"></script>
</body>
</html>


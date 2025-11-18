<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

function landing_fetch_url_content(string $url): ?string
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

function landing_extract_metadata(string $html): array
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

function landing_is_vpn_or_proxy_ip(string $ip): bool
{
    $ip = trim($ip);
    if ($ip === '') {
        return false;
    }

    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,proxy,hosting,isp,message';
    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
        ],
    ]);

    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        return false;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return false;
    }

    if (!empty($data['proxy']) || !empty($data['hosting'])) {
        return true;
    }

    $isp = strtolower((string)($data['isp'] ?? ''));
    if ($isp !== '') {
        $vpnKeywords = ['vpn', 'proxy', 'hosting', 'data center', 'datacenter', 'colo', 'digitalocean', 'ovh', 'm247'];
        foreach ($vpnKeywords as $kw) {
            if (strpos($isp, $kw) !== false) {
                return true;
            }
        }
    }

    return false;
}

// Path of the current request (e.g. /share/my-link or /news/138822)
$uri = $_SERVER['REQUEST_URI'] ?? '/landing';
$path = parse_url($uri, PHP_URL_PATH) ?? '/landing';

$isNews = (bool)preg_match('#^/news/(\d+)#', $path);

// Pretend canonical domain is dailysokalersomoy.com for previews
$pageUrl = 'https://www.dailysokalersomoy.com' . $path;

// Default meta values
$title = 'Daily Sokalersomoy – Smart Link';
$description = 'Open this link to view content. We use basic analytics (IP, device, approximate location) to improve our service.';
$imageUrl = BASE_URL . '/assets/img/preview.jpg';
$targetUrl = null;

$pdo = db();

// If URL is /share/{slug}, try to load configuration from share_links
if (preg_match('#^/share/([A-Za-z0-9_-]+)#', $path, $m)) {
    $slug = $m[1];

    try {
        $stmt = $pdo->prepare('SELECT title, description, image_path, target_url FROM share_links WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $share = $stmt->fetch();
    } catch (Throwable $e) {
        $share = false;
    }

    if ($share && is_array($share)) {
        if (!empty($share['title'])) {
            $title = (string)$share['title'];
        }
        if (isset($share['description']) && $share['description'] !== '') {
            $description = (string)$share['description'];
        }

        if (!empty($share['image_path'])) {
            $img = (string)$share['image_path'];
            if (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) {
                $imageUrl = $img;
            } else {
                if ($img[0] !== '/') {
                    $img = '/' . $img;
                }
                $imageUrl = BASE_URL . $img;
            }
        }

        if (!empty($share['target_url'])) {
            $targetUrl = (string)$share['target_url'];
        }
    }
}

// If still no target URL and this is a /news/{id} URL, build a target URL on the .com site
// and try to fetch the article's own metadata for previews.
if ($targetUrl === null && $isNews) {
    $targetUrl = 'https://www.dailysokalersomoy.com' . $path;

    $html = landing_fetch_url_content($targetUrl);
    if ($html !== null) {
        $meta = landing_extract_metadata($html);
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
}

// For share links, if we have a configured target URL, use it as the canonical URL
// for previews (e.g. WhatsApp) so the shared URL matches the article URL.
if ($targetUrl !== null) {
    $pageUrl = $targetUrl;
}

$vpnBlocked = false;
if ($isNews) {
    $clientIp = get_client_ip();
    if ($clientIp !== '') {
        $vpnBlocked = landing_is_vpn_or_proxy_ip($clientIp);
    }
}

$showIframe = $isNews && $targetUrl !== null && !$vpnBlocked;
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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        :root {
            /* Light theme (default) – dark navy CRM style */
            --bg-gradient: linear-gradient(135deg, #f9fafb 0%, #e5e7eb 40%, #e5e7eb 100%);
            --card-bg: #ffffff;
            --accent: #fb7185; /* neon pink */
            --accent-soft: rgba(251, 113, 133, 0.14);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-subtle: rgba(148, 163, 184, 0.5);
        }

        :root[data-theme="dark"] {
            /* Dark theme – slightly deeper variant */
            --bg-gradient: linear-gradient(135deg, #020617 0%, #020012 40%, #000000 100%);
            --card-bg: #061327;
            --accent: #fb7185;
            --accent-soft: rgba(251, 113, 133, 0.26);
            --text-main: #e5e7eb;
            --text-muted: #9ca3af;
            --border-subtle: rgba(15, 23, 42, 0.9);
        }

        * {
            box-sizing: border-box;
        }

        .icon-inline {
            margin-right: 6px;
            font-size: 1rem;
            vertical-align: -0.1em;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        body.news-body {
            display: block;
            padding: 0;
        }

        .page {
            width: 100%;
            max-width: 520px;
            background: var(--card-bg);
            border-radius: 22px;
            padding: 24px 22px 26px;
            box-shadow:
                0 22px 80px rgba(15, 23, 42, 1),
                0 0 0 1px rgba(15, 23, 42, 0.9);
            position: relative;
            overflow: hidden;
        }

        .news-frame-wrapper {
            width: 100%;
            height: 100vh;
        }

        .news-frame {
            border: 0;
            width: 100%;
            height: 100%;
            display: block;
        }

        .page::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(251, 113, 133, 0.22), transparent 55%);
            pointer-events: none;
        }

        .page-inner {
            position: relative;
            z-index: 1;
        }

        .theme-toggle {
            position: absolute;
            top: 12px;
            right: 14px;
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

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #020b26;
            border: 1px solid rgba(15, 23, 42, 0.9);
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 14px;
        }

        .badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 0 0 4px var(--accent-soft);
        }

        h1 {
            margin: 0 0 6px 0;
            font-size: 1.6rem;
            letter-spacing: -0.02em;
        }

        .subtitle {
            margin: 0 0 18px 0;
            font-size: 0.98rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .banner {
            margin-top: 20px;
            padding: 14px 14px 16px;
            background: #020b26;
            border-radius: 12px;
            font-size: 0.9rem;
            text-align: left;
            border: 1px solid rgba(15, 23, 42, 0.9);
        }

        .banner p {
            margin: 0 0 12px 0;
            color: var(--text-muted);
        }

        .banner-heading {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        button {
            border: none;
            border-radius: 999px;
            padding: 9px 18px;
            font-size: 0.86rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: transform 0.12s ease-out, box-shadow 0.12s ease-out, background-color 0.12s ease-out;
            font-weight: 500;
            white-space: nowrap;
        }

        button:active {
            transform: translateY(0);
            box-shadow: none;
        }

        #btn-allow-location {
            background: var(--accent);
            color: #022c22;
            box-shadow: 0 10px 25px rgba(34, 197, 94, 0.25);
        }

        #btn-allow-location:hover {
            background: #16a34a;
        }

        #btn-no-location {
            background: #020b26;
            color: var(--text-main);
            border: 1px solid rgba(15, 23, 42, 0.9);
        }

        #btn-no-location:hover {
            background: rgba(15, 23, 42, 0.8);
        }

        .note {
            margin-top: 18px;
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .note a {
            color: var(--accent);
            text-decoration: none;
        }

        .note a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .page {
                padding: 20px 16px 22px;
            }
            h1 {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body<?= $showIframe ? ' class="news-body"' : '' ?>>
<?php if ($showIframe): ?>
    <div class="news-frame-wrapper">
        <iframe class="news-frame" src="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
<?php else: ?>
    <div class="page">
        <button type="button" class="theme-toggle" data-theme-toggle>
            <span data-theme-toggle-label>Light</span> mode
        </button>
        <div class="page-inner">
            <div class="badge">
                <span class="badge-dot"></span>
                <i class="bi bi-activity icon-inline"></i>
                Smart visit analytics for Daily Sokalersomoy
            </div>
            <h1>Daily Sokalersomoy</h1>
            <p class="subtitle">
                Thanks for opening this secure smart link. We use basic analytics (IP, browser, device type,
                approximate location from IP) to understand visits and improve our service.
            </p>
            <?php if ($isNews && $vpnBlocked): ?>
                <div class="banner">
                    <div class="banner-heading">VPN / Proxy detected</div>
                    <p>
                        We could not display this news article because your connection appears to be coming from a VPN,
                        proxy or hosting IP. Please disconnect your VPN or proxy and reload this page to read the news.
                    </p>
                </div>

                <p class="note">
                    If you believe this is a mistake, try refreshing the page after disabling any VPN or proxy tools.
                </p>
            <?php else: ?>
                <div id="consent-banner" class="banner">
                    <div class="banner-heading">Optional precise location</div>
                    <p>
                        You can share your precise location (GPS) once using your browser's permission. This is used
                        only for our own analytics and is not shared with third parties.
                    </p>
                    <div class="buttons">
                        <button id="btn-allow-location"><i class="bi bi-geo-alt-fill icon-inline"></i>Allow location</button>
                        <button id="btn-no-location"><i class="bi bi-arrow-right-circle icon-inline"></i>Continue without location</button>
                    </div>
                </div>

                <p class="note">
                    By continuing to use this link, you agree to our use of analytics as described above.
                </p>
                <?php if ($targetUrl !== null): ?>
                    <p class="note" style="margin-top: 10px;">
                        <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') ?>">Continue to article on dailysokalersomoy.com</a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script src="/assets/js/theme.js"></script>
<script src="/assets/js/tracker.js?v=2"></script>
<script>
(function () {
  var allowBtn = document.getElementById('btn-allow-location');
  var noBtn = document.getElementById('btn-no-location');
  var banner = document.getElementById('consent-banner');

  function hideBanner() {
    if (banner) {
      banner.style.display = 'none';
    }
  }

  if (allowBtn) {
    allowBtn.addEventListener('click', function () {
      if (window.Tracker && typeof window.Tracker.requestGeo === 'function') {
        window.Tracker.requestGeo();
      }
      hideBanner();
    });
  }

  if (noBtn) {
    noBtn.addEventListener('click', function () {
      hideBanner();
    });
  }
})();
</script>
</body>
</html>

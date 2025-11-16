<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

// Path of the current request (e.g. /news/138822)
$uri = $_SERVER['REQUEST_URI'] ?? '/landing';
$path = parse_url($uri, PHP_URL_PATH) ?? '/landing';

// Pretend canonical domain is dailysokalersomoy.com for previews
$pageUrl = 'https://www.dailysokalersomoy.com' . $path;

// If this is a /news/{id} URL, build a target URL on the .com site
$targetUrl = null;
if (preg_match('#^/news/(\d+)#', $path)) {
    $targetUrl = 'https://www.dailysokalersomoy.com' . $path;
}

$title = 'Daily Sokalersomoy – Smart Link';
$description = 'Open this link to view content. We use basic analytics (IP, device, approximate location) to improve our service.';
$imageUrl = BASE_URL . '/assets/img/preview.jpg';
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
        :root {
            --bg-gradient: radial-gradient(circle at top left, #1e293b 0, #020617 45%, #020617 100%);
            --card-bg: rgba(15, 23, 42, 0.96);
            --accent: #22c55e;
            --accent-soft: rgba(34, 197, 94, 0.16);
            --text-main: #e5e7eb;
            --text-muted: #9ca3af;
            --border-subtle: rgba(148, 163, 184, 0.25);
        }

        * {
            box-sizing: border-box;
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

        .page {
            width: 100%;
            max-width: 520px;
            background: var(--card-bg);
            border-radius: 18px;
            padding: 24px 22px 26px;
            box-shadow:
                0 18px 60px rgba(15, 23, 42, 0.85),
                0 0 0 1px rgba(148, 163, 184, 0.25);
            position: relative;
            overflow: hidden;
        }

        .page::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(34, 197, 94, 0.18), transparent 55%);
            pointer-events: none;
        }

        .page-inner {
            position: relative;
            z-index: 1;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.3);
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 14px;
        }

        .badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.25);
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
            background: rgba(15, 23, 42, 0.9);
            border-radius: 12px;
            font-size: 0.9rem;
            text-align: left;
            border: 1px solid rgba(148, 163, 184, 0.45);
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
            background: rgba(15, 23, 42, 0.9);
            color: var(--text-main);
            border: 1px solid rgba(148, 163, 184, 0.4);
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
        }
    </style>
</head>
<body>
<div class="page">
    <div class="page-inner">
        <div class="badge">
            <span class="badge-dot"></span>
            Smart visit analytics for Daily Sokalersomoy
        </div>
        <h1>Daily Sokalersomoy</h1>
        <p class="subtitle">
            Thanks for opening this secure smart link. We use basic analytics (IP, browser, device type,
            approximate location from IP) to understand visits and improve our service.
        </p>

        <div id="consent-banner" class="banner">
            <div class="banner-heading">Optional precise location</div>
            <p>
                You can share your precise location (GPS) once using your browser's permission. This is used
                only for our own analytics and is not shared with third parties.
            </p>
            <div class="buttons">
                <button id="btn-allow-location">Allow location</button>
                <button id="btn-no-location">Continue without location</button>
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
    </div>
</div>

<script src="/assets/js/tracker.js"></script>
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

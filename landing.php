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
        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            color: #222;
        }
        .page {
            max-width: 480px;
            margin: 40px auto;
            padding: 24px 20px 32px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            text-align: center;
        }
        h1 {
            margin-top: 0;
            font-size: 1.6rem;
        }
        p {
            line-height: 1.5;
            font-size: 0.98rem;
        }
        .banner {
            margin-top: 20px;
            padding: 14px 12px;
            background: #f0f4ff;
            border-radius: 8px;
            font-size: 0.9rem;
            text-align: left;
        }
        .banner p {
            margin: 0 0 10px 0;
        }
        .buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        button {
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 0.9rem;
            cursor: pointer;
        }
        #btn-allow-location {
            background: #2563eb;
            color: #fff;
        }
        #btn-no-location {
            background: #e5e7eb;
            color: #111827;
        }
        .note {
            margin-top: 12px;
            font-size: 0.8rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
<div class="page">
    <h1>Daily Sokalersomoy</h1>
    <p>
        Thanks for opening this link. We use basic analytics (IP, browser, device type, approximate
        location from IP) to understand visits and improve our service.
    </p>

    <div id="consent-banner" class="banner">
        <p>
            Optionally, you can allow your precise location (GPS) to be shared once using your browser's
            location permission. This is used only for our own analytics and not shared with third parties.
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
        <p style="margin-top: 8px; font-size: 0.9rem;">
            <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') ?>">Continue to article on dailysokalersomoy.com</a>
        </p>
    <?php endif; ?>
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

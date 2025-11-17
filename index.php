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

$origin = 'https://dailysokalersomoy.com';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?? '/';
$queryString = $_SERVER['QUERY_STRING'] ?? '';

$targetUrl = rtrim($origin, '/') . $path;
if ($queryString !== '') {
    $targetUrl .= '?' . $queryString;
}

$html = index_fetch_url_content($targetUrl);

if ($html === null) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Sokalersomoy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <p>Unable to load content from dailysokalersomoy.com.</p>
    <p><a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') ?>">Open original site</a></p>
</body>
</html>
<?php
    exit;
}

// Insert <base> for static assets if not present
if (stripos($html, '<head') !== false && stripos($html, '<base ') === false) {
    $html = preg_replace(
        '#(<head[^>]*>)#i',
        '$1' . "\n" . '<base href="' . htmlspecialchars($origin, ENT_QUOTES, 'UTF-8') . '/">' . "\n",
        $html,
        1
    );
}

$host = $_SERVER['HTTP_HOST'] ?? 'dailysokalersomoy.online';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$proxyOrigin = $scheme . $host;

$proxyJs = <<<HTML
<script src="/assets/js/tracker.js?v=2"></script>
<script>
(function () {
  var origin = '$origin';
  var proxyOrigin = '$proxyOrigin';

  document.addEventListener('click', function (event) {
    if (event.defaultPrevented) return;
    if (event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    var el = event.target;
    while (el && el.nodeName !== 'A') {
      el = el.parentElement;
    }
    if (!el) return;
    if (el.target === '_blank' || el.hasAttribute('download')) return;

    var href = el.getAttribute('href');
    if (!href || href.indexOf('javascript:') === 0 || href.indexOf('#') === 0) return;

    var url;
    try {
      url = new URL(href, origin);
    } catch (e) {
      return;
    }

    if (url.origin === origin) {
      var path = url.pathname;
      var search = url.search || '';
      if (path === '/' || /^\\/news\\//.test(path)) {
        event.preventDefault();
        var newUrl = proxyOrigin + path + search;
        window.location.href = newUrl;
      }
    }
  }, true);
})();
</script>
HTML;

if (stripos($html, '</body>') !== false) {
    $html = preg_replace('#</body>#i', $proxyJs . "\n</body>", $html, 1);
} else {
    $html .= $proxyJs;
}

header('Content-Type: text/html; charset=utf-8');
echo $html;

<?php

declare(strict_types=1);

use BlazingSun\Api\Config\Config;
use BlazingSun\Api\Database\Connection;
use BlazingSun\Api\Models\ContentRepository;
use BlazingSun\Api\Page\Assets;
use BlazingSun\Api\Page\PageCache;
use BlazingSun\Api\Page\PageController;
use BlazingSun\Api\Page\PageRenderer;

// Front controller for the public page: / and /{iso2}/ are rewritten here (root .htaccess, nginx, php -S router).
// Deployed: public_html/api/page.php, so the built site is one level up. In the repo: server/public/page.php.
$root = is_dir(__DIR__ . '/src') ? __DIR__ : dirname(__DIR__);

require $root . '/src/autoload.php';

$send = static function (int $status, array $headers, string $body): void {
    http_response_code($status);
    foreach ($headers as $name => $value) {
        header("$name: $value");
    }
    echo $body;
};

try {
    $config = Config::fromFile(getenv('BLAZINGSUN_API_CONFIG') ?: $root . '/config/config.php');
    $dist = $config->path('dist', dirname($root));
    $controller = new PageController(
        static fn () => (new ContentRepository(new Connection($config)))->snapshot(),
        static fn () => Assets::load($dist . '/asset-manifest.json', $config->path('hot', $root . '/storage/hot'), 'src/main.js'),
        new PageCache($config->path('cache', $root . '/storage/cache')),
        new PageRenderer($root . '/templates/page.php'),
        $config->siteUrl(),
        (int) gmdate('Y'),
    );
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $response = $controller->handle((string) (parse_url(is_string($uri) ? $uri : '/', PHP_URL_PATH) ?: '/'));
    $send($response['status'], $response['headers'], $response['body']);
} catch (Throwable $error) {
    error_log(sprintf('[page] %s in %s:%d', $error->getMessage(), $error->getFile(), $error->getLine()));
    $send(503, ['Content-Type' => 'text/plain; charset=utf-8', 'Retry-After' => '60'], "Service temporarily unavailable.\n");
}

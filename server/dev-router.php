<?php

declare(strict_types=1);

// Router for PHP's built-in server (E2E tests), mirroring nginx/Apache in production:
//   php -S 127.0.0.1:4173 -t dist server/dev-router.php
// /api/* → public/index.php, / and /{iso2}/ → public/page.php, /uploads/* → the uploads directory, the rest → dist/.

use BlazingSun\Api\Config\Config;

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if ($path === '/api' || str_starts_with($path, '/api/')) {
    require __DIR__ . '/public/index.php';
    return true;
}
if (preg_match('~^/([a-z]{2}/?)?$~', $path) === 1) {
    require __DIR__ . '/public/page.php';
    return true;
}
if (str_starts_with($path, '/uploads/')) {
    require __DIR__ . '/src/autoload.php';
    $uploads = Config::fromFile((string) getenv('BLAZINGSUN_API_CONFIG'))->path('uploads', __DIR__ . '/storage/uploads');
    $file = realpath($uploads . '/' . substr($path, strlen('/uploads/')));
    if ($file === false || !str_starts_with($file, realpath($uploads) . '/') || !is_file($file)) {
        http_response_code(404);
        return true;
    }
    $types = ['webp' => 'image/webp', 'png' => 'image/png', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'];
    header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    return true;
}
if ($path === '/adminportal') {
    header('Location: /adminportal/', true, 301);
    return true;
}

return false;

<?php

declare(strict_types=1);

use BlazingSun\Api\Application;
use BlazingSun\Api\Config\Config;
use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use BlazingSun\Api\Http\Router;

// Deployed: everything sits next to this file (public_html/api/). In the repo: one level up (server/).
$root = is_dir(__DIR__ . '/src') ? __DIR__ : dirname(__DIR__);

require $root . '/src/autoload.php';

try {
    // BLAZINGSUN_API_CONFIG lets tests and local dev point at another config file.
    $config = Config::fromFile(getenv('BLAZINGSUN_API_CONFIG') ?: $root . '/config/config.php');
} catch (Throwable $error) {
    error_log('[api] ' . $error->getMessage());
    JsonResponse::error(503, 'Service unavailable')->send();
    return;
}

$router = new Router();
(require $root . '/routes/api.php')($router, $config, $root);

(new Application($router, static function (string $message): void {
    error_log($message);
}))->handle(Request::fromGlobals())->send();

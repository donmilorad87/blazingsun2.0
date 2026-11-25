<?php

declare(strict_types=1);

use BlazingSun\Api\Auth\AdminAuth;
use BlazingSun\Api\Auth\NativeSession;
use BlazingSun\Api\Config\Config;
use BlazingSun\Api\Content\ContentEditor;
use BlazingSun\Api\Content\ContentSeeder;
use BlazingSun\Api\Controllers\Admin\AuthController;
use BlazingSun\Api\Controllers\Admin\ContentController;
use BlazingSun\Api\Controllers\Admin\MediaController;
use BlazingSun\Api\Controllers\HealthController;
use BlazingSun\Api\Controllers\MigrationController;
use BlazingSun\Api\Controllers\VisitController;
use BlazingSun\Api\Database\Connection;
use BlazingSun\Api\Database\SqlMigrationRunner;
use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use BlazingSun\Api\Http\Router;
use BlazingSun\Api\Media\MediaStore;
use BlazingSun\Api\Models\ContentRepository;
use BlazingSun\Api\Models\LoginFailures;
use BlazingSun\Api\Models\PageVisit;
use BlazingSun\Api\Page\PageCache;

/** Registers every API route. Paths are relative to /api. */
return static function (Router $router, Config $config, string $root): void {
    $connection = new Connection($config);
    $visits = new PageVisit($connection);
    $content = new ContentRepository($connection);
    $pages = new PageCache($config->path('cache', $root . '/storage/cache'));

    $router->add('POST', '/visits', [new VisitController($visits), 'record']);
    $router->add('GET', '/health', [new HealthController($visits), 'show']);
    $router->add('POST', '/migrations', [
        new MigrationController(
            new SqlMigrationRunner($connection, $root . '/migrations'),
            $config->migrationToken(),
            static function () use ($content, $pages, $root): bool {
                // Migrations may change what the page shows, so the rendered pages are dropped either way.
                $seeded = (new ContentSeeder($content, $root . '/content/seed.json'))->run();
                $pages->clear();

                return $seeded;
            },
        ),
        'run',
    ]);

    // Admin portal API: everything except login and session needs a logged-in admin; changes also need the CSRF token.
    $auth = new AdminAuth(
        // Secure cookie over HTTPS, also behind a TLS-terminating proxy (Hostinger's CDN, the local reverse proxy).
        new NativeSession(($_SERVER['HTTPS'] ?? '') === 'on' || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'),
        new LoginFailures($connection),
        $config->adminUsername(),
        $config->adminPasswordHash(),
        static fn (): int => time(),
    );
    $admin = static fn (callable $handler): Closure => static fn (Request $request): JsonResponse => $auth->guard($request) ?? $handler($request);
    $session = new AuthController($auth);
    $editor = new ContentController($content, new ContentEditor($content, $pages->clear(...)));
    $dist = $config->path('dist', dirname($root));
    $media = new MediaController(new MediaStore($content, $config->path('uploads', $dist . '/uploads')), is_uploaded_file(...));

    $router->add('POST', '/admin/login', [$session, 'login']);
    $router->add('GET', '/admin/session', [$session, 'show']);
    $router->add('POST', '/admin/logout', $admin([$session, 'logout']));
    $router->add('GET', '/admin/content', $admin([$editor, 'show']));
    $router->add('POST', '/admin/section', $admin([$editor, 'saveSection']));
    $router->add('POST', '/admin/settings', $admin([$editor, 'saveSettings']));
    $router->add('POST', '/admin/menu', $admin([$editor, 'saveMenu']));
    $router->add('POST', '/admin/languages', $admin([$editor, 'saveLanguage']));
    $router->add('POST', '/admin/languages/delete', $admin([$editor, 'deleteLanguage']));
    $router->add('POST', '/admin/languages/main', $admin([$editor, 'setMainLanguage']));
    $router->add('POST', '/admin/media', $admin([$media, 'upload']));
};

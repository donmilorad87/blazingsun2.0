<?php

// Copy to config/config.php for local development (git-ignored).
// In production, the deploy job writes config.php from GitHub secrets (scripts/write-api-config.sh).
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'blazingsun',
        'user' => 'blazingsun',
        'password' => 'change-me',
    ],
    // Bearer token for POST /api/migrations. Empty string disables the endpoint.
    'migration_token' => '',
    // Public origin for canonical and hreflang links (default https://blazingsun.space).
    'site_url' => 'https://blazingsun.space',
    // Optional. dist: the built site (default: the folder above api/). uploads: admin uploads (default: {dist}/uploads).
    'paths' => [],
    // Admin portal login (/adminportal). Empty username disables it. The deploy writes the hash of the
    // ADMIN_PASSWORD secret here; by hand: php -r 'echo password_hash("your password", PASSWORD_ARGON2ID);'
    'admin' => [
        'username' => '',
        'password_hash' => '',
    ],
];

<?php

// Local Docker credentials only (see docker-compose.yml). Production config is written from GitHub secrets.
return [
    'db' => [
        'host' => 'mysql',
        'port' => '3306',
        'name' => 'blazingsun',
        'user' => 'blazingsun',
        'password' => 'blazingsun',
    ],
    'migration_token' => 'local-dev-token',
    'site_url' => 'http://blazingsun.local',
    'paths' => [
        'dist' => '/var/www/dist',
        'uploads' => '/var/www/uploads',
    ],
    // Local admin login from .env ADMIN_USERNAME / ADMIN_PASSWORD (docker-compose.yml passes them; default blazingsun / blazingsun).
    'admin' => [
        'username' => getenv('ADMIN_USERNAME') ?: 'blazingsun',
        // shortcut: hashed on every request with a cheap bcrypt cost, fine locally only; the deploy writes an argon2id hash.
        'password_hash' => password_hash(getenv('ADMIN_PASSWORD') ?: 'blazingsun', PASSWORD_BCRYPT, ['cost' => 4]),
    ],
];

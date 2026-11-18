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
];

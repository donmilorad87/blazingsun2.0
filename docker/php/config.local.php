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
];

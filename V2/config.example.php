<?php
// config.example.php — copy to config.local.php and fill in the real values.
// config.local.php is git-ignored so database credentials never reach the repository.
// Environment variables (DB_HOST, DB_NAME, DB_USER, DB_PASS) override the values below, e.g. in Docker.
return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'name' => getenv('DB_NAME') ?: 'bovornserv_v2',
    'user' => getenv('DB_USER') ?: 'bovornserv',
    'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'CHANGE_ME',
];

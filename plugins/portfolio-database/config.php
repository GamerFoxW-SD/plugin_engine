<?php
declare(strict_types=1);

return [
    'host' => getenv('PORTFOLIO_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('PORTFOLIO_DB_PORT') ?: 3306),
    'database' => getenv('PORTFOLIO_DB_NAME') ?: 'portfolio',
    'username' => getenv('PORTFOLIO_DB_USER') ?: 'root',
    'password' => getenv('PORTFOLIO_DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];

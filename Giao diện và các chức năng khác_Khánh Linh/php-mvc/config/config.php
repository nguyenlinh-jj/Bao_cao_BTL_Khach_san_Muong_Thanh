<?php
declare(strict_types=1);

return [
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'muong_thanh_pq',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Mường Thanh Luxury Phú Quốc',
        'timezone' => 'Asia/Ho_Chi_Minh',
    ],
];

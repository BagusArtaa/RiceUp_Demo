<?php

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    if (!is_string($config['db_user'] ?? null) || $config['db_user'] === ''
        || !is_string($config['db_password'] ?? null)
        || $config['db_user'] === 'YOUR_XAMPP_DB_USER' || $config['db_password'] === 'SET_THIS_LOCALLY'
        || !is_string($config['app_key'] ?? null) || strlen($config['app_key']) < 32
        || $config['app_key'] === 'replace-this-with-a-random-long-local-secret') {
        throw new RuntimeException('Local database credentials or application key are missing. Copy config/local.example.php to config/local.php and set them.');
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
    try {
        $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+08:00'");
        return $pdo;
    } catch (PDOException $exception) {
        error_log('[Rice Up] Database connection failed: ' . $exception->getMessage());
        throw new RuntimeException('Database is unavailable. Check config/local.php and make sure MySQL is running.');
    }
}

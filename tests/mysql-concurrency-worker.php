<?php

if (PHP_SAPI !== 'cli') {
    exit(2);
}
define('APP_ROOT', dirname(__DIR__));
$GLOBALS['app_config'] = require APP_ROOT . '/app/config.php';
require APP_ROOT . '/app/helpers.php';
require APP_ROOT . '/app/database.php';
require APP_ROOT . '/app/OrderService.php';

if (!str_ends_with((string) config('db_name', ''), '_test')) {
    fwrite(STDERR, "Safety stop: concurrency workers only run against a database ending in _test.\n");
    exit(2);
}

$productId = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$token = $argv[2] ?? '';
$barrier = $argv[3] ?? '';
$worker = $argv[4] ?? '';
if ($productId === false || !preg_match('/^[a-f0-9]{64}$/', $token)
    || !preg_match('/^[a-f0-9]{24}$/', $worker) || !is_dir($barrier)) {
    exit(2);
}

file_put_contents($barrier . DIRECTORY_SEPARATOR . $worker . '.ready', 'ready', LOCK_EX);
$deadline = microtime(true) + 15;
while (!is_file($barrier . DIRECTORY_SEPARATOR . 'go')) {
    if (microtime(true) >= $deadline) {
        exit(3);
    }
    usleep(10000);
}

try {
    $order = OrderService::create(db(), $token, [
        'customer_name' => 'Concurrency Test', 'customer_phone' => '081234567890',
        'fulfillment_method' => 'pickup', 'delivery_address' => null, 'notes' => null,
        'payment_method' => 'simulated_transfer',
    ], [(int) $productId => 1]);
    echo json_encode(['outcome' => 'created', 'id' => (int) $order['id']], JSON_THROW_ON_ERROR);
} catch (DomainException $exception) {
    echo json_encode(['outcome' => 'out_of_stock'], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('[Rice Up] Concurrency test worker failed: ' . $exception->getMessage());
    fwrite(STDERR, 'worker_error');
    exit(1);
}

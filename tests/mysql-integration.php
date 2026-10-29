<?php

if (PHP_SAPI !== 'cli') {
    exit("Run this script from PHP CLI.\n");
}

define('APP_ROOT', dirname(__DIR__));
$GLOBALS['app_config'] = require APP_ROOT . '/app/config.php';
require APP_ROOT . '/app/helpers.php';
require APP_ROOT . '/app/database.php';
require APP_ROOT . '/app/OrderService.php';

$databaseName = (string) config('db_name', '');
if (!str_ends_with($databaseName, '_test')) {
    fwrite(STDERR, "Safety stop: set RICEUP_DB_NAME to a dedicated database ending in _test. No rows were changed.\n");
    exit(2);
}

$pdo = db();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
};
$token = bin2hex(random_bytes(32));
$productId = null;
$orderId = null;

try {
    $insertProduct = $pdo->prepare('INSERT INTO products (slug, name, description, price, stock, image_path, is_active) VALUES (?, ?, ?, ?, 1, ?, 1)');
    $insertProduct->execute(['test-' . bin2hex(random_bytes(8)), 'Test rice bowl', 'Integration test fixture only.', '12345.67', 'assets/images/rice-bowl-lava.svg']);
    $productId = (int) $pdo->lastInsertId();
    $input = [
        'customer_name' => 'Integration Test', 'customer_phone' => '081234567890',
        'fulfillment_method' => 'pickup', 'delivery_address' => null, 'notes' => null,
        'payment_method' => 'simulated_transfer',
    ];

    $order = OrderService::create($pdo, $token, $input, [$productId => 1]);
    $orderId = (int) $order['id'];
    $assert($orderId > 0, 'checkout persists an order');
    $stock = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
    $stock->execute([$productId]);
    $assert((int) $stock->fetchColumn() === 0, 'checkout decrements one unit of stock');

    $replay = OrderService::create($pdo, $token, $input, []);
    $assert((int) $replay['id'] === $orderId && !empty($replay['existing']), 'retry after cart clearing returns the original order');

    $snapshot = $pdo->prepare('SELECT product_name_snapshot, unit_price, line_total FROM order_items WHERE order_id = ?');
    $snapshot->execute([$orderId]);
    $item = $snapshot->fetch();
    $pdo->prepare('UPDATE products SET name = ?, price = ? WHERE id = ?')->execute(['Renamed test bowl', '99000.00', $productId]);
    $snapshot->execute([$orderId]);
    $itemAfterPriceChange = $snapshot->fetch();
    $assert($item['product_name_snapshot'] === $itemAfterPriceChange['product_name_snapshot'] && $item['unit_price'] === $itemAfterPriceChange['unit_price'], 'order item name and price remain a snapshot after product edits');

    try {
        OrderService::create($pdo, bin2hex(random_bytes(32)), $input, [$productId => 1]);
        $assert(false, 'checkout rejects a sold-out product');
    } catch (DomainException $exception) {
        $assert(true, 'checkout rejects a sold-out product');
    }

    OrderService::transition($pdo, $orderId, 'cancelled', null);
    $stock->execute([$productId]);
    $assert((int) $stock->fetchColumn() === 1, 'eligible cancellation restores inventory');
    try {
        OrderService::transition($pdo, $orderId, 'cancelled', null);
    } catch (DomainException $exception) {
        // The second transition must be rejected before any inventory can be released again.
    }
    $stock->execute([$productId]);
    $assert((int) $stock->fetchColumn() === 1, 'a repeated cancellation cannot restore inventory twice');
} finally {
    if ($orderId !== null) {
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
    }
    $pdo->prepare('DELETE FROM checkout_requests WHERE idempotency_key = ?')->execute([hash('sha256', $token)]);
    if ($productId !== null) {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);
    }
}

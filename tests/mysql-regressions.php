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
$expectRejected = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
    } catch (DomainException $exception) {
        $assert(true, $message);
        return;
    }
    $assert(false, $message);
};

$suffix = bin2hex(random_bytes(6));
$rollbackToken = 'rollback-' . $suffix;
$orderToken = 'transition-' . $suffix;
$paidToken = 'paid-' . $suffix;
$periodTokens = ['period-start-' . $suffix, 'period-end-' . $suffix, 'period-before-' . $suffix];
$productIds = [];
$orderIds = [];
$expenseIds = [];
$adminId = null;
$tokens = array_merge([$rollbackToken, $orderToken, $paidToken], $periodTokens);

try {
    $insertProduct = $pdo->prepare('INSERT INTO products (slug, name, description, price, stock, image_path, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
    foreach ([
        ['rollback-a', 'Rollback fixture A', '12345.67', 1],
        ['rollback-b', 'Rollback fixture B', '9000.00', 0],
        ['transition', 'Transition fixture', '12345.67', 5],
    ] as [$slug, $name, $price, $stock]) {
        $insertProduct->execute([$slug . '-' . $suffix, $name, 'Temporary MySQL regression fixture.', $price, $stock, 'assets/images/rice-bowl-lava.svg']);
        $productIds[$slug] = (int) $pdo->lastInsertId();
    }

    $createAdmin = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    $createAdmin->execute(['mysql_regression_' . $suffix, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
    $adminId = (int) $pdo->lastInsertId();

    $input = [
        'customer_name' => 'Synthetic Regression Buyer',
        'customer_phone' => '081234567890',
        'fulfillment_method' => 'pickup',
        'delivery_address' => null,
        'notes' => null,
        'payment_method' => 'simulated_transfer',
        'subtotal' => '0.01',
        'total_amount' => '0.01',
        'unit_price' => '0.01',
    ];

    $beforeOrderCount = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $expectRejected(
        static fn() => OrderService::create($pdo, $rollbackToken, $input, [
            $productIds['rollback-a'] => 1,
            $productIds['rollback-b'] => 1,
        ]),
        'multi-item checkout fails when one item is out of stock'
    );
    $stocks = $pdo->prepare('SELECT id, stock FROM products WHERE id IN (?, ?) ORDER BY id');
    $stocks->execute([$productIds['rollback-a'], $productIds['rollback-b']]);
    $rollbackStocks = array_column($stocks->fetchAll(), 'stock', 'id');
    $assert((int) $rollbackStocks[$productIds['rollback-a']] === 1 && (int) $rollbackStocks[$productIds['rollback-b']] === 0, 'failed checkout rolls back every stock change');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() === $beforeOrderCount, 'failed checkout leaves no partial order');
    $key = $pdo->prepare('SELECT COUNT(*) FROM checkout_requests WHERE idempotency_key = ?');
    $key->execute([hash('sha256', $rollbackToken)]);
    $assert((int) $key->fetchColumn() === 0, 'failed checkout releases its idempotency reservation');

    $order = OrderService::create($pdo, $orderToken, $input, [$productIds['transition'] => 2]);
    $orderIds[] = (int) $order['id'];
    $saved = $pdo->prepare('SELECT subtotal, total_amount FROM orders WHERE id = ?');
    $saved->execute([$order['id']]);
    $orderTotals = $saved->fetch();
    $item = $pdo->prepare('SELECT unit_price, line_total FROM order_items WHERE order_id = ?');
    $item->execute([$order['id']]);
    $line = $item->fetch();
    $assert($orderTotals['subtotal'] === '24691.34' && $orderTotals['total_amount'] === '24691.34'
        && $line['unit_price'] === '12345.67' && $line['line_total'] === '24691.34', 'checkout ignores forged browser totals and uses the database price');

    $expectRejected(static fn() => OrderService::transition($pdo, (int) $order['id'], 'completed', $adminId), 'invalid pending-to-completed order transition is rejected');
    $expectRejected(static fn() => OrderService::payment($pdo, (int) $order['id'], 'paid', null), 'unauthenticated payment confirmation is rejected');
    $state = $pdo->prepare('SELECT order_status, payment_status FROM orders WHERE id = ?');
    $state->execute([$order['id']]);
    $assert($state->fetch() === ['order_status' => 'pending', 'payment_status' => 'unpaid'], 'rejected transitions preserve order and payment state');

    OrderService::payment($pdo, (int) $order['id'], 'awaiting_confirmation', null);
    OrderService::payment($pdo, (int) $order['id'], 'rejected', $adminId);
    $expectRejected(static fn() => OrderService::payment($pdo, (int) $order['id'], 'paid', $adminId), 'rejected payment cannot be confirmed as paid later');
    OrderService::transition($pdo, (int) $order['id'], 'rejected', $adminId);
    $stock = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
    $stock->execute([$productIds['transition']]);
    $assert((int) $stock->fetchColumn() === 5, 'rejected unpaid order restores its reserved stock');
    $expectRejected(static fn() => OrderService::transition($pdo, (int) $order['id'], 'rejected', $adminId), 'final order cannot be rejected a second time');
    $stock->execute([$productIds['transition']]);
    $assert((int) $stock->fetchColumn() === 5, 'repeated final transition cannot restore stock twice');

    $paid = OrderService::create($pdo, $paidToken, $input, [$productIds['transition'] => 1]);
    $orderIds[] = (int) $paid['id'];
    OrderService::payment($pdo, (int) $paid['id'], 'awaiting_confirmation', null);
    OrderService::payment($pdo, (int) $paid['id'], 'paid', $adminId);
    $paidDate = $pdo->prepare('SELECT DATE(payment_confirmed_at) FROM orders WHERE id = ? AND payment_status = \'paid\'');
    $paidDate->execute([$paid['id']]);
    $bucketDate = (string) $paidDate->fetchColumn();
    $daily = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0), COUNT(*) FROM orders
        WHERE payment_status = 'paid' AND payment_confirmed_at >= ? AND payment_confirmed_at < DATE_ADD(?, INTERVAL 1 DAY)");
    $daily->execute([$bucketDate, $bucketDate]);
    [$income, $paidCount] = $daily->fetch(PDO::FETCH_NUM);
    $assert((float) $income >= 24691.34 && (int) $paidCount >= 1, 'daily revenue query includes confirmed payments');
    $expectRejected(static fn() => OrderService::payment($pdo, (int) $paid['id'], 'paid', $adminId), 'a payment already confirmed as paid cannot be confirmed twice');
    $unconfirmed = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE id = ? AND payment_status <> 'paid' AND payment_confirmed_at IS NULL");
    $unconfirmed->execute([$order['id']]);
    $assert((int) $unconfirmed->fetchColumn() === 1, 'unpaid or rejected orders have no confirmed revenue timestamp');

    $boundaryOrders = [];
    foreach ([
        [$periodTokens[0], '2026-10-01 00:00:00'],
        [$periodTokens[1], '2026-10-31 23:59:59'],
        [$periodTokens[2], '2026-09-30 23:59:59'],
    ] as [$periodToken, $confirmedAt]) {
        $periodOrder = OrderService::create($pdo, $periodToken, $input, [$productIds['transition'] => 1]);
        $orderIds[] = (int) $periodOrder['id'];
        OrderService::payment($pdo, (int) $periodOrder['id'], 'awaiting_confirmation', null);
        OrderService::payment($pdo, (int) $periodOrder['id'], 'paid', $adminId);
        $pdo->prepare('UPDATE orders SET payment_confirmed_at = ? WHERE id = ?')->execute([$confirmedAt, $periodOrder['id']]);
        $boundaryOrders[] = (int) $periodOrder['id'];
    }
    $boundaryDay = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(total_amount), 0) FROM orders
        WHERE id = ? AND payment_status = 'paid' AND payment_confirmed_at >= ? AND payment_confirmed_at < DATE_ADD(?, INTERVAL 1 DAY)");
    $boundaryDay->execute([$boundaryOrders[0], '2026-10-01', '2026-10-01']);
    [$startDayCount, $startDayIncome] = $boundaryDay->fetch(PDO::FETCH_NUM);
    $assert((int) $startDayCount === 1 && (float) $startDayIncome === 12345.67, 'daily report includes a confirmed payment at the first instant of its date');
    $boundaryDay->execute([$boundaryOrders[1], '2026-10-31', '2026-10-31']);
    [$endDayCount, $endDayIncome] = $boundaryDay->fetch(PDO::FETCH_NUM);
    $assert((int) $endDayCount === 1 && (float) $endDayIncome === 12345.67, 'daily report includes a confirmed payment at the last second of its date');
    $monthly = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(total_amount), 0) FROM orders
        WHERE id IN (?, ?, ?) AND payment_status = 'paid' AND payment_confirmed_at >= ? AND payment_confirmed_at < DATE_ADD(?, INTERVAL 1 DAY)");
    $monthly->execute([$boundaryOrders[0], $boundaryOrders[1], $boundaryOrders[2], '2026-10-01', '2026-10-31']);
    [$monthCount, $monthIncome] = $monthly->fetch(PDO::FETCH_NUM);
    $assert((int) $monthCount === 2 && (float) $monthIncome === 24691.34, 'monthly report includes both month boundaries and excludes the prior month');

    $insertExpense = $pdo->prepare('INSERT INTO expenses (description, category, amount, expense_date, created_by) VALUES (?, ?, ?, ?, ?)');
    foreach ([
        ['Expense boundary start ' . $suffix, '100.00', '2026-10-01'],
        ['Expense boundary end ' . $suffix, '200.00', '2026-10-31'],
        ['Expense before month ' . $suffix, '400.00', '2026-09-30'],
    ] as [$description, $amount, $expenseDate]) {
        $insertExpense->execute([$description, 'Bahan baku', $amount, $expenseDate, $adminId]);
        $expenseIds[] = (int) $pdo->lastInsertId();
    }
    $expenseDaily = $pdo->prepare('SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM expenses WHERE id IN (?, ?, ?) AND expense_date >= ? AND expense_date <= ?');
    $expenseDaily->execute([$expenseIds[0], $expenseIds[1], $expenseIds[2], '2026-10-01', '2026-10-01']);
    [$expenseStartCount, $expenseStartTotal] = $expenseDaily->fetch(PDO::FETCH_NUM);
    $assert((int) $expenseStartCount === 1 && (float) $expenseStartTotal === 100.0, 'daily expense report includes the selected date');
    $expenseDaily->execute([$expenseIds[0], $expenseIds[1], $expenseIds[2], '2026-10-01', '2026-10-31']);
    [$expenseMonthCount, $expenseMonthTotal] = $expenseDaily->fetch(PDO::FETCH_NUM);
    $assert((int) $expenseMonthCount === 2 && (float) $expenseMonthTotal === 300.0, 'monthly expense report includes both date boundaries and excludes the prior month');
} finally {
    if ($expenseIds !== []) {
        $placeholders = implode(',', array_fill(0, count($expenseIds), '?'));
        $pdo->prepare("DELETE FROM expenses WHERE id IN ({$placeholders})")->execute($expenseIds);
    }
    if ($orderIds !== []) {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $pdo->prepare("DELETE FROM orders WHERE id IN ({$placeholders})")->execute($orderIds);
    }
    $deleteRequests = $pdo->prepare('DELETE FROM checkout_requests WHERE idempotency_key = ?');
    foreach ($tokens as $token) {
        $deleteRequests->execute([hash('sha256', $token)]);
    }
    if ($productIds !== []) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $pdo->prepare("DELETE FROM products WHERE id IN ({$placeholders})")->execute(array_values($productIds));
    }
    if ($adminId !== null) {
        $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$adminId]);
    }
}

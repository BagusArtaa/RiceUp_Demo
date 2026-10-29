<?php

define('APP_ROOT', dirname(__DIR__));
$GLOBALS['app_config'] = [
    'app_name' => 'Rice Up', 'base_url' => '/rice-up/public', 'timezone' => 'Asia/Makassar',
    'app_key' => str_repeat('a', 64), 'delivery_fee' => 5000,
];
require APP_ROOT . '/app/helpers.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

$product = [
    'id' => 1, 'name' => 'Nasi Ayam Lava', 'description' => 'Contoh menu DEMO.',
    'price' => '25000.00', 'stock' => 10, 'image_path' => 'assets/images/rice-bowl-lava.svg', 'is_active' => 1,
];
$order = [
    'id' => 1, 'order_code' => 'RU261029A1B2C3D4', 'customer_name' => 'Demo Customer',
    'customer_phone' => '081234567890', 'fulfillment_method' => 'pickup', 'delivery_address' => null,
    'notes' => null, 'subtotal' => '25000.00', 'delivery_fee' => '0.00', 'total_amount' => '25000.00',
    'order_status' => 'pending', 'payment_method' => 'simulated_transfer', 'payment_status' => 'unpaid',
    'payment_confirmed_at' => null, 'created_at' => '2026-10-29 12:00:00',
];
$item = [
    'id' => 1, 'product_id' => 1, 'product_name_snapshot' => 'Nasi Ayam Lava',
    'unit_price' => '25000.00', 'quantity' => 1, 'line_total' => '25000.00',
    'name' => 'Nasi Ayam Lava', 'description' => 'Contoh menu DEMO.', 'price' => '25000.00',
    'stock' => 10, 'image_path' => 'assets/images/rice-bowl-lava.svg', 'is_active' => 1,
];

$cases = [
    'home' => ['title' => 'Menu', 'products' => [$product]],
    'product' => ['title' => 'Produk', 'product' => $product],
    'cart' => ['title' => 'Keranjang', 'items' => [$item], 'subtotal' => 25000],
    'checkout' => ['title' => 'Checkout', 'items' => [$item], 'subtotal' => 25000, 'checkoutToken' => str_repeat('b', 64), 'old' => []],
    'order-lookup' => ['title' => 'Cari Pesanan'],
    'order-history' => ['title' => 'Riwayat', 'orders' => [[
        'id' => 1, 'order_code' => $order['order_code'], 'fulfillment_method' => 'pickup', 'order_status' => 'pending',
        'payment_status' => 'unpaid', 'total_amount' => '25000.00', 'created_at' => '2026-10-29 12:00:00',
    ]]],
    'order' => ['title' => 'Pesanan', 'order' => $order, 'items' => [$item], 'logs' => [], 'created' => false],
    'admin/login' => ['title' => 'Login', 'admin_area' => true],
    'admin/dashboard' => ['title' => 'Dashboard', 'admin_area' => true, 'stats' => ['orders_today' => 0, 'pending_orders' => 0, 'income_today' => 0, 'expenses_month' => 0], 'orders' => [], 'expenseChart' => []],
    'admin/products' => ['title' => 'Produk', 'admin_area' => true, 'products' => [$product]],
    'admin/product-form' => ['title' => 'Tambah Produk', 'admin_area' => true, 'product' => null],
    'admin/orders' => ['title' => 'Pesanan', 'admin_area' => true, 'orders' => [], 'orderStatuses' => ['pending'], 'paymentStatuses' => ['unpaid'], 'selectedStatus' => '', 'selectedPayment' => ''],
    'admin/order-detail' => ['title' => 'Detail Pesanan', 'admin_area' => true, 'order' => $order, 'items' => [$item], 'logs' => [], 'transitions' => ['confirmed']],
    'admin/expenses' => ['title' => 'Pengeluaran', 'admin_area' => true, 'expenses' => [], 'categories' => ['Bahan baku'], 'today' => '2026-10-29'],
    'admin/reports' => ['title' => 'Laporan', 'admin_area' => true, 'group' => 'day', 'from' => '2026-10-29', 'to' => '2026-10-29', 'incomeTotal' => 0, 'expenseTotal' => 0, 'cashDifference' => 0, 'paidCount' => 0, 'chartRows' => []],
    'not-found' => ['title' => 'Tidak ditemukan'],
    'error' => ['title' => 'Terjadi kesalahan'],
];

foreach ($cases as $view => $data) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'demo-admin';
    ob_start();
    render($view, $data);
    $html = ob_get_clean();
    if (strpos($html, '<!doctype html>') === false || preg_match('/\b(?:Warning|Notice|Fatal error):/i', $html)) {
        fwrite(STDERR, "FAIL: {$view} did not render a clean page.\n");
        exit(1);
    }
    echo "PASS: {$view} view renders cleanly\n";
}

echo count($cases) . " views rendered without PHP warnings.\n";

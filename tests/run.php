<?php

$GLOBALS['app_config'] = [
    'app_name' => 'Rice Up', 'base_url' => '/rice-up/public', 'timezone' => 'Asia/Makassar',
    'app_key' => 'test-key', 'delivery_fee' => 5000,
];
require dirname(__DIR__) . '/app/helpers.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
};

$assert(order_transition_allowed('pending', 'confirmed'), 'pending order can be confirmed');
$assert(order_transition_allowed('confirmed', 'processing'), 'confirmed order can be prepared');
$assert(order_transition_allowed('processing', 'ready'), 'prepared order can become ready');
$assert(order_transition_allowed('ready', 'completed'), 'ready order can be completed');
$assert(!order_transition_allowed('completed', 'cancelled'), 'completed order cannot be cancelled');
$assert(!order_transition_allowed('pending', 'completed'), 'pending order cannot skip required statuses');
$assert(phone_normalize('+62 812-3456-7890') === '081234567890', 'Indonesian phone numbers normalize consistently');
$assert(phone_is_valid('081234567890'), 'valid Indonesian phone is accepted');
$assert(!phone_is_valid('123'), 'short phone number is rejected');
$assert(e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;', 'HTML output is escaped');
$assert(route('admin-login') === '/rice-up/public/index.php?page=admin-login', 'page URLs use the configured app base');
$assert(money(27000) === 'Rp27.000', 'rupiah values are formatted without fractional digits');
$assert(!order_transition_allowed('rejected', 'confirmed'), 'terminal order statuses cannot be reopened');
$assert(decimal_to_cents('27000.5') === 2700050, 'decimal prices convert to integer cents without float arithmetic');
$assert(cents_to_decimal(2900000) === '29000.00', 'integer cents convert to a database decimal string');
$assert(money('12345.67') === 'Rp12.345,67', 'fractional Rupiah amounts display without changing stored value');
$assert(money(-1200) === '-Rp1.200', 'negative cash differences format safely');
$csrf = csrf_token();
$assert((bool) preg_match('/^[a-f0-9]{64}$/', $csrf), 'CSRF token has 256 bits of random hex data');
$assert(csrf_is_valid($csrf) && !csrf_is_valid('incorrect-token'), 'CSRF validation accepts only the current session token');

echo "\n{$checks} checks passed.\n";

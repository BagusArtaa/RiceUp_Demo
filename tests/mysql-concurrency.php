<?php

if (PHP_SAPI !== 'cli') {
    exit("Run this script from PHP CLI.\n");
}

define('APP_ROOT', dirname(__DIR__));
$GLOBALS['app_config'] = require APP_ROOT . '/app/config.php';
require APP_ROOT . '/app/helpers.php';
require APP_ROOT . '/app/database.php';

if (!str_ends_with((string) config('db_name', ''), '_test')) {
    fwrite(STDERR, "Safety stop: set RICEUP_DB_NAME to a dedicated database ending in _test. No rows were changed.\n");
    exit(2);
}

$pdo = db();
$productId = null;
$tokens = [bin2hex(random_bytes(32)), bin2hex(random_bytes(32))];
$barrier = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'riceup_concurrency_' . bin2hex(random_bytes(12));
$processes = [];
$orders = [];
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

try {
    if (!mkdir($barrier, 0700)) {
        throw new RuntimeException('Could not create a temporary concurrency barrier.');
    }
    $insert = $pdo->prepare('INSERT INTO products (slug, name, description, price, stock, image_path, is_active) VALUES (?, ?, ?, ?, 1, ?, 1)');
    $insert->execute(['race-' . bin2hex(random_bytes(8)), 'Race test bowl', 'Temporary concurrency test fixture.', '10000.00', 'assets/images/rice-bowl-lava.svg']);
    $productId = (int) $pdo->lastInsertId();

    foreach ($tokens as $token) {
        $worker = bin2hex(random_bytes(12));
        $command = [PHP_BINARY, APP_ROOT . '/tests/mysql-concurrency-worker.php', (string) $productId, $token, $barrier, $worker];
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, APP_ROOT);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start a PHP concurrency worker.');
        }
        fclose($pipes[0]);
        $processes[] = ['process' => $process, 'pipes' => $pipes, 'worker' => $worker];
    }

    $deadline = microtime(true) + 15;
    foreach ($processes as $entry) {
        while (!is_file($barrier . DIRECTORY_SEPARATOR . $entry['worker'] . '.ready')) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Concurrency workers did not reach the start barrier.');
            }
            usleep(10000);
        }
    }
    file_put_contents($barrier . DIRECTORY_SEPARATOR . 'go', 'go', LOCK_EX);

    $outcomes = [];
    foreach ($processes as $entry) {
        $stdout = stream_get_contents($entry['pipes'][1]);
        $stderr = stream_get_contents($entry['pipes'][2]);
        fclose($entry['pipes'][1]);
        fclose($entry['pipes'][2]);
        $exitCode = proc_close($entry['process']);
        if ($exitCode !== 0) {
            throw new RuntimeException('A concurrency worker failed: ' . trim($stderr));
        }
        $result = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
        $outcomes[] = $result['outcome'] ?? 'unknown';
        if (isset($result['id'])) {
            $orders[] = (int) $result['id'];
        }
    }
    sort($outcomes);
    $assert($outcomes === ['created', 'out_of_stock'], 'two simultaneous checkouts for one portion produce exactly one order');
    $stock = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
    $stock->execute([$productId]);
    $assert((int) $stock->fetchColumn() === 0, 'competing checkout does not make stock negative');
} finally {
    foreach ($processes as $entry) {
        if (is_resource($entry['process'])) {
            foreach ([1, 2] as $pipeIndex) {
                if (is_resource($entry['pipes'][$pipeIndex])) {
                    fclose($entry['pipes'][$pipeIndex]);
                }
            }
            proc_terminate($entry['process']);
            proc_close($entry['process']);
        }
    }
    if ($orders !== []) {
        $placeholders = implode(',', array_fill(0, count($orders), '?'));
        $pdo->prepare("DELETE FROM orders WHERE id IN ({$placeholders})")->execute($orders);
    }
    foreach ($tokens as $token) {
        $pdo->prepare('DELETE FROM checkout_requests WHERE idempotency_key = ?')->execute([hash('sha256', $token)]);
    }
    if ($productId !== null) {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);
    }
    if (is_dir($barrier) && strpos(basename($barrier), 'riceup_concurrency_') === 0) {
        foreach (glob($barrier . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($barrier);
    }
}

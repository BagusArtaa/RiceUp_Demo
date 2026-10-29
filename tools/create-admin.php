<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$username = null;
foreach ($argv as $argument) {
    if (strpos($argument, '--username=') === 0) {
        $username = substr($argument, strlen('--username='));
        break;
    }
}
if (!is_string($username) || !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    fwrite(STDERR, "Username wajib 3-50 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.\n");
    exit(2);
}

$password = fgets(STDIN);
if (!is_string($password)) {
    fwrite(STDERR, "Password tidak diterima dari input terminal.\n");
    exit(2);
}
$password = rtrim($password, "\r\n");
if (strlen($password) < 12 || strlen($password) > 200) {
    fwrite(STDERR, "Password admin harus memiliki 12-200 karakter.\n");
    exit(2);
}

$GLOBALS['app_config'] = require dirname(__DIR__) . '/app/config.php';
require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/database.php';
try {
    $pdo = db();
    $insert = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Admin {$username} berhasil dibuat. Simpan password di tempat aman.\n");
} catch (PDOException $exception) {
    error_log('[Rice Up] Admin CLI setup failed: ' . $exception->getMessage());
    fwrite(STDERR, "Admin tidak dapat dibuat. Pastikan database tersedia dan username belum dipakai.\n");
    exit(1);
} catch (Throwable $exception) {
    error_log('[Rice Up] Admin CLI setup failed: ' . $exception->getMessage());
    fwrite(STDERR, "Admin tidak dapat dibuat. Periksa konfigurasi database lokal.\n");
    exit(1);
}

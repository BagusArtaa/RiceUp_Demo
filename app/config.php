<?php

$defaults = [
    'app_name' => 'Rice Up',
    'app_env' => 'demo',
    'app_key' => null,
    'base_url' => '/rice-up/public',
    'timezone' => 'Asia/Makassar',
    'delivery_fee' => 5000,
    'db_host' => '127.0.0.1',
    'db_name' => 'rice_up',
    'db_user' => null,
    'db_password' => null,
];

$localPath = dirname(__DIR__) . '/config/local.php';
$local = is_file($localPath) ? require $localPath : [];
if (!is_array($local)) {
    $local = [];
}

$environment = [];
foreach (['app_key' => 'RICEUP_APP_KEY', 'base_url' => 'RICEUP_BASE_URL', 'delivery_fee' => 'RICEUP_DELIVERY_FEE',
    'db_host' => 'RICEUP_DB_HOST', 'db_name' => 'RICEUP_DB_NAME', 'db_user' => 'RICEUP_DB_USER',
    'db_password' => 'RICEUP_DB_PASSWORD'] as $key => $variable) {
    $value = getenv($variable);
    if ($value !== false) {
        $environment[$key] = $key === 'delivery_fee' ? (int) $value : $value;
    }
}

return array_merge($defaults, $local, $environment);

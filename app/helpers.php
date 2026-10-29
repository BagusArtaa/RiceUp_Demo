<?php

function app_config(): array
{
    return $GLOBALS['app_config'];
}

function config(string $key, $fallback = null)
{
    $values = app_config();
    return $values[$key] ?? $fallback;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money($amount): string
{
    $value = (string) $amount;
    $negative = str_starts_with($value, '-');
    if ($negative) {
        $value = substr($value, 1);
    }
    try {
        $cents = decimal_to_cents($value);
    } catch (InvalidArgumentException $exception) {
        $cents = decimal_to_cents(number_format(abs((float) $amount), 2, '.', ''));
    }
    $whole = intdiv($cents, 100);
    $fraction = $cents % 100;
    $formatted = 'Rp' . number_format($whole, 0, ',', '.') . ($fraction > 0 ? ',' . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT) : '');
    return $negative ? '-' . $formatted : $formatted;
}

function route(string $page = 'home', array $parameters = []): string
{
    $base = rtrim((string) config('base_url'), '/');
    $query = array_merge(['page' => $page], $parameters);
    return $base . '/index.php?' . http_build_query($query);
}

function asset(string $path): string
{
    return rtrim((string) config('base_url'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $destination): void
{
    header('Location: ' . $destination, true, 303);
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($value) ? $value : null;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_is_valid($provided): bool
{
    return is_string($provided) && hash_equals(csrf_token(), $provided);
}

function require_csrf(): void
{
    $provided = $_POST['_csrf'] ?? '';
    if (!csrf_is_valid($provided)) {
        http_response_code(400);
        exit('Permintaan tidak dapat diverifikasi. Muat ulang halaman lalu coba lagi.');
    }
}

function is_admin(): bool
{
    return isset($_SESSION['admin_id'], $_SESSION['admin_username']);
}

function require_admin(): void
{
    if (!is_admin()) {
        flash('error', 'Silakan masuk sebagai admin terlebih dahulu.');
        redirect(route('admin-login'));
    }
}

function post_string(string $key, int $maxLength = 1000): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

function phone_normalize(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '62')) {
        $digits = '0' . substr($digits, 2);
    }
    return $digits;
}

function phone_is_valid(string $phone): bool
{
    return (bool) preg_match('/^0[0-9]{8,13}$/', phone_normalize($phone));
}

function payment_methods(): array
{
    return [
        'simulated_transfer' => 'Transfer simulasi',
        'cash_on_delivery' => 'Tunai saat diantar',
        'cash_on_pickup' => 'Tunai saat diambil',
    ];
}

function order_status_label(string $status): string
{
    return [
        'pending' => 'Menunggu konfirmasi', 'confirmed' => 'Dikonfirmasi', 'processing' => 'Sedang disiapkan',
        'ready' => 'Siap diambil/diantar', 'completed' => 'Selesai', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan',
    ][$status] ?? $status;
}

function payment_status_label(string $status): string
{
    return [
        'unpaid' => 'Belum dibayar', 'awaiting_confirmation' => 'Menunggu konfirmasi admin',
        'paid' => 'Dikonfirmasi lunas', 'rejected' => 'Pembayaran ditolak',
    ][$status] ?? $status;
}

function client_fingerprint(): string
{
    return hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'local'), (string) config('app_key'));
}

function format_date(string $value, string $format = 'd M Y H:i'): string
{
    try {
        return (new DateTimeImmutable($value, new DateTimeZone((string) config('timezone'))))->format($format);
    } catch (Throwable $exception) {
        return $value;
    }
}

function valid_date_range(): array
{
    $today = (new DateTimeImmutable('today', new DateTimeZone((string) config('timezone'))))->format('Y-m-d');
    $from = is_string($_GET['from'] ?? null) ? $_GET['from'] : $today;
    $to = is_string($_GET['to'] ?? null) ? $_GET['to'] : $today;
    foreach ([$from, $to] as $date) {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            return [$today, $today];
        }
    }
    if ($from > $to) {
        return [$to, $from];
    }
    return [$from, $to];
}

function order_transition_allowed(string $from, string $to): bool
{
    $transitions = [
        'pending' => ['confirmed', 'rejected', 'cancelled'],
        'confirmed' => ['processing', 'rejected', 'cancelled'],
        'processing' => ['ready'],
        'ready' => ['completed'],
        'completed' => [], 'rejected' => [], 'cancelled' => [],
    ];
    return in_array($to, $transitions[$from] ?? [], true);
}

function page_title(string $title): string
{
    return $title . ' | ' . config('app_name', 'Rice Up');
}

function decimal_to_cents($amount): int
{
    $value = (string) $amount;
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
        throw new InvalidArgumentException('Invalid decimal amount.');
    }
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
}

function cents_to_decimal(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/app/views/' . $view . '.php';
    $content = ob_get_clean();
    $title = $title ?? 'Rice Up';
    $admin_area = $admin_area ?? false;
    require APP_ROOT . '/app/views/layout.php';
}

<?php

require_once APP_ROOT . '/app/OrderService.php';

function cart_product_rows(PDO $pdo): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart) || $cart === []) {
        return [];
    }
    $ids = [];
    foreach ($cart as $id => $quantity) {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
            $ids[(int) $id] = max(0, (int) $quantity);
        }
    }
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = $pdo->prepare("SELECT id, name, description, price, stock, image_path, is_active FROM products WHERE id IN ({$placeholders})");
    $query->execute(array_keys($ids));
    $rows = [];
    foreach ($query->fetchAll() as $product) {
        $product['quantity'] = $ids[(int) $product['id']];
        $rows[] = $product;
    }
    return $rows;
}

function cart_token_rotate(): void
{
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}

function accessible_order(PDO $pdo, int $orderId): ?array
{
    $authorizedBySession = !empty($_SESSION['accessible_orders'][$orderId]);
    $verifiedPhone = $_SESSION['verified_phone'] ?? null;
    if (!$authorizedBySession && !is_string($verifiedPhone)) {
        return null;
    }
    if (is_string($verifiedPhone)) {
        $query = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND customer_phone = ?');
        $query->execute([$orderId, $verifiedPhone]);
    } else {
        $query = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $query->execute([$orderId]);
    }
    $order = $query->fetch();
    return $order ?: null;
}

function handle_customer_post(PDO $pdo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $page = is_string($_GET['page'] ?? null) ? $_GET['page'] : '';
    if (!in_array($page, ['cart-add', 'cart-update', 'cart-remove', 'checkout-submit', 'order-lookup', 'order-cancel', 'order-payment'], true)) {
        return;
    }
    require_csrf();
    $returnPage = 'cart';

    try {
        if ($page === 'cart-add') {
            $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
            if ($id === false || $quantity === false) {
                throw new DomainException('Produk atau jumlah tidak valid.');
            }
            $query = $pdo->prepare('SELECT stock, is_active FROM products WHERE id = ?');
            $query->execute([$id]);
            $product = $query->fetch();
            if (!$product || (int) $product['is_active'] !== 1 || (int) $product['stock'] < 1) {
                throw new DomainException('Produk tersebut sedang tidak tersedia.');
            }
            $current = (int) ($_SESSION['cart'][$id] ?? 0);
            if ($current + $quantity > min(50, (int) $product['stock'])) {
                throw new DomainException('Jumlah di keranjang melebihi stok yang tersedia.');
            }
            $_SESSION['cart'][$id] = $current + $quantity;
            cart_token_rotate();
            flash('success', 'Menu berhasil ditambahkan ke keranjang.');
            redirect(route('cart'));
        }

        if ($page === 'cart-update' || $page === 'cart-remove') {
            $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || !isset($_SESSION['cart'][$id])) {
                throw new DomainException('Item keranjang tidak ditemukan.');
            }
            if ($page === 'cart-remove') {
                unset($_SESSION['cart'][$id]);
            } else {
                $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 50]]);
                if ($quantity === false) {
                    throw new DomainException('Jumlah harus antara 0 dan 50.');
                }
                if ($quantity === 0) {
                    unset($_SESSION['cart'][$id]);
                } else {
                    $query = $pdo->prepare('SELECT stock, is_active FROM products WHERE id = ?');
                    $query->execute([$id]);
                    $product = $query->fetch();
                    if (!$product || (int) $product['is_active'] !== 1 || (int) $product['stock'] < $quantity) {
                        throw new DomainException('Jumlah melebihi stok terkini atau produk sudah tidak aktif.');
                    }
                    $_SESSION['cart'][$id] = $quantity;
                }
            }
            cart_token_rotate();
            flash('success', 'Keranjang berhasil diperbarui.');
            redirect(route('cart'));
        }

        if ($page === 'checkout-submit') {
            $returnPage = 'checkout';
            $token = $_POST['checkout_token'] ?? '';
            if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)
                || !hash_equals((string) ($_SESSION['checkout_token'] ?? ''), $token)) {
                throw new DomainException('Sesi checkout berubah. Muat ulang checkout lalu coba lagi.');
            }
            $name = post_string('customer_name', 120);
            $phone = phone_normalize(post_string('customer_phone', 30));
            $fulfillment = post_string('fulfillment_method', 20);
            $address = post_string('delivery_address', 500);
            $paymentMethod = post_string('payment_method', 40);
            $notes = post_string('notes', 500);
            if (mb_strlen($name, 'UTF-8') < 2) {
                throw new DomainException('Nama pemesan minimal 2 karakter.');
            }
            if (!phone_is_valid($phone)) {
                throw new DomainException('Masukkan nomor telepon Indonesia yang valid.');
            }
            if (!in_array($fulfillment, ['delivery', 'pickup'], true)) {
                throw new DomainException('Pilih delivery atau ambil sendiri.');
            }
            if ($fulfillment === 'delivery' && mb_strlen($address, 'UTF-8') < 8) {
                throw new DomainException('Alamat delivery harus diisi dengan lengkap.');
            }
            if (!array_key_exists($paymentMethod, payment_methods())) {
                throw new DomainException('Pilih metode pembayaran simulasi.');
            }

            $order = OrderService::create($pdo, $token, [
                'customer_name' => $name,
                'customer_phone' => $phone,
                'fulfillment_method' => $fulfillment,
                'delivery_address' => $fulfillment === 'delivery' ? $address : null,
                'notes' => $notes !== '' ? $notes : null,
                'payment_method' => $paymentMethod,
            ], $_SESSION['cart'] ?? []);
            $_SESSION['cart'] = [];
            if (($_SESSION['verified_phone'] ?? null) !== $order['customer_phone']) {
                unset($_SESSION['verified_phone']);
                $_SESSION['accessible_orders'] = [];
            }
            $_SESSION['accessible_orders'][(int) $order['id']] = time();
            $_SESSION['created_orders'][(int) $order['id']] = true;
            flash('success', $order['existing'] ? 'Checkout ini sudah pernah diproses; kode pesanan lama ditampilkan.' : 'Pesanan berhasil dibuat.');
            redirect(route('order', ['id' => (int) $order['id']]));
        }

        if ($page === 'order-lookup') {
            $returnPage = 'order-lookup';
            $fingerprint = client_fingerprint();
            $pdo->prepare('DELETE FROM order_lookup_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)')->execute();
            $count = $pdo->prepare('SELECT COUNT(*) FROM order_lookup_attempts WHERE fingerprint = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
            $count->execute([$fingerprint]);
            if ((int) $count->fetchColumn() >= 10) {
                throw new DomainException('Terlalu banyak percobaan. Tunggu 15 menit lalu coba lagi.');
            }
            $pdo->prepare('INSERT INTO order_lookup_attempts (fingerprint) VALUES (?)')->execute([$fingerprint]);
            $code = strtoupper(post_string('order_code', 30));
            $phone = phone_normalize(post_string('customer_phone', 30));
            if (!preg_match('/^RU[0-9A-Z]{8,18}$/', $code) || !phone_is_valid($phone)) {
                throw new DomainException('Kode pesanan atau nomor telepon tidak cocok. Periksa kembali datanya.');
            }
            $query = $pdo->prepare('SELECT id FROM orders WHERE order_code = ? AND customer_phone = ?');
            $query->execute([$code, $phone]);
            $orderId = $query->fetchColumn();
            if (!$orderId) {
                throw new DomainException('Kode pesanan atau nomor telepon tidak cocok. Periksa kembali datanya.');
            }
            if (($_SESSION['verified_phone'] ?? null) !== $phone) {
                $_SESSION['accessible_orders'] = [];
            }
            $_SESSION['verified_phone'] = $phone;
            $history = $pdo->prepare('SELECT id FROM orders WHERE customer_phone = ? ORDER BY created_at DESC LIMIT 50');
            $history->execute([$phone]);
            foreach ($history->fetchAll(PDO::FETCH_COLUMN) as $historyOrderId) {
                $_SESSION['accessible_orders'][(int) $historyOrderId] = time();
            }
            redirect(route('order', ['id' => (int) $orderId]));
        }

        if ($page === 'order-cancel' || $page === 'order-payment') {
            $returnPage = 'order-lookup';
            $id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || accessible_order($pdo, (int) $id) === null) {
                throw new DomainException('Pesanan tidak ditemukan. Cari pesanan dengan kode dan nomor telepon.');
            }
            if ($page === 'order-cancel') {
                OrderService::transition($pdo, (int) $id, 'cancelled', null);
                flash('success', 'Pesanan dibatalkan dan stok dikembalikan.');
            } else {
                OrderService::payment($pdo, (int) $id, 'awaiting_confirmation', null);
                flash('success', 'Permintaan konfirmasi pembayaran simulasi dikirim.');
            }
            redirect(route('order', ['id' => (int) $id]));
        }

        throw new DomainException('Aksi tidak dikenali.');
    } catch (DomainException $exception) {
        if ($page === 'checkout-submit') {
            $_SESSION['_checkout_old'] = [
                'customer_name' => post_string('customer_name', 120),
                'customer_phone' => post_string('customer_phone', 30),
                'fulfillment_method' => post_string('fulfillment_method', 20),
                'delivery_address' => post_string('delivery_address', 500),
                'payment_method' => post_string('payment_method', 40),
                'notes' => post_string('notes', 500),
            ];
        }
        flash('error', $exception->getMessage());
        redirect(route($returnPage));
    } catch (Throwable $exception) {
        error_log('[Rice Up] Customer request failed: ' . $exception->getMessage());
        flash('error', 'Permintaan belum dapat diproses. Periksa kembali data lalu coba lagi.');
        redirect(route($returnPage));
    }
}

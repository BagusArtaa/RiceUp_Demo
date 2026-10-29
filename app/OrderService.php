<?php

final class OrderService
{
    public static function create(PDO $pdo, string $idempotencyToken, array $input, array $cart): array
    {
        $idempotencyKey = hash('sha256', $idempotencyToken);
        $existing = self::existingOrder($pdo, $idempotencyKey);
        if ($existing !== null) {
            return $existing;
        }

        try {
            $pdo->beginTransaction();
            $reserve = $pdo->prepare('INSERT INTO checkout_requests (idempotency_key) VALUES (?)');
            $reserve->execute([$idempotencyKey]);

            $productIds = array_keys($cart);
            sort($productIds, SORT_NUMERIC);
            $productQuery = $pdo->prepare('SELECT id, name, price, stock, is_active FROM products WHERE id = ? FOR UPDATE');
            $products = [];
            foreach ($productIds as $productId) {
                $productQuery->execute([(int) $productId]);
                $product = $productQuery->fetch();
                if (!$product || (int) $product['is_active'] !== 1) {
                    throw new DomainException('Salah satu produk sudah tidak tersedia. Periksa kembali keranjang Anda.');
                }
                $quantity = filter_var($cart[$productId], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
                if ($quantity === false) {
                    throw new DomainException('Jumlah produk harus antara 1 dan 50.');
                }
                if ((int) $product['stock'] < $quantity) {
                    throw new DomainException('Stok ' . $product['name'] . ' tidak mencukupi. Perbarui jumlah di keranjang.');
                }
                $products[] = [$product, $quantity];
            }
            if ($products === []) {
                throw new DomainException('Keranjang Anda masih kosong.');
            }

            $subtotalCents = 0;
            foreach ($products as [$product, $quantity]) {
                $subtotalCents += decimal_to_cents($product['price']) * $quantity;
            }
            $deliveryCents = $input['fulfillment_method'] === 'delivery' ? max(0, (int) config('delivery_fee', 5000)) * 100 : 0;
            $totalCents = $subtotalCents + $deliveryCents;
            $orderCode = 'RU' . date('ymd') . strtoupper(bin2hex(random_bytes(4)));

            $insertOrder = $pdo->prepare('INSERT INTO orders
                (order_code, customer_name, customer_phone, fulfillment_method, delivery_address, notes,
                 subtotal, delivery_fee, total_amount, order_status, payment_method, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?, \'unpaid\')');
            $insertOrder->execute([
                $orderCode, $input['customer_name'], $input['customer_phone'], $input['fulfillment_method'],
                $input['delivery_address'], $input['notes'], cents_to_decimal($subtotalCents),
                cents_to_decimal($deliveryCents), cents_to_decimal($totalCents), $input['payment_method'],
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $insertItem = $pdo->prepare('INSERT INTO order_items
                (order_id, product_id, product_name_snapshot, unit_price, quantity, line_total)
                VALUES (?, ?, ?, ?, ?, ?)');
            $decrementStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ? AND is_active = 1');
            foreach ($products as [$product, $quantity]) {
                $unitCents = decimal_to_cents($product['price']);
                $insertItem->execute([
                    $orderId, (int) $product['id'], $product['name'], cents_to_decimal($unitCents),
                    $quantity, cents_to_decimal($unitCents * $quantity),
                ]);
                $decrementStock->execute([$quantity, (int) $product['id'], $quantity]);
                if ($decrementStock->rowCount() !== 1) {
                    throw new DomainException('Stok baru saja berubah. Perbarui keranjang lalu coba lagi.');
                }
            }

            $log = $pdo->prepare("INSERT INTO order_status_logs (order_id, event_type, old_status, new_status, note)
                VALUES (?, 'order', NULL, 'pending', 'Pesanan dibuat oleh pembeli.')");
            $log->execute([$orderId]);
            $linkRequest = $pdo->prepare('UPDATE checkout_requests SET order_id = ? WHERE idempotency_key = ?');
            $linkRequest->execute([$orderId, $idempotencyKey]);
            $pdo->commit();

            return ['id' => $orderId, 'order_code' => $orderCode, 'customer_phone' => $input['customer_phone'], 'existing' => false];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $existing = self::existingOrder($pdo, $idempotencyKey);
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $exception;
        }
    }

    public static function existingOrder(PDO $pdo, string $idempotencyKey): ?array
    {
        $query = $pdo->prepare('SELECT o.id, o.order_code, o.customer_phone FROM checkout_requests c
            INNER JOIN orders o ON o.id = c.order_id WHERE c.idempotency_key = ?');
        $query->execute([$idempotencyKey]);
        $order = $query->fetch();
        if (!$order) {
            return null;
        }
        $order['existing'] = true;
        return $order;
    }

    public static function transition(PDO $pdo, int $orderId, string $target, ?int $adminId, ?string $note = null): void
    {
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare('SELECT order_status, payment_status, stock_released_at FROM orders WHERE id = ? FOR UPDATE');
            $query->execute([$orderId]);
            $order = $query->fetch();
            if (!$order) {
                throw new DomainException('Pesanan tidak ditemukan.');
            }
            $current = (string) $order['order_status'];
            $payment = (string) $order['payment_status'];
            if (!order_transition_allowed($current, $target)) {
                throw new DomainException('Perubahan status pesanan tersebut tidak diizinkan.');
            }
            if ($adminId === null && ($current !== 'pending' || $target !== 'cancelled')) {
                throw new DomainException('Pembeli hanya dapat membatalkan pesanan yang masih menunggu.');
            }
            if (in_array($target, ['cancelled', 'rejected'], true) && !in_array($payment, ['unpaid', 'rejected'], true)) {
                throw new DomainException('Pesanan menunggu konfirmasi pembayaran atau sudah dibayar. Pembatalan/refund tidak tersedia.');
            }
            if ($target === 'completed' && $payment !== 'paid') {
                throw new DomainException('Pembayaran harus dikonfirmasi sebelum pesanan ditandai selesai.');
            }

            $update = $pdo->prepare('UPDATE orders SET order_status = ? WHERE id = ?');
            $update->execute([$target, $orderId]);
            if (in_array($target, ['cancelled', 'rejected'], true) && $order['stock_released_at'] === null) {
                $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
                $items->execute([$orderId]);
                $restore = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                foreach ($items->fetchAll() as $item) {
                    if ($item['product_id'] !== null) {
                        $restore->execute([(int) $item['quantity'], (int) $item['product_id']]);
                    }
                }
                $pdo->prepare('UPDATE orders SET stock_released_at = NOW() WHERE id = ?')->execute([$orderId]);
            }
            $log = $pdo->prepare("INSERT INTO order_status_logs (order_id, event_type, old_status, new_status, changed_by, note)
                VALUES (?, 'order', ?, ?, ?, ?)");
            $log->execute([$orderId, $current, $target, $adminId, $note]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function payment(PDO $pdo, int $orderId, string $target, ?int $adminId): void
    {
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare('SELECT order_status, payment_method, payment_status FROM orders WHERE id = ? FOR UPDATE');
            $query->execute([$orderId]);
            $order = $query->fetch();
            if (!$order) {
                throw new DomainException('Pesanan tidak ditemukan.');
            }
            $current = (string) $order['payment_status'];
            $orderStatus = (string) $order['order_status'];
            $allowed = false;
            if ($adminId === null && $target === 'awaiting_confirmation') {
                $allowed = $order['payment_method'] === 'simulated_transfer'
                    && in_array($current, ['unpaid', 'rejected'], true)
                    && !in_array($orderStatus, ['rejected', 'cancelled'], true);
            } elseif ($adminId !== null && $target === 'paid') {
                $allowed = in_array($current, ['unpaid', 'awaiting_confirmation'], true)
                    && !in_array($orderStatus, ['rejected', 'cancelled'], true);
            } elseif ($adminId !== null && $target === 'rejected') {
                $allowed = $current === 'awaiting_confirmation'
                    && !in_array($orderStatus, ['rejected', 'cancelled'], true);
            }
            if (!$allowed) {
                throw new DomainException('Perubahan status pembayaran tersebut tidak diizinkan.');
            }

            if ($target === 'paid') {
                $update = $pdo->prepare('UPDATE orders SET payment_status = ?, payment_confirmed_at = NOW() WHERE id = ?');
                $update->execute([$target, $orderId]);
            } else {
                $update = $pdo->prepare('UPDATE orders SET payment_status = ?, payment_confirmed_at = NULL WHERE id = ?');
                $update->execute([$target, $orderId]);
            }
            $log = $pdo->prepare("INSERT INTO order_status_logs (order_id, event_type, old_status, new_status, changed_by, note)
                VALUES (?, 'payment', ?, ?, ?, ?)");
            $note = $target === 'awaiting_confirmation' ? 'Pembeli menandai transfer simulasi sudah dikirim.' : null;
            $log->execute([$orderId, $current, $target, $adminId, $note]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

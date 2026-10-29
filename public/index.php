<?php

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';
require APP_ROOT . '/app/CustomerController.php';
require APP_ROOT . '/app/AdminController.php';

try {
    $pdo = db();
} catch (RuntimeException $exception) {
    http_response_code(503);
    $title = e(page_title('Database belum siap'));
    $styles = e(asset('assets/css/style.css'));
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . '</title><link rel="stylesheet" href="' . $styles . '"></head><body><main class="db-error-page"><img src="' . e(asset('assets/images/mark.svg')) . '" width="56" height="56" alt="Rice Up"><h1>Rice Up sedang menyiapkan dapur.</h1><p>Database belum tersambung. Pastikan MySQL aktif di XAMPP, impor <code>database/rice_up.sql</code>, lalu sesuaikan <code>config/local.php</code>.</p></main></body></html>';
    exit;
}

handle_customer_post($pdo);
handle_admin_post($pdo);
$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home';

try {
    switch ($page) {
        case 'home':
        case 'products':
            $products = $pdo->query('SELECT id, name, description, price, stock, image_path FROM products WHERE is_active = 1 ORDER BY id')->fetchAll();
            render('home', ['title' => 'Menu Rice Bowl', 'products' => $products]);
            break;

        case 'product':
            $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $query = $pdo->prepare('SELECT id, name, description, price, stock, image_path FROM products WHERE id = ? AND is_active = 1');
            $query->execute([$id === false ? 0 : $id]);
            $product = $query->fetch();
            if (!$product) {
                http_response_code(404);
                render('not-found', ['title' => 'Menu tidak ditemukan']);
                break;
            }
            render('product', ['title' => $product['name'], 'product' => $product]);
            break;

        case 'cart':
            $items = cart_product_rows($pdo);
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (float) $item['price'] * (int) $item['quantity'];
            }
            render('cart', ['title' => 'Keranjang', 'items' => $items, 'subtotal' => $subtotal]);
            break;

        case 'checkout':
            $items = cart_product_rows($pdo);
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (float) $item['price'] * (int) $item['quantity'];
            }
            if (empty($_SESSION['checkout_token'])) {
                cart_token_rotate();
            }
            $old = $_SESSION['_checkout_old'] ?? [];
            unset($_SESSION['_checkout_old']);
            render('checkout', [
                'title' => 'Checkout', 'items' => $items, 'subtotal' => $subtotal,
                'checkoutToken' => $_SESSION['checkout_token'], 'old' => is_array($old) ? $old : [],
            ]);
            break;

        case 'order-lookup':
            render('order-lookup', ['title' => 'Cek Pesanan']);
            break;

        case 'order-history':
            $verifiedPhone = $_SESSION['verified_phone'] ?? null;
            if (!is_string($verifiedPhone) || !phone_is_valid($verifiedPhone)) {
                flash('error', 'Verifikasi kode pesanan dan nomor telepon untuk membuka riwayat.');
                redirect(route('order-lookup'));
            }
            $history = $pdo->prepare('SELECT id, order_code, fulfillment_method, order_status, payment_status, total_amount, created_at FROM orders WHERE customer_phone = ? ORDER BY created_at DESC LIMIT 50');
            $history->execute([$verifiedPhone]);
            render('order-history', ['title' => 'Riwayat Pesanan', 'orders' => $history->fetchAll()]);
            break;

        case 'order':
            $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $order = $id === false ? null : accessible_order($pdo, (int) $id);
            if (!$order) {
                flash('error', 'Untuk membuka status pesanan, masukkan kode dan nomor telepon.');
                redirect(route('order-lookup'));
            }
            $itemQuery = $pdo->prepare('SELECT product_name_snapshot, unit_price, quantity, line_total FROM order_items WHERE order_id = ? ORDER BY id');
            $itemQuery->execute([(int) $order['id']]);
            $logQuery = $pdo->prepare('SELECT event_type, new_status, note, created_at FROM order_status_logs WHERE order_id = ? ORDER BY id DESC LIMIT 20');
            $logQuery->execute([(int) $order['id']]);
            $created = !empty($_SESSION['created_orders'][(int) $order['id']]);
            unset($_SESSION['created_orders'][(int) $order['id']]);
            render('order', [
                'title' => 'Status ' . $order['order_code'], 'order' => $order,
                'items' => $itemQuery->fetchAll(), 'logs' => $logQuery->fetchAll(), 'created' => $created,
            ]);
            break;

        case 'admin-login':
            if (is_admin()) {
                redirect(route('admin'));
            }
            render('admin/login', ['title' => 'Login Admin', 'admin_area' => true]);
            break;

        case 'admin':
            require_admin();
            $today = (new DateTimeImmutable('today'))->format('Y-m-d');
            $tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
            $monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
            $stats = [];
            $query = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at < ?');
            $query->execute([$today, $tomorrow]);
            $stats['orders_today'] = (int) $query->fetchColumn();
            $stats['pending_orders'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('pending','confirmed','processing','ready')")->fetchColumn();
            $query = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status = 'paid' AND payment_confirmed_at >= ? AND payment_confirmed_at < ?");
            $query->execute([$today, $tomorrow]);
            $stats['income_today'] = $query->fetchColumn();
            $query = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date >= ? AND expense_date < ?');
            $query->execute([$monthStart, $tomorrow]);
            $stats['expenses_month'] = $query->fetchColumn();

            $orders = $pdo->query('SELECT id, order_code, customer_name, total_amount, order_status, created_at FROM orders ORDER BY created_at DESC LIMIT 8')->fetchAll();
            $start = new DateTimeImmutable('today -6 days');
            $chartStart = $start->format('Y-m-d');
            $expenseQuery = $pdo->prepare('SELECT expense_date, SUM(amount) AS amount FROM expenses WHERE expense_date >= ? AND expense_date <= ? GROUP BY expense_date ORDER BY expense_date');
            $expenseQuery->execute([$chartStart, $today]);
            $expenseValues = [];
            foreach ($expenseQuery->fetchAll() as $row) {
                $expenseValues[$row['expense_date']] = (float) $row['amount'];
            }
            $maxExpense = max([1, ...array_values($expenseValues)]);
            $expenseChart = [];
            $weekdayLabels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
            for ($day = 0; $day < 7; $day++) {
                $date = $start->modify('+' . $day . ' days');
                $key = $date->format('Y-m-d');
                $amount = $expenseValues[$key] ?? 0;
                $expenseChart[] = ['label' => $weekdayLabels[(int) $date->format('w')] . ' ' . $date->format('d'), 'amount' => $amount, 'width' => $amount > 0 ? max(3, $amount / $maxExpense * 100) : 0];
            }
            render('admin/dashboard', ['title' => 'Dashboard', 'admin_area' => true, 'stats' => $stats, 'orders' => $orders, 'expenseChart' => $expenseChart]);
            break;

        case 'admin-products':
            require_admin();
            $products = $pdo->query('SELECT id, name, description, price, stock, image_path, is_active FROM products ORDER BY is_active DESC, name')->fetchAll();
            render('admin/products', ['title' => 'Produk', 'admin_area' => true, 'products' => $products]);
            break;

        case 'admin-product-form':
            require_admin();
            $id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $product = null;
            if ($id !== false && (int) $id > 0) {
                $query = $pdo->prepare('SELECT id, name, description, price, stock, image_path, is_active FROM products WHERE id = ?');
                $query->execute([(int) $id]);
                $product = $query->fetch();
                if (!$product) {
                    flash('error', 'Produk tidak ditemukan.');
                    redirect(route('admin-products'));
                }
            }
            render('admin/product-form', ['title' => $product ? 'Ubah Produk' : 'Tambah Produk', 'admin_area' => true, 'product' => $product]);
            break;

        case 'admin-orders':
            require_admin();
            $orderStatuses = ['pending', 'confirmed', 'processing', 'ready', 'completed', 'rejected', 'cancelled'];
            $paymentStatuses = ['unpaid', 'awaiting_confirmation', 'paid', 'rejected'];
            $selectedStatus = is_string($_GET['status'] ?? null) && in_array($_GET['status'], $orderStatuses, true) ? $_GET['status'] : '';
            $selectedPayment = is_string($_GET['payment'] ?? null) && in_array($_GET['payment'], $paymentStatuses, true) ? $_GET['payment'] : '';
            $where = [];
            $parameters = [];
            if ($selectedStatus !== '') {
                $where[] = 'order_status = ?';
                $parameters[] = $selectedStatus;
            }
            if ($selectedPayment !== '') {
                $where[] = 'payment_status = ?';
                $parameters[] = $selectedPayment;
            }
            $sql = 'SELECT id, order_code, customer_name, customer_phone, fulfillment_method, order_status, payment_status, total_amount, created_at FROM orders';
            if ($where !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY created_at DESC LIMIT 100';
            $query = $pdo->prepare($sql);
            $query->execute($parameters);
            render('admin/orders', ['title' => 'Pesanan', 'admin_area' => true, 'orders' => $query->fetchAll(), 'orderStatuses' => $orderStatuses, 'paymentStatuses' => $paymentStatuses, 'selectedStatus' => $selectedStatus, 'selectedPayment' => $selectedPayment]);
            break;

        case 'admin-order':
            require_admin();
            $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $query = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
            $query->execute([$id === false ? 0 : $id]);
            $order = $query->fetch();
            if (!$order) {
                flash('error', 'Pesanan tidak ditemukan.');
                redirect(route('admin-orders'));
            }
            $items = $pdo->prepare('SELECT product_name_snapshot, unit_price, quantity, line_total FROM order_items WHERE order_id = ? ORDER BY id');
            $items->execute([(int) $order['id']]);
            $logs = $pdo->prepare('SELECT l.event_type, l.old_status, l.new_status, l.note, l.created_at, a.username AS actor FROM order_status_logs l LEFT JOIN admins a ON a.id = l.changed_by WHERE l.order_id = ? ORDER BY l.id DESC');
            $logs->execute([(int) $order['id']]);
            $possible = ['confirmed', 'processing', 'ready', 'completed', 'rejected', 'cancelled'];
            $transitions = array_values(array_filter($possible, static fn($status) => order_transition_allowed($order['order_status'], $status)));
            render('admin/order-detail', ['title' => 'Pesanan ' . $order['order_code'], 'admin_area' => true, 'order' => $order, 'items' => $items->fetchAll(), 'logs' => $logs->fetchAll(), 'transitions' => $transitions]);
            break;

        case 'admin-expenses':
            require_admin();
            $expenses = $pdo->query('SELECT id, description, category, amount, expense_date, created_at FROM expenses ORDER BY expense_date DESC, id DESC LIMIT 60')->fetchAll();
            $today = (new DateTimeImmutable('today'))->format('Y-m-d');
            render('admin/expenses', ['title' => 'Pengeluaran', 'admin_area' => true, 'expenses' => $expenses, 'categories' => admin_categories(), 'today' => $today]);
            break;

        case 'admin-reports':
            require_admin();
            $group = is_string($_GET['group'] ?? null) && in_array($_GET['group'], ['day', 'month'], true) ? $_GET['group'] : 'day';
            $todayObject = new DateTimeImmutable('today');
            if (!isset($_GET['from'], $_GET['to'])) {
                $_GET['from'] = $group === 'day' ? $todayObject->modify('-6 days')->format('Y-m-d') : $todayObject->modify('first day of this month')->modify('-11 months')->format('Y-m-d');
                $_GET['to'] = $todayObject->format('Y-m-d');
            }
            [$from, $to] = valid_date_range();
            $dateExpression = $group === 'month' ? "DATE_FORMAT(payment_confirmed_at, '%Y-%m')" : "DATE_FORMAT(payment_confirmed_at, '%Y-%m-%d')";
            $expenseExpression = $group === 'month' ? "DATE_FORMAT(expense_date, '%Y-%m')" : "DATE_FORMAT(expense_date, '%Y-%m-%d')";
            $incomeQuery = $pdo->prepare("SELECT {$dateExpression} AS bucket, SUM(total_amount) AS amount FROM orders WHERE payment_status = 'paid' AND payment_confirmed_at >= ? AND payment_confirmed_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY bucket ORDER BY bucket");
            $incomeQuery->execute([$from, $to]);
            $expenseQuery = $pdo->prepare("SELECT {$expenseExpression} AS bucket, SUM(amount) AS amount FROM expenses WHERE expense_date >= ? AND expense_date <= ? GROUP BY bucket ORDER BY bucket");
            $expenseQuery->execute([$from, $to]);
            $incomeValues = [];
            $expenseValues = [];
            $incomeTotal = 0.0;
            $expenseTotal = 0.0;
            foreach ($incomeQuery->fetchAll() as $row) {
                $incomeValues[$row['bucket']] = (float) $row['amount'];
                $incomeTotal += (float) $row['amount'];
            }
            foreach ($expenseQuery->fetchAll() as $row) {
                $expenseValues[$row['bucket']] = (float) $row['amount'];
                $expenseTotal += (float) $row['amount'];
            }
            $countQuery = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid' AND payment_confirmed_at >= ? AND payment_confirmed_at < DATE_ADD(?, INTERVAL 1 DAY)");
            $countQuery->execute([$from, $to]);
            $paidCount = (int) $countQuery->fetchColumn();
            $buckets = array_unique(array_merge(array_keys($incomeValues), array_keys($expenseValues)));
            sort($buckets);
            $maxValue = max(1, ...array_merge(array_values($incomeValues), array_values($expenseValues)));
            $chartRows = [];
            foreach ($buckets as $bucket) {
                $income = $incomeValues[$bucket] ?? 0;
                $expense = $expenseValues[$bucket] ?? 0;
                $date = DateTimeImmutable::createFromFormat($group === 'month' ? '!Y-m' : '!Y-m-d', $bucket);
                $chartRows[] = [
                    'label' => $date ? $date->format($group === 'month' ? 'M Y' : 'd M') : $bucket,
                    'income' => $income, 'expense' => $expense,
                    'income_width' => $income > 0 ? max(2, $income / $maxValue * 100) : 0,
                    'expense_width' => $expense > 0 ? max(2, $expense / $maxValue * 100) : 0,
                ];
            }
            render('admin/reports', ['title' => 'Laporan', 'admin_area' => true, 'group' => $group, 'from' => $from, 'to' => $to, 'incomeTotal' => $incomeTotal, 'expenseTotal' => $expenseTotal, 'cashDifference' => $incomeTotal - $expenseTotal, 'paidCount' => $paidCount, 'chartRows' => $chartRows]);
            break;

        case 'not-found':
            http_response_code(404);
            render('not-found', ['title' => 'Halaman tidak ditemukan']);
            break;

        default:
            http_response_code(404);
            render('not-found', ['title' => 'Halaman tidak ditemukan']);
            break;
    }
} catch (Throwable $exception) {
    error_log('[Rice Up] Page rendering failed: ' . $exception->getMessage());
    http_response_code(500);
    render('error', ['title' => 'Terjadi kesalahan']);
}

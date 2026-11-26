<?php

require_once APP_ROOT . '/app/OrderService.php';

function admin_categories(): array
{
    return ['Bahan baku', 'Kemasan', 'Transportasi', 'Utilitas', 'Lainnya'];
}

function managed_image_path(?string $path): ?string
{
    if (!$path || strpos($path, 'uploads/') !== 0) {
        return null;
    }
    $root = realpath(APP_ROOT . '/public/uploads');
    $file = realpath(APP_ROOT . '/public/' . $path);
    if ($root && $file && strpos($file, $root . DIRECTORY_SEPARATOR) === 0 && is_file($file)) {
        return $file;
    }
    return null;
}

function remove_managed_image(?string $path): void
{
    $file = managed_image_path($path);
    if ($file !== null) {
        @unlink($file);
    }
}

function upload_product_image(): ?string
{
    if (empty($_FILES['image']) || !is_array($_FILES['image'])) {
        return null;
    }
    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new DomainException('Gambar tidak berhasil diunggah. Pilih gambar baru lalu coba lagi.');
    }
    if ((int) $file['size'] < 1 || (int) $file['size'] > 2 * 1024 * 1024) {
        throw new DomainException('Ukuran gambar maksimal 2 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new DomainException('Format gambar harus JPEG, PNG, atau WebP yang valid.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $destination = APP_ROOT . '/public/uploads/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new DomainException('Gambar tidak dapat disimpan. Periksa izin folder uploads.');
    }
    return 'uploads/' . $name;
}

function unique_product_slug(PDO $pdo, string $name, ?int $exceptId = null): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $ascii) ?? '', '-'));
    if ($base === '') {
        $base = 'rice-up-item';
    }
    $candidate = substr($base, 0, 82);
    for ($suffix = 2; ; $suffix++) {
        $query = $pdo->prepare('SELECT id FROM products WHERE slug = ?' . ($exceptId ? ' AND id <> ?' : ''));
        $query->execute($exceptId ? [$candidate, $exceptId] : [$candidate]);
        if (!$query->fetchColumn()) {
            return $candidate;
        }
        $ending = '-' . $suffix;
        $candidate = substr($base, 0, 90 - strlen($ending)) . $ending;
    }
}

function handle_admin_post(PDO $pdo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $page = is_string($_GET['page'] ?? null) ? $_GET['page'] : '';
    $actions = ['admin-login', 'admin-logout', 'admin-product-save', 'admin-product-toggle', 'admin-product-delete',
        'admin-order-status', 'admin-payment-status', 'admin-expense-create',
        'admin-channel-save', 'admin-channel-toggle', 'admin-channel-delete'];
    if (!in_array($page, $actions, true)) {
        return;
    }
    require_csrf();
    $returnPage = 'admin';

    try {
        if ($page === 'admin-login') {
            $username = post_string('username', 50);
            $password = $_POST['password'] ?? '';
            if (!is_string($password) || strlen($password) > 1024) {
                $password = '';
            }
            $fingerprint = client_fingerprint();
            $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)')->execute();
            $attempts = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE fingerprint = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
            $attempts->execute([$fingerprint]);
            if ((int) $attempts->fetchColumn() >= 5) {
                flash('error', 'Login sementara dibatasi setelah beberapa percobaan. Coba lagi dalam 15 menit.');
                redirect(route('admin-login'));
            }
            $lookup = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
            $lookup->execute([$username]);
            $admin = $lookup->fetch();
            $fallbackHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
            $passwordValid = password_verify($password, $admin['password_hash'] ?? $fallbackHash);
            if (!$admin || !$passwordValid) {
                $pdo->prepare('INSERT INTO login_attempts (fingerprint) VALUES (?)')->execute([$fingerprint]);
                flash('error', 'Username atau password tidak sesuai.');
                redirect(route('admin-login'));
            }
            $pdo->prepare('DELETE FROM login_attempts WHERE fingerprint = ?')->execute([$fingerprint]);
            session_regenerate_id(true);
            unset($_SESSION['_csrf']);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            flash('success', 'Selamat datang, ' . $admin['username'] . '.');
            redirect(route('admin'));
        }

        if ($page === 'admin-logout') {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $parameters = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
                }
                session_destroy();
            }
            redirect(route('admin-login'));
        }

        require_admin();

        if ($page === 'admin-product-save') {
            $returnPage = 'admin-products';
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $name = post_string('name', 150);
            $description = post_string('description', 4000);
            $priceText = post_string('price', 20);
            $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99999]]);
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($id === false || mb_strlen($name, 'UTF-8') < 2 || trim($description) === '') {
                throw new DomainException('Nama dan deskripsi produk perlu diisi.');
            }
            if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $priceText)) {
                throw new DomainException('Harga harus berupa angka positif dengan maksimal dua angka desimal.');
            }
            $priceCents = decimal_to_cents($priceText);
            if ($priceCents < 100 || $priceCents > 10000000000 || $stock === false) {
                throw new DomainException('Harga atau stok berada di luar batas yang diizinkan.');
            }
            $existing = null;
            if ((int) $id > 0) {
                $query = $pdo->prepare('SELECT id, slug, image_path FROM products WHERE id = ?');
                $query->execute([(int) $id]);
                $existing = $query->fetch();
                if (!$existing) {
                    throw new DomainException('Produk tidak ditemukan.');
                }
            }
            $newImage = upload_product_image();
            try {
                $slug = $existing ? $existing['slug'] : unique_product_slug($pdo, $name);
                if ($existing) {
                    $image = $newImage ?? $existing['image_path'];
                    $save = $pdo->prepare('UPDATE products SET slug = ?, name = ?, description = ?, price = ?, stock = ?, image_path = ?, is_active = ? WHERE id = ?');
                    $save->execute([$slug, $name, $description, cents_to_decimal($priceCents), $stock, $image, $active, (int) $id]);
                    if ($newImage !== null && $existing['image_path'] !== $newImage) {
                        remove_managed_image($existing['image_path']);
                    }
                } else {
                    $save = $pdo->prepare('INSERT INTO products (slug, name, description, price, stock, image_path, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $save->execute([$slug, $name, $description, cents_to_decimal($priceCents), $stock, $newImage ?? 'assets/images/rice-bowl-lava.svg', $active]);
                }
            } catch (Throwable $exception) {
                if ($newImage !== null) {
                    remove_managed_image($newImage);
                }
                throw $exception;
            }
            flash('success', $existing ? 'Produk berhasil diperbarui.' : 'Produk baru berhasil ditambahkan.');
            redirect(route('admin-products'));
        }

        if ($page === 'admin-product-toggle') {
            $returnPage = 'admin-products';
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new DomainException('Produk tidak valid.');
            }
            $toggle = $pdo->prepare('UPDATE products SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $toggle->execute([$id]);
            if ($toggle->rowCount() !== 1) {
                throw new DomainException('Produk tidak ditemukan.');
            }
            flash('success', 'Status produk diperbarui.');
            redirect(route('admin-products'));
        }

        if ($page === 'admin-product-delete') {
            $returnPage = 'admin-products';
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new DomainException('Produk tidak valid.');
            }
            $pdo->beginTransaction();
            $query = $pdo->prepare('SELECT image_path FROM products WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $product = $query->fetch();
            if (!$product) {
                $pdo->rollBack();
                throw new DomainException('Produk tidak ditemukan.');
            }
            $references = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?');
            $references->execute([$id]);
            if ((int) $references->fetchColumn() > 0) {
                $pdo->prepare('UPDATE products SET is_active = 0 WHERE id = ?')->execute([$id]);
                $message = 'Produk pernah dipesan, sehingga dinonaktifkan agar riwayat tetap utuh.';
            } else {
                $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
                $message = 'Produk berhasil dihapus.';
            }
            $pdo->commit();
            if (strpos($message, 'dihapus') !== false) {
                remove_managed_image($product['image_path']);
            }
            flash('success', $message);
            redirect(route('admin-products'));
        }

        if ($page === 'admin-order-status') {
            $returnPage = 'admin-orders';
            $id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $target = post_string('order_status', 30);
            if ($id === false) {
                throw new DomainException('Pesanan tidak valid.');
            }
            OrderService::transition($pdo, (int) $id, $target, (int) $_SESSION['admin_id'], post_string('note', 500));
            flash('success', 'Status pesanan berhasil diperbarui.');
            redirect(route('admin-order', ['id' => (int) $id]));
        }

        if ($page === 'admin-payment-status') {
            $returnPage = 'admin-orders';
            $id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $target = post_string('payment_status', 30);
            if ($id === false || !in_array($target, ['paid', 'rejected'], true)) {
                throw new DomainException('Perubahan pembayaran tidak valid.');
            }
            OrderService::payment($pdo, (int) $id, $target, (int) $_SESSION['admin_id']);
            flash('success', $target === 'paid' ? 'Pembayaran ditandai telah diterima.' : 'Pembayaran simulasi ditolak.');
            redirect(route('admin-order', ['id' => (int) $id]));
        }

        if ($page === 'admin-expense-create') {
            $returnPage = 'admin-expenses';
            $description = post_string('description', 200);
            $category = post_string('category', 80);
            $amountText = post_string('amount', 20);
            $date = post_string('expense_date', 10);
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (mb_strlen($description, 'UTF-8') < 3 || !in_array($category, admin_categories(), true)
                || !$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
                throw new DomainException('Lengkapi keterangan, kategori, jumlah, dan tanggal pengeluaran dengan benar.');
            }
            if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amountText)) {
                throw new DomainException('Jumlah pengeluaran harus angka positif.');
            }
            $amountCents = decimal_to_cents($amountText);
            if ($amountCents < 1 || $amountCents > 10000000000) {
                throw new DomainException('Jumlah pengeluaran berada di luar batas yang diizinkan.');
            }
            $save = $pdo->prepare('INSERT INTO expenses (description, category, amount, expense_date, created_by) VALUES (?, ?, ?, ?, ?)');
            $save->execute([$description, $category, cents_to_decimal($amountCents), $date, (int) $_SESSION['admin_id']]);
            flash('success', 'Pengeluaran berhasil dicatat.');
            redirect(route('admin-expenses'));
        }

        if ($page === 'admin-channel-save') {
            $returnPage = 'admin-channels';
            $channelType = post_string('channel_type', 20);
            $bankName = post_string('bank_name', 100);
            $qrisLabel = post_string('qris_label', 100);
            $accountNumber = post_string('account_number', 50);
            $accountHolder = post_string('account_holder', 120);
            $instructions = post_string('instructions', 500);
            if (!in_array($channelType, ['bank_transfer', 'qris'], true)) {
                throw new DomainException('Pilih jenis saluran yang valid.');
            }
            if ($channelType === 'bank_transfer') {
                if (mb_strlen($bankName, 'UTF-8') < 2) {
                    throw new DomainException('Nama bank harus diisi.');
                }
                if (mb_strlen($accountNumber, 'UTF-8') < 3 || mb_strlen($accountHolder, 'UTF-8') < 2) {
                    throw new DomainException('Nomor rekening dan nama pemilik harus diisi untuk transfer bank.');
                }
            }
            $qrisImagePath = null;
            if ($channelType === 'qris') {
                if (empty($_FILES['qris_image']) || ($_FILES['qris_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    throw new DomainException('Gambar QRIS wajib diunggah untuk saluran QRIS.');
                }
                $file = $_FILES['qris_image'];
                if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
                    throw new DomainException('Gambar QRIS tidak berhasil diunggah.');
                }
                if ((int) $file['size'] > 2 * 1024 * 1024) {
                    throw new DomainException('Ukuran gambar QRIS maksimal 2 MB.');
                }
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
                    throw new DomainException('Format gambar QRIS harus JPEG, PNG, atau WebP.');
                }
                $imageName = 'qris_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                $destination = APP_ROOT . '/public/uploads/' . $imageName;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new DomainException('Gambar QRIS tidak dapat disimpan.');
                }
                $qrisImagePath = 'uploads/' . $imageName;
            }
            $pdo->beginTransaction();
            try {
                if ($channelType === 'qris') {
                    $pdo->exec("UPDATE payment_channels SET is_active = 0 WHERE channel_type = 'qris'");
                }
                $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM payment_channels')->fetchColumn();
                $save = $pdo->prepare('INSERT INTO payment_channels (channel_type, bank_name, account_number, account_holder, qris_image_path, instructions, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $save->execute([
                    $channelType,
                    $channelType === 'qris' ? ($qrisLabel !== '' ? $qrisLabel : 'QRIS') : $bankName,
                    $channelType === 'bank_transfer' ? $accountNumber : null,
                    $channelType === 'bank_transfer' ? $accountHolder : null,
                    $qrisImagePath, $instructions !== '' ? $instructions : null, $maxSort + 1,
                ]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($qrisImagePath !== null) {
                    remove_managed_image($qrisImagePath);
                }
                throw $exception;
            }
            flash('success', $channelType === 'qris' ? 'QRIS berhasil ditambahkan dan diaktifkan.' : 'Rekening bank berhasil ditambahkan.');
            redirect(route('admin-channels'));
        }

        if ($page === 'admin-channel-toggle') {
            $returnPage = 'admin-channels';
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new DomainException('Saluran tidak valid.');
            }
            $pdo->beginTransaction();
            $query = $pdo->prepare('SELECT channel_type, is_active FROM payment_channels WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $channel = $query->fetch();
            if (!$channel) {
                $pdo->rollBack();
                throw new DomainException('Saluran tidak ditemukan.');
            }
            $nextActive = (int) $channel['is_active'] === 1 ? 0 : 1;
            if ($channel['channel_type'] === 'qris' && $nextActive === 1) {
                $pdo->prepare("UPDATE payment_channels SET is_active = 0 WHERE channel_type = 'qris' AND id <> ?")->execute([$id]);
            }
            $pdo->prepare('UPDATE payment_channels SET is_active = ? WHERE id = ?')->execute([$nextActive, $id]);
            $pdo->commit();
            flash('success', 'Status saluran pembayaran diperbarui.');
            redirect(route('admin-channels'));
        }

        if ($page === 'admin-channel-delete') {
            $returnPage = 'admin-channels';
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new DomainException('Saluran tidak valid.');
            }
            $query = $pdo->prepare('SELECT qris_image_path FROM payment_channels WHERE id = ?');
            $query->execute([$id]);
            $channel = $query->fetch();
            if (!$channel) {
                throw new DomainException('Saluran tidak ditemukan.');
            }
            $pdo->prepare('DELETE FROM payment_channels WHERE id = ?')->execute([$id]);
            remove_managed_image($channel['qris_image_path']);
            flash('success', 'Saluran pembayaran berhasil dihapus.');
            redirect(route('admin-channels'));
        }
    } catch (DomainException $exception) {
        flash('error', $exception->getMessage());
        redirect(route($returnPage));
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Rice Up] Admin action failed: ' . $exception->getMessage());
        flash('error', 'Permintaan admin belum dapat diproses. Periksa data lalu coba lagi.');
        redirect(route($returnPage));
    }
}

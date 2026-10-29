<?php
$cartCount = 0;
foreach (($_SESSION['cart'] ?? []) as $quantity) {
    $cartCount += max(0, (int) $quantity);
}
$successMessage = flash('success');
$errorMessage = flash('error');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ad3029">
    <title><?= e(page_title($title)) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/images/mark.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css') . '?v=' . filemtime(APP_ROOT . '/public/assets/css/style.css')) ?>">
</head>
<body>
<div class="site-shell">
    <header class="site-header">
        <a class="brand" href="<?= e(route('home')) ?>" aria-label="Rice Up, halaman utama">
            <span class="brand-mark" aria-hidden="true"><img src="<?= e(asset('assets/images/mark.svg')) ?>" alt=""></span>
            <span class="brand-name">rice<span>up</span><small>RICE BOWL, GOOD MOOD</small></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">
            <span class="sr-only">Buka menu</span><span aria-hidden="true">☰</span>
        </button>
        <nav class="primary-nav" id="primary-nav" aria-label="Navigasi utama">
            <?php if ($admin_area && is_admin()): ?>
                <a href="<?= e(route('admin')) ?>">Dashboard</a>
                <a href="<?= e(route('admin-products')) ?>">Produk</a>
                <a href="<?= e(route('admin-orders')) ?>">Pesanan</a>
                <a href="<?= e(route('admin-expenses')) ?>">Pengeluaran</a>
                <a href="<?= e(route('admin-reports')) ?>">Laporan</a>
                <span class="nav-user"><?= e($_SESSION['admin_username'] ?? 'Admin') ?></span>
                <form method="post" action="<?= e(route('admin-logout')) ?>" class="nav-form"><?= csrf_field() ?><button class="nav-link" type="submit">Keluar</button></form>
            <?php else: ?>
                <a href="<?= e(route('home')) ?>">Menu</a>
                <?php if (!empty($_SESSION['verified_phone'])): ?><a href="<?= e(route('order-history')) ?>">Riwayat pesanan</a><?php endif; ?>
                <a href="<?= e(route('order-lookup')) ?>">Cek pesanan</a>
                <a class="cart-link" href="<?= e(route('cart')) ?>">Keranjang <span class="cart-count"><?= $cartCount ?></span></a>
                <a class="admin-shortcut" href="<?= e(route('admin-login')) ?>">Admin</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="page-content">
        <?php if ($successMessage): ?><div class="notice notice-success" role="status"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="notice notice-error" role="alert"><?= e($errorMessage) ?></div><?php endif; ?>
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <div class="footer-brand"><span class="brand-mark small"><img src="<?= e(asset('assets/images/mark.svg')) ?>" alt=""></span><span>rice<span>up</span></span></div>
        <p>Rice Up Demo · Pesan rice bowl favoritmu.</p>
        <p class="footer-note">Harga, stok, dan pembayaran pada situs ini merupakan simulasi untuk demo E-Business.</p>
    </footer>
</div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>

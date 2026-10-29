<section class="subpage-heading"><span class="eyebrow eyebrow-dark">PESANANMU</span><h1>Keranjang belanja</h1><p>Periksa menu dan jumlah sebelum melanjutkan.</p></section>
<?php if ($items === []): ?>
    <div class="empty-state"><span class="empty-icon">🛍</span><h2>Keranjang masih kosong</h2><p>Yuk pilih rice bowl yang paling menggoda.</p><a class="button button-primary" href="<?= e(route('home')) ?>">Lihat menu</a></div>
<?php else: ?>
    <div class="cart-layout">
        <div class="cart-lines">
            <?php foreach ($items as $item): ?>
                <article class="cart-line">
                    <img src="<?= e(asset($item['image_path'] ?: 'assets/images/rice-bowl-lava.svg')) ?>" alt="" loading="lazy">
                    <div class="cart-line-info"><h2><?= e($item['name']) ?></h2><p><?= e(money($item['price'])) ?> / porsi</p><span class="cart-stock"><?= (int) $item['stock'] ?> tersedia</span></div>
                    <form class="cart-quantity" method="post" action="<?= e(route('cart-update')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                        <label><span class="sr-only">Jumlah <?= e($item['name']) ?></span><input aria-label="Jumlah <?= e($item['name']) ?>" type="number" name="quantity" min="0" max="<?= min(50, (int) $item['stock']) ?>" value="<?= (int) $item['quantity'] ?>"></label>
                        <button class="text-button" type="submit">Perbarui</button>
                    </form>
                    <strong class="cart-line-total"><?= e(money((float) $item['price'] * (int) $item['quantity'])) ?></strong>
                    <form method="post" action="<?= e(route('cart-remove')) ?>" class="cart-remove-form"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><button type="submit" class="remove-button" aria-label="Hapus <?= e($item['name']) ?>">×</button></form>
                </article>
            <?php endforeach; ?>
        </div>
        <aside class="summary-card">
            <h2>Ringkasan</h2><div class="summary-row"><span>Subtotal</span><strong><?= e(money($subtotal)) ?></strong></div>
            <p class="summary-hint">Biaya antar dihitung setelah kamu memilih delivery atau pickup.</p>
            <div class="summary-total"><span>Total sementara</span><strong><?= e(money($subtotal)) ?></strong></div>
            <a class="button button-primary button-wide" href="<?= e(route('checkout')) ?>">Lanjut checkout <span aria-hidden="true">→</span></a>
            <a class="continue-link" href="<?= e(route('home')) ?>">← Lanjut pilih menu</a>
        </aside>
    </div>
<?php endif; ?>

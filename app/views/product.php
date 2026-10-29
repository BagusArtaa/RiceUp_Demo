<nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(route('home')) ?>">Menu</a><span aria-hidden="true">/</span><span><?= e($product['name']) ?></span></nav>
<section class="product-detail">
    <div class="detail-image"><img src="<?= e(asset($product['image_path'] ?: 'assets/images/rice-bowl-lava.svg')) ?>" alt="Rice bowl <?= e($product['name']) ?>"></div>
    <div class="detail-copy">
        <span class="eyebrow eyebrow-dark">RICE UP FAVORITE</span>
        <h1><?= e($product['name']) ?></h1>
        <p class="detail-description"><?= nl2br(e($product['description'])) ?></p>
        <p class="detail-price"><?= e(money($product['price'])) ?></p>
        <p class="stock-caption <?= (int) $product['stock'] > 0 ? 'text-success' : 'text-danger' ?>">
            <?= (int) $product['stock'] > 0 ? 'Tersedia · ' . (int) $product['stock'] . ' porsi' : 'Stok sedang habis' ?>
        </p>
        <?php if ((int) $product['stock'] > 0): ?>
            <form class="detail-add-form" method="post" action="<?= e(route('cart-add')) ?>">
                <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <label class="field quantity-field"><span>Jumlah</span><input type="number" name="quantity" min="1" max="<?= min(50, (int) $product['stock']) ?>" value="1" required></label>
                <button class="button button-primary" type="submit">Tambah ke keranjang <span aria-hidden="true">↗</span></button>
            </form>
        <?php else: ?><button class="button button-disabled" disabled>Stok habis</button><?php endif; ?>
        <p class="demo-note">Harga dan stok merupakan contoh DEMO, dapat diperbarui oleh admin.</p>
    </div>
</section>

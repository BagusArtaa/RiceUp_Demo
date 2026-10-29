<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow"><span class="eyebrow-dot"></span> Freshly made, feel-good bowls</span>
        <h1>Semangkuk nikmat,<br><em>mood naik.</em></h1>
        <p>Menu rice bowl favorit dengan rasa berani dan bahan yang bikin hari terasa lebih baik.</p>
        <a class="button button-light" href="#menu">Lihat menu <span aria-hidden="true">↓</span></a>
        <div class="hero-footnote"><span class="mini-stars" aria-hidden="true">✦ ✦ ✦</span> Dibuat segar saat kamu pesan</div>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="hero-sun"></div>
        <div class="hero-ring ring-one"></div><div class="hero-ring ring-two"></div>
        <img src="<?= e(asset('assets/images/rice-bowl-lava.svg')) ?>" alt="">
        <span class="hero-stamp">RICE<br>UP!</span>
        <span class="hero-spark spark-one">✳</span><span class="hero-spark spark-two">✦</span>
    </div>
</section>

<section class="menu-section" id="menu">
    <div class="section-heading">
        <div><span class="eyebrow eyebrow-dark">PILIHAN HARI INI</span><h2>Menu andalan kami</h2></div>
        <p>Hangat, gurih, dan selalu bikin ingin nambah.</p>
    </div>
    <?php if ($products === []): ?>
        <div class="empty-state"><span class="empty-icon">🍚</span><h3>Menu sedang disiapkan</h3><p>Coba kembali beberapa saat lagi.</p></div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <article class="product-card">
                    <a class="product-image" href="<?= e(route('product', ['id' => $product['id']])) ?>">
                        <img src="<?= e(asset($product['image_path'] ?: 'assets/images/rice-bowl-lava.svg')) ?>" alt="Rice bowl <?= e($product['name']) ?>" loading="lazy">
                        <span class="product-image-badge">RICE UP PICK</span>
                    </a>
                    <div class="product-card-body">
                        <div class="product-card-top"><h3><a href="<?= e(route('product', ['id' => $product['id']])) ?>"><?= e($product['name']) ?></a></h3><span class="stock-dot <?= (int) $product['stock'] > 0 ? 'available' : 'unavailable' ?>" aria-hidden="true"></span><span class="sr-only"><?= (int) $product['stock'] > 0 ? 'Stok tersedia' : 'Stok habis' ?></span></div>
                        <p class="product-description"><?= e($product['description']) ?></p>
                        <div class="product-card-bottom"><strong class="price"><?= e(money($product['price'])) ?></strong>
                            <?php if ((int) $product['stock'] > 0): ?>
                                <form method="post" action="<?= e(route('cart-add')) ?>"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="quantity" value="1"><button class="icon-button" type="submit" aria-label="Tambah <?= e($product['name']) ?> ke keranjang">+</button></form>
                            <?php else: ?><span class="sold-out">Habis</span><?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="promise-strip" aria-label="Informasi pemesanan">
    <div><span class="promise-icon">♨</span><p><strong>Fresh dibuat</strong><br><span>Setelah pesanan masuk</span></p></div>
    <div><span class="promise-icon">♡</span><p><strong>Gampang dipesan</strong><br><span>Checkout praktis dan aman</span></p></div>
    <div><span class="promise-icon">⌂</span><p><strong>Delivery atau pickup</strong><br><span>Pilih yang paling nyaman</span></p></div>
</section>

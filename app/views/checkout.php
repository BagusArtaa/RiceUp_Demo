<section class="subpage-heading"><span class="eyebrow eyebrow-dark">LANGKAH TERAKHIR</span><h1>Checkout</h1><p>Isi data pemesanan. Total akan dihitung ulang oleh sistem.</p></section>
<?php if ($items === []): ?>
    <div class="empty-state"><h2>Keranjang masih kosong</h2><a class="button button-primary" href="<?= e(route('home')) ?>">Pilih menu</a></div>
<?php else: ?>
    <div class="checkout-layout">
        <form class="checkout-form panel" method="post" action="<?= e(route('checkout-submit')) ?>">
            <?= csrf_field() ?><input type="hidden" name="checkout_token" value="<?= e($checkoutToken) ?>">
            <h2>Data pemesan</h2>
            <label class="field"><span>Nama lengkap</span><input name="customer_name" value="<?= e($old['customer_name'] ?? '') ?>" maxlength="120" autocomplete="name" required></label>
            <label class="field"><span>Nomor telepon</span><input name="customer_phone" value="<?= e($old['customer_phone'] ?? '') ?>" inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" required><small>Nomor ini dipakai untuk mengakses status pesanan.</small></label>

            <fieldset class="form-section"><legend>Pesanan diambil dengan</legend>
                <label class="choice-card"><input type="radio" name="fulfillment_method" value="delivery" <?= ($old['fulfillment_method'] ?? 'delivery') === 'delivery' ? 'checked' : '' ?>><span><strong>Delivery</strong><small>Biaya antar demo <?= e(money(config('delivery_fee', 5000))) ?></small></span></label>
                <label class="choice-card"><input type="radio" name="fulfillment_method" value="pickup" <?= ($old['fulfillment_method'] ?? '') === 'pickup' ? 'checked' : '' ?>><span><strong>Ambil sendiri</strong><small>Tanpa biaya antar</small></span></label>
            </fieldset>
            <label class="field" id="address-field"><span>Alamat delivery</span><textarea name="delivery_address" rows="3" maxlength="500" autocomplete="street-address"><?= e($old['delivery_address'] ?? '') ?></textarea><small>Alamat layanan belum dibatasi pada demo. Konfirmasi area sebelum dipakai sungguhan.</small></label>
            <label class="field"><span>Catatan pesanan <small>(opsional)</small></span><textarea name="notes" rows="2" maxlength="500" placeholder="Contoh: level pedas sedang"><?= e($old['notes'] ?? '') ?></textarea></label>

            <fieldset class="form-section"><legend>Pembayaran simulasi</legend>
                <?php foreach (payment_methods() as $value => $label): ?>
                    <label class="choice-card"><input type="radio" name="payment_method" value="<?= e($value) ?>" <?= ($old['payment_method'] ?? 'simulated_transfer') === $value ? 'checked' : '' ?>><span><strong><?= e($label) ?></strong><small><?php if ($value === 'simulated_transfer'): ?>Admin perlu mengonfirmasi secara manual. Tidak ada uang yang diproses situs ini.<?php else: ?>Pembayaran ditandai oleh admin setelah diterima.<?php endif; ?></small></span></label>
                <?php endforeach; ?>
            </fieldset>
            <button class="button button-primary button-wide" type="submit">Buat pesanan <span aria-hidden="true">→</span></button>
        </form>

        <aside class="summary-card checkout-summary">
            <h2>Pesananmu</h2>
            <?php foreach ($items as $item): ?><div class="checkout-item"><span><?= (int) $item['quantity'] ?> × <?= e($item['name']) ?></span><strong><?= e(money((float) $item['price'] * (int) $item['quantity'])) ?></strong></div><?php endforeach; ?>
            <div class="summary-row"><span>Subtotal</span><strong><?= e(money($subtotal)) ?></strong></div>
            <div class="summary-row"><span>Biaya antar</span><strong>Ditentukan saat diproses</strong></div>
            <p class="summary-hint">Harga, ketersediaan, dan total akhir diperiksa ulang dari database ketika pesanan dikirim.</p>
        </aside>
    </div>
<?php endif; ?>

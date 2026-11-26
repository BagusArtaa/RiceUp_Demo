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

            <?php $selectedMethod = $old['payment_method'] ?? ''; ?>
            <fieldset class="form-section"><legend>Metode pembayaran</legend>
                <?php $firstMethod = true; foreach (payment_methods() as $value => $label): ?>
                    <label class="choice-card"><input type="radio" name="payment_method" value="<?= e($value) ?>" <?= $selectedMethod === $value ? 'checked' : '' ?> <?= $firstMethod ? 'required' : '' ?>><span><strong><?= e($label) ?></strong><small><?php if ($value === 'bank_transfer'): ?>Transfer ke rekening bank yang tersedia. Upload bukti setelah checkout.<?php elseif ($value === 'qris'): ?>Scan QRIS lalu upload bukti pembayaran.<?php else: ?>Pembayaran ditandai oleh admin setelah diterima.<?php endif; ?></small></span></label>
                <?php $firstMethod = false; endforeach; ?>
            </fieldset>

            <?php
            $bankChannels = array_values(array_filter($paymentChannels, fn($c) => $c['channel_type'] === 'bank_transfer'));
            $qrisChannels = array_values(array_filter($paymentChannels, fn($c) => $c['channel_type'] === 'qris'));
            ?>

            <fieldset class="form-section payment-channel-section" id="channels-bank_transfer" <?= $selectedMethod !== 'bank_transfer' ? 'hidden' : '' ?>><legend>Pilih rekening bank tujuan</legend>
                <?php if ($bankChannels === []): ?>
                    <p class="muted">Belum ada rekening bank yang tersedia. Pilih metode lain atau hubungi admin.</p>
                <?php else: ?>
                    <?php foreach ($bankChannels as $ch): ?>
                        <label class="choice-card channel-card"><input type="radio" name="payment_channel_id" value="<?= (int) $ch['id'] ?>" <?= ($old['payment_channel_id'] ?? '') == $ch['id'] ? 'checked' : '' ?> <?= $selectedMethod !== 'bank_transfer' ? 'disabled' : '' ?>><span><strong><?= e($ch['bank_name']) ?></strong><small><?= e($ch['account_number']) ?> a.n. <?= e($ch['account_holder']) ?></small><?php if ($ch['instructions']): ?><small class="channel-instructions"><?= e($ch['instructions']) ?></small><?php endif; ?></span></label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </fieldset>

            <fieldset class="form-section payment-channel-section" id="channels-qris" <?= $selectedMethod !== 'qris' ? 'hidden' : '' ?>><legend>Pembayaran QRIS</legend>
                <?php if ($qrisChannels === []): ?>
                    <p class="muted">Belum ada QRIS yang tersedia. Pilih metode lain atau hubungi admin.</p>
                <?php else: ?>
                    <?php $activeQris = $qrisChannels[0]; ?>
                    <div class="payment-info-box">
                        <input type="radio" name="payment_channel_id" value="<?= (int) $activeQris['id'] ?>" <?= $selectedMethod === 'qris' ? 'checked' : 'disabled' ?> hidden>
                        <h3><?= e($activeQris['bank_name'] ?: 'QRIS') ?></h3>
                        <?php if ($activeQris['qris_image_path']): ?>
                            <img src="<?= e(asset($activeQris['qris_image_path'])) ?>" class="qris-display-img" alt="QR Code <?= e($activeQris['bank_name'] ?: 'QRIS') ?>">
                        <?php endif; ?>
                        <?php if ($activeQris['instructions']): ?><p class="channel-instructions"><?= e($activeQris['instructions']) ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
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

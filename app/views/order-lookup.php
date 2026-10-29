<section class="subpage-heading"><span class="eyebrow eyebrow-dark">PESANANMU</span><h1>Cek status pesanan</h1><p>Masukkan kode pesanan dan nomor telepon yang dipakai saat checkout.</p></section>
<div class="lookup-card panel">
    <div class="lookup-icon" aria-hidden="true">⌕</div>
    <form method="post" action="<?= e(route('order-lookup')) ?>" class="stack-form">
        <?= csrf_field() ?>
        <label class="field"><span>Kode pesanan</span><input name="order_code" maxlength="30" placeholder="Contoh: RU261029A1B2C3D4" autocapitalize="characters" required></label>
        <label class="field"><span>Nomor telepon</span><input name="customer_phone" inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" required></label>
        <button class="button button-primary button-wide" type="submit">Cari pesanan</button>
    </form>
    <p class="privacy-note">Gunakan kode pesanan dan nomor telepon yang sama dengan data checkout. Data ini hanya dipakai untuk menemukan pesananmu.</p>
</div>

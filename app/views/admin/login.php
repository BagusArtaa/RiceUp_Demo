<section class="login-card panel">
    <span class="brand-mark"><img src="<?= e(asset('assets/images/mark.svg')) ?>" alt="Rice Up"></span>
    <span class="eyebrow eyebrow-dark">AREA ADMIN</span>
    <h1>Selamat datang kembali</h1>
    <p>Masuk untuk mengelola menu, pesanan, dan laporan Rice Up.</p>
    <form method="post" action="<?= e(route('admin-login')) ?>">
        <?= csrf_field() ?>
        <label class="field"><span>Username</span><input name="username" maxlength="50" autocomplete="username" required></label>
        <label class="field"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>
        <button class="button button-primary button-wide" type="submit">Masuk ke dashboard</button>
    </form>
    <p class="privacy-note">Akun admin dibuat secara lokal melalui panduan setup. Tidak ada username/password bawaan.</p>
</section>

<section class="admin-heading"><div><span class="eyebrow eyebrow-dark">PENGATURAN</span><h1>Saluran Pembayaran</h1><p>Kelola rekening bank dan QRIS yang dapat digunakan pembeli untuk membayar.</p></div></section>

<div class="admin-grid">
    <section class="data-panel">
        <h2>Daftar saluran aktif</h2>
        <?php if ($channels === []): ?>
            <div class="empty-state"><h3>Belum ada saluran</h3><p>Tambahkan rekening bank atau QRIS dari form di samping.</p></div>
        <?php else: ?>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Jenis</th><th>Nama Bank / Penyedia</th><th>Detail / Gambar QRIS</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                <?php foreach ($channels as $ch): ?>
                <tr>
                    <td><span class="status-pill <?= $ch['channel_type'] === 'qris' ? 'status-confirmed' : 'status-paid' ?>"><?= $ch['channel_type'] === 'qris' ? 'QRIS' : 'Transfer Bank' ?></span></td>
                    <td><strong><?= e($ch['bank_name'] ?: '—') ?></strong></td>
                    <td>
                        <?php if ($ch['channel_type'] === 'qris'): ?>
                            <?php if ($ch['qris_image_path']): ?>
                                <a href="<?= e(asset($ch['qris_image_path'])) ?>" target="_blank" rel="noopener"><img src="<?= e(asset($ch['qris_image_path'])) ?>" alt="QRIS" style="width:64px;height:64px;object-fit:contain;border-radius:6px;border:1px solid var(--line)"></a>
                            <?php else: ?>
                                <span class="muted">Tidak ada gambar</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= e($ch['account_number'] ?: '—') ?><br><small class="muted">a.n. <?= e($ch['account_holder'] ?: '—') ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-pill <?= (int) $ch['is_active'] ? 'status-paid' : 'status-cancelled' ?>"><?= (int) $ch['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td class="table-actions">
                        <form method="post" action="<?= e(route('admin-channel-toggle')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $ch['id'] ?>"><button class="table-action" type="submit"><?= (int) $ch['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                        <form method="post" action="<?= e(route('admin-channel-delete')) ?>" class="inline-form" data-confirm="Hapus saluran pembayaran ini?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $ch['id'] ?>"><button class="table-action danger-action" type="submit">Hapus</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>

    <section class="data-panel">
        <h2>Tambah saluran baru</h2>
        <form method="post" action="<?= e(route('admin-channel-save')) ?>" class="stack-form" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <label class="field"><span>Jenis saluran</span>
                <select name="channel_type" id="channel-type-select" required>
                    <option value="bank_transfer">Transfer Bank</option>
                    <option value="qris">QRIS</option>
                </select>
            </label>
            <div id="bank-fields" class="stack-form">
                <label class="field"><span>Nama bank</span><input name="bank_name" maxlength="100" placeholder="BCA, BRI, Mandiri, BNI, dll." required></label>
                <label class="field"><span>Nomor rekening</span><input name="account_number" maxlength="50" placeholder="1234567890" required></label>
                <label class="field"><span>Atas nama</span><input name="account_holder" maxlength="120" placeholder="Nama pemilik rekening" required></label>
            </div>
            <div id="qris-fields" class="stack-form" hidden>
                <label class="field"><span>Label QRIS <small>(opsional)</small></span><input name="qris_label" maxlength="100" placeholder="Contoh: QRIS Rice Up"></label>
                <label class="field"><span>Gambar QRIS (wajib, JPEG/PNG/WebP, maks 2 MB)</span><input type="file" name="qris_image" accept="image/jpeg,image/png,image/webp"><small>Maksimal 1 QRIS yang aktif. Menambah QRIS baru akan menonaktifkan QRIS sebelumnya.</small></label>
            </div>
            <label class="field"><span>Instruksi tambahan <small>(opsional)</small></span><textarea name="instructions" rows="2" maxlength="500" placeholder="Contoh: Konfirmasi via WhatsApp setelah transfer"></textarea></label>
            <button class="button button-primary button-wide" type="submit">Simpan saluran</button>
        </form>
    </section>
</div>


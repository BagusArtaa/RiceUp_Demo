<section class="admin-heading"><div><span class="eyebrow eyebrow-dark">FINANCE</span><h1>Pengeluaran</h1><p>Catat biaya operasional demo berdasarkan tanggal transaksi.</p></div></section>
<div class="admin-grid expense-grid">
    <section class="data-panel"><h2>Tambah pengeluaran</h2><form method="post" action="<?= e(route('admin-expense-create')) ?>" class="stack-form"><?= csrf_field() ?>
        <label class="field"><span>Keterangan</span><input name="description" maxlength="200" placeholder="Contoh: pembelian bahan baku" required></label>
        <label class="field"><span>Kategori</span><select name="category" required><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"><?= e($category) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Jumlah (Rupiah)</span><input type="number" name="amount" min="1" max="100000000" step="0.01" required></label>
        <label class="field"><span>Tanggal pengeluaran</span><input type="date" name="expense_date" value="<?= e($today) ?>" required></label>
        <button class="button button-primary" type="submit">Simpan pengeluaran</button>
    </form></section>
    <section class="data-panel"><h2>Pengeluaran terbaru</h2><?php if ($expenses === []): ?><p class="muted">Belum ada pengeluaran yang dicatat.</p><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Tanggal</th><th>Keterangan</th><th>Kategori</th><th>Jumlah</th></tr></thead><tbody><?php foreach ($expenses as $expense): ?><tr><td><?= e(format_date($expense['expense_date'], 'd M Y')) ?></td><td><?= e($expense['description']) ?></td><td><?= e($expense['category']) ?></td><td><?= e(money($expense['amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</div>

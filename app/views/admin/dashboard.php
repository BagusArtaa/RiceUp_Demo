<section class="admin-heading"><div><span class="eyebrow eyebrow-dark">OVERVIEW</span><h1>Halo, <?= e($_SESSION['admin_username']) ?> 👋</h1><p>Ringkasan operasional Rice Up untuk hari ini.</p></div><div class="admin-actions"><a class="button button-outline" href="<?= e(route('admin-orders')) ?>">Lihat semua pesanan</a><a class="button button-primary" href="<?= e(route('admin-product-form')) ?>">+ Tambah produk</a></div></section>

<section class="stats-grid" aria-label="Ringkasan bisnis">
    <article class="stat-card"><span>Pesanan hari ini</span><strong><?= (int) $stats['orders_today'] ?></strong><small>Semua status</small></article>
    <article class="stat-card"><span>Perlu diproses</span><strong><?= (int) $stats['pending_orders'] ?></strong><small>Menunggu tindakan admin</small></article>
    <article class="stat-card"><span>Pemasukan terkonfirmasi</span><strong><?= e(money($stats['income_today'])) ?></strong><small>Hari ini · pembayaran diterima</small></article>
    <article class="stat-card"><span>Pengeluaran tercatat</span><strong><?= e(money($stats['expenses_month'])) ?></strong><small>Bulan ini</small></article>
</section>

<div class="admin-grid">
    <section class="data-panel"><div class="panel-heading-row"><h2>Pesanan terbaru</h2><a class="text-button" href="<?= e(route('admin-orders')) ?>">Semua pesanan →</a></div>
        <?php if ($orders === []): ?><p class="muted">Belum ada pesanan. Pesanan baru akan muncul di sini.</p><?php else: ?>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Kode</th><th>Pemesan</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
            <?php foreach ($orders as $order): ?><tr><td><strong><?= e($order['order_code']) ?></strong><br><small><?= e(format_date($order['created_at'])) ?></small></td><td><?= e($order['customer_name']) ?></td><td><span class="status-pill status-<?= e($order['order_status']) ?>"><?= e(order_status_label($order['order_status'])) ?></span></td><td><?= e(money($order['total_amount'])) ?></td><td><a class="table-action" href="<?= e(route('admin-order', ['id' => $order['id']])) ?>">Detail</a></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
    <section class="data-panel"><h2>Pengeluaran 7 hari terakhir</h2>
        <?php if ($expenseChart === []): ?><p class="muted">Belum ada pengeluaran tercatat pada periode ini.</p><?php else: ?>
            <div class="chart-list"><?php foreach ($expenseChart as $day): ?><div class="chart-row"><span><?= e($day['label']) ?></span><div class="chart-track"><div class="chart-bar" style="width:<?= (float) $day['width'] ?>%"></div></div><strong><?= e(money($day['amount'])) ?></strong></div><?php endforeach; ?></div>
        <?php endif; ?>
        <a class="continue-link" href="<?= e(route('admin-reports')) ?>">Buka laporan →</a>
    </section>
</div>

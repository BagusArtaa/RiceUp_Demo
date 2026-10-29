<section class="subpage-heading"><span class="eyebrow eyebrow-dark">PESANANMU</span><h1>Riwayat pesanan</h1><p>Pesanan yang pernah dibuat dengan nomor telepon terverifikasi. Maksimal 50 pesanan terbaru ditampilkan.</p></section>
<section class="data-panel customer-history">
    <?php if ($orders === []): ?><div class="empty-state"><h2>Belum ada riwayat pesanan</h2><p>Pesananmu akan muncul setelah checkout berhasil.</p><a class="button button-primary" href="<?= e(route('home')) ?>">Lihat menu</a></div><?php else: ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Kode pesanan</th><th>Tanggal</th><th>Pengambilan</th><th>Status</th><th>Pembayaran</th><th>Total</th><th></th></tr></thead><tbody>
        <?php foreach ($orders as $order): ?><tr><td><strong><?= e($order['order_code']) ?></strong></td><td><?= e(format_date($order['created_at'])) ?></td><td><?= $order['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Ambil sendiri' ?></td><td><span class="status-pill status-<?= e($order['order_status']) ?>"><?= e(order_status_label($order['order_status'])) ?></span></td><td><span class="status-pill status-<?= e($order['payment_status']) ?>"><?= e(payment_status_label($order['payment_status'])) ?></span></td><td><?= e(money($order['total_amount'])) ?></td><td><a class="table-action" href="<?= e(route('order', ['id' => $order['id']])) ?>">Detail</a></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<p class="center-link"><a class="continue-link" href="<?= e(route('order-lookup')) ?>">Verifikasi pesanan lain</a> <span>·</span> <a class="continue-link" href="<?= e(route('home')) ?>">Kembali ke menu</a></p>

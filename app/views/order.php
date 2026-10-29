<section class="order-confirmation">
    <div class="confirmation-check" aria-hidden="true">✓</div>
    <span class="eyebrow eyebrow-dark"><?= $created ? 'PESANAN DITERIMA' : 'DETAIL PESANAN' ?></span>
    <h1><?= $created ? 'Yeay, pesananmu masuk!' : 'Status pesananku' ?></h1>
    <p><?= $created ? 'Simpan kode pesanan ini untuk mengecek status kapan saja.' : 'Status terbaru pesanan Rice Up kamu.' ?></p>
</section>

<div class="order-detail-layout">
    <section class="panel order-main">
        <div class="order-code-block"><span>KODE PESANAN</span><strong><?= e($order['order_code']) ?></strong><button class="copy-button" type="button" data-copy="<?= e($order['order_code']) ?>" aria-live="polite">Salin</button></div>
        <div class="order-status-grid">
            <div><span>Status pesanan</span><strong class="status-pill status-<?= e($order['order_status']) ?>"><?= e(order_status_label($order['order_status'])) ?></strong></div>
            <div><span>Status pembayaran</span><strong class="status-pill status-<?= e($order['payment_status']) ?>"><?= e(payment_status_label($order['payment_status'])) ?></strong></div>
        </div>
        <h2>Detail menu</h2>
        <div class="order-item-list">
            <?php foreach ($items as $item): ?><div class="order-item"><span><?= (int) $item['quantity'] ?> × <?= e($item['product_name_snapshot']) ?></span><strong><?= e(money($item['line_total'])) ?></strong></div><?php endforeach; ?>
        </div>
        <div class="summary-row"><span>Subtotal</span><strong><?= e(money($order['subtotal'])) ?></strong></div>
        <div class="summary-row"><span>Biaya delivery</span><strong><?= e(money($order['delivery_fee'])) ?></strong></div>
        <div class="summary-total"><span>Total pesanan</span><strong><?= e(money($order['total_amount'])) ?></strong></div>
        <div class="order-meta"><p><span>Pemesan</span><?= e($order['customer_name']) ?></p><p><span>Tanggal</span><?= e(format_date($order['created_at'])) ?></p><p><span>Pengambilan</span><?= $order['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Ambil sendiri' ?></p><?php if ($order['delivery_address']): ?><p><span>Alamat</span><?= nl2br(e($order['delivery_address'])) ?></p><?php endif; ?></div>

        <?php if ($order['payment_method'] === 'simulated_transfer' && in_array($order['payment_status'], ['unpaid', 'rejected'], true) && !in_array($order['order_status'], ['rejected', 'cancelled'], true)): ?>
            <div class="inline-action-box"><div><strong>Transfer simulasi</strong><p>Jika demo pembayaran sudah dilakukan, kirim permintaan konfirmasi ke admin. Ini tidak memproses uang sungguhan.</p></div><form method="post" action="<?= e(route('order-payment')) ?>"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><button class="button button-outline" type="submit">Minta konfirmasi pembayaran</button></form></div>
        <?php endif; ?>
        <?php if ($order['order_status'] === 'pending' && in_array($order['payment_status'], ['unpaid', 'rejected'], true)): ?>
            <form method="post" action="<?= e(route('order-cancel')) ?>" class="cancel-order-form" data-confirm="Batalkan pesanan ini? Stok akan dikembalikan satu kali."><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><button class="text-button danger-text" type="submit">Batalkan pesanan</button></form>
        <?php endif; ?>
        <?php if (in_array($order['payment_status'], ['paid', 'awaiting_confirmation'], true)): ?><p class="refund-policy">Pembatalan pesanan yang sudah dibayar atau sedang diperiksa admin perlu ditangani langsung. Refund belum tersedia pada versi demo.</p><?php endif; ?>
    </section>

    <aside class="panel timeline-panel"><h2>Riwayat pembaruan</h2>
        <?php if ($logs === []): ?><p class="muted">Belum ada pembaruan tambahan.</p><?php else: ?>
            <ol class="timeline">
                <?php foreach ($logs as $log): ?><li><span class="timeline-dot"></span><strong><?= $log['event_type'] === 'payment' ? 'Pembayaran' : 'Pesanan' ?> · <?= e($log['new_status']) ?></strong><p><?= e($log['note'] ?: 'Status diperbarui oleh admin.') ?></p><time><?= e(format_date($log['created_at'])) ?></time></li><?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </aside>
</div>
<p class="center-link"><?php if (!empty($_SESSION['verified_phone'])): ?><a class="continue-link" href="<?= e(route('order-history')) ?>">Lihat riwayat pesanan</a> <span>·</span><?php endif; ?> <a class="continue-link" href="<?= e(route('order-lookup')) ?>">Verifikasi kode untuk melihat riwayat</a> <span>·</span> <a class="continue-link" href="<?= e(route('home')) ?>">Kembali ke menu</a></p>

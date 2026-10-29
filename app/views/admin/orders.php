<section class="admin-heading"><div><span class="eyebrow eyebrow-dark">ORDER MANAGEMENT</span><h1>Pesanan</h1><p>Lihat detail dan proses status pesanan serta pembayaran.</p></div></section>
<section class="data-panel">
    <form class="filter-form" method="get" action="<?= e(route('admin-orders')) ?>">
        <label class="field"><span>Status pesanan</span><select name="status"><option value="">Semua status</option><?php foreach ($orderStatuses as $status): ?><option value="<?= e($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= e(order_status_label($status)) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Status pembayaran</span><select name="payment"><option value="">Semua status</option><?php foreach ($paymentStatuses as $status): ?><option value="<?= e($status) ?>" <?= $selectedPayment === $status ? 'selected' : '' ?>><?= e(payment_status_label($status)) ?></option><?php endforeach; ?></select></label>
        <button class="button button-primary" type="submit">Filter</button><a class="button button-outline" href="<?= e(route('admin-orders')) ?>">Reset</a>
    </form>
    <div class="table-wrap orders-table"><table class="data-table"><thead><tr><th>Pesanan</th><th>Pemesan</th><th>Pengambilan</th><th>Status</th><th>Pembayaran</th><th>Total</th><th></th></tr></thead><tbody>
    <?php if ($orders === []): ?><tr><td colspan="7" class="table-empty">Belum ada pesanan sesuai filter.</td></tr><?php endif; ?>
    <?php foreach ($orders as $order): ?><tr><td><strong><?= e($order['order_code']) ?></strong><br><small><?= e(format_date($order['created_at'])) ?></small></td><td><?= e($order['customer_name']) ?><br><small><?= e($order['customer_phone']) ?></small></td><td><?= $order['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Pickup' ?></td><td><span class="status-pill status-<?= e($order['order_status']) ?>"><?= e(order_status_label($order['order_status'])) ?></span></td><td><span class="status-pill status-<?= e($order['payment_status']) ?>"><?= e(payment_status_label($order['payment_status'])) ?></span></td><td><?= e(money($order['total_amount'])) ?></td><td><a class="table-action" href="<?= e(route('admin-order', ['id' => $order['id']])) ?>">Detail</a></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>

# Demo decisions and values awaiting owner confirmation

These are temporary values for a classroom demonstration. They are not recommendations for a live Rice Up business.

| Setting | Demo value | Where to change |
|---|---|---|
| Currency | Indonesian rupiah (IDR) | Application formatting helper |
| Nasi Ayam Lava price | Rp25,000 | Initial `INSERT` in `database/rice_up.sql`; later edit in admin |
| Nasi Ayam Sambal Matah price | Rp27,000 | Initial `INSERT` in `database/rice_up.sql`; later edit in admin |
| Nasi Ayam Katsu Cabe Hijau price | Rp29,000 | Initial `INSERT` in `database/rice_up.sql`; later edit in admin |
| Opening stock | 20 portions per product | Initial `INSERT` in `database/rice_up.sql`; later edit in admin |
| Delivery fee | Rp5,000 | `RICEUP_DELIVERY_FEE` environment variable or `delivery_fee` in `config/local.php` |
| Delivery service area | Not restricted by the demo | Confirm supported area before real use |
| Payment methods | Simulated transfer, cash on delivery, cash on pickup | `payment_methods()` in `app/helpers.php` |
| Initial administrator | Created by the operator using `tools/create-admin.ps1` | No default account/password is shipped |
| Expense categories | Ingredients, packaging, transport, utilities, other | Admin expense form; editable per record |
| Product and logo images | Local placeholders | Replace images under `public/assets/images/` or upload in product management |

No real bank account, payment gateway, courier, or customer data is included. A simulated transfer only moves the order into “waiting for admin confirmation”; it does not move money. Cash methods remain unpaid until the admin records a received payment.

## Accounting and cancellation policy

Income is recognized when an admin confirms payment, using `payment_confirmed_at` in the configured Asia/Makassar timezone. Expenses use the selected `expense_date`. The dashboard shows confirmed income minus entered expenses as a simple cash difference; this is not accounting profit.

Refunds are not implemented. A paid order cannot be cancelled or rejected, and the interface explains that policy. Inventory is restored once only for an eligible unpaid order that is cancelled or rejected.

Order transitions are limited to `pending → confirmed/rejected/cancelled`, `confirmed → processing/rejected/cancelled`, `processing → ready`, and `ready → completed`. Completion requires a confirmed payment. Order and payment status changes are logged separately with the acting admin where applicable.

## Items requiring owner confirmation before live use

- Final product descriptions, prices, and opening inventory.
- Delivery fee and service area.
- Accepted payment methods and actual payment instructions, if any.
- Official logo and product photography.
- Expense categories and reporting/accounting policy.
- Production hosting, HTTPS, database credentials, backups, and administrator accounts.

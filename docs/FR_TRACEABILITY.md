# FR-01 to FR-30 traceability

Acceptance evidence was collected on 2026-10-29 using XAMPP PHP/Apache/MariaDB and an isolated `rice_up_test` database. `PASS` means the stated browser or database check actually ran. It does not mean the normal `rice_up` database was created or modified. Test accounts, orders, product, and expense were removed after the run; the three SQL seed products remain in `rice_up_test`.

| FR | Requirement | Implementation | Acceptance evidence |
|---|---|---|---|
| FR-01 | Rice Up homepage | `public/index.php`, `app/views/home.php` | PASS — loaded in browser at 375, 768, and 1440 px. |
| FR-02 | Product catalog from MySQL | `public/index.php`, `database/rice_up.sql` | PASS — three seeded products rendered from `rice_up_test`; seed names, prices, and opening stock checked. |
| FR-03 | Product image, details, price, and stock | `app/views/home.php`, `app/views/product.php` | PASS — detail page opened; no broken images across tested pages and sizes. |
| FR-04 | Add to cart | `app/CustomerController.php`, session cart | PASS — product added from catalog and cart count/contents updated. |
| FR-05 | Change quantity/remove cart item | `app/CustomerController.php`, `app/views/cart.php` | PASS — quantity changed and subtotal recalculated; remove cleared the cart. |
| FR-06 | Checkout validation | `app/CustomerController.php` | PASS — invalid Indonesian phone was rejected and cart retained; valid phone completed checkout. |
| FR-07 | Server validates price and stock | `app/OrderService.php` | PASS — MySQL regression supplied forged client totals/prices; persisted totals still used locked database prices. Oversell is rejected. |
| FR-08 | Persist order and item snapshots | `app/OrderService.php`, `database/rice_up.sql` | PASS — pickup and delivery orders persisted; integration regression confirmed historical name/price snapshots survive product edits. |
| FR-09 | Consistent stock deduction | Transaction and conditional stock update in `app/OrderService.php` | PASS — browser stock changed on checkout; two concurrent MySQL workers competing for one portion produced one order and no negative stock; rollback and cancellation restoration verified. |
| FR-10 | Delivery or pickup | Checkout fields and server validation in `app/CustomerController.php` | PASS — both flows completed; pickup had no fee, delivery added Rp5.000. |
| FR-11 | Simulated payment | `payment_methods()` in `app/helpers.php`; workflow in `app/OrderService.php` | PASS — transfer request, admin confirmation, and admin rejection were exercised. Alternate payment method was not separately completed. |
| FR-12 | Unique order code and status | `app/OrderService.php`, `app/views/order.php` | PASS — unique browser order codes persisted; status and payment status rendered separately. |
| FR-13 | Secure order status/history access | Customer session and order-code plus normalized-phone verification | PASS — customer looked up orders with code and phone; unauthorized admin route was redirected. Other-phone privacy enumeration was not separately attempted. |
| FR-14 | Admin login/logout | `app/AdminController.php`, `tools/create-admin.ps1` | PASS — temporary local admin logged in and out through Apache; setup script selects XAMPP PHP and validates required extensions. |
| FR-15 | Admin authorization | `require_admin()` and protected routes | PASS — logged-out direct admin request returned HTTP 303 to login; missing CSRF token returned HTTP 400 before mutation. |
| FR-16 | Add products | `app/AdminController.php`, `app/views/admin/product-form.php` | PASS — synthetic product created in the admin UI. |
| FR-17 | View products | `app/views/admin/products.php` | PASS — seeded and synthetic products with stock/status rendered. |
| FR-18 | Edit products | `app/AdminController.php` | PASS — synthetic product name and price edited in the admin UI. |
| FR-19 | Deactivate/delete products safely | Product toggle and delete handling in `app/AdminController.php` | PASS — deactivation tested and the synthetic product cleaned up. Hard-delete and historical-product deletion paths were not exercised. |
| FR-20 | Update stock | Admin product form and save handler | PASS — synthetic product stock changed from 9 to 7 through the UI. |
| FR-21 | View order list/detail | `app/views/admin/orders.php`, `app/views/admin/order-detail.php` | PASS — order list and pickup/delivery details opened; detail pages also checked at 375, 768, and 1440 px. |
| FR-22 | Change order status | `OrderService::transition()` | PASS — pending → confirmed → processing → ready → completed and pending → rejected exercised; invalid skips and repeated final transition rejected. |
| FR-23 | Confirm/reject payment | `OrderService::payment()` | PASS — payment confirmation and rejection exercised; unauthorized confirmation, duplicate confirmation, and rejected → paid were rejected. |
| FR-24 | Log important state changes | `order_status_logs` | PASS — status and payment changes appeared in the order detail log. |
| FR-25 | Record expenses | `app/AdminController.php`, `app/views/admin/expenses.php` | PASS — synthetic Rp12.000 expense saved and displayed. |
| FR-26 | Show confirmed income | Report filters paid orders by `payment_confirmed_at` | PASS — report showed Rp50.000 confirmed income and one paid order; regression checks excluded unpaid/rejected orders. |
| FR-27 | Daily/monthly expenses | Report groups expenses by selected date range | PASS — browser daily/monthly values matched; MySQL regressions checked inclusive first/last date and exclusion of the prior month. |
| FR-28 | Simple cash difference | Confirmed income less recorded expenses | PASS — report showed Rp38.000 for Rp50.000 income less Rp12.000 expense. |
| FR-29 | Expense charts | Dashboard seven-day chart and report charts | PASS — empty dashboard chart rendered after a PHP 8 fix; populated daily and monthly report bars rendered. |
| FR-30 | Validate input and readable errors | Server validation, escaped output, generic failure page/messages | PASS — phone and CSRF errors were visible; helper/view checks passed; no browser console errors were recorded. |

## Cross-cutting invariants

- Checkout recalculates prices from locked database rows, reserves a unique idempotency key, and updates stock inside a transaction.
- Two concurrent checkout workers against one portion created exactly one order and left stock non-negative.
- A two-item checkout with one item sold out rolled back all stock/order/idempotency changes.
- Repeating the same checkout token returns the original order; snapshots remain unchanged after the product is edited.
- Order/payment transitions are serialized on a locked order row and invalid transitions leave the row unchanged.
- Cancellation/rejection restores reserved stock once only when payment is unpaid or rejected; test confirmed stock returns to its original level.
- Paid orders cannot be cancelled/rejected by the available demo flow; refunds are outside this scope.
- Report queries use confirmed payments and inclusive date ranges. Monthly boundary tests included 2026-10-01 and 2026-10-31 and excluded 2026-09-30.

## Not exercised in this acceptance run

- Product image upload success and file-size/type limits.
- Physical product deletion, including deletion of an unused product.
- A second payment method beyond simulated transfer.
- Full production application setup against the normal `rice_up` database was not completed in that acceptance run. The subsequent schema import is recorded in the current gate below; dynamic app verification on `rice_up` remains NOT RUN.
- Browser-level resubmission of the same checkout POST; the same-token retry after cart clearing was verified through the database-backed order service used by the request handler.
- Request-level browser Network panel checks; console logs and loaded images were inspected, but the connector exposes no Network panel/API.

## Final demo setup gate — 2026-10-29

The FR-01–FR-30 `PASS` entries above remain evidence from the completed `rice_up_test` acceptance run. They must not be read as browser acceptance on the final demo database.

| Gate | Result | Evidence |
|---|---|---|
| XAMPP preflight | PASS | Existing XAMPP PHP 8.2.12, Apache 2.4.58, MariaDB 10.4.32; `pdo_mysql`, `mbstring`, and `fileinfo` are enabled. Apache and MariaDB were already running. |
| Demo schema `rice_up` | PASS | Confirmed absent, created, imported from `database/rice_up.sql`; 9 InnoDB tables, 6 foreign keys, 33 index metadata rows, and 3 seed products at stock 20. |
| Apache path mapping | PASS | The absent `D:\xampp\htdocs\rice-up` path now points to this checkout through a directory junction; the browser loaded a static local Rice Up image. |
| PHP homepage route | BLOCKED | Apache executed the route, which rendered the missing-`config/local.php` setup page. No customer or admin workflow was accepted on this route. |
| App DB config and initial admin | NOT RUN | `config/local.php` is absent and the initial admin table was empty at preflight. Secure operator-entered credentials are still required. |
| Dynamic customer/admin flows on `rice_up` | NOT RUN | Blocked until the local DB account, ignored config, and initial admin are prepared. |
| Remaining targeted tests | NOT RUN | Image upload/replacement, both product deletion paths, all configured payment methods, and browser-level duplicate checkout POST remain pending. |
| Final responsive smoke on `rice_up` | NOT RUN | The prior responsive pass applies to `rice_up_test`; demo-database pages have not loaded. |

Current environment status is **BLOCKED**, not accepted for presentation yet. The schema and Apache mapping are ready; the dynamic application and browser workflow remain unverified until the secure credential step completes.

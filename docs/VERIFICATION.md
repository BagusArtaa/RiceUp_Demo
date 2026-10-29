# Verification record — 2026-10-29

## Environment

- XAMPP root: `D:\xampp`.
- Bundled PHP CLI: `D:\xampp\php\php.exe`, PHP 8.2.12; PDO drivers `mysql,sqlite`; `pdo_mysql`, `mbstring`, and `fileinfo` loaded.
- XAMPP Apache: 2.4.58 with PHP 8.2.12. Apache served the app and successfully connected to MariaDB through PDO, confirming the Apache PHP path as well as the CLI binary.
- XAMPP MariaDB: 10.4.32, already running on port 3306 before this acceptance run. It was left running.
- Acceptance Apache used a temporary config in the workspace, bound only to `127.0.0.1:18080`; no permanent system configuration was changed.
- The temporary Apache process has exited and port 18080 is closed. The pre-existing MariaDB service remains running.
- No software was installed. `config/local.php` was not created; test DB settings and a temporary app key were supplied to the XAMPP processes only.

## Database integration

- Created and imported `database/rice_up.sql` into the isolated `rice_up_test` database. The existing `rice_up` database was not created or modified; the original preflight found no `rice_up` schema.
- Import result: 9 InnoDB tables, six foreign-key constraints, primary/unique keys and indexes present, and the three expected seed products at Rp25.000 / Rp27.000 / Rp29.000.
- Schema inspection, seed reads, order writes, product writes, expense writes, transaction rollback, row locking, and foreign-key behavior were exercised against MariaDB. This is a real MySQL/MariaDB run, not a unit-test substitute.
- Acceptance data was cleaned after testing. `rice_up_test` remains in place with only the three active seed products at their original stock of 20 each; orders, expenses, and admin accounts are empty. The database itself was not dropped.

## Executed checks

| Check | Result | Evidence |
|---|---|---|
| XAMPP PHP extensions | PASS | `D:\xampp\php\php.exe -r ...` reported PHP 8.2.12, PDO mysql, `pdo_mysql=yes`, `mbstring=yes`, and `fileinfo=yes`. |
| XAMPP admin setup selection | PASS | `tools/create-admin.ps1` parsed and ran against `rice_up_test`; it selected a PHP executable meeting the required extensions and created a temporary test admin. The account then logged into Apache and was removed. |
| SQL import and metadata | PASS | `database/rice_up.sql` imported; 9 InnoDB tables, 6 foreign keys, indexes, constraints, and 3 seed products verified. |
| PHP syntax | PASS | XAMPP PHP `-l` across 34 project PHP files, including the MySQL regression script. |
| Deterministic helper checks | PASS | `tests/run.php` — 19 checks; ran with a workspace session path to avoid the sandbox denying XAMPP's default `D:\xampp\tmp`. |
| Server-rendered view smoke checks | PASS | `tests/view-smoke.php` — all 17 customer/admin/error views rendered without PHP warnings. |
| MySQL integration | PASS | `tests/mysql-integration.php` — persistence, stock decrement, same-token replay after cart clearing, snapshots, sold-out rejection, cancellation restoration exactly once. |
| MySQL concurrency | PASS | `tests/mysql-concurrency.php` — two workers competing for one unit produced exactly one order and no negative stock. |
| MySQL transaction/status/report regressions | PASS | `tests/mysql-regressions.php` — rollback, forged totals, invalid order/payment transitions, payment retry rejection, stock restoration once, confirmed-revenue filtering, and daily/monthly income/expense boundaries. |
| Apache customer/admin pages | PASS | Homepage, product detail, cart, checkout, order lookup, dashboard, products, orders, order details, expenses, and reports loaded over Apache. |
| CSRF and access guard | PASS | Missing CSRF token returned HTTP 400 without mutation; unauthenticated admin route returned HTTP 303 to login. |
| Admin setup/login/logout | PASS | Admin setup CLI completed in the isolated DB; that account logged in, reached the dashboard, logged out, and could no longer open an admin route. |

To reproduce the database checks, create a separate `rice_up_test` database in phpMyAdmin and import `database/rice_up.sql`; set the process environment to that database and your local XAMPP DB account, then run:

```powershell
$xamppPhp = 'D:\xampp\php\php.exe'
$env:RICEUP_DB_HOST = '127.0.0.1'
$env:RICEUP_DB_NAME = 'rice_up_test'
$env:RICEUP_DB_USER = '<local XAMPP user>'
$env:RICEUP_DB_PASSWORD = '<local XAMPP password>'
$env:RICEUP_APP_KEY = '<random local key, at least 32 characters>'
& $xamppPhp tests/mysql-integration.php
& $xamppPhp tests/mysql-concurrency.php
& $xamppPhp tests/mysql-regressions.php
Remove-Item Env:RICEUP_DB_HOST, Env:RICEUP_DB_NAME, Env:RICEUP_DB_USER, Env:RICEUP_DB_PASSWORD, Env:RICEUP_APP_KEY
```

All MySQL scripts stop unless the selected schema name ends in `_test`. They clean their own uniquely identified fixtures and do not drop schemas. Use local credentials only in a private terminal or ignored local config.

## Customer and admin end-to-end

Customer browser flow passed for catalog and detail view, add/change/remove cart, invalid phone feedback, pickup checkout, delivery checkout, fee calculation, order persistence/stock deduction, simulated payment request, and order lookup with order code plus phone. Pickup produced Rp50.000 for two Rp25.000 items; delivery produced Rp32.000 for one Rp27.000 item plus Rp5.000 delivery.

Admin browser flow passed for login/logout, protected routes, product create/edit/stock update/deactivation, order list/detail, order status progression through completion, rejecting a simulated payment and its order, expense entry, and report filters/charts. The synthetic report showed Rp50.000 confirmed income, Rp12.000 recorded expense, Rp38.000 simple cash difference, and one paid order. The same report was checked in daily and monthly modes.

## Responsive and browser QA

- Checked home, product, cart, checkout, order lookup, admin login, dashboard, product list, order list, order detail, expenses, and reports at 375 × 812, 768 × 1024, and 1440 × 900.
- After the CSS fix, every checked route fit its viewport with no document-level horizontal overflow. All displayed images completed with non-zero natural width. The mobile navigation opened and set `aria-expanded=true`.
- Browser console error/warning log returned no entries.
- Request-level network failures were not separately enumerated because this in-app browser connector exposes console logs but no Network panel/API. Main pages rendered and no broken images were found; the request-level check is therefore **NOT RUN**.
- Mobile report screenshot: `C:\Users\ADVAN\.codex\visualizations\2026\10\29\01a187d2-a1f5-7ee0-bd21-074a6c962750\riceup-report-mobile-qa.png`.

## Defects found and fixed

1. An idempotent checkout retry returned the existing order without the `existing` flag used by the customer feedback message. The service now marks replayed orders, and the DB test replays the token after cart clearing.
2. The dashboard threw a PHP 8 `max()` type error when there were no expense rows. The maximum now receives a single array input; the empty chart renders.
3. Unauthenticated admin login inherited admin-only navigation/logout controls. Those controls now require an authenticated admin session.
4. Submitting the GET report filter removed the `page=admin-reports` query parameter and returned the user to the homepage. The filter now submits the page parameter explicitly; daily and monthly browser filters pass.
5. Admin dashboard grid children retained a wide intrinsic minimum and caused mobile horizontal overflow. Grid tracks/children now allow shrinking, and CSS URLs use the file modification time for browser cache refresh; all tested routes fit the three viewports.
6. The admin setup script previously relied on whichever `php` was first on PATH. It now prefers the PHP bundled with XAMPP, checks PHP version and required extensions, and supports `RICEUP_PHP` as an explicit override.

## Remaining limits and demo setup

- At the time of this acceptance run, the production/demo schema `rice_up` remained untouched. The subsequent demo setup attempt is recorded in the addendum below. The tested app used process-scoped settings for `rice_up_test`.
- Image upload success and size/type limit behavior were not run.
- Physical product deletion and historical-product deletion were not run; product deactivation was tested.
- Payment methods other than simulated transfer were not completed in the browser.
- The MySQL retry regression replays the same persisted checkout token after the cart is empty through the service used by the HTTP endpoint; a literal browser-level resubmission of the original POST was not separately performed.

## Suggested demo scenario (synthetic data)

1. Create/select the demo database, import the schema, and add an admin through `tools/create-admin.ps1`.
2. As a customer, add two Nasi Ayam Lava bowls, use pickup, and submit a valid Indonesian phone number. Expected total: Rp50.000.
3. Use the order code and same phone number to look up the order, then request simulated transfer confirmation.
4. As admin, confirm payment and move the order through confirmed → processing → ready → completed.
5. Create a second one-item Sambal Matah delivery order. Expected total: Rp32.000. Reject its simulated payment, then reject the unpaid order; reserved stock returns once.
6. Record a synthetic Rp12.000 bahan baku expense for the current date. The daily report should show Rp50.000 confirmed income, Rp12.000 expenses, Rp38.000 simple cash difference, and one paid order; the monthly report should include the same rows when the date range covers the month.

## Final demo setup attempt — 2026-10-29

### Environment and database preparation

- Rechecked the existing installation at `D:\xampp`: PHP 8.2.12, Apache 2.4.58, and MariaDB 10.4.32. XAMPP PHP CLI reports `pdo_mysql`, `mbstring`, and `fileinfo` enabled; Apache's loaded modules include `php_module`.
- Apache was already running from `D:\xampp\apache\bin\httpd.exe` on ports 80 and 443. MariaDB was already running from `D:\xampp\mysql\bin\mysqld.exe` on port 3306. Neither service was started twice, stopped, or reconfigured.
- `D:\xampp\htdocs\rice-up` was absent, so a directory junction was created to `D:\TUGAS\RiceUp_Demo`. A browser request to `http://localhost/rice-up/public/assets/images/mark.svg` returned the Rice Up image. This confirms the Apache path mapping; it does not confirm the PHP application is ready.
- A read-only MariaDB preflight found no `rice_up` schema. The schema was then created and `database/rice_up.sql` imported. Verification found 9 InnoDB tables, 6 foreign keys, 33 index metadata rows, and the three active seed products at Rp25.000 / Rp27.000 / Rp29.000 with stock 20 each.
- `rice_up_test` was only inspected; no test script or mutation was run against it during this setup attempt. The preflight observed one existing `order_lookup_attempts` row and left it untouched.
- A synthetic PNG fixture passed XAMPP PHP `finfo` and `getimagesize` checks. Product image upload through the application was not run.
- The browser loaded the dynamic `/rice-up/public/` route and received the application's “Database belum tersambung” setup page. A fresh-tab console check for that page had no error or warning entries; this is a configuration-error state, not a customer page acceptance pass.

### Current gate

| Check | Result | Evidence / blocker |
|---|---|---|
| XAMPP environment preflight | PASS | Existing XAMPP binaries and versions confirmed; required PHP extensions enabled. Apache and MariaDB are already active. |
| `rice_up` schema and seed import | PASS | The schema was absent before setup; import created 9 InnoDB tables, 6 foreign keys, and 3 expected seed products. |
| Application database credentials | NOT RUN | `config/local.php` is absent. A secure local prompt is pending so a least-privilege `rice_up` app account can be created without inventing or exposing a password. |
| Initial demo administrator | NOT RUN | The new database has no admin; the documented CLI setup is waiting for operator-entered credentials. No password was created or printed. |
| PHP homepage route on `rice_up` | BLOCKED | `public/index.php` executed and rendered the expected missing-configuration page; customer/admin views cannot load until local credentials and app key are configured. |
| Customer/admin E2E on `rice_up` | NOT RUN | Blocked on local config and initial admin setup. The prior full E2E remains PASS on `rice_up_test` only. |
| Image upload/replacement, deletion rules, all payment methods, browser POST retry | NOT RUN | No application session was available before the secure credential step. |
| Final responsive browser smoke on `rice_up` | NOT RUN | The earlier responsive pass on `rice_up_test` remains valid; final demo database routes were not loaded. |

**Environment status: BLOCKED.** The database schema and Apache path are prepared, but the dynamic site is not operational until the operator enters a local app DB password and first admin credentials in the secure terminal. Do not treat the existing `rice_up_test` acceptance results as final demo-database E2E evidence.

The configured local URL is `http://localhost/rice-up/public/`. After the secure setup completes, verify that the home page loads before presenting. On presentation day, open `D:\xampp\xampp-control.exe`, start Apache and MySQL only if stopped, and browse to that URL.

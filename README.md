# Rice Up

Rice Up is a PHP and MySQL rice bowl ordering demo built for the E-Business project. It uses server-rendered PHP, PDO, MySQL/MariaDB, HTML, CSS, and vanilla JavaScript. No package manager or external image/font service is required.

## Requirements

- XAMPP with Apache, MySQL/MariaDB, and bundled PHP 8.1 or newer.
- PHP extensions `pdo_mysql`, `mbstring`, and `fileinfo` enabled.
- A browser on the same computer for the local demo.

## Run with XAMPP

1. Copy this project folder to `<XAMPP_ROOT>\htdocs\rice-up` (for example, `C:\xampp\htdocs\rice-up` or `D:\xampp\htdocs\rice-up`).
2. In XAMPP Control Panel, start **Apache** and **MySQL**.
3. Confirm that the PHP bundled with this XAMPP has the required extensions. Replace the example path with your XAMPP root:

   ```powershell
   $xamppPhp = 'C:\xampp\php\php.exe'
   & $xamppPhp -r "echo PHP_VERSION, ' | pdo_mysql=', extension_loaded('pdo_mysql') ? 'yes' : 'no', ' | mbstring=', extension_loaded('mbstring') ? 'yes' : 'no', ' | fileinfo=', extension_loaded('fileinfo') ? 'yes' : 'no', PHP_EOL;"
   ```

   Apache and PHP CLI can load different `php.ini` files. Check the bundled PHP executable and the PHP module used by Apache; a different `php` found on `PATH` does not prove XAMPP's `pdo_mysql` support.
4. Open `http://localhost/phpmyadmin/` and inspect whether `rice_up` already exists. Import `database/rice_up.sql` only into a new or verified-empty demo database; preserve any existing rows. The SQL creates missing tables and seeds the three demo products with `INSERT IGNORE`, so it does not reset current stock or prices, but it is not a migration tool.
5. Copy `config/local.example.php` to `config/local.php`. Use a database account scoped to `rice_up` with only the application's required `SELECT`, `INSERT`, `UPDATE`, and `DELETE` privileges; do not put a MariaDB root credential in the app config. Set `base_url` to `/rice-up/public`. Generate a random key with the bundled PHP executable, for example `& $xamppPhp -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`, and put it in `app_key`. The local file is ignored by Git.
6. From the project folder, create the first administrator in a PowerShell terminal:

   ```powershell
   powershell -ExecutionPolicy Bypass -File .\tools\create-admin.ps1
   ```

   The script asks for a username and two masked password entries. Passwords must be at least 12 characters. No default admin account or password is included. The setup script only runs under PHP CLI; it is not an HTTP endpoint. It prefers the PHP bundled with XAMPP based on the project location, checks `pdo_mysql`, `mbstring`, and `fileinfo`, and accepts `RICEUP_PHP` as an explicit executable path override.
7. Open `http://localhost/rice-up/public/` (or `http://localhost/rice-up/` if Apache allows the project `.htaccess` file).
8. Admin access is available from the **Admin** link. To change demo product prices, stock, or descriptions, use the Products page.

### Presentation-day start

On the current machine, `D:\xampp\htdocs\rice-up` is a directory junction to this checkout, so the application can be served without copying it. Open `D:\xampp\xampp-control.exe`, start **Apache** and **MySQL** if they are stopped, then open `http://localhost/rice-up/public/`. Keep the local database config and the admin password private. If the application reports missing configuration, complete steps 4–6 above before the presentation.

If the site says the database is not connected, verify MySQL is running, check `config/local.php`, and confirm `pdo_mysql` is enabled in the PHP configuration used by Apache. The PHP CLI and Apache can load different `php.ini` files.

For hosting, point Apache's document root at `public/`, provide the database settings as environment variables or a private `config/local.php`, enable HTTPS, and configure regular database backups. XAMPP is a local development environment, not public hosting.

## Customer demonstration

1. Open the catalog and choose one of the three seeded products.
2. Add it to the cart, change the quantity, and continue to checkout.
3. Enter a name and Indonesian phone number, then select delivery or pickup. Delivery adds the configurable demo fee.
4. Choose **Transfer simulasi** and submit. The site creates a unique order code and reports the initial order/payment status.
5. Save the order code. Use **Cek pesanan** and the same phone number to retrieve the order after opening a new session.
6. On the order page, mark the simulated transfer as sent to request admin review. This action does not process real funds. The verified session also shows up to 50 recent orders for that phone number.

## Admin demonstration

1. Sign in using the account created by the local CLI setup.
2. Add or edit a menu item, change stock, or deactivate a product.
3. Open the customer's order. Confirm the simulated payment, then move its order status through confirmed → processing → ready → completed.
4. Record a demo expense and open Reports. Select daily or monthly grouping and adjust the date range.
5. Try cancelling a pending unpaid order; inventory is restored once. The application blocks cancellation after payment confirmation because refunds are outside this demo.

## Business demo values

Seed prices (Rp25,000 / Rp27,000 / Rp29,000), 20 portions of opening stock per product, and the Rp5,000 delivery fee are temporary demo values. The delivery service area is not restricted in the demo. Expense categories and sample payment labels are configurable in the application. See [docs/DEMO_DECISIONS.md](docs/DEMO_DECISIONS.md) before using real business data.

Payments are simulated. There is no bank account, real payment gateway, courier integration, or refund flow. “Selisih kas sederhana” is confirmed income minus recorded expenses, not accounting profit.

## Configuration

`config/local.example.php` is a template only. Copy it to `config/local.php` and replace the placeholders. Alternatively use `RICEUP_DB_HOST`, `RICEUP_DB_NAME`, `RICEUP_DB_USER`, `RICEUP_DB_PASSWORD`, `RICEUP_BASE_URL`, `RICEUP_APP_KEY`, and `RICEUP_DELIVERY_FEE` environment variables. Environment variables override the local file.

For uploaded product images, JPEG, PNG, and WebP up to 2 MB are accepted. Uploads receive random names and are not stored in source control.

## Verification

Run the PHP checks from the project folder using the PHP bundled with the same XAMPP installation (adjust the path as needed):

```powershell
$xamppPhp = 'C:\xampp\php\php.exe'
& $xamppPhp tests/run.php
& $xamppPhp tests/view-smoke.php
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & $xamppPhp -l $_.FullName }
```

For database integration, create a separate `rice_up_test` database in phpMyAdmin and import the same schema there. Keep the normal local settings in `config/local.php`, then run:

```powershell
$env:RICEUP_DB_NAME = 'rice_up_test'
& $xamppPhp tests/mysql-integration.php
& $xamppPhp tests/mysql-concurrency.php
& $xamppPhp tests/mysql-regressions.php
Remove-Item Env:RICEUP_DB_NAME
```

The MySQL scripts refuse database names that do not end in `_test`; they remove only their uniquely identified synthetic fixtures and never drop a database. Do not point them at a database containing demo or user data. `docs/FR_TRACEABILITY.md` maps FR-01 through FR-30 to implementation and acceptance evidence, while `docs/VERIFICATION.md` records exact results and remaining limitations.

## Project structure

- `app/` — configuration loading, PDO, helpers, ordering, controllers, and views.
- `public/` — Apache document entry point, CSS/JavaScript, local illustrations, and image uploads.
- `database/rice_up.sql` — importable schema and demo seed.
- `config/local.example.php` — local configuration template; credentials belong in ignored `config/local.php`.
- `tools/` — CLI-only first-admin setup.
- `tests/` — helper, view, MySQL integration, concurrency, and regression checks.
- `docs/` — demo assumptions, FR traceability, and verification status.

Demo12345678Demo12345678
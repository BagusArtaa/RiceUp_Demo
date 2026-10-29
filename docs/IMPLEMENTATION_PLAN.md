# Rice Up implementation plan

The PRD is the product source of truth. This repository started with no application code and no `AGENTS.md` instructions.

## Capability map and build order

| Capability | Responsibility | Depends on |
|---|---|---|
| Foundation | Configuration, database schema, sessions, shared validation and security helpers | — |
| Customer ordering | Catalog, cart, checkout, order lookup, payment simulation | Foundation |
| Admin operations | Admin setup/login, products, inventory, order and payment workflows | Foundation, Customer ordering |
| Finance | Expenses, confirmed-payment income, date filters, cash summary, charts | Foundation, Admin operations |
| Delivery and verification | Responsive layout, security review, traceability, XAMPP and demo documentation | All prior capabilities |

Build order: Foundation → Customer ordering → Admin operations → Finance → Delivery and verification.

## Business decisions held as demo configuration

Prices, opening stock, delivery fee, initial payment method labels, service coverage, and the first administrator are not final business facts. Sample product prices and inventory live in the SQL seed; the delivery fee is configurable. The seed does not contain a password or real payment details. See [DEMO_DECISIONS.md](DEMO_DECISIONS.md).

## Verification contract

- PHP lint and small deterministic CLI checks run locally.
- MySQL transaction, concurrency, browser, Apache, and phpMyAdmin checks require an XAMPP MySQL/Apache environment; this machine currently exposes PHP CLI without `pdo_mysql` and has no MySQL/Apache service.
- The final FR matrix must distinguish implemented behavior from checks actually run.

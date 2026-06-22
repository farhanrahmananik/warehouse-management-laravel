# Warehouse Management - Laravel

Warehouse Management - Laravel is a production-style Warehouse and Inventory Management System built with Laravel 12. It demonstrates a full web application architecture for catalog management, warehouses, stock operations, purchase order workflows, reporting, exports, audit logs, and dashboard visibility.

## Core Features

| Domain | Features |
| --- | --- |
| Authentication & Authorization | Session-based login/logout, custom roles and permissions, permission middleware, Gates, and protected Blade visibility. |
| Catalog Management | Products, categories, suppliers, units, validation, soft deletes, and permission-protected CRUD screens. |
| Warehouse Management | Warehouse master data with create, read, update, delete, validation, authorization, and soft deletes. |
| Stock Management | Stock overview, stock adjustments, stock in, stock out, stock transfer, warehouse stock balances, and stock movement ledger. |
| Purchase Orders | Draft, approve, cancel, receive, purchase order items, status transitions, and receiving into stock. |
| Reports | Inventory, stock movement, low stock, and purchase order report pages. |
| CSV Export Reports | Permission-protected CSV exports for implemented reports. |
| Audit Logs | Read-only audit log UI plus audit events for authentication, catalog, warehouse, stock, and purchase order actions. |
| Dashboard | Read-only dashboard summaries for catalog, warehouse, stock, purchase orders, low stock, and recent movements. |

## Tech Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Database | MySQL for local development |
| Testing Database | SQLite in-memory via `phpunit.xml` |
| Frontend Assets | Bootstrap 5, Vite |
| Testing | PHPUnit, Laravel feature tests |
| Version Control | Git and GitHub |

## Architecture Highlights

- Thin controllers that delegate validation to Form Requests and business behavior to services.
- Service layer for stock mutations, purchase order workflows, report queries, dashboard data, and audit logging.
- Eloquent models with explicit relationships for users, roles, permissions, catalog data, warehouses, stock documents, purchase orders, and audit logs.
- Permission-based route protection using custom RBAC middleware and Laravel Gates.
- Feature tests for authentication, authorization, CRUD workflows, stock rules, purchase orders, reports, exports, dashboard data, and audit logging.
- Audit logging for important user and business actions, with sensitive-value sanitization.

## Documentation

- [Database Design](docs/database/database-design.md)
- [Entity Relationship Diagram](docs/database/erd.md)
- [Testing Guide](docs/testing/testing-guide.md)

## Installation

Clone the repository, install dependencies, configure the environment, and run the migrations:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Before running `php artisan migrate --seed`, configure `.env` for your local MySQL database connection.

The Super Admin seed user is environment-driven. Set these values in `.env` before seeding:

```env
SEED_SUPER_ADMIN_NAME=
SEED_SUPER_ADMIN_EMAIL=
SEED_SUPER_ADMIN_PASSWORD=
```

Credentials are intentionally not hardcoded in the repository.

## Testing

Run the automated test suite:

```bash
php artisan test
```

Run the frontend build check:

```bash
npm run build
```

Current baseline: **249 tests passing with 1455 assertions**.

The test suite uses SQLite in-memory for automated tests, as configured in `phpunit.xml`, so test runs do not touch the local MySQL development database.

## Default Login / Seeded Access

The project includes RBAC seeders for roles, permissions, role-permission mappings, product units, and the initial Super Admin user. The Super Admin account is created by `SuperAdminSeeder` using:

- `SEED_SUPER_ADMIN_NAME`
- `SEED_SUPER_ADMIN_EMAIL`
- `SEED_SUPER_ADMIN_PASSWORD`

If the email or password value is missing, the seeder fails with a clear error instead of creating an unsafe default account.

## Git Workflow

Development is organized around focused feature branches and pull requests.

```text
feature/<scope-name>
```

Common commit prefixes used in this project:

```text
docs: ...
feat: ...
test: ...
fix: ...
```

Before opening a pull request, run:

```bash
git status --short
php artisan test
npm run build
git diff --stat
```

## Project Status

Core warehouse and inventory workflows are implemented, including catalog management, warehouse CRUD, stock overview, stock adjustments, stock in/out/transfer workflows, purchase orders, reports, CSV exports, dashboard summaries, audit logs, and testing documentation.

## Learning / Portfolio Value

This project demonstrates practical Laravel application architecture: RBAC, permission middleware, service-oriented workflow logic, stock ledger rules, purchase order state transitions, reporting, auditability, and automated feature testing for both happy paths and business-rule failures.

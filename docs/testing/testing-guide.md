# Testing Guide

## Testing Philosophy

Warehouse Management - Laravel uses feature tests heavily to verify real application behavior across the main workflows. The test suite focuses on authentication, authorization, CRUD flows, stock movement rules, purchase order behavior, reports, exports, audit logs, and business-rule enforcement.

Tests should prove both successful behavior and protected failure paths. A passing feature is not only one that works for an authorized user; it must also reject guests, unauthorized users, invalid data, and unsafe state transitions.

## Test Environment

The test environment is configured in `phpunit.xml`.

Current test database configuration:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

This means `php artisan test` uses an in-memory SQLite database for the test process. Tests should not touch the local MySQL development database. This isolation is important because many feature tests use `RefreshDatabase`, which migrates and resets database state during the run. Running those tests against a local MySQL database could wipe or alter development data.

Other important test environment settings include:

- `APP_ENV=testing`
- `CACHE_STORE=array`
- `QUEUE_CONNECTION=sync`
- `SESSION_DRIVER=array`
- `MAIL_MAILER=array`
- `BCRYPT_ROUNDS=4`

## How to Run Tests

Run the full test suite:

```bash
php artisan test
```

Run focused tests while developing a specific scope:

```bash
php artisan test --filter=AuthenticationFlowTest
php artisan test --filter=StockTransferWorkflowTest
php artisan test --filter=ReportExportTest
```

Use focused tests during implementation, then run the full suite before opening a pull request.

## How to Run Frontend Build Check

Run the frontend production build:

```bash
npm run build
```

This validates the configured Vite asset pipeline and Bootstrap compilation. If UI libraries such as DataTables or SweetAlert2 are later added to the local asset pipeline, this command should also catch build-time integration issues for those assets.

## Current Test Coverage Matrix

| Scope | Test Files | What is Covered |
| --- | --- | --- |
| Authentication & Authorization | `tests/Feature/Auth/AuthenticationFlowTest.php`<br>`tests/Feature/Auth/AuthorizationMiddlewareTest.php`<br>`tests/Feature/Auth/GateAuthorizationTest.php`<br>`tests/Feature/Auth/BladeAuthorizationTest.php`<br>`tests/Feature/ExampleTest.php` | Login/logout flow, guest redirects, session behavior, authorization middleware, Gate checks, Blade authorization visibility, and homepage redirect behavior. |
| Catalog: Categories, Suppliers, Products | `tests/Feature/Catalog/CategoryManagementTest.php`<br>`tests/Feature/Catalog/SupplierManagementTest.php`<br>`tests/Feature/Catalog/ProductManagementTest.php` | CRUD access, validation, permission checks, generated/default fields, soft deletes, and catalog workflow behavior. |
| Warehouse Management | `tests/Feature/Warehouse/WarehouseManagementTest.php` | Warehouse CRUD, validation, authorization, status handling, and soft delete behavior. |
| Stock Management | `tests/Feature/Stock/StockOverviewTest.php`<br>`tests/Feature/Stock/StockMovementLedgerTest.php`<br>`tests/Feature/Stock/StockAdjustmentTest.php`<br>`tests/Feature/Stock/StockInWorkflowTest.php`<br>`tests/Feature/Stock/StockOutWorkflowTest.php`<br>`tests/Feature/Stock/StockTransferWorkflowTest.php` | Stock overview, movement ledger filters, opening stock/adjustments, stock in, stock out, transfers, quantity mutations, reserved stock protection, duplicate product rejection, and ledger entries. |
| Purchase Orders | `tests/Feature/PurchaseOrder/PurchaseOrderWorkflowTest.php` | Purchase order creation, approval, cancellation, receiving, status transitions, receiving validation, stock updates, and purchase-in stock movements. |
| Dashboard | `tests/Feature/Dashboard/DashboardTest.php` | Dashboard authorization, page access, summary data, low stock display, and recent stock movements. |
| Reports | `tests/Feature/Reports/ReportsAccessTest.php`<br>`tests/Feature/Reports/InventoryReportTest.php`<br>`tests/Feature/Reports/StockMovementReportTest.php`<br>`tests/Feature/Reports/LowStockReportTest.php`<br>`tests/Feature/Reports/PurchaseOrderReportTest.php` | Report access permissions, inventory filters, stock movement filters, low stock filters, purchase order report filters, invalid filter rejection, and report data rendering. |
| Export Reports | `tests/Feature/Reports/ReportExportTest.php` | CSV download access, export permission enforcement, download headers, CSV column headers, seeded export permissions, and filter-aware export output. |
| Audit Logs | `tests/Feature/Audit/AuditLogServiceTest.php`<br>`tests/Feature/Audit/AuditLogAccessTest.php`<br>`tests/Feature/Audit/AuthAuditLogTest.php`<br>`tests/Feature/Audit/CatalogAuditLogTest.php`<br>`tests/Feature/Audit/WarehouseAuditLogTest.php`<br>`tests/Feature/Audit/StockAdjustmentAuditLogTest.php`<br>`tests/Feature/Audit/StockInAuditLogTest.php`<br>`tests/Feature/Audit/StockOutAuditLogTest.php`<br>`tests/Feature/Audit/StockTransferAuditLogTest.php`<br>`tests/Feature/Audit/PurchaseOrderAuditLogTest.php`<br>`tests/Feature/Audit/PurchaseOrderReceiveAuditLogTest.php` | Audit log creation, sanitization, request context, read-only audit log UI access, authentication audit events, catalog/warehouse/stock/purchase order audit events, and failure cases that must not create audit records. |

## Business Rules Covered by Tests

The feature tests cover business rules and security behavior such as:

- Unauthorized users receive `403` responses.
- Guests are redirected to login.
- Stock cannot go below zero.
- Stock cannot go below reserved quantity.
- Duplicate product rows are rejected in stock workflows.
- Purchase orders cannot be received in draft state.
- Approved purchase orders cannot be edited or deleted.
- Invalid report filters are rejected.
- CSV export requires `reports.export` permission.
- Failed validation does not create audit logs.
- Failed stock mutations do not create stock movements or audit logs.
- Successful stock mutations create the expected warehouse stock and ledger entries.

## Common Testing Mistakes

Avoid these mistakes when adding or running tests:

- Running tests against the local MySQL development database accidentally.
- Forgetting to use `RefreshDatabase` for database-backed feature tests.
- Creating test users without the permissions required by route middleware.
- Testing only happy paths and skipping negative or unauthorized cases.
- Asserting only that a page loads without checking important data or state changes.
- Forgetting to verify that failed validation leaves data unchanged.
- Leaving generated build artifacts, cache files, or unrelated local changes in Git.
- Depending on production seed data instead of creating test data inside the test.

## Pre-PR Quality Checklist

Before opening a pull request, run:

```bash
git status --short
php artisan test
npm run build
git diff --stat
```

Also review:

- New or changed tests cover both success and failure paths.
- Permission-sensitive routes have authorization tests.
- Stock and purchase order changes include business-rule regression tests.
- Audit-log integrations test both created logs and failure cases.
- Documentation is updated when behavior, commands, setup, or workflow expectations change.

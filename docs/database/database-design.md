# Warehouse Management - Laravel Database Design

## Purpose

This document describes the database structure currently implemented in the Laravel 12 Warehouse Management project. It reflects the active migrations in `database/migrations`, not an earlier conceptual design.

The design supports authentication and authorization, product catalog management, suppliers, warehouses, warehouse stock balances, inventory movement history, stock documents, purchase orders, reports, dashboard summaries, and audit logs.

## Database Scope

- Application: Warehouse Management - Laravel
- Framework: Laravel 12
- Local development database: MySQL
- Automated test database: SQLite in-memory through `phpunit.xml`
- Primary schema source: Laravel migrations in `database/migrations`

Laravel framework support tables such as password reset tokens, sessions, cache, and jobs are created by default migrations. The core warehouse management schema documented below focuses on the project tables used by the application modules.

## Design Principles

- Use plural `snake_case` table names.
- Use `id` as the primary key.
- Use singular foreign key names such as `product_id`, `warehouse_id`, and `created_by`.
- Do not store current stock quantity in the `products` table.
- Store current stock per warehouse/product in `warehouse_stocks`.
- Store inventory history in `stock_movements`.
- Store source documents for stock in, stock out, and stock transfer workflows.
- Use soft deletes for master data and purchase order headers where historical references matter.
- Use restrictive deletes for master data references and cascade deletes only for dependent item rows or pure pivot rows.
- Use `event` and `module` in `audit_logs` for searchable audit history.
- Low stock is calculated from `products.reorder_level` and `warehouse_stocks`; it is not stored as a persisted alert table in the current implementation.

## Implemented Table Groups

| Group | Tables |
| --- | --- |
| Authentication & Authorization | `users`, `roles`, `permissions`, `role_user`, `role_permission` |
| Product Catalog | `categories`, `units`, `products` |
| Supplier Management | `suppliers` |
| Warehouse Management | `warehouses`, `warehouse_stocks` |
| Inventory Ledger | `stock_movements` |
| Purchase Order Workflow | `purchase_orders`, `purchase_order_items` |
| Stock In | `stock_ins`, `stock_in_items` |
| Stock Out | `stock_outs`, `stock_out_items` |
| Stock Transfer | `stock_transfers`, `stock_transfer_items` |
| Audit Logs | `audit_logs` |

## Authentication & Authorization

### users

The `users` table is the Laravel authentication table.

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| name | VARCHAR | Required |
| email | VARCHAR | Required, unique |
| email_verified_at | TIMESTAMP | Nullable |
| password | VARCHAR | Required |
| remember_token | VARCHAR | Nullable |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

### roles

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| name | VARCHAR(100) | Required |
| slug | VARCHAR(120) | Required, unique |
| description | TEXT | Nullable |
| is_active | BOOLEAN | Default true, indexed |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |
| deleted_at | TIMESTAMP | Soft delete |

### permissions

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| name | VARCHAR(150) | Required |
| slug | VARCHAR(180) | Required, unique |
| module | VARCHAR(100) | Required, indexed |
| description | TEXT | Nullable |
| is_active | BOOLEAN | Default true, indexed |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |
| deleted_at | TIMESTAMP | Soft delete |

### role_user

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| user_id | BIGINT UNSIGNED | FK to `users.id`, cascade on delete |
| role_id | BIGINT UNSIGNED | FK to `roles.id`, cascade on delete |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

Constraint: unique pair on `user_id` and `role_id`.

### role_permission

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| role_id | BIGINT UNSIGNED | FK to `roles.id`, cascade on delete |
| permission_id | BIGINT UNSIGNED | FK to `permissions.id`, cascade on delete |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

Constraint: unique pair on `role_id` and `permission_id`.

## Product Catalog and Suppliers

### categories

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| name | VARCHAR(150) | Required |
| slug | VARCHAR(180) | Required, unique |
| description | TEXT | Nullable |
| is_active | BOOLEAN | Default true, indexed |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |
| deleted_at | TIMESTAMP | Soft delete |

### units

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| name | VARCHAR(100) | Required |
| short_name | VARCHAR(30) | Required, unique |
| description | TEXT | Nullable |
| is_active | BOOLEAN | Default true, indexed |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |
| deleted_at | TIMESTAMP | Soft delete |

### suppliers

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| name | VARCHAR(180) | Required, indexed |
| company_name | VARCHAR(180) | Nullable |
| email | VARCHAR(180) | Nullable, indexed |
| phone | VARCHAR(50) | Nullable, indexed |
| address | TEXT | Nullable |
| tax_number | VARCHAR(100) | Nullable |
| opening_balance | DECIMAL(15,2) | Default 0 |
| current_balance | DECIMAL(15,2) | Default 0 |
| is_active | BOOLEAN | Default true, indexed |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |
| deleted_at | TIMESTAMP | Soft delete |

Supplier balances are stored on the supplier record, but purchase order receiving currently updates stock only and does not mutate supplier accounting balances.

### products

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| category_id | BIGINT UNSIGNED | FK to `categories.id`, indexed, restrict on delete |
| unit_id | BIGINT UNSIGNED | FK to `units.id`, indexed, restrict on delete |
| name | VARCHAR(180) | Required |
| slug | VARCHAR(220) | Required, unique |
| sku | VARCHAR(100) | Required, unique |
| barcode | VARCHAR(150) | Nullable, unique |
| description | TEXT | Nullable |
| purchase_price | DECIMAL(15,2) | Default 0 |
| selling_price | DECIMAL(15,2) | Default 0 |
| reorder_level | DECIMAL(15,2) | Default 0 |
| is_active | BOOLEAN | Default true, indexed |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |
| deleted_at | TIMESTAMP | Soft delete |

Important design rule: `products` does not contain `quantity`, `stock`, or `available_stock`. Stock is calculated from `warehouse_stocks` and explained by `stock_movements`.

## Warehouse and Inventory

### warehouses

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| code | VARCHAR | Required, unique |
| name | VARCHAR | Required |
| contact_person | VARCHAR | Nullable |
| phone | VARCHAR | Nullable |
| email | VARCHAR | Nullable |
| address | TEXT | Nullable |
| city | VARCHAR | Nullable |
| is_active | BOOLEAN | Default true, indexed |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |
| deleted_at | TIMESTAMP | Soft delete |

### warehouse_stocks

The `warehouse_stocks` table stores the current balance for a product in a warehouse.

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| warehouse_id | BIGINT UNSIGNED | FK to `warehouses.id`, indexed, restrict on delete |
| product_id | BIGINT UNSIGNED | FK to `products.id`, indexed, restrict on delete |
| quantity | DECIMAL(15,4) | Default 0 |
| reserved_quantity | DECIMAL(15,4) | Default 0 |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

Constraint: unique pair on `warehouse_id` and `product_id`.

Available stock is calculated as:

```text
quantity - reserved_quantity
```

### stock_movements

The `stock_movements` table is the inventory ledger. Every successful stock mutation creates one or more movement rows.

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| warehouse_id | BIGINT UNSIGNED | FK to `warehouses.id`, indexed, restrict on delete |
| product_id | BIGINT UNSIGNED | FK to `products.id`, indexed, restrict on delete |
| movement_type | VARCHAR | Required, indexed |
| quantity | DECIMAL(15,4) | Required |
| balance_after | DECIMAL(15,4) | Required |
| reference_type | VARCHAR | Nullable |
| reference_id | BIGINT UNSIGNED | Nullable |
| remarks | TEXT | Nullable |
| created_by | BIGINT UNSIGNED | Nullable FK to `users.id`, null on delete |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

Indexes:

- `warehouse_id`
- `product_id`
- `movement_type`
- `created_by`
- `reference_type`, `reference_id`

Implemented movement types:

| Movement type | Source workflow |
| --- | --- |
| opening_balance | Stock adjustment |
| adjustment_in | Stock adjustment |
| adjustment_out | Stock adjustment |
| purchase_in | Purchase order receiving |
| stock_in | Stock In document |
| stock_out | Stock Out document |
| transfer_in | Stock Transfer document |
| transfer_out | Stock Transfer document |

The implemented services store movement quantities as positive values. Direction is represented by `movement_type`, while `balance_after` records the resulting warehouse/product balance.

## Purchase Order Workflow

### purchase_orders

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| po_number | VARCHAR(50) | Required, unique |
| supplier_id | BIGINT UNSIGNED | FK to `suppliers.id`, indexed, restrict on delete |
| warehouse_id | BIGINT UNSIGNED | FK to `warehouses.id`, indexed, restrict on delete |
| status | VARCHAR(30) | Default `draft`, indexed |
| order_date | DATE | Required, indexed |
| expected_date | DATE | Nullable, indexed |
| subtotal | DECIMAL(12,2) | Default 0 |
| discount_amount | DECIMAL(12,2) | Default 0 |
| tax_amount | DECIMAL(12,2) | Default 0 |
| shipping_amount | DECIMAL(12,2) | Default 0 |
| total_amount | DECIMAL(12,2) | Default 0 |
| notes | TEXT | Nullable |
| approved_at | TIMESTAMP | Nullable |
| approved_by | BIGINT UNSIGNED | Nullable FK to `users.id`, null on delete |
| received_at | TIMESTAMP | Nullable |
| received_by | BIGINT UNSIGNED | Nullable FK to `users.id`, null on delete |
| cancelled_at | TIMESTAMP | Nullable |
| cancelled_by | BIGINT UNSIGNED | Nullable FK to `users.id`, null on delete |
| created_by | BIGINT UNSIGNED | Nullable FK to `users.id`, null on delete |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |
| deleted_at | TIMESTAMP | Soft delete |

Implemented statuses:

| Status | Meaning |
| --- | --- |
| draft | Purchase order can be edited or deleted |
| approved | Purchase order can be received |
| partially_received | Some ordered quantities have been received |
| received | All ordered quantities have been received |
| cancelled | Purchase order has been cancelled |

### purchase_order_items

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| purchase_order_id | BIGINT UNSIGNED | FK to `purchase_orders.id`, indexed, cascade on delete |
| product_id | BIGINT UNSIGNED | FK to `products.id`, indexed, restrict on delete |
| quantity | DECIMAL(12,3) | Required |
| received_quantity | DECIMAL(12,3) | Default 0 |
| unit_cost | DECIMAL(12,2) | Required |
| line_total | DECIMAL(12,2) | Required |
| notes | TEXT | Nullable |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

Constraint: unique pair on `purchase_order_id` and `product_id`.

## Stock Document Workflows

Stock In, Stock Out, and Stock Transfer are document-based workflows. Documents are created through services that update `warehouse_stocks` inside database transactions and create matching `stock_movements` ledger rows.

### stock_ins

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| document_no | VARCHAR(50) | Required, unique |
| warehouse_id | BIGINT UNSIGNED | FK to `warehouses.id`, indexed, restrict on delete |
| stock_date | DATE | Required, indexed |
| remarks | TEXT | Nullable |
| created_by | BIGINT UNSIGNED | Nullable FK to `users.id`, indexed, null on delete |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

### stock_in_items

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| stock_in_id | BIGINT UNSIGNED | FK to `stock_ins.id`, indexed, cascade on delete |
| product_id | BIGINT UNSIGNED | FK to `products.id`, indexed, restrict on delete |
| quantity | DECIMAL(15,4) | Required |
| remarks | TEXT | Nullable |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

### stock_outs

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| document_no | VARCHAR(50) | Required, unique |
| warehouse_id | BIGINT UNSIGNED | FK to `warehouses.id`, indexed, restrict on delete |
| stock_date | DATE | Required, indexed |
| remarks | TEXT | Nullable |
| created_by | BIGINT UNSIGNED | Nullable FK to `users.id`, indexed, null on delete |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

### stock_out_items

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| stock_out_id | BIGINT UNSIGNED | FK to `stock_outs.id`, indexed, cascade on delete |
| product_id | BIGINT UNSIGNED | FK to `products.id`, indexed, restrict on delete |
| quantity | DECIMAL(15,4) | Required |
| remarks | TEXT | Nullable |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

### stock_transfers

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| document_no | VARCHAR(50) | Required, unique |
| from_warehouse_id | BIGINT UNSIGNED | FK to `warehouses.id`, indexed, restrict on delete |
| to_warehouse_id | BIGINT UNSIGNED | FK to `warehouses.id`, indexed, restrict on delete |
| transfer_date | DATE | Required, indexed |
| remarks | TEXT | Nullable |
| created_by | BIGINT UNSIGNED | Nullable FK to `users.id`, indexed, null on delete |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

### stock_transfer_items

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| stock_transfer_id | BIGINT UNSIGNED | FK to `stock_transfers.id`, indexed, cascade on delete |
| product_id | BIGINT UNSIGNED | FK to `products.id`, indexed, restrict on delete |
| quantity | DECIMAL(15,4) | Required |
| remarks | TEXT | Nullable |
| created_at | TIMESTAMP | Laravel timestamp |
| updated_at | TIMESTAMP | Laravel timestamp |

## Audit Logs

### audit_logs

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED | Primary key |
| user_id | BIGINT UNSIGNED | Nullable FK to `users.id`, indexed, null on delete |
| event | VARCHAR(100) | Required, indexed |
| module | VARCHAR(100) | Required, indexed |
| auditable_type | VARCHAR | Nullable polymorphic type |
| auditable_id | BIGINT UNSIGNED | Nullable polymorphic id |
| description | TEXT | Nullable |
| old_values | JSON | Nullable |
| new_values | JSON | Nullable |
| ip_address | VARCHAR(45) | Nullable |
| user_agent | TEXT | Nullable |
| url | VARCHAR | Nullable |
| method | VARCHAR(10) | Nullable |
| metadata | JSON | Nullable |
| created_at | TIMESTAMP | Laravel timestamp, indexed |
| updated_at | TIMESTAMP | Laravel timestamp |

Audit logs are append-only application history and do not use soft deletes.

## Relationship Summary

| Parent table | Child table | Foreign key | Cardinality | Business meaning |
| --- | --- | --- | --- | --- |
| users | role_user | user_id | One-to-many pivot rows | Assigns users to roles. |
| roles | role_user | role_id | One-to-many pivot rows | Allows roles to be assigned to users. |
| roles | role_permission | role_id | One-to-many pivot rows | Assigns permissions to roles. |
| permissions | role_permission | permission_id | One-to-many pivot rows | Allows permissions to belong to roles. |
| categories | products | category_id | One-to-many | Groups products by category. |
| units | products | unit_id | One-to-many | Defines the measurement unit for products. |
| suppliers | purchase_orders | supplier_id | One-to-many | Tracks supplier purchase orders. |
| warehouses | purchase_orders | warehouse_id | One-to-many | Tracks the receiving warehouse for purchase orders. |
| purchase_orders | purchase_order_items | purchase_order_id | One-to-many | Stores item lines under each purchase order. |
| products | purchase_order_items | product_id | One-to-many | Identifies ordered products. |
| warehouses | warehouse_stocks | warehouse_id | One-to-many | Stores current product balances by warehouse. |
| products | warehouse_stocks | product_id | One-to-many | Stores current warehouse balances by product. |
| warehouses | stock_movements | warehouse_id | One-to-many | Scopes ledger entries to a warehouse. |
| products | stock_movements | product_id | One-to-many | Scopes ledger entries to a product. |
| users | stock_movements | created_by | One-to-many optional | Records the user responsible for a ledger entry. |
| warehouses | stock_ins | warehouse_id | One-to-many | Identifies the receiving warehouse. |
| stock_ins | stock_in_items | stock_in_id | One-to-many | Stores received product lines. |
| warehouses | stock_outs | warehouse_id | One-to-many | Identifies the issuing warehouse. |
| stock_outs | stock_out_items | stock_out_id | One-to-many | Stores issued product lines. |
| warehouses | stock_transfers | from_warehouse_id | One-to-many | Identifies the source warehouse. |
| warehouses | stock_transfers | to_warehouse_id | One-to-many | Identifies the destination warehouse. |
| stock_transfers | stock_transfer_items | stock_transfer_id | One-to-many | Stores transferred product lines. |
| products | stock_in_items | product_id | One-to-many | Identifies received products. |
| products | stock_out_items | product_id | One-to-many | Identifies issued products. |
| products | stock_transfer_items | product_id | One-to-many | Identifies transferred products. |
| users | purchase_orders | created_by, approved_by, received_by, cancelled_by | Optional one-to-many | Records purchase order actors. |
| users | stock_ins, stock_outs, stock_transfers | created_by | Optional one-to-many | Records stock document creators. |
| users | audit_logs | user_id | Optional one-to-many | Records the user who performed an audited action. |

`stock_movements.reference_type` and `stock_movements.reference_id` are logical references to the source document or workflow. `audit_logs.auditable_type` and `audit_logs.auditable_id` are polymorphic logical references to audited records.

## Foreign Key Delete Strategy

| Table | Foreign key | References | Delete behavior |
| --- | --- | --- | --- |
| role_user | user_id | users.id | Cascade |
| role_user | role_id | roles.id | Cascade |
| role_permission | role_id | roles.id | Cascade |
| role_permission | permission_id | permissions.id | Cascade |
| products | category_id | categories.id | Restrict |
| products | unit_id | units.id | Restrict |
| warehouse_stocks | warehouse_id | warehouses.id | Restrict |
| warehouse_stocks | product_id | products.id | Restrict |
| stock_movements | warehouse_id | warehouses.id | Restrict |
| stock_movements | product_id | products.id | Restrict |
| stock_movements | created_by | users.id | Null on delete |
| purchase_orders | supplier_id | suppliers.id | Restrict |
| purchase_orders | warehouse_id | warehouses.id | Restrict |
| purchase_orders | approved_by | users.id | Null on delete |
| purchase_orders | received_by | users.id | Null on delete |
| purchase_orders | cancelled_by | users.id | Null on delete |
| purchase_orders | created_by | users.id | Null on delete |
| purchase_order_items | purchase_order_id | purchase_orders.id | Cascade |
| purchase_order_items | product_id | products.id | Restrict |
| stock_ins | warehouse_id | warehouses.id | Restrict |
| stock_ins | created_by | users.id | Null on delete |
| stock_in_items | stock_in_id | stock_ins.id | Cascade |
| stock_in_items | product_id | products.id | Restrict |
| stock_outs | warehouse_id | warehouses.id | Restrict |
| stock_outs | created_by | users.id | Null on delete |
| stock_out_items | stock_out_id | stock_outs.id | Cascade |
| stock_out_items | product_id | products.id | Restrict |
| stock_transfers | from_warehouse_id | warehouses.id | Restrict |
| stock_transfers | to_warehouse_id | warehouses.id | Restrict |
| stock_transfers | created_by | users.id | Null on delete |
| stock_transfer_items | stock_transfer_id | stock_transfers.id | Cascade |
| stock_transfer_items | product_id | products.id | Restrict |
| audit_logs | user_id | users.id | Null on delete |

## Migration Order

| Order | Table | Reason |
| ---: | --- | --- |
| 1 | roles | Independent authorization table |
| 2 | permissions | Independent authorization table |
| 3 | role_user | Depends on users and roles |
| 4 | role_permission | Depends on roles and permissions |
| 5 | categories | Parent for products |
| 6 | units | Parent for products |
| 7 | suppliers | Parent for purchase orders |
| 8 | products | Depends on categories and units |
| 9 | warehouses | Parent for stock and purchase order workflows |
| 10 | warehouse_stocks | Depends on warehouses and products |
| 11 | stock_movements | Depends on warehouses, products, and users |
| 12 | purchase_orders | Depends on suppliers, warehouses, and users |
| 13 | purchase_order_items | Depends on purchase orders and products |
| 14 | stock_ins | Depends on warehouses and users |
| 15 | stock_in_items | Depends on stock_ins and products |
| 16 | stock_outs | Depends on warehouses and users |
| 17 | stock_out_items | Depends on stock_outs and products |
| 18 | stock_transfers | Depends on warehouses and users |
| 19 | stock_transfer_items | Depends on stock_transfers and products |
| 20 | audit_logs | Depends on users for actor history |

## Soft Delete Strategy

Soft deletes are implemented for:

- `roles`
- `permissions`
- `categories`
- `units`
- `suppliers`
- `products`
- `warehouses`
- `purchase_orders`

Soft deletes are not implemented for pivots, stock balance rows, stock movement ledger rows, stock document rows, stock document item rows, purchase order item rows, or audit logs.

## Stock Integrity Rules

- Stock mutations must be handled by services, not controllers.
- Stock mutations should run inside database transactions.
- Current stock is updated in `warehouse_stocks`.
- Every successful mutation creates corresponding `stock_movements`.
- Stock Out and Stock Transfer workflows prevent stock from dropping below zero or below reserved quantity.
- Purchase order receiving increases `warehouse_stocks.quantity` and creates `purchase_in` movements.
- Low stock reporting uses `products.reorder_level` and current available stock calculations.

## Portfolio Review Notes

The implemented schema is production-oriented because it separates master data, transactional documents, current balances, ledger history, RBAC, and audit history. The design keeps stock traceable, protects historical references with restrictive foreign keys, and supports feature-tested workflows for warehouse operations, reports, exports, and auditability.

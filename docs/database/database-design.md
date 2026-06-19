\# Warehouse Management Laravel - Database Design



\## Scope



This document defines the database design for a production-style Warehouse and Inventory Management System built with Laravel 12 and MySQL.



\## Database



\- Database Name: warehouse\_management\_laravel

\- Application Name: Warehouse Management

\- Framework: Laravel 12.62.0

\- Database Engine: MySQL

\- Timezone: Europe/Berlin



\## Design Principles



\- Use plural snake\_case table names.

\- Use id as the primary key.

\- Use singular foreign key names such as product\_id, supplier\_id, warehouse\_id.

\- Do not store stock quantity directly inside the products table.

\- Use stock\_movements as the inventory ledger.

\- Use stock\_balances for current stock quantity.

\- Keep audit-friendly columns where needed.

\- Use constraints and indexes for data integrity and performance.



\## Table Groups



\### Authentication \& Authorization



\- users

\- roles

\- permissions

\- role\_user

\- role\_permission



\### Master Data



\- categories

\- units

\- suppliers

\- warehouses

\- warehouse\_locations

\- products



\### Purchase Order



\- purchase\_orders

\- purchase\_order\_items



\### Stock Operations



\- stock\_ins

\- stock\_in\_items

\- stock\_outs

\- stock\_out\_items

\- stock\_transfers

\- stock\_transfer\_items



\### Inventory Ledger



\- stock\_movements

\- stock\_balances



\### Alerts \& Audit



\- low\_stock\_alerts

\- audit\_logs


## Master Data Table Design



Master data tables store the core reusable information of the warehouse system. These tables are created before transaction tables because purchase orders, stock operations, and inventory reports depend on them.



\---



\### categories



The `categories` table stores product categories such as Electronics, Food, Office Supplies, Spare Parts, etc.



| Column      | Type            | Constraint / Notes |

| ----------- | --------------- | ------------------ |

| id          | BIGINT UNSIGNED | Primary key        |

| name        | VARCHAR(150)    | Required           |

| slug        | VARCHAR(180)    | Required, unique   |

| description | TEXT            | Nullable           |

| is\_active   | BOOLEAN         | Default true       |

| created\_at  | TIMESTAMP       | Laravel timestamp  |

| updated\_at  | TIMESTAMP       | Laravel timestamp  |

| deleted\_at  | TIMESTAMP       | Soft delete        |



\#### Constraints and Indexes



\* Unique index on `slug`

\* Index on `is\_active`

\* Soft delete enabled to prevent losing historical product category references



\---



\### units



The `units` table stores measurement units such as pcs, kg, liter, box, carton, packet, meter, etc.



| Column      | Type            | Constraint / Notes |

| ----------- | --------------- | ------------------ |

| id          | BIGINT UNSIGNED | Primary key        |

| name        | VARCHAR(100)    | Required           |

| short\_name  | VARCHAR(30)     | Required, unique   |

| description | TEXT            | Nullable           |

| is\_active   | BOOLEAN         | Default true       |

| created\_at  | TIMESTAMP       | Laravel timestamp  |

| updated\_at  | TIMESTAMP       | Laravel timestamp  |

| deleted\_at  | TIMESTAMP       | Soft delete        |



\#### Constraints and Indexes



\* Unique index on `short\_name`

\* Index on `is\_active`

\* Soft delete enabled because products may still reference old units



\---



\### suppliers



The `suppliers` table stores supplier/vendor information used in purchase orders.



| Column          | Type            | Constraint / Notes |

| --------------- | --------------- | ------------------ |

| id              | BIGINT UNSIGNED | Primary key        |

| name            | VARCHAR(180)    | Required           |

| company\_name    | VARCHAR(180)    | Nullable           |

| email           | VARCHAR(180)    | Nullable           |

| phone           | VARCHAR(50)     | Nullable           |

| address         | TEXT            | Nullable           |

| tax\_number      | VARCHAR(100)    | Nullable           |

| opening\_balance | DECIMAL(15,2)   | Default 0          |

| current\_balance | DECIMAL(15,2)   | Default 0          |

| is\_active       | BOOLEAN         | Default true       |

| created\_at      | TIMESTAMP       | Laravel timestamp  |

| updated\_at      | TIMESTAMP       | Laravel timestamp  |

| deleted\_at      | TIMESTAMP       | Soft delete        |



\#### Constraints and Indexes



\* Index on `name`

\* Index on `email`

\* Index on `phone`

\* Index on `is\_active`

\* Soft delete enabled because purchase history may reference suppliers



\---



\### warehouses



The `warehouses` table stores physical warehouses, branches, or storage points.



| Column       | Type            | Constraint / Notes |

| ------------ | --------------- | ------------------ |

| id           | BIGINT UNSIGNED | Primary key        |

| name         | VARCHAR(180)    | Required           |

| code         | VARCHAR(50)     | Required, unique   |

| email        | VARCHAR(180)    | Nullable           |

| phone        | VARCHAR(50)     | Nullable           |

| address      | TEXT            | Nullable           |

| manager\_name | VARCHAR(180)    | Nullable           |

| is\_active    | BOOLEAN         | Default true       |

| created\_at   | TIMESTAMP       | Laravel timestamp  |

| updated\_at   | TIMESTAMP       | Laravel timestamp  |

| deleted\_at   | TIMESTAMP       | Soft delete        |



\#### Constraints and Indexes



\* Unique index on `code`

\* Index on `name`

\* Index on `is\_active`

\* Soft delete enabled because stock history may reference warehouses



\---



\### warehouse\_locations



The `warehouse\_locations` table stores internal warehouse locations such as zone, aisle, rack, shelf, and bin.



| Column       | Type            | Constraint / Notes        |

| ------------ | --------------- | ------------------------- |

| id           | BIGINT UNSIGNED | Primary key               |

| warehouse\_id | BIGINT UNSIGNED | Foreign key to warehouses |

| name         | VARCHAR(180)    | Required                  |

| code         | VARCHAR(80)     | Required                  |

| zone         | VARCHAR(80)     | Nullable                  |

| aisle        | VARCHAR(80)     | Nullable                  |

| rack         | VARCHAR(80)     | Nullable                  |

| shelf        | VARCHAR(80)     | Nullable                  |

| bin          | VARCHAR(80)     | Nullable                  |

| is\_pickable  | BOOLEAN         | Default true              |

| is\_active    | BOOLEAN         | Default true              |

| created\_at   | TIMESTAMP       | Laravel timestamp         |

| updated\_at   | TIMESTAMP       | Laravel timestamp         |

| deleted\_at   | TIMESTAMP       | Soft delete               |



\#### Relationships



\* Each warehouse location belongs to one warehouse

\* One warehouse can have many warehouse locations



\#### Constraints and Indexes



\* Foreign key: `warehouse\_id` references `warehouses.id`

\* Unique index on `warehouse\_id` and `code`

\* Index on `warehouse\_id`

\* Index on `is\_pickable`

\* Index on `is\_active`



\---



\### products



The `products` table stores product master information. Stock quantity is not stored directly in this table. Current stock is stored in `stock\_balances`, and stock history is stored in `stock\_movements`.



| Column         | Type            | Constraint / Notes        |

| -------------- | --------------- | ------------------------- |

| id             | BIGINT UNSIGNED | Primary key               |

| category\_id    | BIGINT UNSIGNED | Foreign key to categories |

| unit\_id        | BIGINT UNSIGNED | Foreign key to units      |

| name           | VARCHAR(180)    | Required                  |

| slug           | VARCHAR(220)    | Required, unique          |

| sku            | VARCHAR(100)    | Required, unique          |

| barcode        | VARCHAR(150)    | Nullable, unique          |

| description    | TEXT            | Nullable                  |

| purchase\_price | DECIMAL(15,2)   | Default 0                 |

| selling\_price  | DECIMAL(15,2)   | Default 0                 |

| reorder\_level  | DECIMAL(15,2)   | Default 0                 |

| is\_active      | BOOLEAN         | Default true              |

| created\_at     | TIMESTAMP       | Laravel timestamp         |

| updated\_at     | TIMESTAMP       | Laravel timestamp         |

| deleted\_at     | TIMESTAMP       | Soft delete               |



\#### Relationships



\* Each product belongs to one category

\* Each product belongs to one unit

\* One category can have many products

\* One unit can be used by many products



\#### Constraints and Indexes



\* Foreign key: `category\_id` references `categories.id`

\* Foreign key: `unit\_id` references `units.id`

\* Unique index on `slug`

\* Unique index on `sku`

\* Unique index on `barcode`

\* Index on `category\_id`

\* Index on `unit\_id`

\* Index on `is\_active`



\#### Important Design Decision



The `products` table must not contain a direct stock quantity column such as `quantity`, `stock`, or `available\_stock`.



Reason:



\* One product can exist in multiple warehouses

\* One product can exist in multiple locations inside the same warehouse

\* Stock changes need historical tracking

\* Inventory reports need movement-level traceability



Correct design:



\* Current stock: `stock\_balances`

\* Stock history: `stock\_movements`


## Purchase Order Table Design

Purchase order tables store supplier purchase requests before products are received into warehouse stock. A purchase order does not directly increase stock. Stock only increases after a proper stock-in/receiving process.

---

### purchase_orders

The `purchase_orders` table stores the main purchase order header information such as supplier, receiving warehouse, order date, expected delivery date, status, totals, and approval information.

| Column          | Type            | Constraint / Notes             |
| --------------- | --------------- | ------------------------------ |
| id              | BIGINT UNSIGNED | Primary key                    |
| supplier_id     | BIGINT UNSIGNED | Foreign key to suppliers       |
| warehouse_id    | BIGINT UNSIGNED | Foreign key to warehouses      |
| po_number       | VARCHAR(50)     | Required, unique               |
| order_date      | DATE            | Required                       |
| expected_date   | DATE            | Nullable                       |
| status          | VARCHAR(30)     | Default `draft`                |
| subtotal        | DECIMAL(15,2)   | Default 0                      |
| tax_amount      | DECIMAL(15,2)   | Default 0                      |
| discount_amount | DECIMAL(15,2)   | Default 0                      |
| shipping_amount | DECIMAL(15,2)   | Default 0                      |
| grand_total     | DECIMAL(15,2)   | Default 0                      |
| notes           | TEXT            | Nullable                       |
| approved_by     | BIGINT UNSIGNED | Nullable, foreign key to users |
| approved_at     | TIMESTAMP       | Nullable                       |
| created_by      | BIGINT UNSIGNED | Nullable, foreign key to users |
| created_at      | TIMESTAMP       | Laravel timestamp              |
| updated_at      | TIMESTAMP       | Laravel timestamp              |
| deleted_at      | TIMESTAMP       | Soft delete                    |

#### Status Values

The `status` column should follow a controlled lifecycle:

| Status             | Meaning                                         |
| ------------------ | ----------------------------------------------- |
| draft              | Purchase order is being prepared                |
| pending_approval   | Purchase order is submitted for approval        |
| approved           | Purchase order is approved but not received yet |
| partially_received | Some items have been received                   |
| received           | All items have been received                    |
| cancelled          | Purchase order has been cancelled               |

#### Relationships

* Each purchase order belongs to one supplier
* Each purchase order belongs to one receiving warehouse
* Each purchase order can have many purchase order items
* Each purchase order may be approved by one user
* Each purchase order may be created by one user

#### Constraints and Indexes

* Foreign key: `supplier_id` references `suppliers.id`
* Foreign key: `warehouse_id` references `warehouses.id`
* Foreign key: `approved_by` references `users.id`
* Foreign key: `created_by` references `users.id`
* Unique index on `po_number`
* Index on `supplier_id`
* Index on `warehouse_id`
* Index on `status`
* Index on `order_date`
* Index on `expected_date`

#### Important Design Decision

A purchase order should not directly update stock quantity.

Correct workflow:

* Purchase order is created
* Purchase order is approved
* Goods are physically received
* Stock-in record is created
* Stock movement is recorded
* Stock balance is updated

This prevents fake stock increases before goods actually arrive.

---

### purchase_order_items

The `purchase_order_items` table stores the product-level details of each purchase order.

| Column            | Type            | Constraint / Notes             |
| ----------------- | --------------- | ------------------------------ |
| id                | BIGINT UNSIGNED | Primary key                    |
| purchase_order_id | BIGINT UNSIGNED | Foreign key to purchase_orders |
| product_id        | BIGINT UNSIGNED | Foreign key to products        |
| ordered_quantity  | DECIMAL(15,2)   | Required, default 0            |
| received_quantity | DECIMAL(15,2)   | Default 0                      |
| unit_price        | DECIMAL(15,2)   | Default 0                      |
| tax_amount        | DECIMAL(15,2)   | Default 0                      |
| discount_amount   | DECIMAL(15,2)   | Default 0                      |
| line_total        | DECIMAL(15,2)   | Default 0                      |
| notes             | TEXT            | Nullable                       |
| created_at        | TIMESTAMP       | Laravel timestamp              |
| updated_at        | TIMESTAMP       | Laravel timestamp              |

#### Relationships

* Each purchase order item belongs to one purchase order
* Each purchase order item belongs to one product
* One purchase order can have many purchase order items
* One product can appear in many purchase order items

#### Constraints and Indexes

* Foreign key: `purchase_order_id` references `purchase_orders.id`
* Foreign key: `product_id` references `products.id`
* Index on `purchase_order_id`
* Index on `product_id`
* Composite index on `purchase_order_id` and `product_id`

#### Quantity Design

`ordered_quantity` and `received_quantity` use `DECIMAL(15,2)` instead of integer because some products may be measured in decimal units.

Examples:

* 10 pcs
* 15.50 kg
* 3.25 liter

#### Important Design Decision

The `received_quantity` column helps track partial receiving.

Example:

| Product   | Ordered Quantity | Received Quantity |
| --------- | ---------------: | ----------------: |
| Product A |              100 |                60 |

In this case, the purchase order status can become `partially_received`.

When all ordered quantities are received, the purchase order status can become `received`.


## Stock Operation Table Design

Stock operation tables store physical inventory activities such as receiving goods, issuing goods, and transferring goods between warehouses or warehouse locations.

Important rule:

A stock operation record alone should not be treated as final inventory history. Final inventory history will be stored in `stock_movements`, and current stock will be stored in `stock_balances`.

---

### stock_ins

The `stock_ins` table stores the main receiving record when products physically enter a warehouse.

A stock-in may be created from an approved purchase order or directly for manual adjustment/opening stock.

| Column            | Type            | Constraint / Notes                       |
| ----------------- | --------------- | ---------------------------------------- |
| id                | BIGINT UNSIGNED | Primary key                              |
| stock_in_number   | VARCHAR(50)     | Required, unique                         |
| purchase_order_id | BIGINT UNSIGNED | Nullable, foreign key to purchase_orders |
| supplier_id       | BIGINT UNSIGNED | Nullable, foreign key to suppliers       |
| warehouse_id      | BIGINT UNSIGNED | Foreign key to warehouses                |
| received_date     | DATE            | Required                                 |
| status            | VARCHAR(30)     | Default `draft`                          |
| notes             | TEXT            | Nullable                                 |
| received_by       | BIGINT UNSIGNED | Nullable, foreign key to users           |
| approved_by       | BIGINT UNSIGNED | Nullable, foreign key to users           |
| approved_at       | TIMESTAMP       | Nullable                                 |
| created_by        | BIGINT UNSIGNED | Nullable, foreign key to users           |
| created_at        | TIMESTAMP       | Laravel timestamp                        |
| updated_at        | TIMESTAMP       | Laravel timestamp                        |
| deleted_at        | TIMESTAMP       | Soft delete                              |

#### Status Values

| Status           | Meaning                                            |
| ---------------- | -------------------------------------------------- |
| draft            | Receiving record is being prepared                 |
| pending_approval | Receiving record is waiting for approval           |
| approved         | Receiving record is approved                       |
| posted           | Stock movement and stock balance have been updated |
| cancelled        | Receiving record has been cancelled                |

#### Relationships

* Each stock-in may belong to one purchase order
* Each stock-in may belong to one supplier
* Each stock-in belongs to one warehouse
* Each stock-in can have many stock-in items
* Each stock-in may be received by one user
* Each stock-in may be approved by one user
* Each stock-in may be created by one user

#### Constraints and Indexes

* Foreign key: `purchase_order_id` references `purchase_orders.id`
* Foreign key: `supplier_id` references `suppliers.id`
* Foreign key: `warehouse_id` references `warehouses.id`
* Foreign key: `received_by` references `users.id`
* Foreign key: `approved_by` references `users.id`
* Foreign key: `created_by` references `users.id`
* Unique index on `stock_in_number`
* Index on `purchase_order_id`
* Index on `supplier_id`
* Index on `warehouse_id`
* Index on `received_date`
* Index on `status`

---

### stock_in_items

The `stock_in_items` table stores product-level details for each stock-in record.

| Column                | Type            | Constraint / Notes                           |
| --------------------- | --------------- | -------------------------------------------- |
| id                    | BIGINT UNSIGNED | Primary key                                  |
| stock_in_id           | BIGINT UNSIGNED | Foreign key to stock_ins                     |
| product_id            | BIGINT UNSIGNED | Foreign key to products                      |
| warehouse_location_id | BIGINT UNSIGNED | Nullable, foreign key to warehouse_locations |
| quantity              | DECIMAL(15,2)   | Required, default 0                          |
| unit_cost             | DECIMAL(15,2)   | Default 0                                    |
| line_total            | DECIMAL(15,2)   | Default 0                                    |
| batch_number          | VARCHAR(100)    | Nullable                                     |
| expiry_date           | DATE            | Nullable                                     |
| notes                 | TEXT            | Nullable                                     |
| created_at            | TIMESTAMP       | Laravel timestamp                            |
| updated_at            | TIMESTAMP       | Laravel timestamp                            |

#### Relationships

* Each stock-in item belongs to one stock-in
* Each stock-in item belongs to one product
* Each stock-in item may belong to one warehouse location

#### Constraints and Indexes

* Foreign key: `stock_in_id` references `stock_ins.id`
* Foreign key: `product_id` references `products.id`
* Foreign key: `warehouse_location_id` references `warehouse_locations.id`
* Index on `stock_in_id`
* Index on `product_id`
* Index on `warehouse_location_id`
* Index on `batch_number`
* Index on `expiry_date`

---

### stock_outs

The `stock_outs` table stores the main issuing record when products physically leave a warehouse.

Stock-out can happen for sales, damage, internal use, return, correction, or manual adjustment.

| Column           | Type            | Constraint / Notes             |
| ---------------- | --------------- | ------------------------------ |
| id               | BIGINT UNSIGNED | Primary key                    |
| stock_out_number | VARCHAR(50)     | Required, unique               |
| warehouse_id     | BIGINT UNSIGNED | Foreign key to warehouses      |
| stock_out_date   | DATE            | Required                       |
| reason_type      | VARCHAR(50)     | Required                       |
| status           | VARCHAR(30)     | Default `draft`                |
| notes            | TEXT            | Nullable                       |
| issued_by        | BIGINT UNSIGNED | Nullable, foreign key to users |
| approved_by      | BIGINT UNSIGNED | Nullable, foreign key to users |
| approved_at      | TIMESTAMP       | Nullable                       |
| created_by       | BIGINT UNSIGNED | Nullable, foreign key to users |
| created_at       | TIMESTAMP       | Laravel timestamp              |
| updated_at       | TIMESTAMP       | Laravel timestamp              |
| deleted_at       | TIMESTAMP       | Soft delete                    |

#### Reason Types

| Reason Type        | Meaning                         |
| ------------------ | ------------------------------- |
| sale               | Product issued for sale         |
| damage             | Product removed because damaged |
| internal_use       | Product used internally         |
| return_to_supplier | Product returned to supplier    |
| adjustment         | Manual stock correction         |
| other              | Other reason                    |

#### Status Values

| Status           | Meaning                                            |
| ---------------- | -------------------------------------------------- |
| draft            | Stock-out record is being prepared                 |
| pending_approval | Stock-out is waiting for approval                  |
| approved         | Stock-out is approved                              |
| posted           | Stock movement and stock balance have been updated |
| cancelled        | Stock-out has been cancelled                       |

#### Relationships

* Each stock-out belongs to one warehouse
* Each stock-out can have many stock-out items
* Each stock-out may be issued by one user
* Each stock-out may be approved by one user
* Each stock-out may be created by one user

#### Constraints and Indexes

* Foreign key: `warehouse_id` references `warehouses.id`
* Foreign key: `issued_by` references `users.id`
* Foreign key: `approved_by` references `users.id`
* Foreign key: `created_by` references `users.id`
* Unique index on `stock_out_number`
* Index on `warehouse_id`
* Index on `stock_out_date`
* Index on `reason_type`
* Index on `status`

---

### stock_out_items

The `stock_out_items` table stores product-level details for each stock-out record.

| Column                | Type            | Constraint / Notes                           |
| --------------------- | --------------- | -------------------------------------------- |
| id                    | BIGINT UNSIGNED | Primary key                                  |
| stock_out_id          | BIGINT UNSIGNED | Foreign key to stock_outs                    |
| product_id            | BIGINT UNSIGNED | Foreign key to products                      |
| warehouse_location_id | BIGINT UNSIGNED | Nullable, foreign key to warehouse_locations |
| quantity              | DECIMAL(15,2)   | Required, default 0                          |
| unit_cost             | DECIMAL(15,2)   | Default 0                                    |
| line_total            | DECIMAL(15,2)   | Default 0                                    |
| batch_number          | VARCHAR(100)    | Nullable                                     |
| notes                 | TEXT            | Nullable                                     |
| created_at            | TIMESTAMP       | Laravel timestamp                            |
| updated_at            | TIMESTAMP       | Laravel timestamp                            |

#### Relationships

* Each stock-out item belongs to one stock-out
* Each stock-out item belongs to one product
* Each stock-out item may belong to one warehouse location

#### Constraints and Indexes

* Foreign key: `stock_out_id` references `stock_outs.id`
* Foreign key: `product_id` references `products.id`
* Foreign key: `warehouse_location_id` references `warehouse_locations.id`
* Index on `stock_out_id`
* Index on `product_id`
* Index on `warehouse_location_id`
* Index on `batch_number`

#### Important Design Decision

Before posting stock-out, the system must check available stock from `stock_balances`.

Stock-out should not be posted if requested quantity is greater than available quantity.

---

### stock_transfers

The `stock_transfers` table stores the main record for moving products from one warehouse/location to another warehouse/location.

| Column            | Type            | Constraint / Notes             |
| ----------------- | --------------- | ------------------------------ |
| id                | BIGINT UNSIGNED | Primary key                    |
| transfer_number   | VARCHAR(50)     | Required, unique               |
| from_warehouse_id | BIGINT UNSIGNED | Foreign key to warehouses      |
| to_warehouse_id   | BIGINT UNSIGNED | Foreign key to warehouses      |
| transfer_date     | DATE            | Required                       |
| status            | VARCHAR(30)     | Default `draft`                |
| notes             | TEXT            | Nullable                       |
| requested_by      | BIGINT UNSIGNED | Nullable, foreign key to users |
| approved_by       | BIGINT UNSIGNED | Nullable, foreign key to users |
| dispatched_by     | BIGINT UNSIGNED | Nullable, foreign key to users |
| received_by       | BIGINT UNSIGNED | Nullable, foreign key to users |
| approved_at       | TIMESTAMP       | Nullable                       |
| dispatched_at     | TIMESTAMP       | Nullable                       |
| received_at       | TIMESTAMP       | Nullable                       |
| created_by        | BIGINT UNSIGNED | Nullable, foreign key to users |
| created_at        | TIMESTAMP       | Laravel timestamp              |
| updated_at        | TIMESTAMP       | Laravel timestamp              |
| deleted_at        | TIMESTAMP       | Soft delete                    |

#### Status Values

| Status           | Meaning                                              |
| ---------------- | ---------------------------------------------------- |
| draft            | Transfer is being prepared                           |
| pending_approval | Transfer is waiting for approval                     |
| approved         | Transfer is approved                                 |
| in_transit       | Products have left source warehouse                  |
| received         | Products have arrived at destination warehouse       |
| posted           | Stock movements and stock balances have been updated |
| cancelled        | Transfer has been cancelled                          |

#### Relationships

* Each stock transfer belongs to one source warehouse
* Each stock transfer belongs to one destination warehouse
* Each stock transfer can have many transfer items
* Each stock transfer may be requested, approved, dispatched, and received by users

#### Constraints and Indexes

* Foreign key: `from_warehouse_id` references `warehouses.id`
* Foreign key: `to_warehouse_id` references `warehouses.id`
* Foreign key: `requested_by` references `users.id`
* Foreign key: `approved_by` references `users.id`
* Foreign key: `dispatched_by` references `users.id`
* Foreign key: `received_by` references `users.id`
* Foreign key: `created_by` references `users.id`
* Unique index on `transfer_number`
* Index on `from_warehouse_id`
* Index on `to_warehouse_id`
* Index on `transfer_date`
* Index on `status`

#### Important Design Decision

A stock transfer should create two stock movement records after posting:

* OUT movement from source warehouse/location
* IN movement to destination warehouse/location

This keeps the inventory ledger accurate.

---

### stock_transfer_items

The `stock_transfer_items` table stores product-level details for each stock transfer.

| Column                     | Type            | Constraint / Notes                           |
| -------------------------- | --------------- | -------------------------------------------- |
| id                         | BIGINT UNSIGNED | Primary key                                  |
| stock_transfer_id          | BIGINT UNSIGNED | Foreign key to stock_transfers               |
| product_id                 | BIGINT UNSIGNED | Foreign key to products                      |
| from_warehouse_location_id | BIGINT UNSIGNED | Nullable, foreign key to warehouse_locations |
| to_warehouse_location_id   | BIGINT UNSIGNED | Nullable, foreign key to warehouse_locations |
| quantity                   | DECIMAL(15,2)   | Required, default 0                          |
| unit_cost                  | DECIMAL(15,2)   | Default 0                                    |
| line_total                 | DECIMAL(15,2)   | Default 0                                    |
| batch_number               | VARCHAR(100)    | Nullable                                     |
| notes                      | TEXT            | Nullable                                     |
| created_at                 | TIMESTAMP       | Laravel timestamp                            |
| updated_at                 | TIMESTAMP       | Laravel timestamp                            |

#### Relationships

* Each transfer item belongs to one stock transfer
* Each transfer item belongs to one product
* Each transfer item may belong to one source warehouse location
* Each transfer item may belong to one destination warehouse location

#### Constraints and Indexes

* Foreign key: `stock_transfer_id` references `stock_transfers.id`
* Foreign key: `product_id` references `products.id`
* Foreign key: `from_warehouse_location_id` references `warehouse_locations.id`
* Foreign key: `to_warehouse_location_id` references `warehouse_locations.id`
* Index on `stock_transfer_id`
* Index on `product_id`
* Index on `from_warehouse_location_id`
* Index on `to_warehouse_location_id`
* Index on `batch_number`


## Inventory Ledger and Stock Balance Table Design

Inventory ledger and stock balance tables are the core of the warehouse management system.

The system should not calculate current stock from product records. Instead:

* `stock_movements` stores every inventory transaction as a historical ledger.
* `stock_balances` stores the current available quantity per product, warehouse, and warehouse location.

---

### stock_movements

The `stock_movements` table stores immutable inventory movement history. Every posted stock-in, stock-out, or stock-transfer operation should create stock movement records.

This table works like an inventory ledger.

| Column                | Type            | Constraint / Notes                           |
| --------------------- | --------------- | -------------------------------------------- |
| id                    | BIGINT UNSIGNED | Primary key                                  |
| product_id            | BIGINT UNSIGNED | Foreign key to products                      |
| warehouse_id          | BIGINT UNSIGNED | Foreign key to warehouses                    |
| warehouse_location_id | BIGINT UNSIGNED | Nullable, foreign key to warehouse_locations |
| movement_type         | VARCHAR(30)     | Required                                     |
| reference_type        | VARCHAR(100)    | Required                                     |
| reference_id          | BIGINT UNSIGNED | Required                                     |
| quantity              | DECIMAL(15,2)   | Required                                     |
| unit_cost             | DECIMAL(15,2)   | Default 0                                    |
| total_cost            | DECIMAL(15,2)   | Default 0                                    |
| batch_number          | VARCHAR(100)    | Nullable                                     |
| expiry_date           | DATE            | Nullable                                     |
| movement_date         | DATE            | Required                                     |
| notes                 | TEXT            | Nullable                                     |
| created_by            | BIGINT UNSIGNED | Nullable, foreign key to users               |
| created_at            | TIMESTAMP       | Laravel timestamp                            |
| updated_at            | TIMESTAMP       | Laravel timestamp                            |

#### Movement Types

| Movement Type  | Meaning                                            |
| -------------- | -------------------------------------------------- |
| stock_in       | Product quantity increased from receiving          |
| stock_out      | Product quantity decreased from issuing            |
| transfer_in    | Product quantity increased from transfer receiving |
| transfer_out   | Product quantity decreased from transfer dispatch  |
| adjustment_in  | Manual positive stock correction                   |
| adjustment_out | Manual negative stock correction                   |

#### Reference Design

The `reference_type` and `reference_id` columns connect a stock movement to its source document.

Examples:

| Operation          | reference_type | reference_id       |
| ------------------ | -------------- | ------------------ |
| Stock In           | stock_in       | stock_ins.id       |
| Stock Out          | stock_out      | stock_outs.id      |
| Stock Transfer In  | stock_transfer | stock_transfers.id |
| Stock Transfer Out | stock_transfer | stock_transfers.id |

This design keeps the ledger flexible without creating many nullable foreign key columns.

#### Relationships

* Each stock movement belongs to one product
* Each stock movement belongs to one warehouse
* Each stock movement may belong to one warehouse location
* Each stock movement may be created by one user

#### Constraints and Indexes

* Foreign key: `product_id` references `products.id`
* Foreign key: `warehouse_id` references `warehouses.id`
* Foreign key: `warehouse_location_id` references `warehouse_locations.id`
* Foreign key: `created_by` references `users.id`
* Index on `product_id`
* Index on `warehouse_id`
* Index on `warehouse_location_id`
* Index on `movement_type`
* Index on `reference_type` and `reference_id`
* Index on `movement_date`
* Index on `batch_number`
* Index on `expiry_date`

#### Important Design Decision

Stock movements should be treated as historical records.

In a production-level system, posted stock movement records should not be edited directly. If correction is needed, the system should create a new adjustment movement.

Example:

Wrong approach:

* Edit old stock movement quantity from 100 to 80

Correct approach:

* Keep original stock-in movement of 100
* Create adjustment_out movement of 20

This preserves audit history and makes inventory reports trustworthy.

---

### stock_balances

The `stock_balances` table stores current stock quantity per product, warehouse, and warehouse location.

This table is optimized for fast stock checking, dashboard cards, low stock alerts, and availability validation.

| Column                | Type            | Constraint / Notes                           |
| --------------------- | --------------- | -------------------------------------------- |
| id                    | BIGINT UNSIGNED | Primary key                                  |
| product_id            | BIGINT UNSIGNED | Foreign key to products                      |
| warehouse_id          | BIGINT UNSIGNED | Foreign key to warehouses                    |
| warehouse_location_id | BIGINT UNSIGNED | Nullable, foreign key to warehouse_locations |
| quantity_on_hand      | DECIMAL(15,2)   | Default 0                                    |
| quantity_reserved     | DECIMAL(15,2)   | Default 0                                    |
| quantity_available    | DECIMAL(15,2)   | Default 0                                    |
| average_cost          | DECIMAL(15,2)   | Default 0                                    |
| last_movement_at      | TIMESTAMP       | Nullable                                     |
| created_at            | TIMESTAMP       | Laravel timestamp                            |
| updated_at            | TIMESTAMP       | Laravel timestamp                            |

#### Quantity Meaning

| Column             | Meaning                                      |
| ------------------ | -------------------------------------------- |
| quantity_on_hand   | Physical stock currently stored              |
| quantity_reserved  | Stock reserved for future order/issue        |
| quantity_available | Available stock after reservation            |
| average_cost       | Average product cost for inventory valuation |

Formula:

```text
quantity_available = quantity_on_hand - quantity_reserved
```

#### Relationships

* Each stock balance belongs to one product
* Each stock balance belongs to one warehouse
* Each stock balance may belong to one warehouse location

#### Constraints and Indexes

* Foreign key: `product_id` references `products.id`
* Foreign key: `warehouse_id` references `warehouses.id`
* Foreign key: `warehouse_location_id` references `warehouse_locations.id`
* Unique index on `product_id`, `warehouse_id`, and `warehouse_location_id`
* Index on `product_id`
* Index on `warehouse_id`
* Index on `warehouse_location_id`
* Index on `quantity_available`
* Index on `last_movement_at`

#### Important Design Decision

The unique stock balance rule should be:

```text
product_id + warehouse_id + warehouse_location_id
```

This means the same product can have only one balance row for the same warehouse and same location.

Example:

| Product    | Warehouse           | Location | Quantity |
| ---------- | ------------------- | -------- | -------: |
| Dell Mouse | Main Warehouse      | Rack A1  |       50 |
| Dell Mouse | Main Warehouse      | Rack B2  |       20 |
| Dell Mouse | Secondary Warehouse | Rack C1  |      100 |

This allows accurate location-based inventory tracking.

#### Stock Update Rule

When a stock operation is posted:

1. Create stock movement record
2. Update or create stock balance row
3. Recalculate quantity_on_hand
4. Recalculate quantity_available
5. Update last_movement_at

The system should never update stock balance without a matching stock movement record.

#### Low Stock Logic

Low stock should be checked using:

```text
stock_balances.quantity_available <= products.reorder_level
```

If this condition is true, the system can create a low stock alert.


## Roles, Permissions, Alerts and Audit Table Design

This section defines authorization, low stock alert tracking, and audit logging tables.

The system uses custom role and permission tables instead of storing role names directly inside the `users` table. This keeps authorization flexible and scalable.

---

### roles

The `roles` table stores user roles such as Super Admin, Admin, Manager, Warehouse Staff, and Viewer.

| Column      | Type            | Constraint / Notes |
| ----------- | --------------- | ------------------ |
| id          | BIGINT UNSIGNED | Primary key        |
| name        | VARCHAR(100)    | Required           |
| slug        | VARCHAR(120)    | Required, unique   |
| description | TEXT            | Nullable           |
| is_active   | BOOLEAN         | Default true       |
| created_at  | TIMESTAMP       | Laravel timestamp  |
| updated_at  | TIMESTAMP       | Laravel timestamp  |
| deleted_at  | TIMESTAMP       | Soft delete        |

#### Example Roles

| Role            | Purpose                                              |
| --------------- | ---------------------------------------------------- |
| super_admin     | Full system access                                   |
| admin           | General administrative access                        |
| manager         | Can approve purchase and stock operations            |
| warehouse_staff | Can create stock-in, stock-out, and transfer records |
| viewer          | Read-only access                                     |

#### Constraints and Indexes

* Unique index on `slug`
* Index on `is_active`
* Soft delete enabled to avoid breaking historical user-role references

---

### permissions

The `permissions` table stores fine-grained access rules such as product view, product create, purchase approve, stock transfer post, etc.

| Column      | Type            | Constraint / Notes |
| ----------- | --------------- | ------------------ |
| id          | BIGINT UNSIGNED | Primary key        |
| name        | VARCHAR(150)    | Required           |
| slug        | VARCHAR(180)    | Required, unique   |
| module      | VARCHAR(100)    | Required           |
| description | TEXT            | Nullable           |
| is_active   | BOOLEAN         | Default true       |
| created_at  | TIMESTAMP       | Laravel timestamp  |
| updated_at  | TIMESTAMP       | Laravel timestamp  |
| deleted_at  | TIMESTAMP       | Soft delete        |

#### Example Permission Modules

| Module          | Example Permissions                                                                         |
| --------------- | ------------------------------------------------------------------------------------------- |
| dashboard       | dashboard.view                                                                              |
| products        | products.view, products.create, products.edit, products.delete                              |
| suppliers       | suppliers.view, suppliers.create, suppliers.edit, suppliers.delete                          |
| warehouses      | warehouses.view, warehouses.create, warehouses.edit, warehouses.delete                      |
| purchase_orders | purchase_orders.view, purchase_orders.create, purchase_orders.approve                       |
| stock_ins       | stock_ins.view, stock_ins.create, stock_ins.approve, stock_ins.post                         |
| stock_outs      | stock_outs.view, stock_outs.create, stock_outs.approve, stock_outs.post                     |
| stock_transfers | stock_transfers.view, stock_transfers.create, stock_transfers.approve, stock_transfers.post |
| reports         | reports.view, reports.export                                                                |
| users           | users.view, users.create, users.edit, users.delete                                          |
| roles           | roles.view, roles.create, roles.edit, roles.delete                                          |

#### Constraints and Indexes

* Unique index on `slug`
* Index on `module`
* Index on `is_active`

---

### role_user

The `role_user` table is a pivot table that connects users with roles.

One user can have multiple roles, and one role can belong to many users.

| Column     | Type            | Constraint / Notes   |
| ---------- | --------------- | -------------------- |
| id         | BIGINT UNSIGNED | Primary key          |
| user_id    | BIGINT UNSIGNED | Foreign key to users |
| role_id    | BIGINT UNSIGNED | Foreign key to roles |
| created_at | TIMESTAMP       | Laravel timestamp    |
| updated_at | TIMESTAMP       | Laravel timestamp    |

#### Relationships

* Each role-user record belongs to one user
* Each role-user record belongs to one role

#### Constraints and Indexes

* Foreign key: `user_id` references `users.id`
* Foreign key: `role_id` references `roles.id`
* Unique index on `user_id` and `role_id`
* Index on `user_id`
* Index on `role_id`

#### Delete Rule

For pivot tables, if a user or role is deleted, related pivot rows can be removed using cascade delete.

Reason:

* Pivot rows are relationship records only
* They do not store business transaction history

---

### role_permission

The `role_permission` table is a pivot table that connects roles with permissions.

One role can have many permissions, and one permission can belong to many roles.

| Column        | Type            | Constraint / Notes         |
| ------------- | --------------- | -------------------------- |
| id            | BIGINT UNSIGNED | Primary key                |
| role_id       | BIGINT UNSIGNED | Foreign key to roles       |
| permission_id | BIGINT UNSIGNED | Foreign key to permissions |
| created_at    | TIMESTAMP       | Laravel timestamp          |
| updated_at    | TIMESTAMP       | Laravel timestamp          |

#### Relationships

* Each role-permission record belongs to one role
* Each role-permission record belongs to one permission

#### Constraints and Indexes

* Foreign key: `role_id` references `roles.id`
* Foreign key: `permission_id` references `permissions.id`
* Unique index on `role_id` and `permission_id`
* Index on `role_id`
* Index on `permission_id`

#### Delete Rule

For pivot tables, if a role or permission is deleted, related pivot rows can be removed using cascade delete.

---

### low_stock_alerts

The `low_stock_alerts` table stores low stock alert history when product available stock reaches or goes below the reorder level.

| Column                | Type            | Constraint / Notes                           |
| --------------------- | --------------- | -------------------------------------------- |
| id                    | BIGINT UNSIGNED | Primary key                                  |
| product_id            | BIGINT UNSIGNED | Foreign key to products                      |
| warehouse_id          | BIGINT UNSIGNED | Foreign key to warehouses                    |
| warehouse_location_id | BIGINT UNSIGNED | Nullable, foreign key to warehouse_locations |
| stock_balance_id      | BIGINT UNSIGNED | Nullable, foreign key to stock_balances      |
| quantity_available    | DECIMAL(15,2)   | Required                                     |
| reorder_level         | DECIMAL(15,2)   | Required                                     |
| status                | VARCHAR(30)     | Default `open`                               |
| message               | TEXT            | Nullable                                     |
| resolved_by           | BIGINT UNSIGNED | Nullable, foreign key to users               |
| resolved_at           | TIMESTAMP       | Nullable                                     |
| created_at            | TIMESTAMP       | Laravel timestamp                            |
| updated_at            | TIMESTAMP       | Laravel timestamp                            |

#### Status Values

| Status       | Meaning                                    |
| ------------ | ------------------------------------------ |
| open         | Low stock issue is active                  |
| acknowledged | User has seen/accepted the alert           |
| resolved     | Stock has been replenished or issue closed |

#### Relationships

* Each low stock alert belongs to one product
* Each low stock alert belongs to one warehouse
* Each low stock alert may belong to one warehouse location
* Each low stock alert may belong to one stock balance row
* Each low stock alert may be resolved by one user

#### Constraints and Indexes

* Foreign key: `product_id` references `products.id`
* Foreign key: `warehouse_id` references `warehouses.id`
* Foreign key: `warehouse_location_id` references `warehouse_locations.id`
* Foreign key: `stock_balance_id` references `stock_balances.id`
* Foreign key: `resolved_by` references `users.id`
* Index on `product_id`
* Index on `warehouse_id`
* Index on `warehouse_location_id`
* Index on `stock_balance_id`
* Index on `status`
* Index on `created_at`

#### Important Design Decision

Low stock alerts should be stored as alert history, not only calculated live.

Reason:

* Managers can see when stock became low
* Alerts can be acknowledged and resolved
* Dashboard can show active low stock issues
* Reports can show recurring low stock products

Low stock condition:

```text id="83kopv"
quantity_available <= reorder_level
```

---

### audit_logs

The `audit_logs` table stores important user actions and data changes for accountability.

This table is useful for tracking who created, updated, approved, posted, cancelled, or deleted important records.

| Column         | Type            | Constraint / Notes             |
| -------------- | --------------- | ------------------------------ |
| id             | BIGINT UNSIGNED | Primary key                    |
| user_id        | BIGINT UNSIGNED | Nullable, foreign key to users |
| auditable_type | VARCHAR(150)    | Required                       |
| auditable_id   | BIGINT UNSIGNED | Required                       |
| action         | VARCHAR(50)     | Required                       |
| old_values     | JSON            | Nullable                       |
| new_values     | JSON            | Nullable                       |
| ip_address     | VARCHAR(45)     | Nullable                       |
| user_agent     | TEXT            | Nullable                       |
| created_at     | TIMESTAMP       | Laravel timestamp              |

#### Action Values

| Action    | Meaning                    |
| --------- | -------------------------- |
| created   | Record was created         |
| updated   | Record was updated         |
| deleted   | Record was deleted         |
| approved  | Record was approved        |
| posted    | Stock operation was posted |
| cancelled | Record was cancelled       |
| login     | User logged in             |
| logout    | User logged out            |

#### Reference Design

The `auditable_type` and `auditable_id` columns identify which model or table was affected.

Examples:

| auditable_type  | auditable_id | action    |
| --------------- | -----------: | --------- |
| products        |            5 | updated   |
| purchase_orders |           10 | approved  |
| stock_ins       |            7 | posted    |
| stock_transfers |            3 | cancelled |

#### Relationships

* Each audit log may belong to one user
* Each audit log refers to one auditable record using `auditable_type` and `auditable_id`

#### Constraints and Indexes

* Foreign key: `user_id` references `users.id`
* Index on `user_id`
* Index on `auditable_type` and `auditable_id`
* Index on `action`
* Index on `created_at`

#### Important Design Decision

Audit logs should not be soft deleted.

Reason:

* Audit logs are system history
* Deleted audit logs reduce accountability
* Portfolio-ready systems should preserve critical activity history


## Full Relationship Summary

This section summarizes the main database relationships of the Warehouse Management System.

---

### User, Role and Permission Relationships

```text
users
  └── role_user
        └── roles
              └── role_permission
                    └── permissions
```

#### Relationship Details

* One user can have many roles.
* One role can belong to many users.
* One role can have many permissions.
* One permission can belong to many roles.
* `role_user` is the pivot table between `users` and `roles`.
* `role_permission` is the pivot table between `roles` and `permissions`.

---

### Master Data Relationships

```text
categories
  └── products

units
  └── products

warehouses
  └── warehouse_locations
```

#### Relationship Details

* One category can have many products.
* One product belongs to one category.
* One unit can be used by many products.
* One product belongs to one unit.
* One warehouse can have many warehouse locations.
* One warehouse location belongs to one warehouse.

---

### Purchase Order Relationships

```text
suppliers
  └── purchase_orders
        └── purchase_order_items
              └── products

warehouses
  └── purchase_orders
```

#### Relationship Details

* One supplier can have many purchase orders.
* One purchase order belongs to one supplier.
* One warehouse can receive many purchase orders.
* One purchase order belongs to one receiving warehouse.
* One purchase order can have many purchase order items.
* One purchase order item belongs to one purchase order.
* One product can appear in many purchase order items.
* One purchase order item belongs to one product.

---

### Stock-In Relationships

```text
purchase_orders
  └── stock_ins
        └── stock_in_items
              └── products

warehouses
  └── stock_ins

warehouse_locations
  └── stock_in_items
```

#### Relationship Details

* One stock-in may belong to one purchase order.
* One stock-in may belong to one supplier.
* One stock-in belongs to one warehouse.
* One stock-in can have many stock-in items.
* One stock-in item belongs to one product.
* One stock-in item may belong to one warehouse location.

---

### Stock-Out Relationships

```text
warehouses
  └── stock_outs
        └── stock_out_items
              └── products

warehouse_locations
  └── stock_out_items
```

#### Relationship Details

* One stock-out belongs to one warehouse.
* One stock-out can have many stock-out items.
* One stock-out item belongs to one product.
* One stock-out item may belong to one warehouse location.

---

### Stock Transfer Relationships

```text
warehouses
  ├── stock_transfers.from_warehouse_id
  └── stock_transfers.to_warehouse_id

stock_transfers
  └── stock_transfer_items
        └── products

warehouse_locations
  ├── stock_transfer_items.from_warehouse_location_id
  └── stock_transfer_items.to_warehouse_location_id
```

#### Relationship Details

* One stock transfer belongs to one source warehouse.
* One stock transfer belongs to one destination warehouse.
* One stock transfer can have many stock transfer items.
* One stock transfer item belongs to one product.
* One stock transfer item may belong to one source warehouse location.
* One stock transfer item may belong to one destination warehouse location.

---

### Inventory Ledger Relationships

```text
products
  ├── stock_movements
  └── stock_balances

warehouses
  ├── stock_movements
  └── stock_balances

warehouse_locations
  ├── stock_movements
  └── stock_balances
```

#### Relationship Details

* One product can have many stock movements.
* One product can have many stock balance rows.
* One warehouse can have many stock movements.
* One warehouse can have many stock balance rows.
* One warehouse location can have many stock movements.
* One warehouse location can have many stock balance rows.
* Stock movements store historical inventory changes.
* Stock balances store current stock snapshots.

---

### Low Stock and Audit Relationships

```text
products
  └── low_stock_alerts

warehouses
  └── low_stock_alerts

stock_balances
  └── low_stock_alerts

users
  └── audit_logs
```

#### Relationship Details

* One product can have many low stock alerts.
* One warehouse can have many low stock alerts.
* One stock balance row can have many low stock alerts.
* One user can have many audit logs.
* Audit logs use `auditable_type` and `auditable_id` to reference affected records.

---

## Migration Creation Order

Migration order is important because foreign key tables must be created after their parent tables.

Laravel default migrations should already exist for:

* users
* password_reset_tokens
* sessions
* cache
* jobs

Custom project migrations should be created in this order:

| Order | Table                | Reason                                                                          |
| ----: | -------------------- | ------------------------------------------------------------------------------- |
|     1 | roles                | Independent authorization table                                                 |
|     2 | permissions          | Independent authorization table                                                 |
|     3 | role_user            | Depends on users and roles                                                      |
|     4 | role_permission      | Depends on roles and permissions                                                |
|     5 | categories           | Parent table for products                                                       |
|     6 | units                | Parent table for products                                                       |
|     7 | suppliers            | Parent table for purchase orders and stock-ins                                  |
|     8 | warehouses           | Parent table for warehouse locations and stock operations                       |
|     9 | warehouse_locations  | Depends on warehouses                                                           |
|    10 | products             | Depends on categories and units                                                 |
|    11 | purchase_orders      | Depends on suppliers, warehouses, and users                                     |
|    12 | purchase_order_items | Depends on purchase_orders and products                                         |
|    13 | stock_ins            | Depends on purchase_orders, suppliers, warehouses, and users                    |
|    14 | stock_in_items       | Depends on stock_ins, products, and warehouse_locations                         |
|    15 | stock_outs           | Depends on warehouses and users                                                 |
|    16 | stock_out_items      | Depends on stock_outs, products, and warehouse_locations                        |
|    17 | stock_transfers      | Depends on warehouses and users                                                 |
|    18 | stock_transfer_items | Depends on stock_transfers, products, and warehouse_locations                   |
|    19 | stock_movements      | Depends on products, warehouses, warehouse_locations, and users                 |
|    20 | stock_balances       | Depends on products, warehouses, and warehouse_locations                        |
|    21 | low_stock_alerts     | Depends on products, warehouses, warehouse_locations, stock_balances, and users |
|    22 | audit_logs           | Depends on users                                                                |

---

## Foreign Key Delete Strategy

Foreign key delete behavior should be chosen carefully.

### Cascade Delete

Cascade delete can be used for pure pivot tables:

* `role_user`
* `role_permission`

Reason:

* These tables only store relationship records.
* They do not store business transaction history.

### Restrict or Null on Delete

Business tables should not use aggressive cascade delete.

For important transaction/history tables, use one of these approaches:

* `restrictOnDelete()` when parent deletion should be blocked
* `nullOnDelete()` when the reference can safely become null

Examples:

* A product used in stock movements should not be deleted permanently.
* A warehouse used in stock history should not be deleted permanently.
* A user who approved a record may later be removed, so approval user reference can become null.

---

## Soft Delete Strategy

Soft delete should be used for master and business records where history matters.

Recommended soft delete tables:

* roles
* permissions
* categories
* units
* suppliers
* warehouses
* warehouse_locations
* products
* purchase_orders
* stock_ins
* stock_outs
* stock_transfers

Soft delete should not be used for:

* role_user
* role_permission
* purchase_order_items
* stock_in_items
* stock_out_items
* stock_transfer_items
* stock_movements
* stock_balances
* low_stock_alerts
* audit_logs

Reason:

* Item rows are detail records connected to their parent header.
* Stock movements are historical ledger records.
* Stock balances are current snapshot records.
* Audit logs should remain permanent for accountability.


## Final Database Design Review and Corrections

This section records final architecture decisions before creating Laravel migration files.

---

### 1. Stock Balance Unique Constraint with Nullable Location

The `stock_balances` table is designed to store one current stock row per product, warehouse, and warehouse location.

Conceptual unique rule:

```text
product_id + warehouse_id + warehouse_location_id


```

However, MySQL allows multiple `NULL` values inside a unique index. Because `warehouse_location_id` is nullable, a normal composite unique index may not fully prevent duplicate rows when the location is `NULL`.

Example risky duplicate:

| product_id | warehouse_id | warehouse_location_id |
|---:|---:|---:|
| 1 | 1 | NULL |
| 1 | 1 | NULL |

#### Final Decision

The application service layer must carefully update or create stock balance rows using product, warehouse, and location identity.

During migration implementation, the project should use one of these safe strategies:

1. Keep `warehouse_location_id` nullable and enforce uniqueness through controlled stock posting service logic.
2. Use a database-level generated key or functional index if stricter MySQL-level enforcement is required.
3. Create a default warehouse location such as `GENERAL` and make stock balance location mandatory.

For this project, the design will keep `warehouse_location_id` nullable for flexibility, but stock balance updates must only happen through a controlled stock posting service.

---

### 2. Signed Quantity in Stock Movements

The `stock_movements.quantity` column should support signed values.

Positive movements increase stock.

Examples:

| Movement Type | Quantity Effect |
|---|---:|
| stock_in | +100 |
| transfer_in | +50 |
| adjustment_in | +10 |

Negative movements decrease stock.

Examples:

| Movement Type | Quantity Effect |
|---|---:|
| stock_out | -20 |
| transfer_out | -50 |
| adjustment_out | -5 |

#### Final Decision

`stock_movements.quantity` must allow positive and negative decimal values.

However, item table quantities should remain positive.

Examples:

- `stock_in_items.quantity` should be positive
- `stock_out_items.quantity` should be positive
- `stock_transfer_items.quantity` should be positive
- `purchase_order_items.ordered_quantity` should be positive

The movement direction is represented in the ledger by signed `stock_movements.quantity`.

---

### 3. Status Columns

Status columns are stored as `VARCHAR(30)` instead of database ENUM.

Reason:

- Easier to maintain in Laravel
- Easier to extend later
- Avoids database migration every time a new status is added
- Works well with model constants, validation rules, and service-layer workflow checks

Examples:

```text
draft
pending_approval
approved
posted
cancelled
```

#### Final Decision

Status values should be controlled by Laravel model constants, Form Request validation, and service-layer workflow rules.

---

### 4. Monetary and Quantity Precision

The system uses `DECIMAL(15,2)` for quantity and money-related values.

Examples:

- quantity
- unit_price
- unit_cost
- line_total
- subtotal
- grand_total
- average_cost

Reason:

- Avoids floating-point precision problems
- Supports both item count and measured products
- Works for pcs, kg, liter, box, carton, etc.

#### Final Decision

Do not use FLOAT or DOUBLE for inventory quantity or money values.

---

### 5. Stock Update Rule

Stock balance must never be updated directly from controllers.

Correct flow:

```text
Controller
  → Form Request Validation
  → Service Layer
  → Database Transaction
  → Create Stock Movement
  → Update Stock Balance
  → Create Audit Log
```

#### Final Decision

Stock posting logic must be handled inside a dedicated service layer, not inside controllers.

This keeps controllers thin and protects inventory accuracy.

---

### 6. Final Database Design Status

The database design is ready for migration planning.

Completed design areas:

- Authentication and authorization tables
- Master data tables
- Purchase order tables
- Stock operation tables
- Inventory ledger tables
- Stock balance table
- Low stock alert table
- Audit log table
- Relationship summary
- Migration creation order
- Soft delete strategy
- Final architectural corrections
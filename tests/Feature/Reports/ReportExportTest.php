<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_with_export_permission_can_download_inventory_csv_and_filters_are_respected(): void
    {
        $user = $this->userWithPermissions(['reports.inventory.view', 'reports.export']);
        $category = $this->category(['name' => 'Export Category', 'slug' => 'export-category']);
        $unit = $this->unit(['name' => 'Piece', 'short_name' => 'pcs']);
        $matchingWarehouse = $this->warehouse([
            'code' => 'EXPORT-WH-001',
            'name' => 'CSV Export Warehouse',
        ]);
        $otherWarehouse = $this->warehouse([
            'code' => 'EXPORT-WH-002',
            'name' => 'Hidden Export Warehouse',
        ]);
        $matchingProduct = $this->product($category, $unit, [
            'name' => 'CSV Export Product',
            'slug' => 'csv-export-product',
            'sku' => 'CSV-EXPORT-001',
            'reorder_level' => 5,
        ]);
        $otherProduct = $this->product($category, $unit, [
            'name' => 'Hidden Export Product',
            'slug' => 'hidden-export-product',
            'sku' => 'CSV-HIDDEN-001',
        ]);
        $this->warehouseStock($matchingWarehouse, $matchingProduct, quantity: 12, reservedQuantity: 2);
        $this->warehouseStock($otherWarehouse, $otherProduct, quantity: 8, reservedQuantity: 1);

        $response = $this->actingAs($user)
            ->get(route('reports.inventory.export', ['warehouse_id' => $matchingWarehouse->id]));

        $csv = $this->assertCsvDownload($response, 'inventory-report', [
            'Warehouse',
            'Product SKU',
            'Product Name',
            'Category',
            'Quantity',
            'Reserved Quantity',
            'Available Quantity',
            'Reorder Level',
        ]);

        $this->assertStringContainsString('CSV Export Warehouse', $csv);
        $this->assertStringContainsString('CSV-EXPORT-001', $csv);
        $this->assertStringContainsString('10.0000', $csv);
        $this->assertStringNotContainsString('Hidden Export Warehouse', $csv);
        $this->assertStringNotContainsString('CSV-HIDDEN-001', $csv);
    }

    public function test_user_without_export_permission_cannot_download_csv(): void
    {
        $user = $this->userWithPermissions(['reports.inventory.view']);

        $this->actingAs($user)
            ->get(route('reports.inventory.export'))
            ->assertForbidden();
    }

    public function test_seeded_admin_roles_receive_reports_export_permission(): void
    {
        $reportExportPermission = Permission::query()
            ->where('slug', 'reports.export')
            ->firstOrFail();

        foreach (['super-admin', 'manager'] as $roleSlug) {
            $role = Role::query()
                ->where('slug', $roleSlug)
                ->firstOrFail();

            $this->assertTrue(
                $role->permissions()->whereKey($reportExportPermission->id)->exists(),
                sprintf('Expected seeded %s role to receive reports.export permission.', $roleSlug),
            );
        }
    }

    public function test_stock_movement_report_can_be_exported_as_csv(): void
    {
        $user = $this->userWithPermissions(['reports.stock-movements.view', 'reports.export']);
        $warehouse = $this->warehouse([
            'code' => 'MOVE-EXPORT-WH',
            'name' => 'Movement Export Warehouse',
        ]);
        $product = $this->product($this->category(), $this->unit(), [
            'name' => 'Movement Export Product',
            'slug' => 'movement-export-product',
            'sku' => 'MOVE-EXPORT-001',
        ]);

        StockMovement::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'stock_in',
            'quantity' => 6.25,
            'balance_after' => 16.25,
            'reference_type' => StockIn::class,
            'reference_id' => 123,
            'remarks' => 'Export stock movement.',
            'created_by' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('reports.stock-movements.export'));

        $csv = $this->assertCsvDownload($response, 'stock-movements-report', [
            'Date',
            'Warehouse',
            'Product SKU',
            'Product Name',
            'Movement Type',
            'Quantity',
            'Balance After',
            'Reference Type',
            'Reference ID',
        ]);

        $this->assertStringContainsString('Movement Export Warehouse', $csv);
        $this->assertStringContainsString('MOVE-EXPORT-001', $csv);
        $this->assertStringContainsString('Stock In', $csv);
        $this->assertStringContainsString('6.2500', $csv);
        $this->assertStringContainsString('StockIn', $csv);
        $this->assertStringContainsString('123', $csv);
    }

    public function test_low_stock_report_can_be_exported_as_csv(): void
    {
        $user = $this->userWithPermissions(['reports.low-stock.view', 'reports.export']);
        $category = $this->category(['name' => 'Low Export Category', 'slug' => 'low-export-category']);
        $unit = $this->unit();
        $warehouse = $this->warehouse([
            'code' => 'LOW-EXPORT-WH',
            'name' => 'Low Export Warehouse',
        ]);
        $product = $this->product($category, $unit, [
            'name' => 'Low Export Product',
            'slug' => 'low-export-product',
            'sku' => 'LOW-EXPORT-001',
            'reorder_level' => 5,
        ]);
        $this->warehouseStock($warehouse, $product, quantity: 3, reservedQuantity: 1);

        $response = $this->actingAs($user)
            ->get(route('reports.low-stock.export'));

        $csv = $this->assertCsvDownload($response, 'low-stock-report', [
            'Warehouse',
            'Product SKU',
            'Product Name',
            'Category',
            'Quantity',
            'Reserved Quantity',
            'Available Quantity',
            'Reorder Level',
            'Shortage Quantity',
        ]);

        $this->assertStringContainsString('Low Export Warehouse', $csv);
        $this->assertStringContainsString('LOW-EXPORT-001', $csv);
        $this->assertStringContainsString('3.0000', $csv);
    }

    public function test_purchase_order_report_can_be_exported_as_csv(): void
    {
        $user = $this->userWithPermissions(['reports.purchase-orders.view', 'reports.export']);
        $supplier = $this->supplier(['name' => 'Export Supplier']);
        $warehouse = $this->warehouse([
            'code' => 'PO-EXPORT-WH',
            'name' => 'PO Export Warehouse',
        ]);
        $purchaseOrder = $this->purchaseOrder($supplier, $warehouse, [
            'po_number' => 'PO-EXPORT-001',
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'order_date' => '2026-06-15',
            'expected_date' => '2026-06-20',
            'total_amount' => 125,
        ]);
        $this->purchaseOrderItem($purchaseOrder, $this->product(), [
            'quantity' => 10,
            'received_quantity' => 4,
            'unit_cost' => 10,
            'line_total' => 100,
        ]);
        $this->purchaseOrderItem($purchaseOrder, $this->product(), [
            'quantity' => 2,
            'received_quantity' => 2,
            'unit_cost' => 12.5,
            'line_total' => 25,
        ]);

        $response = $this->actingAs($user)
            ->get(route('reports.purchase-orders.export'));

        $csv = $this->assertCsvDownload($response, 'purchase-orders-report', [
            'PO Number',
            'Supplier',
            'Warehouse',
            'Status',
            'Order Date',
            'Expected Date',
            'Items Count',
            'Ordered Quantity',
            'Received Quantity',
            'Pending Quantity',
            'Total Amount',
        ]);

        $this->assertStringContainsString('PO-EXPORT-001', $csv);
        $this->assertStringContainsString('Export Supplier', $csv);
        $this->assertStringContainsString('Received', $csv);
        $this->assertStringContainsString('12.000', $csv);
        $this->assertStringContainsString('6.000', $csv);
        $this->assertStringContainsString('125.00', $csv);
    }

    /**
     * @param  list<string>  $expectedHeaders
     */
    private function assertCsvDownload(TestResponse $response, string $filenamePrefix, array $expectedHeaders): string
    {
        $response->assertOk();

        $contentDisposition = $response->baseResponse->headers->get('content-disposition', '');
        $contentType = $response->baseResponse->headers->get('content-type', '');

        $this->assertStringContainsString('attachment;', $contentDisposition);
        $this->assertStringContainsString($filenamePrefix, $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);
        $this->assertStringContainsString('text/csv', $contentType);

        $csv = $response->streamedContent();

        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString($header, $csv);
        }

        return $csv;
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create();
        $permissions = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->get();

        $this->assertCount(count($permissionSlugs), $permissions);

        $role = Role::query()->create([
            'name' => 'Test Role',
            'slug' => 'test-role-'.Str::uuid(),
            'description' => 'Temporary role for report export tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function category(array $overrides = []): Category
    {
        $key = Str::lower(Str::random(8));

        return Category::query()->create(array_replace([
            'name' => 'Category '.$key,
            'slug' => 'category-'.$key,
            'description' => 'Report export test category.',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function unit(array $overrides = []): Unit
    {
        $key = Str::lower(Str::random(8));

        return Unit::query()->create(array_replace([
            'name' => 'Piece '.$key,
            'short_name' => 'pcs-'.$key,
            'description' => 'Report export test unit.',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function warehouse(array $overrides = []): Warehouse
    {
        $key = Str::upper(Str::random(8));

        return Warehouse::query()->create(array_replace([
            'code' => 'WH-'.$key,
            'name' => 'Warehouse '.$key,
            'contact_person' => 'Warehouse Manager',
            'phone' => '+49 111 222333',
            'email' => 'warehouse-'.Str::lower($key).'@example.com',
            'address' => 'Berlin, Germany',
            'city' => 'Berlin',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function product(?Category $category = null, ?Unit $unit = null, array $overrides = []): Product
    {
        $key = Str::upper(Str::random(8));
        $category ??= $this->category();
        $unit ??= $this->unit();

        return Product::query()->create(array_replace([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Product '.$key,
            'slug' => 'product-'.Str::lower($key),
            'sku' => 'SKU-'.$key,
            'description' => 'Report export test product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ], $overrides));
    }

    private function warehouseStock(
        Warehouse $warehouse,
        Product $product,
        float|int $quantity,
        float|int $reservedQuantity,
    ): WarehouseStock {
        return WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'reserved_quantity' => $reservedQuantity,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function supplier(array $overrides = []): Supplier
    {
        $key = Str::lower(Str::random(8));

        return Supplier::query()->create(array_replace([
            'name' => 'Supplier '.$key,
            'company_name' => 'Supplier '.$key.' GmbH',
            'email' => 'supplier-'.$key.'@example.com',
            'phone' => '+49 100 200300',
            'address' => 'Berlin, Germany',
            'tax_number' => 'TAX-'.Str::upper($key),
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function purchaseOrder(Supplier $supplier, Warehouse $warehouse, array $overrides = []): PurchaseOrder
    {
        return PurchaseOrder::query()->create(array_replace([
            'po_number' => 'PO-EXPORT-'.Str::upper(Str::random(8)),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'order_date' => '2026-06-15',
            'expected_date' => '2026-06-22',
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 50,
            'notes' => 'Purchase order export test.',
            'created_by' => User::factory()->create()->id,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function purchaseOrderItem(
        PurchaseOrder $purchaseOrder,
        Product $product,
        array $overrides = [],
    ): PurchaseOrderItem {
        $quantity = (float) ($overrides['quantity'] ?? 5);
        $unitCost = (float) ($overrides['unit_cost'] ?? 10);

        return PurchaseOrderItem::query()->create(array_replace([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'received_quantity' => 0,
            'unit_cost' => $unitCost,
            'line_total' => round($quantity * $unitCost, 2),
            'notes' => 'Purchase order export item.',
        ], $overrides));
    }
}

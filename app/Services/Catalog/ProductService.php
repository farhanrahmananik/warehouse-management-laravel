<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductService
{
    private const MAX_SLUG_LENGTH = 220;

    private const NUMERIC_DEFAULT_FIELDS = [
        'purchase_price',
        'selling_price',
        'reorder_level',
    ];

    private const AUDIT_FIELDS = [
        'category_id',
        'unit_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'description',
        'purchase_price',
        'selling_price',
        'reorder_level',
        'is_active',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function list(): Builder
    {
        return Product::query()->with(['category', 'unit'])->latest();
    }

    public function create(array $data): Product
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $this->generateUniqueSlug($slug !== '' ? $slug : (string) ($data['name'] ?? 'item'));
        $data = $this->normalizeNumericDefaults($data);

        $product = Product::create($data)->refresh();

        $this->auditLogService->record(
            event: 'created',
            module: 'products',
            auditable: $product,
            description: sprintf('Product "%s" was created.', $product->name),
            newValues: $this->auditValues($product),
            metadata: [
                'model' => 'product',
            ],
        );

        return $product;
    }

    public function update(Product $product, array $data): Product
    {
        $oldValues = $this->auditValues($product);

        $slug = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $this->generateUniqueSlug(
            $slug !== '' ? $slug : (string) ($data['name'] ?? $product->name),
            (int) $product->getKey(),
        );

        $data = $this->normalizeNumericDefaults($data, true);

        $product->update($data);
        $product = $product->refresh();

        [$changedOldValues, $changedNewValues] = $this->changedAuditValues(
            $oldValues,
            $this->auditValues($product),
        );

        if ($changedNewValues !== []) {
            $this->auditLogService->record(
                event: 'updated',
                module: 'products',
                auditable: $product,
                description: sprintf('Product "%s" was updated.', $product->name),
                oldValues: $changedOldValues,
                newValues: $changedNewValues,
                metadata: [
                    'model' => 'product',
                ],
            );
        }

        return $product;
    }

    public function delete(Product $product): bool|null
    {
        $oldValues = $this->auditValues($product);
        $name = $product->name;
        $deleted = $product->delete();

        if ($deleted) {
            $this->auditLogService->record(
                event: 'deleted',
                module: 'products',
                auditable: $product,
                description: sprintf('Product "%s" was deleted.', $name),
                oldValues: $oldValues,
                metadata: [
                    'model' => 'product',
                ],
            );
        }

        return $deleted;
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'item';
        }

        $baseSlug = substr($baseSlug, 0, self::MAX_SLUG_LENGTH);
        $slug = $baseSlug;
        $counter = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $suffix = '-'.$counter++;
            $slug = substr($baseSlug, 0, self::MAX_SLUG_LENGTH - strlen($suffix)).$suffix;
        }

        return $slug;
    }

    private function normalizeNumericDefaults(array $data, bool $onlyExistingKeys = false): array
    {
        foreach (self::NUMERIC_DEFAULT_FIELDS as $field) {
            if ($onlyExistingKeys && ! array_key_exists($field, $data)) {
                continue;
            }

            if (! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                $data[$field] = 0;
            }
        }

        return $data;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = Product::withTrashed()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Product $product): array
    {
        $values = [];

        foreach (self::AUDIT_FIELDS as $field) {
            $values[$field] = $product->getAttribute($field);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function changedAuditValues(array $oldValues, array $newValues): array
    {
        $changedOldValues = [];
        $changedNewValues = [];

        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $changedOldValues[$field] = $oldValue;
            $changedNewValues[$field] = $newValue;
        }

        return [$changedOldValues, $changedNewValues];
    }
}

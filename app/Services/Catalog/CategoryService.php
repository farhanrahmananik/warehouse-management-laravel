<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryService
{
    private const MAX_SLUG_LENGTH = 180;

    private const AUDIT_FIELDS = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function list(): Builder
    {
        return Category::query()->latest();
    }

    public function create(array $data): Category
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $this->generateUniqueSlug($slug !== '' ? $slug : (string) ($data['name'] ?? 'item'));

        $category = Category::create($data)->refresh();

        $this->auditLogService->record(
            event: 'created',
            module: 'categories',
            auditable: $category,
            description: sprintf('Category "%s" was created.', $category->name),
            newValues: $this->auditValues($category),
            metadata: [
                'model' => 'category',
            ],
        );

        return $category;
    }

    public function update(Category $category, array $data): Category
    {
        $oldValues = $this->auditValues($category);

        $slug = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $this->generateUniqueSlug(
            $slug !== '' ? $slug : (string) ($data['name'] ?? $category->name),
            (int) $category->getKey(),
        );

        $category->update($data);
        $category = $category->refresh();

        [$changedOldValues, $changedNewValues] = $this->changedAuditValues(
            $oldValues,
            $this->auditValues($category),
        );

        if ($changedNewValues !== []) {
            $this->auditLogService->record(
                event: 'updated',
                module: 'categories',
                auditable: $category,
                description: sprintf('Category "%s" was updated.', $category->name),
                oldValues: $changedOldValues,
                newValues: $changedNewValues,
                metadata: [
                    'model' => 'category',
                ],
            );
        }

        return $category;
    }

    public function delete(Category $category): bool|null
    {
        $oldValues = $this->auditValues($category);
        $name = $category->name;
        $deleted = $category->delete();

        if ($deleted) {
            $this->auditLogService->record(
                event: 'deleted',
                module: 'categories',
                auditable: $category,
                description: sprintf('Category "%s" was deleted.', $name),
                oldValues: $oldValues,
                metadata: [
                    'model' => 'category',
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

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = Category::withTrashed()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Category $category): array
    {
        $values = [];

        foreach (self::AUDIT_FIELDS as $field) {
            $values[$field] = $category->getAttribute($field);
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

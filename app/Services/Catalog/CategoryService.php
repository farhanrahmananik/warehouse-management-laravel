<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryService
{
    private const MAX_SLUG_LENGTH = 180;

    public function list(): Builder
    {
        return Category::query()->latest();
    }

    public function create(array $data): Category
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $this->generateUniqueSlug($slug !== '' ? $slug : (string) ($data['name'] ?? 'item'));

        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $this->generateUniqueSlug(
            $slug !== '' ? $slug : (string) ($data['name'] ?? $category->name),
            (int) $category->getKey(),
        );

        $category->update($data);

        return $category->refresh();
    }

    public function delete(Category $category): bool|null
    {
        return $category->delete();
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
}

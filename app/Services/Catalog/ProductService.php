<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductService
{
    private const MAX_SLUG_LENGTH = 220;

    public function list(): Builder
    {
        return Product::query()->with(['category', 'unit'])->latest();
    }

    public function create(array $data): Product
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $this->generateUniqueSlug($slug !== '' ? $slug : (string) ($data['name'] ?? 'item'));

        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $this->generateUniqueSlug(
            $slug !== '' ? $slug : (string) ($data['name'] ?? $product->name),
            (int) $product->getKey(),
        );

        $product->update($data);

        return $product->refresh();
    }

    public function delete(Product $product): bool|null
    {
        return $product->delete();
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
        $query = Product::withTrashed()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}

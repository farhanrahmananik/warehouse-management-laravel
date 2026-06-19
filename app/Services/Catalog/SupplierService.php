<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;

class SupplierService
{
    public function list(): Builder
    {
        return Supplier::query()->latest();
    }

    public function create(array $data): Supplier
    {
        if (! array_key_exists('opening_balance', $data) || $data['opening_balance'] === null || $data['opening_balance'] === '') {
            $data['opening_balance'] = 0;
        }

        if (! array_key_exists('current_balance', $data) || $data['current_balance'] === null || $data['current_balance'] === '') {
            $data['current_balance'] = $data['opening_balance'];
        }

        return Supplier::create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->refresh();
    }

    public function delete(Supplier $supplier): bool|null
    {
        return $supplier->delete();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

class WarehouseService
{
    public function list(): Builder
    {
        return Warehouse::query()->latest();
    }

    public function create(array $data): Warehouse
    {
        $data = $this->normalizeCheckboxes($data);

        return Warehouse::create($data);
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $data = $this->normalizeCheckboxes($data);

        $warehouse->update($data);

        return $warehouse->refresh();
    }

    public function delete(Warehouse $warehouse): bool|null
    {
        return $warehouse->delete();
    }

    private function normalizeCheckboxes(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}

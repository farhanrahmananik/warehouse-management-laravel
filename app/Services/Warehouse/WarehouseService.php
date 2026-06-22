<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Models\Warehouse;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Builder;

class WarehouseService
{
    private const AUDIT_FIELDS = [
        'code',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'city',
        'is_active',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function list(): Builder
    {
        return Warehouse::query()->latest();
    }

    public function create(array $data): Warehouse
    {
        $data = $this->normalizeCheckboxes($data);

        $warehouse = Warehouse::create($data)->refresh();

        $this->auditLogService->record(
            event: 'created',
            module: 'warehouses',
            auditable: $warehouse,
            description: sprintf('Warehouse "%s" was created.', $warehouse->name),
            newValues: $this->auditValues($warehouse),
            metadata: [
                'model' => 'warehouse',
            ],
        );

        return $warehouse;
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $oldValues = $this->auditValues($warehouse);
        $data = $this->normalizeCheckboxes($data);

        $warehouse->update($data);
        $warehouse = $warehouse->refresh();

        [$changedOldValues, $changedNewValues] = $this->changedAuditValues(
            $oldValues,
            $this->auditValues($warehouse),
        );

        if ($changedNewValues !== []) {
            $this->auditLogService->record(
                event: 'updated',
                module: 'warehouses',
                auditable: $warehouse,
                description: sprintf('Warehouse "%s" was updated.', $warehouse->name),
                oldValues: $changedOldValues,
                newValues: $changedNewValues,
                metadata: [
                    'model' => 'warehouse',
                ],
            );
        }

        return $warehouse;
    }

    public function delete(Warehouse $warehouse): bool|null
    {
        $oldValues = $this->auditValues($warehouse);
        $name = $warehouse->name;
        $deleted = $warehouse->delete();

        if ($deleted) {
            $this->auditLogService->record(
                event: 'deleted',
                module: 'warehouses',
                auditable: $warehouse,
                description: sprintf('Warehouse "%s" was deleted.', $name),
                oldValues: $oldValues,
                metadata: [
                    'model' => 'warehouse',
                ],
            );
        }

        return $deleted;
    }

    private function normalizeCheckboxes(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Warehouse $warehouse): array
    {
        $values = [];

        foreach (self::AUDIT_FIELDS as $field) {
            $values[$field] = $warehouse->getAttribute($field);
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

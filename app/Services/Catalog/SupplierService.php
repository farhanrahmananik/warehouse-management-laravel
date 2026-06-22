<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Supplier;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Builder;

class SupplierService
{
    private const AUDIT_FIELDS = [
        'name',
        'company_name',
        'email',
        'phone',
        'address',
        'tax_number',
        'opening_balance',
        'current_balance',
        'is_active',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

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

        $supplier = Supplier::create($data)->refresh();

        $this->auditLogService->record(
            event: 'created',
            module: 'suppliers',
            auditable: $supplier,
            description: sprintf('Supplier "%s" was created.', $supplier->name),
            newValues: $this->auditValues($supplier),
            metadata: [
                'model' => 'supplier',
            ],
        );

        return $supplier;
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $oldValues = $this->auditValues($supplier);

        $supplier->update($data);
        $supplier = $supplier->refresh();

        [$changedOldValues, $changedNewValues] = $this->changedAuditValues(
            $oldValues,
            $this->auditValues($supplier),
        );

        if ($changedNewValues !== []) {
            $this->auditLogService->record(
                event: 'updated',
                module: 'suppliers',
                auditable: $supplier,
                description: sprintf('Supplier "%s" was updated.', $supplier->name),
                oldValues: $changedOldValues,
                newValues: $changedNewValues,
                metadata: [
                    'model' => 'supplier',
                ],
            );
        }

        return $supplier;
    }

    public function delete(Supplier $supplier): bool|null
    {
        $oldValues = $this->auditValues($supplier);
        $name = $supplier->name;
        $deleted = $supplier->delete();

        if ($deleted) {
            $this->auditLogService->record(
                event: 'deleted',
                module: 'suppliers',
                auditable: $supplier,
                description: sprintf('Supplier "%s" was deleted.', $name),
                oldValues: $oldValues,
                metadata: [
                    'model' => 'supplier',
                ],
            );
        }

        return $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Supplier $supplier): array
    {
        $values = [];

        foreach (self::AUDIT_FIELDS as $field) {
            $values[$field] = $supplier->getAttribute($field);
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

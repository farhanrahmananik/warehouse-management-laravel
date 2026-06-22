<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AuditLogQueryService
{
    /**
     * @param  array{user_id?: int|string|null, module?: string|null, event?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function paginated(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        $query = AuditLog::query()
            ->with('user')
            ->when($normalizedFilters['user_id'] ?? null, function ($query, $userId) {
                $query->where('user_id', $userId);
            })
            ->when($normalizedFilters['module'] ?? null, function ($query, string $module) {
                $query->where('module', $module);
            })
            ->when($normalizedFilters['event'] ?? null, function ($query, string $event) {
                $query->where('event', $event);
            })
            ->when($normalizedFilters['date_from'] ?? null, function ($query, string $dateFrom) {
                $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            })
            ->when($normalizedFilters['date_to'] ?? null, function ($query, string $dateTo) {
                $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
            })
            ->latest('created_at')
            ->latest('id');

        return $query->paginate($perPage)->appends($this->filledFilters($normalizedFilters));
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function users(): EloquentCollection
    {
        return User::query()
            ->orderBy('name')
            ->orderBy('email')
            ->get();
    }

    /**
     * @return Collection<int, string>
     */
    public function modules(): Collection
    {
        return AuditLog::query()
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');
    }

    /**
     * @return Collection<int, string>
     */
    public function events(): Collection
    {
        return AuditLog::query()
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        $expectedFilters = [
            'user_id' => null,
            'module' => null,
            'event' => null,
            'date_from' => null,
            'date_to' => null,
        ];

        foreach ($expectedFilters as $key => $defaultValue) {
            $value = $filters[$key] ?? $defaultValue;
            $expectedFilters[$key] = $value === '' ? null : $value;
        }

        return $expectedFilters;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function filledFilters(array $filters): array
    {
        return array_filter($filters, fn ($value): bool => $value !== null && $value !== '');
    }
}

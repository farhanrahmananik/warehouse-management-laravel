<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class AuditLogService
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'api_token',
        'secret',
        'key',
        '_token',
        'csrf_token',
    ];

    public function record(
        string $event,
        string $module,
        ?Model $auditable = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?User $user = null
    ): AuditLog {
        $authenticatedUser = auth()->user();

        if ($user === null && $authenticatedUser instanceof User) {
            $user = $authenticatedUser;
        }

        try {
            $request = request();
        } catch (Throwable) {
            $request = null;
        }

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'event' => $event,
            'module' => $module,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'old_values' => $this->sanitizeValues($oldValues),
            'new_values' => $this->sanitizeValues($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'metadata' => $this->sanitizeValues($metadata),
        ]);
    }

    /**
     * @param  array<string|int, mixed>|null  $values
     * @return array<string|int, mixed>|null
     */
    private function sanitizeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sanitized = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitizeValues($value)
                : $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        return in_array(strtolower($key), self::SENSITIVE_KEYS, true);
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'created_by');
    }

    public function createdPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by');
    }

    public function approvedPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'approved_by');
    }

    public function receivedPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'received_by');
    }

    public function cancelledPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'cancelled_by');
    }

    public function stockIns(): HasMany
    {
        return $this->hasMany(StockIn::class, 'created_by');
    }

    public function stockOuts(): HasMany
    {
        return $this->hasMany(StockOut::class, 'created_by');
    }

    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'created_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where(function ($query) use ($role) {
                $query->where('slug', $role)
                    ->orWhere('name', $role);
            })
            ->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('slug', $permission)
                    ->orWhere('name', $permission);
            })
            ->exists();
    }
}

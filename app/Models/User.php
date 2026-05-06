<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public const ROLE_OWNER = 'owner';
    public const ROLE_INVENTORY = 'inventory_staff';
    public const ROLE_BARISTA = 'barista';

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function productionLogs()
    {
        return $this->hasMany(ProductionLog::class);
    }

    public function stockUsages()
    {
        return $this->hasMany(StockUsage::class);
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function approvedStockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class, 'approved_by');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}

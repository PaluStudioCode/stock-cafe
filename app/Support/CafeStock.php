<?php

namespace App\Support;

final class CafeStock
{
    public const ROLES = ['owner', 'inventory_staff', 'barista'];
    public const PURCHASE_STATUSES = ['draft', 'received'];
    public const PRODUCTION_STATUSES = ['completed', 'cancelled'];
    public const USAGE_STATUSES = ['draft', 'completed', 'cancelled'];
    public const ADJUSTMENT_STATUSES = ['draft', 'approved', 'cancelled'];
    public const USAGE_TYPES = ['waste', 'expired', 'damaged', 'internal_use', 'sample', 'other'];
    public const MOVEMENT_TYPES = [
        'opening_stock', 'purchase_receipt', 'production_usage', 'manual_usage', 'waste',
        'adjustment_in', 'adjustment_out', 'cancel_production', 'cancel_usage', 'cancel_adjustment',
    ];

    public static function code(string $prefix, string $table, string $column, ?string $date = null): string
    {
        $date = $date ? date('Ymd', strtotime($date)) : now()->format('Ymd');
        $like = $prefix.'-'.$date.'-%';
        $last = \DB::table($table)->where($column, 'like', $like)->orderByDesc($column)->value($column);
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $next);
    }
}

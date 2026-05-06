<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockUsage extends Model
{
    protected $guarded = [];
    protected $casts = ['usage_date' => 'datetime', 'estimated_total_cost' => 'decimal:2'];

    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(StockUsageItem::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockUsageItem extends Model
{
    protected $guarded = [];
    protected $casts = ['quantity' => 'decimal:3', 'unit_cost_snapshot' => 'decimal:2', 'estimated_cost' => 'decimal:2'];

    public function stockUsage() { return $this->belongsTo(StockUsage::class); }
    public function ingredient() { return $this->belongsTo(Ingredient::class); }
}

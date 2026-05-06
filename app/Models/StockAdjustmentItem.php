<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentItem extends Model
{
    protected $guarded = [];
    protected $casts = ['system_stock' => 'decimal:3', 'counted_stock' => 'decimal:3', 'difference' => 'decimal:3'];

    public function stockAdjustment() { return $this->belongsTo(StockAdjustment::class); }
    public function ingredient() { return $this->belongsTo(Ingredient::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionLogItem extends Model
{
    protected $guarded = [];
    protected $casts = [
        'quantity_per_serving_snapshot' => 'decimal:3',
        'quantity_used' => 'decimal:3',
        'unit_cost_snapshot' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
    ];

    public function productionLog() { return $this->belongsTo(ProductionLog::class); }
    public function ingredient() { return $this->belongsTo(Ingredient::class); }
}

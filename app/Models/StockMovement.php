<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity_in' => 'decimal:3',
        'quantity_out' => 'decimal:3',
        'stock_before' => 'decimal:3',
        'stock_after' => 'decimal:3',
        'unit_cost_snapshot' => 'decimal:2',
    ];

    public function ingredient() { return $this->belongsTo(Ingredient::class); }
    public function user() { return $this->belongsTo(User::class); }
}

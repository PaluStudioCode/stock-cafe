<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionLog extends Model
{
    protected $guarded = [];
    protected $casts = ['production_date' => 'datetime', 'quantity' => 'decimal:3', 'estimated_total_cost' => 'decimal:2'];

    public function menuItem() { return $this->belongsTo(MenuItem::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(ProductionLogItem::class); }
}

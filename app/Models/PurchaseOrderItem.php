<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $guarded = [];
    protected $casts = ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'subtotal' => 'decimal:2'];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function ingredient() { return $this->belongsTo(Ingredient::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'purchase_date' => 'date',
        'received_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(PurchaseOrderItem::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class, 'primary_supplier_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $guarded = [];
    protected $casts = ['adjustment_date' => 'datetime', 'approved_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function items() { return $this->hasMany(StockAdjustmentItem::class); }
}

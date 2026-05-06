<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_unit_cost' => 'decimal:2',
        'current_stock' => 'decimal:3',
        'minimum_stock' => 'decimal:3',
        'reorder_level' => 'decimal:3',
    ];

    public function category()
    {
        return $this->belongsTo(IngredientCategory::class, 'ingredient_category_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'primary_supplier_id');
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function productionLogItems()
    {
        return $this->hasMany(ProductionLogItem::class);
    }

    public function stockUsageItems()
    {
        return $this->hasMany(StockUsageItem::class);
    }

    public function stockAdjustmentItems()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'selling_price' => 'decimal:2'];

    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function productionLogs()
    {
        return $this->hasMany(ProductionLog::class);
    }
}

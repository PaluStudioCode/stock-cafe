<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeItem extends Model
{
    protected $guarded = [];

    protected $casts = ['quantity_per_serving' => 'decimal:3'];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}

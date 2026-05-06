<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngredientCategory extends Model
{
    protected $guarded = [];

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class);
    }
}

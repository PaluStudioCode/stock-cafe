<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $guarded = [];

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class);
    }
}

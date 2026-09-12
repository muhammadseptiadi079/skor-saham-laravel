<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockUniverseItem extends Model
{
    protected $fillable = ['ticker', 'market', 'name', 'category'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchlistItem extends Model
{
    protected $fillable = ['ticker', 'market', 'name', 'sector', 'is_favorite'];

    protected $casts = [
        'is_favorite' => 'boolean',
    ];
}

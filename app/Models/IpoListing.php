<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpoListing extends Model
{
    protected $table = 'ipo_listings';

    protected $fillable = [
        'market', 'ticker', 'company_name', 'ipo_date',
        'price_range', 'status', 'source', 'fetched_at',
    ];

    protected $casts = [
        'ipo_date' => 'date',
        'fetched_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualNewsItem extends Model
{
    protected $fillable = ['ticker', 'market', 'text', 'source', 'sentiment_score'];

    protected $casts = [
        'sentiment_score' => 'float',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisHistory extends Model
{
    protected $table = 'analysis_history';

    protected $fillable = [
        'ticker', 'market', 'name', 'currency',
        'longterm_score', 'longterm_label',
        'trading_score', 'trading_label',
        'sub_scores', 'generated_at',
    ];

    protected $casts = [
        'sub_scores' => 'array',
        'generated_at' => 'datetime',
    ];
}

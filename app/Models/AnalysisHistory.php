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
        'sub_scores', 'generated_at', 'pe_ratio', 'peg_ratio',
        'price_at_generation', 'benchmark_price_at_generation', 'evaluation_horizon_days',
        'evaluated_at', 'forward_return', 'outcome_correct', 'market_regime',
    ];

    protected $casts = [
        'sub_scores' => 'array',
        'generated_at' => 'datetime',
        'evaluated_at' => 'datetime',
        'outcome_correct' => 'boolean',
    ];
}

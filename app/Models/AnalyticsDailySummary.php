<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsDailySummary extends Model
{
    protected $fillable = [
        'date',
        'users',
        'sessions',
        'screen_page_views',
        'event_count',
        'conversions',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}

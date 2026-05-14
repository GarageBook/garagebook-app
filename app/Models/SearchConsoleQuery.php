<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchConsoleQuery extends Model
{
    protected $fillable = [
        'date',
        'query',
        'clicks',
        'impressions',
        'ctr',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'ctr' => 'decimal:4',
            'position' => 'decimal:2',
        ];
    }
}

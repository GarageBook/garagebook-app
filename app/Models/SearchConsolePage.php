<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchConsolePage extends Model
{
    protected $fillable = [
        'date',
        'page',
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

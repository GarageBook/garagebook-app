<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GscQuerySnapshot extends Model
{
    protected $fillable = [
        'date',
        'query',
        'page_url',
        'path',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'page_type',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clicks' => 'integer',
            'impressions' => 'integer',
            'ctr' => 'decimal:4',
            'position' => 'decimal:2',
        ];
    }
}

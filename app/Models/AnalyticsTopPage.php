<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsTopPage extends Model
{
    protected $fillable = [
        'date',
        'page_path',
        'page_title',
        'views',
        'users',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}

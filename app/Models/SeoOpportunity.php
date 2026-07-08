<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoOpportunity extends Model
{
    protected $fillable = [
        'date',
        'type',
        'title',
        'description',
        'impact_score',
        'effort',
        'priority',
        'page_url',
        'path',
        'query',
        'page_type',
        'brand',
        'recommended_action',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'impact_score' => 'integer',
            'metadata' => 'array',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VehicleAuthorityIndex extends Model
{
    protected $table = 'vehicle_authority_index';

    protected $fillable = [
        'brand',
        'model',
        'generation',
        'category',
        'slug',
        'vehicle_count',
        'public_vehicle_count',
        'is_indexable',
        'first_seen_at',
        'last_seen_at',
        'organic_clicks',
        'organic_impressions',
        'ctr',
        'average_position',
    ];

    protected function casts(): array
    {
        return [
            'is_indexable' => 'boolean',
            'vehicle_count' => 'integer',
            'public_vehicle_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public static function makeSlug(string $brand, string $model): string
    {
        return Str::slug($brand.' '.$model);
    }
}

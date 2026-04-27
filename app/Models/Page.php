<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'hero_image',
        'meta_title',
        'meta_description',
        'canonical_url',
        'indexable',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'indexable' => 'boolean',
        ];
    }
}

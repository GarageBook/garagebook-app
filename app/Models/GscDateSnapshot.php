<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GscDateSnapshot extends Model
{
    protected $fillable = ['date', 'data_date', 'clicks', 'impressions', 'ctr', 'position'];

    protected function casts(): array
    {
        return ['date' => 'date', 'data_date' => 'date', 'clicks' => 'integer', 'impressions' => 'integer', 'ctr' => 'decimal:4', 'position' => 'decimal:2'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GscDeviceSnapshot extends Model
{
    protected $fillable = ['date', 'device', 'clicks', 'impressions', 'ctr', 'position'];

    protected function casts(): array
    {
        return ['date' => 'date', 'clicks' => 'integer', 'impressions' => 'integer', 'ctr' => 'decimal:4', 'position' => 'decimal:2'];
    }
}

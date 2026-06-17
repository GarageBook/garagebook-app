<?php

namespace App\Models;

use Database\Factories\OutreachCampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutreachCampaign extends Model
{
    /** @use HasFactory<OutreachCampaignFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function prospects(): HasMany
    {
        return $this->hasMany(OutreachProspect::class);
    }
}

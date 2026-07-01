<?php

namespace Database\Seeders;

use App\Models\GrowthCampaign;
use Illuminate\Database\Seeder;

class GrowthCampaignSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['name' => 'Club2026', 'slug' => 'club2026'],
            ['name' => 'Community2026', 'slug' => 'community2026', 'description' => 'Merkclubs, oldtimerclubs, camperclubs, youngtimerclubs en andere voertuigcommunities.'],
            ['name' => 'Partner2026', 'slug' => 'partner2026', 'description' => 'Gespecialiseerde bedrijven rondom onderhoud, onderdelen, banden, detailing, tuning, vering, remmen, oldtimers, campers, 4x4 en motoraccessoires.'],
            ['name' => 'Classic2026', 'slug' => 'classic2026'],
            ['name' => 'Event2026', 'slug' => 'event2026'],
            ['name' => 'Training2026', 'slug' => 'training2026'],
            ['name' => 'Workshop2026', 'slug' => 'workshop2026'],
            ['name' => 'Media2026', 'slug' => 'media2026'],
        ])->each(function (array $campaign): void {
            GrowthCampaign::query()->updateOrCreate(
                ['slug' => $campaign['slug']],
                [
                    'name' => $campaign['name'],
                    'status' => GrowthCampaign::STATUS_DRAFT,
                    'description' => $campaign['description'] ?? null,
                ],
            );
        });
    }
}

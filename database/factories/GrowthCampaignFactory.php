<?php

namespace Database\Factories;

use App\Models\GrowthCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GrowthCampaign>
 */
class GrowthCampaignFactory extends Factory
{
    protected $model = GrowthCampaign::class;

    public function definition(): array
    {
        $name = fake()->words(2, true).' 2026';

        return [
            'name' => str($name)->title()->value(),
            'slug' => Str::slug($name.'-'.fake()->unique()->numberBetween(1, 9999)),
            'status' => fake()->randomElement(GrowthCampaign::STATUSES),
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}

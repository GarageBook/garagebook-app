<?php

namespace Database\Factories;

use App\Models\OutreachCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutreachCampaign>
 */
class OutreachCampaignFactory extends Factory
{
    protected $model = OutreachCampaign::class;

    public function definition(): array
    {
        $name = fake()->company() . ' campagne';

        return [
            'name' => $name,
            'slug' => Str::slug($name . '-' . fake()->unique()->numberBetween(1, 9999)),
            'description' => fake()->sentence(),
        ];
    }
}

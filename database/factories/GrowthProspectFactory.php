<?php

namespace Database\Factories;

use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GrowthProspect>
 */
class GrowthProspectFactory extends Factory
{
    protected $model = GrowthProspect::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'website' => fake()->domainName(),
            'category' => fake()->randomElement(['club', 'event', 'media', 'training', 'workshop']),
            'subcategory' => fake()->word(),
            'region' => fake()->city(),
            'estimated_reach' => fake()->randomElement(['100-500', '500-1.000', '1.000+']),
            'newsletter_status' => fake()->randomElement(['unknown', 'available', 'not_available']),
            'primary_contact_channel' => fake()->randomElement(['email', 'contact_form', 'instagram', 'linkedin']),
            'contact_name' => fake()->name(),
            'email' => fake()->companyEmail(),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'warmth' => fake()->randomElement(['cold', 'warm', 'hot']),
            'score' => fake()->numberBetween(1, 100),
            'status' => fake()->randomElement(['new', 'researching', 'contacted', 'paused']),
            'campaign_id' => GrowthCampaign::factory(),
            'partner_slug' => Str::slug($name.'-'.fake()->unique()->numberBetween(1, 9999)),
            'notes' => fake()->sentence(),
            'why_interesting' => fake()->sentence(),
            'approach_strategy' => fake()->sentence(),
            'last_contacted_at' => null,
            'next_follow_up_at' => null,
        ];
    }
}

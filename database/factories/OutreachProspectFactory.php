<?php

namespace Database\Factories;

use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutreachProspect>
 */
class OutreachProspectFactory extends Factory
{
    protected $model = OutreachProspect::class;

    public function definition(): array
    {
        return [
            'outreach_campaign_id' => OutreachCampaign::factory(),
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->companyEmail(),
            'website' => fake()->domainName(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'Nederland',
            'source' => 'factory',
            'import_note' => null,
            'archived_at' => null,
            'notes' => fake()->sentence(),
            'user_id' => null,
            'clicked_at' => null,
            'first_login_at' => null,
            'last_login_at' => null,
            'login_count' => 0,
        ];
    }

    public function withUser(?User $user = null): static
    {
        return $this->state(fn () => [
            'user_id' => ($user ?? User::factory()->create())->id,
        ]);
    }
}

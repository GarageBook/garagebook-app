<?php

namespace Database\Factories;

use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use App\Services\Growth\GrowthProspectNormalizer;
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

        $website = fake()->domainName();
        $email = fake()->companyEmail();
        $normalizer = app(GrowthProspectNormalizer::class);
        $domain = $normalizer->normalizeDomain($website);

        return [
            'name' => $name,
            'website' => $website,
            'organization_key' => $normalizer->organizationKey($name, $domain),
            'normalized_domain' => $domain,
            'category' => fake()->randomElement(['club', 'event', 'media', 'training', 'workshop']),
            'subcategory' => fake()->word(),
            'prospect_type' => 'community',
            'prospect_subtype' => fake()->randomElement(GrowthProspect::PROSPECT_SUBTYPES),
            'region' => fake()->city(),
            'estimated_reach' => fake()->randomElement(['100-500', '500-1.000', '1.000+']),
            'newsletter_status' => fake()->randomElement(['unknown', 'available', 'not_available']),
            'primary_contact_channel' => fake()->randomElement(['email', 'contact_form', 'instagram', 'linkedin']),
            'contact_name' => fake()->name(),
            'email' => $email,
            'normalized_email' => $normalizer->normalizeEmail($email),
            'email_status' => GrowthProspect::EMAIL_STATUS_FOUND,
            'verification_required' => false,
            'phone' => null,
            'city' => fake()->city(),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'warmth' => fake()->randomElement(['cold', 'warm', 'hot']),
            'score' => fake()->numberBetween(1, 100),
            'status' => fake()->randomElement(['new', 'researching', 'contacted', 'paused']),
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'campaign_id' => GrowthCampaign::factory(),
            'last_campaign_id' => null,
            'last_campaign_slug' => null,
            'partner_slug' => Str::slug($name.'-'.fake()->unique()->numberBetween(1, 9999)),
            'duplicate_of_id' => null,
            'skip_reason' => null,
            'source_url' => null,
            'source_type' => null,
            'notes' => fake()->sentence(),
            'why_interesting' => fake()->sentence(),
            'approach_strategy' => fake()->sentence(),
            'last_contacted_at' => null,
            'next_follow_up_at' => null,
        ];
    }
}

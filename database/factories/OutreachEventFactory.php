<?php

namespace Database\Factories;

use App\Models\OutreachEvent;
use App\Models\OutreachProspect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutreachEvent>
 */
class OutreachEventFactory extends Factory
{
    protected $model = OutreachEvent::class;

    public function definition(): array
    {
        return [
            'outreach_prospect_id' => OutreachProspect::factory(),
            'event_type' => 'email_link_opened',
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}

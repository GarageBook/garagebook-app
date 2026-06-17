<?php

namespace Tests\Feature;

use App\Models\OutreachProspect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachDemoIntroTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_shows_demo_intro_for_outreach_demo_user_and_dismisses_persistently(): void
    {
        $user = User::factory()->create([
            'is_outreach_demo' => true,
        ]);

        $prospect = OutreachProspect::factory()->create([
            'user_id' => $user->id,
            'demo_intro_shown_at' => null,
            'demo_intro_dismissed_at' => null,
        ]);

        $this->actingAs($user)
            ->get('/admin/tijdlijn')
            ->assertOk()
            ->assertSeeText('Welkom in GarageBook')
            ->assertSeeText('Start demo');

        $prospect->refresh();
        $this->assertNotNull($prospect->demo_intro_shown_at);
        $this->assertNull($prospect->demo_intro_dismissed_at);
        $this->assertDatabaseHas('outreach_events', [
            'outreach_prospect_id' => $prospect->id,
            'event_type' => 'demo_intro_shown',
        ]);

        $this->actingAs($user)
            ->post(route('outreach.demo.intro-dismiss'))
            ->assertOk();

        $prospect->refresh();
        $this->assertNotNull($prospect->demo_intro_dismissed_at);
        $this->assertDatabaseHas('outreach_events', [
            'outreach_prospect_id' => $prospect->id,
            'event_type' => 'demo_intro_dismissed',
        ]);

        $this->actingAs($user)
            ->get('/admin/tijdlijn')
            ->assertOk()
            ->assertDontSeeText('Welkom in GarageBook');
    }

    public function test_timeline_does_not_show_demo_intro_for_regular_user_or_admin(): void
    {
        $regularUser = User::factory()->create([
            'is_outreach_demo' => false,
        ]);

        $admin = User::factory()->create([
            'email' => User::ADMIN_EMAIL,
            'is_outreach_demo' => true,
        ]);

        $this->actingAs($regularUser)
            ->get('/admin/tijdlijn')
            ->assertOk()
            ->assertDontSeeText('Welkom in GarageBook');

        $this->actingAs($admin)
            ->get('/admin/tijdlijn')
            ->assertOk()
            ->assertDontSeeText('Welkom in GarageBook');
    }
}

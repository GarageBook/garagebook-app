<?php

namespace Tests\Feature;

use App\Filament\Resources\GrowthProspects\Pages\CreateGrowthProspect;
use App\Filament\Resources\GrowthProspects\Pages\EditGrowthProspect;
use App\Filament\Resources\GrowthProspects\Pages\ListGrowthProspects;
use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GrowthProspectResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_growth_prospect_index(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Club2026',
        ]);

        GrowthProspect::factory()->create([
            'name' => 'Motorclub Noord',
            'category' => 'club',
            'campaign_id' => $campaign->id,
        ]);

        $this->actingAs($admin)
            ->get('/admin/growth-prospects')
            ->assertOk()
            ->assertSeeText('Motorclub Noord');
    }

    public function test_admin_can_create_growth_prospect(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Event2026',
        ]);

        Livewire::actingAs($admin)
            ->test(CreateGrowthProspect::class)
            ->fillForm([
                'name' => 'Circuit Partner',
                'website' => 'circuit.example',
                'category' => 'event',
                'subcategory' => 'trackday',
                'region' => 'Noord-Holland',
                'estimated_reach' => '1.000+',
                'newsletter_status' => 'available',
                'primary_contact_channel' => 'email',
                'contact_name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'priority' => 'high',
                'warmth' => 'warm',
                'score' => 82,
                'status' => 'new',
                'campaign_id' => $campaign->id,
                'partner_slug' => 'circuit-partner',
                'notes' => 'Heeft een actieve community.',
                'why_interesting' => 'Bereikt sportieve motorrijders.',
                'approach_strategy' => 'Benaderen met event-specifieke onderhoudsboek propositie.',
                'last_contacted_at' => null,
                'next_follow_up_at' => '2026-07-10 09:00:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('growth_prospects', [
            'name' => 'Circuit Partner',
            'category' => 'event',
            'campaign_id' => $campaign->id,
            'partner_slug' => 'circuit-partner',
            'priority' => 'high',
            'warmth' => 'warm',
            'score' => 82,
            'status' => 'new',
        ]);
    }

    public function test_admin_can_update_growth_prospect(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Workshop2026',
        ]);
        $prospect = GrowthProspect::factory()->create([
            'name' => 'Oude werkplaats',
            'campaign_id' => null,
            'partner_slug' => 'oude-werkplaats',
            'status' => 'new',
        ]);

        Livewire::actingAs($admin)
            ->test(EditGrowthProspect::class, ['record' => $prospect->getRouteKey()])
            ->fillForm([
                'name' => 'Bijgewerkte werkplaats',
                'website' => 'werkplaats.example',
                'category' => 'workshop',
                'subcategory' => 'maintenance',
                'region' => 'Utrecht',
                'estimated_reach' => '500-1.000',
                'newsletter_status' => 'unknown',
                'primary_contact_channel' => 'contact_form',
                'contact_name' => 'John Doe',
                'email' => 'john@example.com',
                'priority' => 'medium',
                'warmth' => 'hot',
                'score' => 91,
                'status' => 'contacted',
                'campaign_id' => $campaign->id,
                'partner_slug' => 'bijgewerkte-werkplaats',
                'notes' => 'Nieuwe notitie.',
                'why_interesting' => 'Veel klanten met onderhoudsvragen.',
                'approach_strategy' => 'Persoonlijke demo aanbieden.',
                'last_contacted_at' => '2026-07-01 10:30:00',
                'next_follow_up_at' => '2026-07-08 10:30:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $prospect->refresh();

        $this->assertSame('Bijgewerkte werkplaats', $prospect->name);
        $this->assertSame('contacted', $prospect->status);
        $this->assertSame('hot', $prospect->warmth);
        $this->assertSame(91, $prospect->score);
        $this->assertTrue($prospect->campaign->is($campaign));
    }

    public function test_growth_prospect_belongs_to_growth_campaign(): void
    {
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Media2026',
        ]);
        $prospect = GrowthProspect::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $this->assertTrue($prospect->campaign->is($campaign));
    }

    public function test_growth_prospect_partner_slug_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();

        GrowthProspect::factory()->create([
            'partner_slug' => 'unique-partner',
        ]);

        Livewire::actingAs($admin)
            ->test(CreateGrowthProspect::class)
            ->fillForm([
                'name' => 'Duplicaat partner',
                'partner_slug' => 'unique-partner',
            ])
            ->call('create')
            ->assertHasFormErrors(['partner_slug' => 'unique']);
    }

    public function test_growth_prospect_list_supports_relevant_query_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Training2026',
        ]);

        GrowthProspect::factory()->create([
            'name' => 'Training Partner',
            'campaign_id' => $campaign->id,
            'category' => 'training',
            'priority' => 'high',
            'warmth' => 'warm',
            'status' => 'researching',
        ]);
        GrowthProspect::factory()->create([
            'name' => 'Andere Partner',
            'category' => 'media',
            'priority' => 'low',
            'warmth' => 'cold',
            'status' => 'paused',
        ]);

        Livewire::actingAs($admin)
            ->test(ListGrowthProspects::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(GrowthProspect::query()
                ->where('campaign_id', $campaign->id)
                ->where('category', 'training')
                ->where('priority', 'high')
                ->where('warmth', 'warm')
                ->where('status', 'researching')
                ->get());
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\GrowthCampaigns\Pages\CreateGrowthCampaign;
use App\Filament\Resources\GrowthCampaigns\Pages\EditGrowthCampaign;
use App\Filament\Resources\GrowthCampaigns\Pages\ListGrowthCampaigns;
use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GrowthCampaignResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_growth_campaign_index(): void
    {
        $admin = User::factory()->admin()->create();
        GrowthCampaign::factory()->create([
            'name' => 'Club2026',
            'slug' => 'club2026',
        ]);

        $this->actingAs($admin)
            ->get('/admin/growth-campaigns')
            ->assertOk()
            ->assertSeeText('Club2026');
    }

    public function test_admin_can_create_growth_campaign(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateGrowthCampaign::class)
            ->fillForm([
                'name' => 'Event2026',
                'slug' => 'event2026',
                'status' => GrowthCampaign::STATUS_ACTIVE,
                'starts_at' => '2026-07-01 09:00:00',
                'ends_at' => null,
                'stop_criteria' => 'Stop als CPA boven 25 euro blijft na 50 leads.',
                'scale_criteria' => 'Opschalen bij minimaal 10 betaalde accounts met CPA onder 12 euro.',
                'kpi_notes' => 'Meet op activatie, betaalde conversie en demo-aanvragen.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('growth_campaigns', [
            'name' => 'Event2026',
            'slug' => 'event2026',
            'status' => GrowthCampaign::STATUS_ACTIVE,
            'stop_criteria' => 'Stop als CPA boven 25 euro blijft na 50 leads.',
            'scale_criteria' => 'Opschalen bij minimaal 10 betaalde accounts met CPA onder 12 euro.',
            'kpi_notes' => 'Meet op activatie, betaalde conversie en demo-aanvragen.',
        ]);
    }

    public function test_admin_can_update_growth_campaign(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Workshop2026',
            'slug' => 'workshop2026',
            'status' => GrowthCampaign::STATUS_DRAFT,
        ]);

        Livewire::actingAs($admin)
            ->test(EditGrowthCampaign::class, ['record' => $campaign->getRouteKey()])
            ->fillForm([
                'name' => 'Workshop2026 bijgewerkt',
                'slug' => 'workshop2026',
                'status' => GrowthCampaign::STATUS_PAUSED,
                'starts_at' => null,
                'ends_at' => null,
                'stop_criteria' => 'Pauzeren als er drie dagen geen activaties volgen.',
                'scale_criteria' => 'Budget verhogen bij stabiele activatiekosten onder 8 euro.',
                'kpi_notes' => 'Controleer wekelijkse cohort-retentie naast registraties.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $campaign->refresh();

        $this->assertSame('Workshop2026 bijgewerkt', $campaign->name);
        $this->assertSame(GrowthCampaign::STATUS_PAUSED, $campaign->status);
        $this->assertSame('Pauzeren als er drie dagen geen activaties volgen.', $campaign->stop_criteria);
        $this->assertSame('Budget verhogen bij stabiele activatiekosten onder 8 euro.', $campaign->scale_criteria);
        $this->assertSame('Controleer wekelijkse cohort-retentie naast registraties.', $campaign->kpi_notes);
    }

    public function test_admin_can_delete_growth_campaign(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Media2026',
            'slug' => 'media2026',
        ]);

        Livewire::actingAs($admin)
            ->test(EditGrowthCampaign::class, ['record' => $campaign->getRouteKey()])
            ->callAction('delete')
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('growth_campaigns', [
            'id' => $campaign->id,
        ]);
    }

    public function test_growth_campaign_slug_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();

        GrowthCampaign::factory()->create([
            'slug' => 'classic2026',
        ]);

        Livewire::actingAs($admin)
            ->test(CreateGrowthCampaign::class)
            ->fillForm([
                'name' => 'Classic2026 duplicaat',
                'slug' => 'classic2026',
                'status' => GrowthCampaign::STATUS_DRAFT,
                'starts_at' => null,
                'ends_at' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_growth_campaign_list_livewire_component_loads(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ListGrowthCampaigns::class)
            ->assertSuccessful();
    }

    public function test_growth_campaign_counts_are_separate_from_legacy_outreach_campaign_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $club = GrowthCampaign::factory()->create(['name' => 'Club2026', 'slug' => 'club2026']);
        $classic = GrowthCampaign::factory()->create(['name' => 'Classic2026', 'slug' => 'classic2026']);
        $legacy = OutreachCampaign::factory()->create(['name' => 'Growth club2026', 'slug' => 'growth-club2026']);

        GrowthProspect::factory()->count(21)->create(['campaign_id' => $club->id]);
        GrowthProspect::factory()->count(9)->create(['campaign_id' => $classic->id]);
        OutreachProspect::factory()->count(4)->create(['outreach_campaign_id' => $legacy->id]);

        $growthCounts = GrowthCampaign::query()
            ->withCount('prospects')
            ->whereIn('slug', ['club2026', 'classic2026'])
            ->pluck('prospects_count', 'slug');

        $this->assertSame(21, $growthCounts->get('club2026'));
        $this->assertSame(9, $growthCounts->get('classic2026'));
        $this->assertSame(4, $legacy->prospects()->count());
        $this->assertSame(0, GrowthCampaign::query()->where('slug', 'growth-club2026')->count());
        $this->assertSame(0, OutreachCampaign::query()->whereIn('slug', ['club2026', 'classic2026'])->count());

        Livewire::actingAs($admin)
            ->test(ListGrowthCampaigns::class)
            ->assertSeeText('Club2026')
            ->assertSeeText('Classic2026')
            ->assertSeeText('21')
            ->assertSeeText('9')
            ->assertDontSeeText('growth-club2026');
    }
}

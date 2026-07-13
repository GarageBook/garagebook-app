<?php

namespace Tests\Feature;

use App\Filament\Resources\OutreachCampaigns\Pages\ListOutreachCampaigns;
use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OutreachCampaignResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_growth_club2026_outreach_campaign_counts_only_outreach_prospects(): void
    {
        $admin = User::factory()->admin()->create();
        $club = GrowthCampaign::factory()->create(['name' => 'Club2026', 'slug' => 'club2026']);
        $legacy = OutreachCampaign::factory()->create(['name' => 'Growth club2026', 'slug' => 'growth-club2026']);

        GrowthProspect::factory()->count(21)->create(['campaign_id' => $club->id]);
        OutreachProspect::factory()->count(4)->create(['outreach_campaign_id' => $legacy->id]);

        $this->assertSame(21, $club->prospects()->count());
        $this->assertSame(4, $legacy->prospects()->count());

        $legacyWithCount = OutreachCampaign::query()
            ->withCount('prospects')
            ->where('slug', 'growth-club2026')
            ->firstOrFail();

        $this->assertSame(4, $legacyWithCount->prospects_count);

        Livewire::actingAs($admin)
            ->test(ListOutreachCampaigns::class)
            ->assertSeeText('Growth club2026')
            ->assertSeeText('growth-club2026')
            ->assertSeeText('4')
            ->assertDontSeeText('Club2026')
            ->assertDontSeeText('21');
    }
}

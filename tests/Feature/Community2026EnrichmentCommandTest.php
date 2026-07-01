<?php

namespace Tests\Feature;

use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Community2026EnrichmentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrichment_promotes_high_confidence_mailto_to_found_and_ready(): void
    {
        Mail::fake();
        Queue::fake();
        $campaign = $this->communityCampaign();
        $prospect = $this->missingProspect($campaign, 'https://club.example');

        Http::fake([
            'https://club.example/contact' => Http::response('<html><body><a href="mailto:info@club.example">Mail ons</a></body></html>'),
            'https://club.example/*' => Http::response('', 404),
            'https://club.example' => Http::response('<html><body><a href="/contact">Contact</a></body></html>'),
        ]);

        $this->artisan('garagebook:community2026-enrich', ['--limit' => 1])
            ->expectsOutput('e-mails automatisch gevonden: 1')
            ->expectsOutput('suggested_email gevonden: 0')
            ->assertSuccessful();

        $prospect->refresh();
        $this->assertSame('info@club.example', $prospect->email);
        $this->assertSame(GrowthProspect::EMAIL_STATUS_FOUND, $prospect->email_status);
        $this->assertFalse($prospect->verification_required);
        $this->assertSame(GrowthProspect::LIFECYCLE_READY, $prospect->lifecycle_status);
        $this->assertSame(95, $prospect->suggested_email_confidence);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_lower_confidence_email_is_stored_as_suggestion_only(): void
    {
        Mail::fake();
        Queue::fake();
        $campaign = $this->communityCampaign();
        $prospect = $this->missingProspect($campaign, 'https://oldtimer.example');

        Http::fake([
            'https://oldtimer.example/privacy' => Http::response('<html><body>Privacy: contact@oldtimer.example</body></html>'),
            'https://oldtimer.example/*' => Http::response('', 404),
            'https://oldtimer.example' => Http::response('<html><body><a href="/privacy">Privacybeleid</a></body></html>'),
        ]);

        $this->artisan('garagebook:community2026-enrich', ['--limit' => 1])
            ->assertSuccessful();

        Http::assertSent(fn ($request): bool => (string) $request->url() === 'https://oldtimer.example/privacy');

        $prospect->refresh();
        $this->assertNull($prospect->email);
        $this->assertSame(GrowthProspect::EMAIL_STATUS_MISSING, $prospect->email_status);
        $this->assertTrue($prospect->verification_required);
        $this->assertSame('contact@oldtimer.example', $prospect->suggested_email);
        $this->assertSame(80, $prospect->suggested_email_confidence);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_enrichment_blocks_personal_noreply_and_webshop_addresses(): void
    {
        $campaign = $this->communityCampaign();
        $prospect = $this->missingProspect($campaign, 'https://vereniging.example');

        Http::fake([
            'https://vereniging.example/contact' => Http::response(implode('', [
                '<html><body>',
                '<a href="mailto:jan@vereniging.example">Jan</a>',
                '<a href="mailto:noreply@vereniging.example">Noreply</a>',
                '<a href="mailto:webshop@vereniging.example">Webshop</a>',
                '<a href="mailto:secretariaat@vereniging.example">Secretariaat</a>',
                '</body></html>',
            ])),
            'https://vereniging.example/*' => Http::response('', 404),
            'https://vereniging.example' => Http::response('<html><body><a href="/contact">Contact</a></body></html>'),
        ]);

        $this->artisan('garagebook:community2026-enrich', ['--limit' => 1])->assertSuccessful();

        $prospect->refresh();
        $this->assertSame('secretariaat@vereniging.example', $prospect->email);
        $this->assertSame('secretariaat@vereniging.example', $prospect->suggested_email);
        $this->assertSame(95, $prospect->suggested_email_confidence);
    }

    public function test_enrichment_only_scans_missing_verified_non_archived_prospects(): void
    {
        $campaign = $this->communityCampaign();
        $eligible = $this->missingProspect($campaign, 'https://eligible.example');
        $this->missingProspect($campaign, 'https://archived.example', [
            'lifecycle_status' => GrowthProspect::LIFECYCLE_ARCHIVED,
            'status' => GrowthProspect::LIFECYCLE_ARCHIVED,
        ]);
        $this->missingProspect($campaign, 'https://not-verification.example', [
            'verification_required' => false,
        ]);

        Http::fake([
            'https://eligible.example/contact' => Http::response('<html><body><a href="mailto:info@eligible.example">Info</a></body></html>'),
            'https://eligible.example/*' => Http::response('', 404),
            'https://eligible.example' => Http::response('<html><body><a href="/contact">Contact</a></body></html>'),
            '*' => Http::response('', 500),
        ]);

        $this->artisan('garagebook:community2026-enrich')->assertSuccessful();

        $this->assertSame('info@eligible.example', $eligible->fresh()->email);
        $this->assertNull(GrowthProspect::query()->where('website', 'https://archived.example')->firstOrFail()->suggested_email);
        $this->assertNull(GrowthProspect::query()->where('website', 'https://not-verification.example')->firstOrFail()->suggested_email);
    }

    private function communityCampaign(): GrowthCampaign
    {
        return GrowthCampaign::query()->create([
            'slug' => 'community2026',
            'name' => 'Community2026',
            'status' => GrowthCampaign::STATUS_DRAFT,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function missingProspect(GrowthCampaign $campaign, string $website, array $overrides = []): GrowthProspect
    {
        return GrowthProspect::factory()->create(array_merge([
            'campaign_id' => $campaign->id,
            'website' => $website,
            'normalized_domain' => parse_url($website, PHP_URL_HOST),
            'email' => null,
            'normalized_email' => null,
            'email_status' => GrowthProspect::EMAIL_STATUS_MISSING,
            'verification_required' => true,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_ENRICHED,
            'status' => GrowthProspect::LIFECYCLE_ENRICHED,
            'skip_reason' => 'missing_email',
        ], $overrides));
    }
}

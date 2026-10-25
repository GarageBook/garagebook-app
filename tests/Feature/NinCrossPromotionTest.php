<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\NinLinkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NinCrossPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('nin-cross-promo.enabled', true);
        Cache::flush();
    }

    public function test_model_match_keeps_both_public_banners_and_uses_one_lookup(): void
    {
        Http::fake(['noginnederland.nl/api/v1/lookup' => Http::response([
            'status' => 'model',
            'url' => 'https://noginnederland.nl/auto/volkswagen/golf',
        ])]);

        $vehicle = $this->vehicle('Volkswagen', 'Golf', true);
        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 12000,
            'maintenance_date' => '2026-05-01',
        ]);

        $html = $this->get('/garage/'.$vehicle->public_slug)->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-nin-cross-promo="true"'));
        $this->assertSame(2, substr_count($html, 'href="https://noginnederland.nl/auto/volkswagen/golf"'));
        $this->assertSame(2, substr_count($html, 'Hoeveel Volkswagen Golf zijn er nog in Nederland?'));
        $this->assertLessThan(strpos($html, 'data-nin-cross-promo="true"'), strpos($html, 'Laatst bijgewerkt'));
        $this->assertLessThan(strrpos($html, 'data-nin-cross-promo="true"'), strpos($html, 'Onderhoudstijdlijn'));
        $this->assertStringContainsString('images/noginnederland-logo.png', $html);

        $this->get('/garage/'.$vehicle->public_slug)->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->data() === ['brand' => 'Volkswagen', 'model' => 'Golf']);
    }

    public function test_registration_match_uses_confirmed_url_in_html_and_clickable_pdf(): void
    {
        Http::fake(['noginnederland.nl/api/v1/lookup' => Http::response([
            'status' => 'registration',
            'url' => 'https://noginnederland.nl/auto/volkswagen/golf?registration=TEST',
        ])]);

        $vehicle = $this->vehicle('Volkswagen', 'Golf', true, '12-AB-34');
        $html = $this->get('/garage/'.$vehicle->public_slug)->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'href="https://noginnederland.nl/auto/volkswagen/golf?registration=TEST"'));
        $this->assertStringNotContainsString('12-AB-34', $html);

        $response = $this->actingAs($vehicle->user)->get('/maintenance/pdf?vehicle_id='.$vehicle->id);
        $response->assertOk()->assertHeader('content-type', 'application/pdf');

        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertMatchesRegularExpression('~/URI\\s*\\(https://noginnederland\\.nl/auto/volkswagen/golf\\?registration=TEST\\)~', $pdf);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->data() === ['brand' => 'Volkswagen', 'model' => 'Golf']);
    }

    public function test_motorcycles_without_a_nin_match_have_no_banner(): void
    {
        Http::fake(['noginnederland.nl/api/v1/lookup' => Http::response([
            'status' => 'no_match',
            'reason' => 'not_found',
        ])]);

        foreach ([['Aprilia', 'RSV Mille'], ['Kawasaki', 'Z650']] as [$brand, $model]) {
            $vehicle = $this->vehicle($brand, $model, true);
            $html = $this->get('/garage/'.$vehicle->public_slug)->assertOk()->getContent();
            $this->assertStringNotContainsString('data-nin-cross-promo', $html);
        }

        Http::assertSentCount(2);
    }

    public function test_unavailable_lookup_reuses_last_valid_match_then_retries_after_cooldown(): void
    {
        Http::fakeSequence()
            ->push(['status' => 'model', 'url' => 'https://noginnederland.nl/auto/volkswagen/golf'])
            ->push(['status' => 'temporarily_unavailable'])
            ->push(['status' => 'model', 'url' => 'https://noginnederland.nl/auto/volkswagen/golf']);

        $vehicle = $this->vehicle('Volkswagen', 'Golf');
        $resolver = app(NinLinkResolver::class);

        $this->assertSame('https://noginnederland.nl/auto/volkswagen/golf', $resolver->resolve($vehicle));
        $this->travel(13)->hours();
        $this->assertSame('https://noginnederland.nl/auto/volkswagen/golf', $resolver->resolve($vehicle));
        $this->assertSame('https://noginnederland.nl/auto/volkswagen/golf', $resolver->resolve($vehicle));
        Http::assertSentCount(2);

        $this->travel(6)->minutes();
        $this->assertSame('https://noginnederland.nl/auto/volkswagen/golf', $resolver->resolve($vehicle));
        Http::assertSentCount(3);
    }

    public function test_no_match_removes_previous_match_and_hides_banner(): void
    {
        Http::fakeSequence()
            ->push(['status' => 'model', 'url' => 'https://noginnederland.nl/auto/volkswagen/golf'])
            ->push(['status' => 'no_match'])
            ->push(['status' => 'temporarily_unavailable']);

        $vehicle = $this->vehicle('Volkswagen', 'Golf');
        $resolver = app(NinLinkResolver::class);

        $this->assertNotNull($resolver->resolve($vehicle));
        $this->travel(13)->hours();
        $this->assertNull($resolver->resolve($vehicle));
        $this->assertNull($resolver->resolve($vehicle));
        Http::assertSentCount(2);

        $this->travel(7)->hours();
        $this->assertNull($resolver->resolve($vehicle));
        Http::assertSentCount(3);
    }

    public function test_failure_without_cached_match_hides_banner_and_does_not_retry_on_every_request(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('timeout');
        });

        $vehicle = $this->vehicle('Volkswagen', 'Golf', true);
        $this->assertStringNotContainsString('data-nin-cross-promo', $this->get('/garage/'.$vehicle->public_slug)->assertOk()->getContent());
        $this->assertStringNotContainsString('data-nin-cross-promo', $this->get('/garage/'.$vehicle->public_slug)->assertOk()->getContent());
        $this->assertSame(1, $attempts);
    }

    public function test_untrusted_url_is_never_used_as_a_banner_destination(): void
    {
        Http::fake(['noginnederland.nl/api/v1/lookup' => Http::response([
            'status' => 'model',
            'url' => 'https://noginnederland.nl.evil.example/auto/volkswagen/golf',
        ])]);

        $vehicle = $this->vehicle('Volkswagen', 'Golf', true);
        $html = $this->get('/garage/'.$vehicle->public_slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('data-nin-cross-promo', $html);
    }

    public function test_http_failure_keeps_a_previous_valid_match(): void
    {
        Http::fakeSequence()
            ->push(['status' => 'model', 'url' => 'https://noginnederland.nl/auto/volkswagen/golf'])
            ->push(['status' => 'temporarily_unavailable'], 503);

        $vehicle = $this->vehicle('Volkswagen', 'Golf');
        $resolver = app(NinLinkResolver::class);

        $this->assertSame('https://noginnederland.nl/auto/volkswagen/golf', $resolver->resolve($vehicle));
        $this->travel(13)->hours();
        $this->assertSame('https://noginnederland.nl/auto/volkswagen/golf', $resolver->resolve($vehicle));
        Http::assertSentCount(2);
    }

    public function test_no_match_hides_the_pdf_banner(): void
    {
        Http::fake(['noginnederland.nl/api/v1/lookup' => Http::response(['status' => 'no_match'])]);

        $vehicle = $this->vehicle('Aprilia', 'RSV Mille');
        $pdf = $this->actingAs($vehicle->user)
            ->get('/maintenance/pdf?vehicle_id='.$vehicle->id)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('https://noginnederland.nl', $pdf);
    }

    public function test_cold_pdf_lookup_makes_one_request_and_keeps_the_link_clickable(): void
    {
        Http::fake(['noginnederland.nl/api/v1/lookup' => Http::response([
            'status' => 'model',
            'url' => 'https://noginnederland.nl/auto/volkswagen/golf',
        ])]);

        $vehicle = $this->vehicle('Volkswagen', 'Golf', false, '12-AB-34');
        $pdf = $this->actingAs($vehicle->user)
            ->get('/maintenance/pdf?vehicle_id='.$vehicle->id)
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('~/URI\\s*\\(https://noginnederland\\.nl/auto/volkswagen/golf\\)~', $pdf);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->data() === ['brand' => 'Volkswagen', 'model' => 'Golf']);
    }

    private function vehicle(string $brand, string $model, bool $isPublic = false, ?string $licensePlate = null): Vehicle
    {
        return Vehicle::query()->create([
            'user_id' => User::factory()->create()->id,
            'brand' => $brand,
            'model' => $model,
            'is_public' => $isPublic,
            'license_plate' => $licensePlate,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAuthorityIndex;
use App\Services\VehicleAuthorityIndexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleAuthorityIndexServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleAuthorityIndexService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VehicleAuthorityIndexService::class);
    }

    private function indexEntry(array $attributes = []): VehicleAuthorityIndex
    {
        return VehicleAuthorityIndex::create(array_merge([
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'slug' => 'yamaha-mt-07',
            'vehicle_count' => 3,
            'public_vehicle_count' => 2,
            'is_indexable' => true,
            'first_seen_at' => now()->subDays(10),
            'last_seen_at' => now(),
        ], $attributes));
    }

    private function regularUser(): User
    {
        return User::factory()->create(['is_outreach_demo' => false]);
    }

    private function publicVehicle(User $user, string $brand, string $model, string $slug): Vehicle
    {
        return Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => $brand,
            'model' => $model,
            'is_public' => true,
            'public_slug' => $slug,
        ]);
    }

    // -------------------------------------------------------------------------
    // resolveBySlug
    // -------------------------------------------------------------------------

    public function test_resolve_by_slug_returns_indexable_entry(): void
    {
        $this->indexEntry();

        $result = $this->service->resolveBySlug('yamaha-mt-07');

        $this->assertNotNull($result);
        $this->assertSame('Yamaha', $result->brand);
        $this->assertSame('MT-07', $result->model);
    }

    public function test_resolve_by_slug_returns_null_for_unknown_slug(): void
    {
        $this->assertNull($this->service->resolveBySlug('unknown-model-xyz'));
    }

    public function test_resolve_by_slug_returns_null_for_non_indexable_entry(): void
    {
        $this->indexEntry(['is_indexable' => false, 'public_vehicle_count' => 0]);

        $this->assertNull($this->service->resolveBySlug('yamaha-mt-07'));
    }

    // -------------------------------------------------------------------------
    // allIndexableSlugs
    // -------------------------------------------------------------------------

    public function test_all_indexable_slugs_returns_only_indexable_entries(): void
    {
        $this->indexEntry(['slug' => 'yamaha-mt-07', 'model' => 'MT-07', 'is_indexable' => true, 'public_vehicle_count' => 2]);
        $this->indexEntry(['slug' => 'yamaha-mt-09', 'model' => 'MT-09', 'is_indexable' => false, 'public_vehicle_count' => 0]);

        $slugs = $this->service->allIndexableSlugs();

        $this->assertContains('yamaha-mt-07', $slugs->all());
        $this->assertNotContains('yamaha-mt-09', $slugs->all());
    }

    public function test_all_indexable_slugs_sorted_by_public_vehicle_count_desc(): void
    {
        $this->indexEntry(['slug' => 'yamaha-mt-07', 'model' => 'MT-07', 'is_indexable' => true, 'public_vehicle_count' => 5]);
        $this->indexEntry(['slug' => 'yamaha-mt-09', 'model' => 'MT-09', 'is_indexable' => true, 'public_vehicle_count' => 12]);

        $slugs = $this->service->allIndexableSlugs()->values();

        $this->assertSame('yamaha-mt-09', $slugs[0]);
        $this->assertSame('yamaha-mt-07', $slugs[1]);
    }

    // -------------------------------------------------------------------------
    // relatedModels
    // -------------------------------------------------------------------------

    public function test_related_models_returns_same_brand_excluding_current(): void
    {
        $this->indexEntry(['slug' => 'yamaha-mt-07', 'model' => 'MT-07', 'is_indexable' => true]);
        $this->indexEntry(['slug' => 'yamaha-mt-09', 'model' => 'MT-09', 'is_indexable' => true, 'public_vehicle_count' => 5]);
        $this->indexEntry(['slug' => 'yamaha-r1', 'model' => 'R1', 'is_indexable' => true, 'public_vehicle_count' => 1]);

        $related = $this->service->relatedModels('Yamaha', 'MT-07', 8);

        $models = $related->pluck('model')->all();
        $this->assertContains('MT-09', $models);
        $this->assertContains('R1', $models);
        $this->assertNotContains('MT-07', $models);
    }

    public function test_related_models_does_not_include_non_indexable(): void
    {
        $this->indexEntry(['slug' => 'yamaha-mt-07', 'model' => 'MT-07', 'is_indexable' => true]);
        $this->indexEntry(['slug' => 'yamaha-nmax', 'model' => 'NMAX', 'is_indexable' => false, 'public_vehicle_count' => 0]);

        $related = $this->service->relatedModels('Yamaha', 'MT-07', 8);

        $this->assertNotContains('NMAX', $related->pluck('model')->all());
    }

    public function test_related_models_respects_limit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->indexEntry([
                'slug' => 'yamaha-model-'.$i,
                'model' => 'Model '.$i,
                'is_indexable' => true,
                'public_vehicle_count' => $i,
            ]);
        }

        $related = $this->service->relatedModels('Yamaha', 'Nonexistent', 8);

        $this->assertLessThanOrEqual(8, $related->count());
    }

    public function test_related_models_sorted_by_public_vehicle_count_desc(): void
    {
        $this->indexEntry(['slug' => 'yamaha-mt-07', 'model' => 'MT-07', 'is_indexable' => true, 'public_vehicle_count' => 2]);
        $this->indexEntry(['slug' => 'yamaha-mt-09', 'model' => 'MT-09', 'is_indexable' => true, 'public_vehicle_count' => 10]);
        $this->indexEntry(['slug' => 'yamaha-r1', 'model' => 'R1', 'is_indexable' => true, 'public_vehicle_count' => 5]);

        $related = $this->service->relatedModels('Yamaha', 'MT-07', 8);

        $this->assertSame('MT-09', $related->first()->model, 'Most popular model should come first');
    }

    // -------------------------------------------------------------------------
    // topModels
    // -------------------------------------------------------------------------

    public function test_top_models_returns_sorted_by_public_vehicle_count_desc(): void
    {
        $this->indexEntry(['slug' => 'yamaha-mt-07', 'model' => 'MT-07', 'is_indexable' => true, 'public_vehicle_count' => 3]);
        $this->indexEntry(['slug' => 'yamaha-mt-09', 'model' => 'MT-09', 'is_indexable' => true, 'public_vehicle_count' => 8]);

        $top = $this->service->topModels(20);

        $this->assertSame('MT-09', $top->first()->model);
    }

    public function test_top_models_excludes_non_indexable(): void
    {
        $this->indexEntry(['slug' => 'yamaha-mt-07', 'model' => 'MT-07', 'is_indexable' => true, 'public_vehicle_count' => 3]);
        $this->indexEntry(['slug' => 'yamaha-hidden', 'model' => 'Hidden', 'is_indexable' => false, 'public_vehicle_count' => 0]);

        $top = $this->service->topModels(20);

        $this->assertNotContains('Hidden', $top->pluck('model')->all());
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    public function test_stats_returns_correct_counts(): void
    {
        $this->indexEntry(['slug' => 'yamaha-mt-07', 'model' => 'MT-07', 'is_indexable' => true, 'public_vehicle_count' => 3]);
        $this->indexEntry(['slug' => 'yamaha-hidden', 'model' => 'Hidden', 'is_indexable' => false, 'public_vehicle_count' => 0]);

        $stats = $this->service->stats();

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['indexable']);
        $this->assertSame(1, $stats['hidden']);
        $this->assertSame(1, $stats['no_public_vehicles']);
    }

    // -------------------------------------------------------------------------
    // Sitemap (sitemap-vehicle-authority.xml)
    // -------------------------------------------------------------------------

    public function test_sitemap_vehicle_authority_returns_xml(): void
    {
        $this->indexEntry();

        $this->get('/sitemap-vehicle-authority.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    }

    public function test_sitemap_vehicle_authority_contains_indexable_slugs(): void
    {
        $this->indexEntry(['slug' => 'yamaha-mt-07', 'model' => 'MT-07', 'is_indexable' => true]);

        $this->get('/sitemap-vehicle-authority.xml')
            ->assertOk()
            ->assertSee(url('/onderhoud/yamaha-mt-07'), false);
    }

    public function test_sitemap_vehicle_authority_excludes_non_indexable(): void
    {
        $this->indexEntry(['slug' => 'yamaha-hidden', 'model' => 'Hidden', 'is_indexable' => false, 'public_vehicle_count' => 0]);

        $response = $this->get('/sitemap-vehicle-authority.xml')->assertOk();

        $this->assertStringNotContainsString(
            url('/onderhoud/yamaha-hidden'),
            $response->getContent()
        );
    }

    // -------------------------------------------------------------------------
    // Caching
    // -------------------------------------------------------------------------

    public function test_resolve_by_slug_is_cached_after_first_call(): void
    {
        $this->indexEntry();

        // First call populates cache
        $first = $this->service->resolveBySlug('yamaha-mt-07');

        // Delete from DB
        VehicleAuthorityIndex::where('slug', 'yamaha-mt-07')->delete();

        // Second call should hit cache and still return the entry
        $second = $this->service->resolveBySlug('yamaha-mt-07');

        $this->assertNotNull($first);
        $this->assertNotNull($second, 'Should return cached value even after DB delete');
        $this->assertSame($first->id, $second->id);
    }

    public function test_flush_cache_clears_stats_and_slugs_cache(): void
    {
        $this->indexEntry();

        // Warm the caches
        $this->service->stats();
        $this->service->allIndexableSlugs();

        // Add new entry and flush
        $this->indexEntry(['slug' => 'honda-cb500f', 'brand' => 'Honda', 'model' => 'CB500F', 'is_indexable' => true, 'public_vehicle_count' => 1]);
        $this->service->flushCache();

        // Stats should reflect new entry
        $stats = $this->service->stats();
        $this->assertSame(2, $stats['total']);
    }

    // -------------------------------------------------------------------------
    // makeSlug static helper
    // -------------------------------------------------------------------------

    public function test_make_slug_returns_correct_format(): void
    {
        $this->assertSame('yamaha-mt-07', VehicleAuthorityIndex::makeSlug('Yamaha', 'MT-07'));
        $this->assertSame('honda-cb500f', VehicleAuthorityIndex::makeSlug('Honda', 'CB500F'));
        $this->assertSame('bmw-r1250-gs', VehicleAuthorityIndex::makeSlug('BMW', 'R1250 GS'));
    }
}

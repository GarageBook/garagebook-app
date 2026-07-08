<?php

namespace App\Services;

use App\Models\VehicleAuthorityIndex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class VehicleAuthorityIndexService
{
    private const CACHE_TTL = 1800;

    public function resolveBySlug(string $slug): ?VehicleAuthorityIndex
    {
        return Cache::remember("vai:slug:{$slug}", self::CACHE_TTL, function () use ($slug) {
            return VehicleAuthorityIndex::where('slug', $slug)
                ->where('is_indexable', true)
                ->first();
        });
    }

    /**
     * @return Collection<int, string>
     */
    public function allIndexableSlugs(): Collection
    {
        return Cache::remember('vai:all-slugs', self::CACHE_TTL, function () {
            return VehicleAuthorityIndex::where('is_indexable', true)
                ->orderByDesc('public_vehicle_count')
                ->pluck('slug');
        });
    }

    /**
     * @param  array{brand?: string, is_indexable?: bool, min_public_vehicles?: int}  $filters
     * @return Collection<int, VehicleAuthorityIndex>
     */
    public function indexableModels(array $filters = []): Collection
    {
        $query = VehicleAuthorityIndex::query()
            ->where('is_indexable', true)
            ->orderByDesc('public_vehicle_count')
            ->orderBy('brand')
            ->orderBy('model');

        if (! empty($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }

        if (isset($filters['min_public_vehicles']) && $filters['min_public_vehicles'] > 0) {
            $query->where('public_vehicle_count', '>=', $filters['min_public_vehicles']);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, VehicleAuthorityIndex>
     */
    public function relatedModels(string $brand, string $currentModel, int $limit = 8): Collection
    {
        return VehicleAuthorityIndex::where('brand', $brand)
            ->where('model', '!=', $currentModel)
            ->where('is_indexable', true)
            ->orderByDesc('public_vehicle_count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, VehicleAuthorityIndex>
     */
    public function topModels(int $limit = 20): Collection
    {
        return VehicleAuthorityIndex::where('is_indexable', true)
            ->orderByDesc('public_vehicle_count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, VehicleAuthorityIndex>
     */
    public function newestModels(int $limit = 10): Collection
    {
        return VehicleAuthorityIndex::where('is_indexable', true)
            ->orderByDesc('first_seen_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, string>
     */
    public function distinctBrands(): Collection
    {
        return VehicleAuthorityIndex::where('is_indexable', true)
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');
    }

    /**
     * @return array{total: int, indexable: int, hidden: int, no_public_vehicles: int}
     */
    public function stats(): array
    {
        return Cache::remember('vai:stats', self::CACHE_TTL, function () {
            $total = VehicleAuthorityIndex::count();
            $indexable = VehicleAuthorityIndex::where('is_indexable', true)->count();
            $noPublic = VehicleAuthorityIndex::where('public_vehicle_count', 0)->count();

            return [
                'total' => $total,
                'indexable' => $indexable,
                'hidden' => $total - $indexable,
                'no_public_vehicles' => $noPublic,
            ];
        });
    }

    public function flushCache(): void
    {
        Cache::forget('vai:stats');
        Cache::forget('vai:all-slugs');
    }

    public function flushSlugCache(string $slug): void
    {
        Cache::forget("vai:slug:{$slug}");
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'public_slug')) {
                $table->string('public_slug')->nullable()->after('year');
            }

            if (! Schema::hasColumn('vehicles', 'is_public')) {
                $table->boolean('is_public')->default(false)->after('public_slug');
            }

            if (! Schema::hasColumn('vehicles', 'share_costs_publicly')) {
                $table->boolean('share_costs_publicly')->default(false)->after('is_public');
            }

            if (! Schema::hasColumn('vehicles', 'share_attachments_publicly')) {
                $table->boolean('share_attachments_publicly')->default(false)->after('share_costs_publicly');
            }
        });

        $usedSlugs = [];

        DB::table('vehicles')
            ->select(['id', 'brand', 'model', 'year', 'public_slug'])
            ->orderBy('id')
            ->get()
            ->each(function (object $vehicle) use (&$usedSlugs): void {
                $publicSlug = $vehicle->public_slug ?: $this->uniquePublicSlug(
                    $this->slugBase($vehicle),
                    $usedSlugs,
                );

                $usedSlugs[$publicSlug] = true;

                DB::table('vehicles')
                    ->where('id', $vehicle->id)
                    ->update([
                        'is_public' => true,
                        'public_slug' => $publicSlug,
                        'share_costs_publicly' => false,
                        'share_attachments_publicly' => false,
                    ]);
            });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique('public_slug');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['public_slug']);
            $table->dropColumn([
                'public_slug',
                'is_public',
                'share_costs_publicly',
                'share_attachments_publicly',
            ]);
        });
    }

    private function slugBase(object $vehicle): string
    {
        $base = Str::slug(implode(' ', array_filter([
            $vehicle->year,
            $vehicle->brand,
            $vehicle->model,
        ])));

        return $base !== '' ? $base : 'garage-vehicle';
    }

    private function uniquePublicSlug(string $baseSlug, array $usedSlugs): string
    {
        $candidate = $baseSlug;
        $suffix = 2;

        while (isset($usedSlugs[$candidate])) {
            $candidate = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
};

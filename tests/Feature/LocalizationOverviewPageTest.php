<?php

namespace Tests\Feature;

use App\Filament\Pages\LocalizationOverview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationOverviewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_localization_overview_page(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get(LocalizationOverview::getUrl())
            ->assertOk()
            ->assertSeeText('Talen en vertalingen')
            ->assertSeeText('Nederlands')
            ->assertSeeText('English');
    }

    public function test_non_admin_cannot_view_localization_overview_page(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(LocalizationOverview::getUrl())
            ->assertForbidden();
    }
}

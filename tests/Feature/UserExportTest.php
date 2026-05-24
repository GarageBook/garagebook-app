<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_users(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'is_admin' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callAction('export')
            ->assertFileDownloaded('gebruikers-export-' . now()->format('Y-m-d') . '.csv');
    }

    public function test_regular_user_cannot_access_user_list_and_export(): void
    {
        $user = User::factory()->create([
            'email' => 'regular@example.com',
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/users')
            ->assertForbidden();
    }
}

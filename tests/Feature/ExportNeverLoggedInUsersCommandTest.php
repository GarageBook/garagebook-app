<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExportNeverLoggedInUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(storage_path('app/test-never-logged-in-users.csv'));
        File::delete(storage_path('app/test-never-logged-in-users-all.csv'));

        parent::tearDown();
    }

    public function test_command_exports_only_post_tracking_users_by_default(): void
    {
        User::factory()->create([
            'name' => 'New User',
            'email' => 'new@example.com',
            'created_at' => '2026-04-23 09:00:00',
            'first_login_at' => null,
        ]);

        User::factory()->create([
            'name' => 'Old User',
            'email' => 'old@example.com',
            'created_at' => '2026-04-20 09:00:00',
            'first_login_at' => null,
        ]);

        User::factory()->create([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'created_at' => '2026-04-24 09:00:00',
            'first_login_at' => '2026-04-25 09:00:00',
        ]);

        $outputPath = storage_path('app/test-never-logged-in-users.csv');

        $this->artisan('users:export-never-logged-in', [
            '--output' => $outputPath,
        ])->assertSuccessful();

        $this->assertFileExists($outputPath);

        $csv = file_get_contents($outputPath);

        $this->assertStringContainsString('name,email', $csv);
        $this->assertStringContainsString('new@example.com', $csv);
        $this->assertStringNotContainsString('old@example.com', $csv);
        $this->assertStringNotContainsString('active@example.com', $csv);
    }

    public function test_command_can_include_pre_tracking_users_when_requested(): void
    {
        User::factory()->create([
            'name' => 'Old User',
            'email' => 'old@example.com',
            'created_at' => '2026-04-20 09:00:00',
            'first_login_at' => null,
        ]);

        $outputPath = storage_path('app/test-never-logged-in-users-all.csv');

        $this->artisan('users:export-never-logged-in', [
            '--output' => $outputPath,
            '--include-pre-tracking' => true,
        ])->assertSuccessful();

        $this->assertFileExists($outputPath);

        $csv = file_get_contents($outputPath);

        $this->assertStringContainsString('old@example.com', $csv);
    }
}

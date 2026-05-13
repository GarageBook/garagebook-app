<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportUserSnapshotCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_blocked_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('users:import-snapshot', [
            'export' => storage_path('app/non-existent.json'),
        ])
            ->expectsOutput('Dit commando is alleen beschikbaar in lokale of testomgevingen.')
            ->assertFailed();
    }

    public function test_command_supports_dry_run_in_testing(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);

        $exportPath = storage_path('app/test-user-snapshot.json');

        file_put_contents($exportPath, json_encode([
            'user' => [
                'name' => 'Snapshot User',
            ],
            'vehicles' => [],
            'maintenance_logs' => [],
            'fuel_logs' => [],
            'vehicle_documents' => [],
        ], JSON_UNESCAPED_SLASHES));

        try {
            $this->artisan('users:import-snapshot', [
                'export' => $exportPath,
                '--email' => $user->email,
            ])
                ->expectsOutputToContain('target_email: local@example.com')
                ->expectsOutputToContain('Dry run: geen wijzigingen opgeslagen.')
                ->assertSuccessful();
        } finally {
            @unlink($exportPath);
        }
    }
}

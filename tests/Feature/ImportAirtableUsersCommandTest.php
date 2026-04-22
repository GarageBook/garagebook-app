<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportAirtableUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('airtable.personal_access_token', 'test-token');
        Config::set('airtable.base_id', 'appTestBase');
        Config::set('airtable.users_table', 'Users');
        Config::set('airtable.users_name_field', 'Name');
        Config::set('airtable.users_email_field', 'Email');
        Config::set('airtable.vehicles_table', 'Vehicles');
        Config::set('airtable.maintenance_table', 'Maintenance');
        Config::set('airtable.media_table', 'Media');

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/test-airtable-passwords.csv'));

        parent::tearDown();
    }

    public function test_command_dry_run_does_not_persist_user(): void
    {
        Http::fake([
            'https://api.airtable.com/v0/appTestBase/Users*' => Http::response([
                'records' => [[
                    'id' => 'recWillem123',
                    'fields' => [
                        'Name' => 'Willem van Veelen',
                        'Email' => 'willem@garagebook.nl',
                    ],
                ]],
            ]),
        ]);

        $this->artisan('airtable:import-users', [
            '--email' => 'willem@garagebook.nl',
        ])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', [
            'email' => 'willem@garagebook.nl',
        ]);
    }

    public function test_command_imports_user_idempotently_and_preserves_existing_password(): void
    {
        $user = User::factory()->create([
            'name' => 'Willem oud',
            'email' => 'willem@garagebook.nl',
            'password' => bcrypt('bestaand-geheim'),
        ]);

        Http::fake([
            'https://api.airtable.com/v0/appTestBase/Users/recWillem123' => Http::response([
                'id' => 'recWillem123',
                'fields' => [
                    'Name' => 'Willem van Veelen',
                    'Email' => 'willem@garagebook.nl',
                ],
            ]),
            'https://api.airtable.com/v0/appTestBase/Users*' => Http::response([
                'records' => [[
                    'id' => 'recWillem123',
                    'fields' => [
                        'Name' => 'Willem van Veelen',
                        'Email' => 'willem@garagebook.nl',
                    ],
                ]],
            ]),
        ]);

        $this->artisan('airtable:import-users', [
            '--email' => 'willem@garagebook.nl',
            '--force' => true,
        ])->assertSuccessful();

        $importedUser = $user->fresh();

        $this->assertSame('Willem van Veelen', $importedUser->name);
        $this->assertSame('recWillem123', $importedUser->airtable_record_id);
        $this->assertSame($user->password, $importedUser->password);

        $this->artisan('airtable:import-users', [
            '--record' => 'recWillem123',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_command_preserves_local_login_email_for_user_already_linked_by_airtable_record(): void
    {
        User::factory()->create([
            'name' => 'Willem',
            'email' => 'willemvanveelen@icloud.com',
            'airtable_record_id' => 'recWillem123',
            'is_admin' => true,
        ]);

        Http::fake([
            'https://api.airtable.com/v0/appTestBase/Users*' => Http::response([
                'records' => [[
                    'id' => 'recWillem123',
                    'fields' => [
                        'Name' => 'Willem van Veelen',
                        'Email' => 'willem@garagebook.nl',
                    ],
                ]],
            ]),
        ]);

        $this->artisan('airtable:import-users', [
            '--email' => 'willem@garagebook.nl',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'willemvanveelen@icloud.com',
            'airtable_record_id' => 'recWillem123',
            'is_admin' => true,
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'willem@garagebook.nl',
        ]);
    }

    public function test_command_can_import_related_vehicle_and_maintenance(): void
    {
        Http::fake([
            'https://api.airtable.com/v0/appTestBase/Users/recWillem123' => Http::response([
                'id' => 'recWillem123',
                'fields' => [
                    'Name' => 'Willem van Veelen',
                    'Email' => 'willem@garagebook.nl',
                    'Vehicles' => ['veh123'],
                    'Maintenance' => ['mnt123'],
                ],
            ]),
            'https://api.airtable.com/v0/appTestBase/Users*' => Http::response([
                'records' => [[
                    'id' => 'recWillem123',
                    'fields' => [
                        'Name' => 'Willem van Veelen',
                        'Email' => 'willem@garagebook.nl',
                    ],
                ]],
            ]),
            'https://api.airtable.com/v0/appTestBase/Vehicles/veh123' => Http::response([
                'id' => 'veh123',
                'fields' => [
                    'Name' => 'Aprilia RSV Mille Alitalia',
                    'Brand' => 'Aprilia',
                    'Model' => 'RSV Mille',
                    'Year' => 1999,
                    'License_plate' => 'MG-XS-98',
                    'Current_km' => 55000,
                    'Notes' => 'Projectmotor',
                    'Photo' => [[
                        'id' => 'attVehicleImage',
                        'url' => 'https://files.example.test/vehicle-image.jpg',
                        'filename' => 'Vehicle Image.jpg',
                    ]],
                    'Media' => ['med123'],
                ],
            ]),
            'https://api.airtable.com/v0/appTestBase/Media/med123' => Http::response([
                'id' => 'med123',
                'fields' => [
                    'videos' => [[
                        'id' => 'attVehicleVideo',
                        'url' => 'https://files.example.test/vehicle-video.mp4',
                        'filename' => 'Vehicle Video.mp4',
                    ]],
                ],
            ]),
            'https://api.airtable.com/v0/appTestBase/Maintenance/mnt123' => Http::response([
                'id' => 'mnt123',
                'fields' => [
                    'Title' => 'Titanium boutjes',
                    'Voertuig' => ['veh123'],
                    'Datum' => '2024-07-03',
                    'Kilometerstand' => 45167,
                    'Type' => 'Nieuw',
                    'Beschrijving' => 'Nieuwe titanium boutjes gemonteerd.',
                    'Kosten' => 212.36,
                    'Attachments' => [[
                        'id' => 'attMaintenancePdf',
                        'url' => 'https://files.example.test/maintenance-note.pdf',
                        'filename' => 'Maintenance Note.pdf',
                    ]],
                ],
            ]),
            'https://files.example.test/*' => Http::response('test-file-content', 200),
        ]);

        $this->artisan('airtable:import-users', [
            '--email' => 'willem@garagebook.nl',
            '--force' => true,
            '--with-related' => true,
        ])->assertSuccessful();

        $user = User::query()->where('email', 'willem@garagebook.nl')->firstOrFail();

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $user->id,
            'airtable_record_id' => 'veh123',
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
        ]);

        $vehicle = \App\Models\Vehicle::query()->where('airtable_record_id', 'veh123')->firstOrFail();
        $this->assertNotNull($vehicle->photo);
        $this->assertSame(1, count($vehicle->media_attachments ?? []));

        $this->assertDatabaseHas('maintenance_logs', [
            'vehicle_id' => $vehicle->id,
            'airtable_record_id' => 'mnt123',
            'description' => 'Titanium boutjes',
        ]);

        $log = \App\Models\MaintenanceLog::query()->where('airtable_record_id', 'mnt123')->firstOrFail();

        $this->assertSame(1, count($log->attachments ?? []));
        $this->assertSame(1, count($log->file_attachments ?? []));
        $this->assertSame(0, count($log->media_attachments ?? []));
        Storage::disk('public')->assertExists($vehicle->photo);
        Storage::disk('public')->assertExists($vehicle->media_attachments[0]);
        Storage::disk('public')->assertExists($log->file_attachments[0]);
    }

    public function test_bulk_import_command_writes_password_csv_for_new_users(): void
    {
        Http::fake([
            'https://api.airtable.com/v0/appTestBase/Users*' => Http::response([
                'records' => [
                    [
                        'id' => 'recWillem123',
                        'fields' => [
                            'Name' => 'Willem van Veelen',
                            'Email' => 'willem@garagebook.nl',
                        ],
                    ],
                    [
                        'id' => 'recErik123',
                        'fields' => [
                            'Name' => 'Erik Hoekstra',
                            'Email' => 'hoekie91@gmail.com',
                        ],
                    ],
                ],
            ]),
        ]);

        $outputPath = storage_path('app/test-airtable-passwords.csv');

        $this->artisan('airtable:import-all-users', [
            '--force' => true,
            '--passwords-output' => $outputPath,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'willem@garagebook.nl',
            'airtable_record_id' => 'recWillem123',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'hoekie91@gmail.com',
            'airtable_record_id' => 'recErik123',
        ]);

        $this->assertFileExists($outputPath);

        $csv = file_get_contents($outputPath);

        $this->assertStringContainsString('willem@garagebook.nl', $csv);
        $this->assertStringContainsString('hoekie91@gmail.com', $csv);
        $this->assertStringContainsString('temporary_password', $csv);
    }
}

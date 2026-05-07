<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_private_vehicle_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'current_km' => 12000,
        ]);

        Storage::disk('local')->put('vehicle-documents/test/manual.pdf', 'document-body');

        $document = VehicleDocument::query()->create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Handleiding',
            'document_type' => 'manual',
            'file_path' => 'vehicle-documents/test/manual.pdf',
            'original_filename' => 'manual.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($user)
            ->get(route('vehicle-documents.show', $document))
            ->assertOk();
    }

    public function test_other_user_cannot_open_private_vehicle_document(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'current_km' => 12000,
        ]);

        Storage::disk('local')->put('vehicle-documents/test/insurance.pdf', 'document-body');

        $document = VehicleDocument::query()->create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Polis',
            'document_type' => 'insurance',
            'file_path' => 'vehicle-documents/test/insurance.pdf',
            'original_filename' => 'insurance.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($otherUser)
            ->get(route('vehicle-documents.show', $document))
            ->assertNotFound();
    }

    public function test_vehicle_edit_page_renders_document_vault_for_owner(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'current_km' => 12000,
        ]);

        $this->actingAs($user)
            ->get('/admin/documentkluis?vehicle_id=' . $vehicle->id)
            ->assertOk()
            ->assertSeeText('Documentkluis')
            ->assertSeeText('Prive documentkluis')
            ->assertSeeText('verzekeringsbewijzen, garantiebewijzen, aankoopbewijzen')
            ->assertSeeText('Deze documentkluis is alleen zichtbaar binnen jouw account');
    }
}

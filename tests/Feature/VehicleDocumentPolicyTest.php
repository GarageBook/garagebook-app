<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class VehicleDocumentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_update_and_delete_own_vehicle_document(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'Tuareg 660',
            'current_km' => 9000,
        ]);

        $vehicleDocument = VehicleDocument::query()->create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Handleiding',
            'document_type' => 'manual',
            'file_path' => 'vehicle-documents/manual.pdf',
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $vehicleDocument));
        $this->assertTrue(Gate::forUser($user)->allows('update', $vehicleDocument));
        $this->assertTrue(Gate::forUser($user)->allows('delete', $vehicleDocument));
    }

    public function test_user_cannot_view_update_or_delete_another_users_vehicle_document(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Moto Guzzi',
            'model' => 'V85 TT',
            'current_km' => 14000,
        ]);

        $vehicleDocument = VehicleDocument::query()->create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Aankoopfactuur',
            'document_type' => 'invoice',
            'file_path' => 'vehicle-documents/invoice.pdf',
        ]);

        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $vehicleDocument));
        $this->assertFalse(Gate::forUser($otherUser)->allows('update', $vehicleDocument));
        $this->assertFalse(Gate::forUser($otherUser)->allows('delete', $vehicleDocument));
    }

    public function test_admin_bypass_applies_to_vehicle_document_policy(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Ducati',
            'model' => 'Multistrada V4',
            'current_km' => 25000,
        ]);

        $vehicleDocument = VehicleDocument::query()->create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Polis',
            'document_type' => 'insurance',
            'file_path' => 'vehicle-documents/policy.pdf',
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $vehicleDocument));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $vehicleDocument));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $vehicleDocument));
    }
}

<?php

namespace App\Services\Outreach;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\MaintenanceLog;
use App\Models\OutreachEvent;
use App\Models\OutreachProspect;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OutreachDemoService
{
    public function loginFromToken(string $token, Request $request): RedirectResponse
    {
        $prospect = OutreachProspect::query()
            ->where('token', $token)
            ->firstOrFail();

        [$prospect, $user, $vehicle] = DB::transaction(function () use ($prospect, $request): array {
            /** @var OutreachProspect $lockedProspect */
            $lockedProspect = OutreachProspect::query()
                ->whereKey($prospect->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->recordEvent($lockedProspect, 'email_link_opened', $request);

            if ($lockedProspect->clicked_at === null) {
                $lockedProspect->clicked_at = now();
                $lockedProspect->save();
            }

            $user = $lockedProspect->user;

            if (! $user instanceof User) {
                $user = $this->createDemoUser($lockedProspect);
                $vehicle = $this->seedDemoVehicle($lockedProspect, $user);

                $lockedProspect->user()->associate($user);
                $lockedProspect->save();
            } else {
                $vehicle = $user->vehicles()->latest('id')->first();
            }

            $this->recordEvent($lockedProspect, 'demo_login_started', $request);

            return [$lockedProspect, $user, $vehicle];
        });

        abort_unless($vehicle instanceof Vehicle, 404);

        Auth::login($user, true);
        $request->session()->regenerate();

        DB::transaction(function () use ($prospect, $request): void {
            /** @var OutreachProspect $lockedProspect */
            $lockedProspect = OutreachProspect::query()
                ->whereKey($prospect->id)
                ->lockForUpdate()
                ->firstOrFail();

            $now = now();

            $lockedProspect->forceFill([
                'first_login_at' => $lockedProspect->first_login_at ?: $now,
                'last_login_at' => $now,
                'login_count' => $lockedProspect->login_count + 1,
            ])->save();

            $this->recordEvent($lockedProspect, 'demo_login_completed', $request);
        });

        return redirect()->to(VehicleResource::getUrl('view', ['record' => $vehicle]));
    }

    private function createDemoUser(OutreachProspect $prospect): User
    {
        return User::query()->create([
            'name' => $prospect->company_name,
            'email' => $this->resolveDemoEmail($prospect),
            'password' => Str::random(40),
            'email_verified_at' => now(),
            'is_admin' => false,
            'is_outreach_demo' => true,
            'registration_source' => 'outreach_demo',
        ]);
    }

    private function seedDemoVehicle(OutreachProspect $prospect, User $user): Vehicle
    {
        $directory = 'outreach-demos/prospect-' . $prospect->id;
        $reportPath = $directory . '/onderhoudsrapport.txt';
        $vehiclePhotoPaths = $this->importDemoVehicleImages($prospect);
        $primaryPhotoPath = $vehiclePhotoPaths[0] ?? null;
        $galleryPhotoPaths = array_slice($vehiclePhotoPaths, 1);

        Storage::disk('local')->put($reportPath, $this->demoDocumentText($prospect->company_name));

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'display_variant' => 'Garage demo',
            'nickname' => 'Demo motor voor ' . $prospect->company_name,
            'current_km' => 18750,
            'distance_unit' => 'km',
            'year' => 2023,
            'is_public' => true,
            'share_costs_publicly' => true,
            'share_attachments_publicly' => true,
            'notes' => 'Voorbeeldaccount voor outreach naar ' . $prospect->company_name . '.',
            'photo' => $primaryPhotoPath,
            'photos' => $galleryPhotoPaths,
        ]);

        $maintenanceLogs = [
            [
                'description' => 'Afleverbeurt en software-check',
                'maintenance_date' => now()->subMonths(8)->toDateString(),
                'km_reading' => 13200,
                'cost' => '219.00',
                'notes' => 'Klaargezet als showroomwaardige demo met aantoonbare historie.',
            ],
            [
                'description' => 'Jaarbeurt met kettingsetcontrole',
                'maintenance_date' => now()->subMonths(4)->toDateString(),
                'km_reading' => 15980,
                'cost' => '348.50',
                'notes' => 'Inclusief controle op remvloeistof en bandenslijtage.',
            ],
            [
                'description' => 'Voorjaarsservice met bewijsbestand',
                'maintenance_date' => now()->subWeeks(6)->toDateString(),
                'km_reading' => 18420,
                'cost' => '289.95',
                'notes' => 'Deze demo-log bevat een voorbeeldafbeelding en gekoppeld document.',
                'attachments' => $primaryPhotoPath ? [$primaryPhotoPath] : [],
                'media_attachments' => $primaryPhotoPath ? [$primaryPhotoPath] : [],
                'file_attachments' => [],
                'share_attachments_publicly' => true,
                'hide_photos_on_public_page' => false,
            ],
        ];

        foreach ($maintenanceLogs as $attributes) {
            MaintenanceLog::query()->create([
                'vehicle_id' => $vehicle->id,
                ...$attributes,
            ]);
        }

        VehicleDocument::query()->create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Voorbeeld onderhoudsrapport',
            'document_type' => 'maintenance_report',
            'file_path' => $reportPath,
            'original_filename' => 'garagebook-demo-onderhoudsrapport.txt',
            'mime_type' => 'text/plain',
            'file_size' => Storage::disk('local')->size($reportPath),
            'document_date' => now()->subWeeks(6)->toDateString(),
            'notes' => 'Voorbeeldbewijsstuk voor de demo-flow.',
        ]);

        return $vehicle;
    }

    /**
     * @return list<string>
     */
    private function importDemoVehicleImages(OutreachProspect $prospect): array
    {
        $sourceDirectory = (string) config('services.outreach_demo.image_source_path', '/temp/3');

        if ($sourceDirectory === '' || ! is_dir($sourceDirectory)) {
            Log::info('Outreach demo image import skipped', [
                'prospect_id' => $prospect->id,
                'source_path' => $sourceDirectory,
                'imported_count' => 0,
                'reason' => 'missing_directory',
            ]);

            return [];
        }

        $paths = collect(File::files($sourceDirectory))
            ->filter(fn (\SplFileInfo $file): bool => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true))
            ->sortBy(fn (\SplFileInfo $file): string => strtolower($file->getFilename()))
            ->values()
            ->map(function (\SplFileInfo $file, int $index) use ($prospect): string {
                $extension = strtolower($file->getExtension());
                $targetPath = sprintf(
                    'vehicle-photos/outreach-demo-%d-%02d.%s',
                    $prospect->id,
                    $index + 1,
                    $extension,
                );

                Storage::disk('public')->put($targetPath, File::get($file->getPathname()));

                return $targetPath;
            })
            ->all();

        Log::info('Outreach demo images imported', [
            'prospect_id' => $prospect->id,
            'source_path' => $sourceDirectory,
            'imported_count' => count($paths),
        ]);

        return $paths;
    }

    private function recordEvent(OutreachProspect $prospect, string $eventType, Request $request): OutreachEvent
    {
        return $prospect->events()->create([
            'event_type' => $eventType,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);
    }

    private function resolveDemoEmail(OutreachProspect $prospect): string
    {
        $base = 'outreach+' . $prospect->id . '@garagebook.nl';

        if (! User::query()->where('email', $base)->exists()) {
            return $base;
        }

        return 'outreach+' . $prospect->id . '-' . Str::lower(Str::random(6)) . '@garagebook.nl';
    }

    private function demoDocumentText(string $companyName): string
    {
        return implode(PHP_EOL, [
            'GarageBook demo-onderhoudsrapport',
            'Prospect: ' . $companyName,
            'Onderdeel: Voorjaarsservice Yamaha MT-07',
            'Werkzaamheden: olie, filters, kettingspanning, remcontrole',
            'Doel: laten zien hoe garages onderhoud, bewijs en een publieke voertuigpagina kunnen delen.',
        ]);
    }
}

<?php

namespace App\Services\Outreach;

use App\Filament\Pages\Timeline;
use App\Models\MaintenanceLog;
use App\Models\OutreachCampaign;
use App\Models\OutreachEvent;
use App\Models\OutreachProspect;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OutreachDemoService
{
    private const CLUB2026_CAMPAIGN_SLUG = 'club2026';

    private const CLUB2026_DEMO_WEBSITE = 'growth-partner:club2026-yamaha-mt-07';

    public function demoRouteForGrowthPartner(string $partnerSlug, string $campaignSlug): string
    {
        $partnerSlug = Str::slug(Str::limit($partnerSlug, 120, '')) ?: 'partner';
        $campaignSlug = Str::slug(Str::limit($campaignSlug, 120, '')) ?: 'campaign';
        $isClub2026 = $campaignSlug === self::CLUB2026_CAMPAIGN_SLUG;

        $campaign = OutreachCampaign::query()->firstOrCreate(
            ['slug' => 'growth-'.$campaignSlug],
            [
                'name' => 'Growth '.$campaignSlug,
                'description' => 'Automatisch aangemaakte demo-campagne voor growth partnerlinks.',
            ],
        );

        $prospect = OutreachProspect::query()->firstOrCreate(
            [
                'outreach_campaign_id' => $campaign->id,
                'source' => 'growth_partner',
                'website' => $isClub2026 ? self::CLUB2026_DEMO_WEBSITE : 'growth-partner:'.$partnerSlug,
            ],
            [
                'company_name' => $isClub2026 ? 'Club2026 Yamaha MT-07 demo' : $partnerSlug,
                'contact_name' => null,
                'email' => null,
                'city' => null,
                'notes' => $isClub2026
                    ? 'Canonieke Yamaha MT-07 demo-prospect voor Club2026 partner tracking URLs.'
                    : 'Automatisch aangemaakte demo-prospect voor partner tracking URL.',
            ],
        );

        return route('outreach.demo.login', ['token' => $prospect->token], false);
    }

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

        return redirect()->to(Timeline::getUrl(['vehicle_id' => $vehicle->id]));
    }

    public function shouldShowDemoIntroForAuthenticatedUser(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->is_outreach_demo || $user->isAdmin()) {
            return false;
        }

        $prospect = $user->outreachProspect;

        if (! $prospect instanceof OutreachProspect || $prospect->demo_intro_dismissed_at !== null) {
            return false;
        }

        if ($prospect->demo_intro_shown_at === null) {
            $prospect->forceFill(['demo_intro_shown_at' => now()])->save();
            $prospect->events()->create([
                'event_type' => 'demo_intro_shown',
                'ip_address' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
            ]);
        }

        return true;
    }

    public function dismissDemoIntroForAuthenticatedUser(Request $request): void
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_outreach_demo || $user->isAdmin()) {
            return;
        }

        $prospect = $user->outreachProspect;

        if (! $prospect instanceof OutreachProspect || $prospect->demo_intro_dismissed_at !== null) {
            return;
        }

        $prospect->forceFill([
            'demo_intro_shown_at' => $prospect->demo_intro_shown_at ?: now(),
            'demo_intro_dismissed_at' => now(),
        ])->save();

        $prospect->events()->create([
            'event_type' => 'demo_intro_dismissed',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);
    }

    /**
     * @return array{source_path:string, source_found:bool, found_count:int, imported_count:int, final_image_count:int, source_filenames:list<string>, imported_paths:list<string>, final_paths:list<string>}
     */
    public function refreshVehicleDemoImages(Vehicle $vehicle, ?string $sourceDirectory = null, bool $force = false): array
    {
        $sourceDirectory = $this->resolveImageSourceDirectory($sourceDirectory);
        $sourceFound = $sourceDirectory !== '' && is_dir($sourceDirectory);
        $existingPaths = $this->currentVehicleImagePaths($vehicle);

        if (! $sourceFound) {
            $result = [
                'source_path' => $sourceDirectory,
                'source_found' => false,
                'found_count' => 0,
                'imported_count' => 0,
                'final_image_count' => $existingPaths->count(),
                'source_filenames' => [],
                'imported_paths' => [],
                'final_paths' => $existingPaths->all(),
            ];

            $this->logImageImport($vehicle, $result, $force);

            return $result;
        }

        $files = collect(File::files($sourceDirectory))
            ->filter(fn (\SplFileInfo $file): bool => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true))
            ->sortBy(fn (\SplFileInfo $file): string => strtolower($file->getFilename()))
            ->values();

        $sourceFilenames = $files->map(fn (\SplFileInfo $file): string => $file->getFilename())->all();
        $targetPaths = $files->map(fn (\SplFileInfo $file, int $index): string => $this->demoVehicleImageTargetPath($vehicle, $file, $index))->all();

        foreach ($files as $index => $file) {
            $targetPath = $targetPaths[$index];

            if ($force || ! Storage::disk('public')->exists($targetPath)) {
                Storage::disk('public')->put($targetPath, File::get($file->getPathname()));
            }
        }

        if ($force && $targetPaths !== []) {
            foreach ($existingPaths as $existingPath) {
                if (! $this->isManagedDemoImagePath($vehicle, $existingPath) || in_array($existingPath, $targetPaths, true)) {
                    continue;
                }

                Storage::disk('public')->delete($existingPath);
            }

            $vehicle->forceFill([
                'photo' => $targetPaths[0] ?? null,
                'photos' => array_values(array_slice($targetPaths, 1)),
            ])->save();

            $vehicle->refresh();

            $finalPaths = $this->currentVehicleImagePaths($vehicle);
            $result = [
                'source_path' => $sourceDirectory,
                'source_found' => true,
                'found_count' => count($sourceFilenames),
                'imported_count' => count($targetPaths),
                'final_image_count' => $finalPaths->count(),
                'source_filenames' => $sourceFilenames,
                'imported_paths' => $targetPaths,
                'final_paths' => $finalPaths->all(),
            ];

            $this->logImageImport($vehicle, $result, $force);

            return $result;
        }

        $existingPrimary = is_string($vehicle->photo) && filled($vehicle->photo)
            ? ltrim($vehicle->photo, '/')
            : null;
        $existingGallery = collect(Arr::wrap($vehicle->photos))
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->map(fn (string $path): string => ltrim($path, '/'))
            ->values();
        $knownPaths = collect([$existingPrimary, ...$existingGallery])->filter()->values();

        $importedPaths = [];
        $newPrimary = $existingPrimary;
        $newGallery = $existingGallery->all();

        foreach ($targetPaths as $targetPath) {
            if ($knownPaths->contains($targetPath)) {
                continue;
            }

            if ($newPrimary === null) {
                $newPrimary = $targetPath;
            } elseif (! in_array($targetPath, $newGallery, true)) {
                $newGallery[] = $targetPath;
            }

            $knownPaths->push($targetPath);
            $importedPaths[] = $targetPath;
        }

        if ($newPrimary !== $existingPrimary || $newGallery !== $existingGallery->all()) {
            $vehicle->forceFill([
                'photo' => $newPrimary,
                'photos' => array_values($newGallery),
            ])->save();
        }

        $vehicle->refresh();
        $finalPaths = $this->currentVehicleImagePaths($vehicle);

        $result = [
            'source_path' => $sourceDirectory,
            'source_found' => true,
            'found_count' => count($sourceFilenames),
            'imported_count' => count($importedPaths),
            'final_image_count' => $finalPaths->count(),
            'source_filenames' => $sourceFilenames,
            'imported_paths' => $importedPaths,
            'final_paths' => $finalPaths->all(),
        ];

        $this->logImageImport($vehicle, $result, $force);

        return $result;
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
        $directory = 'outreach-demos/prospect-'.$prospect->id;
        $reportPath = $directory.'/onderhoudsrapport.txt';

        Storage::disk('local')->put($reportPath, $this->demoDocumentText($prospect->company_name));

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'display_variant' => 'Garage demo',
            'nickname' => 'Demo motor voor '.$prospect->company_name,
            'current_km' => 18750,
            'distance_unit' => 'km',
            'year' => 2023,
            'is_public' => true,
            'share_costs_publicly' => true,
            'share_attachments_publicly' => true,
            'notes' => 'Voorbeeldaccount voor outreach naar '.$prospect->company_name.'.',
        ]);

        $importResult = $this->shouldImportDemoImages($prospect)
            ? $this->refreshVehicleDemoImages($vehicle)
            : $this->emptyImageImportResult($vehicle);
        $vehicle->refresh();
        $primaryPhotoPath = $vehicle->photo;

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

        Log::info('Outreach demo vehicle seeded', [
            'prospect_id' => $prospect->id,
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'imported_count' => $importResult['imported_count'],
            'final_image_count' => $importResult['final_image_count'],
        ]);

        return $vehicle;
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
        $base = 'outreach+'.$prospect->id.'@garagebook.nl';

        if (! User::query()->where('email', $base)->exists()) {
            return $base;
        }

        return 'outreach+'.$prospect->id.'-'.Str::lower(Str::random(6)).'@garagebook.nl';
    }

    private function demoDocumentText(string $companyName): string
    {
        return implode(PHP_EOL, [
            'GarageBook demo-onderhoudsrapport',
            'Prospect: '.$companyName,
            'Onderdeel: Voorjaarsservice Yamaha MT-07',
            'Werkzaamheden: olie, filters, kettingspanning, remcontrole',
            'Doel: laten zien hoe garages onderhoud, bewijs en een publieke voertuigpagina kunnen delen.',
        ]);
    }

    private function resolveImageSourceDirectory(?string $sourceDirectory = null): string
    {
        return trim((string) ($sourceDirectory ?: config('services.outreach_demo.image_source_path', '/temp/3')));
    }

    private function shouldImportDemoImages(OutreachProspect $prospect): bool
    {
        return ! (
            $prospect->source === 'growth_partner'
            && $prospect->website === self::CLUB2026_DEMO_WEBSITE
        );
    }

    /**
     * @return array{source_path:string, source_found:bool, found_count:int, imported_count:int, final_image_count:int, source_filenames:list<string>, imported_paths:list<string>, final_paths:list<string>}
     */
    private function emptyImageImportResult(Vehicle $vehicle): array
    {
        $finalPaths = $this->currentVehicleImagePaths($vehicle);

        return [
            'source_path' => '',
            'source_found' => false,
            'found_count' => 0,
            'imported_count' => 0,
            'final_image_count' => $finalPaths->count(),
            'source_filenames' => [],
            'imported_paths' => [],
            'final_paths' => $finalPaths->all(),
        ];
    }

    private function demoVehicleImageTargetPath(Vehicle $vehicle, \SplFileInfo $file, int $index): string
    {
        $extension = strtolower($file->getExtension());
        $filename = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $slug = Str::slug($filename);

        if ($slug === '') {
            $slug = 'image';
        }

        return sprintf(
            'vehicle-photos/outreach-demo-vehicle-%d-%02d-%s.%s',
            $vehicle->id,
            $index + 1,
            $slug,
            $extension,
        );
    }

    private function isManagedDemoImagePath(Vehicle $vehicle, string $path): bool
    {
        return str_starts_with($path, 'vehicle-photos/outreach-demo-vehicle-'.$vehicle->id.'-');
    }

    /**
     * @return Collection<int, string>
     */
    private function currentVehicleImagePaths(Vehicle $vehicle): Collection
    {
        return collect([
            $vehicle->photo,
            ...Arr::wrap($vehicle->photos),
        ])
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->map(fn (string $path): string => ltrim($path, '/'))
            ->unique()
            ->values();
    }

    /**
     * @param  array{source_path:string, source_found:bool, found_count:int, imported_count:int, final_image_count:int, source_filenames:list<string>, imported_paths:list<string>, final_paths:list<string>}  $result
     */
    private function logImageImport(Vehicle $vehicle, array $result, bool $force): void
    {
        Log::info('Outreach demo image import processed', [
            'user_id' => $vehicle->user_id,
            'vehicle_id' => $vehicle->id,
            'force' => $force,
            'source_path' => $result['source_path'],
            'source_found' => $result['source_found'],
            'found_image_count' => $result['found_count'],
            'imported_count' => $result['imported_count'],
            'final_image_count' => $result['final_image_count'],
            'source_filenames' => $result['source_filenames'],
            'imported_paths' => $result['imported_paths'],
            'final_paths' => $result['final_paths'],
        ]);
    }
}

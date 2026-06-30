<?php

namespace App\Services\Outreach;

use App\Filament\Pages\Timeline;
use App\Models\OutreachCampaign;
use App\Models\OutreachEvent;
use App\Models\OutreachProspect;
use App\Models\User;
use App\Models\Vehicle;
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
use RuntimeException;

class OutreachDemoService
{
    public const CURRENT_PROSPECT_SESSION_KEY = 'outreach_demo.current_prospect_id';

    public function demoRouteForGrowthPartner(string $partnerSlug, string $campaignSlug): string
    {
        $partnerSlug = Str::slug(Str::limit($partnerSlug, 120, '')) ?: 'partner';
        $campaignSlug = Str::slug(Str::limit($campaignSlug, 120, '')) ?: 'campaign';

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
                'website' => 'growth-partner:'.$partnerSlug,
            ],
            [
                'company_name' => $partnerSlug,
                'contact_name' => null,
                'email' => null,
                'city' => null,
                'notes' => 'Automatisch aangemaakte demo-prospect voor partner tracking URL.',
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

            [$user, $vehicle] = $this->resolveDemoUserAndVehicle($lockedProspect);

            if ((int) $lockedProspect->user_id !== (int) $user->id) {
                $lockedProspect->user()->associate($user);
                $lockedProspect->save();
            }

            $this->recordEvent($lockedProspect, 'demo_login_started', $request);

            return [$lockedProspect, $user, $vehicle];
        });

        abort_unless($vehicle instanceof Vehicle, 404);

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put(self::CURRENT_PROSPECT_SESSION_KEY, $prospect->id);

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

    public function getCanonicalDemoVehicle(): Vehicle
    {
        return $this->getExistingPhotographedDemoVehicle();
    }

    public function getExistingPhotographedDemoVehicle(): Vehicle
    {
        $vehicle = Vehicle::query()
            ->with(['user.outreachProspect'])
            ->whereHas('user', fn ($query) => $query->where('is_outreach_demo', true))
            ->where('brand', 'Yamaha')
            ->where('model', 'MT-07')
            ->where('display_variant', 'Garage demo')
            ->latest('id')
            ->get()
            ->first(fn (Vehicle $vehicle): bool => $this->visibleDemoImagePaths($vehicle)->isNotEmpty());

        if (! $vehicle instanceof Vehicle) {
            $this->failMissingCanonicalDemoVehicle('Existing photographed Yamaha MT-07 outreach demo vehicle is missing.', [
                'brand' => 'Yamaha',
                'model' => 'MT-07',
                'display_variant' => 'Garage demo',
            ]);
        }

        return $vehicle;
    }

    public function currentProspectForAuthenticatedDemoUser(): ?OutreachProspect
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->is_outreach_demo || $user->isAdmin()) {
            return null;
        }

        $prospectId = session(self::CURRENT_PROSPECT_SESSION_KEY);

        if (is_numeric($prospectId)) {
            $prospect = OutreachProspect::query()
                ->whereKey((int) $prospectId)
                ->where('user_id', $user->id)
                ->first();

            if ($prospect instanceof OutreachProspect) {
                return $prospect;
            }
        }

        return $user->outreachProspect;
    }

    public function shouldShowDemoIntroForAuthenticatedUser(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->is_outreach_demo || $user->isAdmin()) {
            return false;
        }

        $prospect = $this->currentProspectForAuthenticatedDemoUser();

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

        $prospect = $this->currentProspectForAuthenticatedDemoUser();

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

    /**
     * @return array{0:User, 1:Vehicle}
     */
    private function resolveDemoUserAndVehicle(OutreachProspect $prospect): array
    {
        try {
            $vehicle = $this->getExistingPhotographedDemoVehicle();
            $user = $vehicle->user;

            if (! $user instanceof User) {
                $this->failMissingCanonicalDemoVehicle('Existing photographed Yamaha MT-07 outreach demo vehicle has no owner.', [
                    'vehicle_id' => $vehicle->id,
                    'public_slug' => $vehicle->public_slug,
                ]);
            }

            return [$user, $vehicle];
        } catch (RuntimeException $exception) {
            Log::error('Existing photographed Yamaha MT-07 outreach demo vehicle unavailable.', [
                'prospect_id' => $prospect->id,
                'message' => $exception->getMessage(),
            ]);

            abort(503, 'Demo tijdelijk niet beschikbaar.');
        }
    }

    private function recordEvent(OutreachProspect $prospect, string $eventType, Request $request): OutreachEvent
    {
        return $prospect->events()->create([
            'event_type' => $eventType,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);
    }

    private function resolveImageSourceDirectory(?string $sourceDirectory = null): string
    {
        return trim((string) ($sourceDirectory ?: config('services.outreach_demo.image_source_path', '/temp/3')));
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

    private function visibleDemoImagePaths(Vehicle $vehicle): Collection
    {
        return $this->currentVehicleImagePaths($vehicle)
            ->filter(fn (string $path): bool => Storage::disk('public')->exists($path))
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

    private function failMissingCanonicalDemoVehicle(string $message, array $context): never
    {
        Log::critical($message, $context);

        throw new RuntimeException($message);
    }
}

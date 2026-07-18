<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\ImageThumbnail;
use App\Support\MediaPath;
use App\Support\PublicSeoUrl;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicGarageService
{
    public function findPublicVehicleBySlug(string $publicSlug): ?Vehicle
    {
        return Vehicle::query()
            ->with([
                'user',
                'maintenanceLogs' => fn ($query) => $query
                    ->latest('maintenance_date')
                    ->latest('id'),
            ])
            ->where('public_slug', trim($publicSlug))
            ->where('is_public', true)
            ->first();
    }

    public function findLegacyPublicVehicle(string $username, string $vehicleSlug): ?Vehicle
    {
        $user = User::query()
            ->get(['id', 'name'])
            ->first(fn (User $user) => Str::slug(trim($user->name)) === trim($username));

        if (! $user) {
            return null;
        }

        return Vehicle::query()
            ->where('user_id', $user->id)
            ->where('is_public', true)
            ->get()
            ->first(fn (Vehicle $vehicle) => $this->legacyVehicleSlug($vehicle) === trim($vehicleSlug));
    }

    public function publicUrl(Vehicle $vehicle): string
    {
        return $this->canonicalUrl($vehicle);
    }

    public function canonicalUrl(Vehicle $vehicle): string
    {
        $publicSlug = $vehicle->public_slug ?: $this->ensurePublicSlug($vehicle);

        return PublicSeoUrl::garage($publicSlug);
    }

    public function ensurePublicSlug(Vehicle $vehicle): string
    {
        if (filled($vehicle->public_slug)) {
            return $vehicle->public_slug;
        }

        $vehicle->forceFill([
            'public_slug' => $this->generatePublicSlug($vehicle, $vehicle->exists ? $vehicle->id : null),
        ]);

        $vehicle->saveQuietly();

        return (string) $vehicle->public_slug;
    }

    public function generatePublicSlug(Vehicle $vehicle, ?int $ignoreVehicleId = null): string
    {
        $baseSlug = $this->publicSlugBase($vehicle);
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($candidate, $ignoreVehicleId)) {
            $candidate = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    public function shouldIndex(Vehicle $vehicle): bool
    {
        if (! $vehicle->is_public || blank($vehicle->public_slug)) {
            return false;
        }

        if ($this->isOutreachDemoVehicle($vehicle)) {
            return false;
        }

        return true;
    }

    public function indexableVehicles(): Collection
    {
        return Vehicle::query()
            ->with([
                'user',
                'maintenanceLogs' => fn ($query) => $query
                    ->latest('maintenance_date')
                    ->latest('id'),
            ])
            ->where('is_public', true)
            ->whereNotNull('public_slug')
            ->where('public_slug', '!=', '')
            ->orderBy('public_slug')
            ->get()
            ->filter(fn (Vehicle $vehicle) => $this->shouldIndex($vehicle))
            ->values();
    }

    public function isOutreachDemoVehicle(Vehicle $vehicle): bool
    {
        $user = $vehicle->relationLoaded('user')
            ? $vehicle->user
            : $vehicle->user()->first();

        return (bool) ($user?->is_outreach_demo ?? false);
    }

    public function publicVehicleName(Vehicle $vehicle): string
    {
        return implode(' ', array_filter([
            $vehicle->year,
            $vehicle->brand,
            $vehicle->model,
            $this->variantForDisplay($vehicle),
        ]));
    }

    public function publicVehicleHeading(Vehicle $vehicle): string
    {
        return implode(' ', array_filter([
            $vehicle->brand,
            $vehicle->model,
            $this->variantForDisplay($vehicle),
        ]));
    }

    public function publicMetaDescription(Vehicle $vehicle): string
    {
        $stats = $this->publicStats($vehicle);
        $costSegment = $stats['shared_costs_enabled']
            ? ($stats['shared_cost_count'] > 0 ? ' Kosten zijn op '.$stats['shared_cost_count'].' moment(en) gedeeld.' : '')
            : '';

        return sprintf(
            'Bekijk de gedeelde onderhoudsgeschiedenis van deze %s in GarageBook. %d onderhoudsmoment(en) en %d moment(en) met kilometerstand laten zien wat de eigenaar aantoonbaar heeft vastgelegd.%s',
            trim($this->publicVehicleHeading($vehicle)),
            $stats['maintenance_count'],
            $stats['documented_km_count'],
            $costSegment,
        );
    }

    public function publicIntroText(Vehicle $vehicle): string
    {
        return sprintf(
            'Deze publieke GarageBook-pagina laat zien welke onderhoudsmomenten de eigenaar van deze %s heeft opgebouwd. Onderhoud, kilometerstanden, foto\'s en bewijsstukken worden hier deelbaar samengebracht, terwijl de eigenaar controle houdt over wat openbaar is.',
            $this->publicVehicleName($vehicle),
        );
    }

    public function publicMaintenanceSeoContent(Vehicle $vehicle): array
    {
        $vehicleName = $this->publicVehicleName($vehicle);
        $hasMaintenance = $vehicle->relationLoaded('maintenanceLogs')
            ? $vehicle->maintenanceLogs->isNotEmpty()
            : $vehicle->maintenanceLogs()->exists();

        if ($hasMaintenance) {
            return [
                'title' => 'Onderhoud van '.$vehicleName,
                'body' => sprintf(
                    'Deze GarageBook-pagina bevat de onderhoudsgeschiedenis van een %s. Hier vind je uitgevoerde onderhoudsbeurten, vervangen onderdelen, reparaties, kilometerstanden en overige werkzaamheden.',
                    $vehicleName,
                ),
            ];
        }

        return [
            'title' => 'Onderhoud van deze '.$this->publicVehicleHeading($vehicle),
            'body' => 'Voor dit voertuig zijn op dit moment nog geen onderhoudswerkzaamheden geregistreerd. Zodra de eigenaar onderhoud toevoegt, verschijnt dit automatisch op deze pagina.',
        ];
    }

    public function publicVehiclePhotos(Vehicle $vehicle): array
    {
        return collect([
            $vehicle->photo,
            ...Arr::wrap($vehicle->photos),
        ])
            ->filter(fn ($path) => is_string($path) && filled($path) && MediaPath::isImage($path))
            ->map(fn (string $path) => ltrim($path, '/'))
            ->unique()
            ->values()
            ->map(function (string $path): ?array {
                $thumbnailPath = ImageThumbnail::path($path, 960);

                if ($thumbnailPath === null && ! $this->publicStoragePathExists($path)) {
                    return null;
                }

                return [
                    'path' => $path,
                    'thumbnail_url' => asset('storage/'.ltrim($thumbnailPath ?: $path, '/')),
                    'url' => asset('storage/'.ltrim($path, '/')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function publicTimelineItems(Vehicle $vehicle): array
    {
        $logs = $vehicle->relationLoaded('maintenanceLogs')
            ? $vehicle->maintenanceLogs
            : $vehicle->maintenanceLogs()->latest('maintenance_date')->latest('id')->get();

        return $logs->map(function ($log) use ($vehicle): array {
            $attachments = collect($log->attachments)
                ->filter(fn ($path) => is_string($path) && filled($path))
                ->values();

            $imageAttachments = $attachments
                ->filter(fn (string $path): bool => MediaPath::isImage($path))
                ->values();

            $otherAttachments = $attachments
                ->reject(fn (string $path): bool => MediaPath::isImage($path))
                ->values();

            $publicImageAttachments = $log->hide_photos_on_public_page
                ? []
                : $imageAttachments
                    ->map(fn (string $path): ?array => $this->publicAttachmentImage($path, $vehicle))
                    ->filter()
                    ->values()
                    ->all();

            $publicOtherAttachments = $log->share_attachments_publicly
                ? $otherAttachments
                    ->map(fn (string $path): ?array => $this->publicAttachmentImage($path, $vehicle))
                    ->filter()
                    ->values()
                    ->all()
                : [];

            $publicAttachments = [...$publicImageAttachments, ...$publicOtherAttachments];

            $hasHiddenImages = $imageAttachments->isNotEmpty() && $log->hide_photos_on_public_page;
            $hasHiddenOtherAttachments = $otherAttachments->isNotEmpty() && ! $log->share_attachments_publicly;

            return [
                'date_label' => $log->maintenance_date?->format('d-m-Y'),
                'description' => trim((string) $log->description),
                'km_label' => $log->km_reading > 0
                    ? app(DistanceUnitService::class)->formatFromKilometers($log->km_reading, $vehicle->distance_unit, 0)
                    : null,
                'cost_label' => $vehicle->share_costs_publicly && $log->cost !== null
                    ? '€ '.number_format((float) $log->cost, 2, ',', '.')
                    : null,
                'notes' => trim((string) $log->notes) !== '' ? trim((string) $log->notes) : null,
                'has_km_reading' => $log->km_reading > 0,
                'has_cost' => $log->cost !== null,
                'has_public_attachments' => $publicAttachments !== [],
                'has_private_attachments' => $hasHiddenImages || $hasHiddenOtherAttachments,
                'evidence_labels' => array_values(array_filter([
                    $log->km_reading > 0 ? 'Kilometerstand vastgelegd' : null,
                    $publicAttachments !== [] ? 'Bijlagen zichtbaar' : null,
                    $hasHiddenImages || $hasHiddenOtherAttachments ? 'Aanvullend bewijs privé bewaard' : null,
                    $vehicle->share_costs_publicly && $log->cost !== null ? 'Kosten transparant gedeeld' : null,
                ])),
                'public_attachments' => $publicAttachments,
                'public_image_attachments' => $publicImageAttachments,
                'public_other_attachments' => $publicOtherAttachments,
            ];
        })->all();
    }

    public function publicStats(Vehicle $vehicle): array
    {
        $metrics = $this->publicTimelineMetrics($vehicle);

        return [
            'maintenance_count' => $metrics['maintenance_count'],
            'last_updated_label' => $metrics['last_updated_label'],
            'history_period_label' => $metrics['history_period_label'],
            'documented_km_count' => $metrics['documented_km_count'],
            'public_vehicle_photo_count' => $metrics['public_vehicle_photo_count'],
            'public_attachment_count' => $metrics['public_attachment_count'],
            'visible_photo_count' => $metrics['visible_photo_count'],
            'shared_cost_count' => $metrics['shared_cost_count'],
            'shared_costs_enabled' => $vehicle->share_costs_publicly,
            'has_private_evidence' => $metrics['has_private_evidence'],
        ];
    }

    public function publicHistoryHighlights(Vehicle $vehicle): array
    {
        $metrics = $this->publicTimelineMetrics($vehicle);

        return [
            [
                'label' => 'Gedocumenteerd onderhoud',
                'value' => $metrics['maintenance_count'] > 0
                    ? $metrics['maintenance_count'].' onderhoudsmomenten gedeeld door eigenaar'
                    : 'Deze publieke historie groeit mee zodra onderhoud wordt toegevoegd',
                'tone' => 'neutral',
            ],
            [
                'label' => 'Onderbouwde kilometerstanden',
                'value' => $metrics['documented_km_count'] > 0
                    ? $metrics['documented_km_count'].' momenten met datum en kilometerstand ondersteunen de opbouw van de historie'
                    : 'Kilometerstanden maken deze historie nog sterker zodra ze worden vastgelegd',
                'tone' => 'success',
            ],
            [
                'label' => 'Bijlagen en bewijs',
                'value' => $metrics['public_attachment_count'] > 0
                    ? $metrics['public_attachment_count'].' zichtbare bijlagen laten zien wat er aan dit voertuig is gedaan'
                    : ($metrics['public_vehicle_photo_count'] > 0
                        ? $metrics['public_vehicle_photo_count'].' voertuigfoto\'s ondersteunen de publieke presentatie van deze historie'
                        : 'Er zijn nog geen publieke bijlagen zichtbaar, maar de eigenaar kan bewijs veilig blijven aanvullen'),
                'tone' => 'neutral',
            ],
            [
                'label' => 'Delen en verkoop',
                'value' => $metrics['maintenance_count'] > 0
                    ? 'Deze pagina is geschikt om te delen met koper, garage of liefhebber en helpt vertrouwen opbouwen bij verkoop'
                    : 'Ook een beginnende historie kan later helpen bij verkoop, overdracht en waardebehoud',
                'tone' => 'neutral',
            ],
        ];
    }

    public function publicShareCues(Vehicle $vehicle): array
    {
        $audienceLabel = match ($this->normalizedVehicleType($vehicle)) {
            'car' => 'koper, garage of taxateur',
            'motorcycle' => 'koper, liefhebber of garage',
            default => 'koper, community of garage',
        };

        return [
            'eyebrow' => 'Deelbare voertuiggeschiedenis',
            'title' => 'Gemaakt om vertrouwen op te bouwen',
            'description' => 'Deze publieke pagina bundelt onderhoud, gebruik en bewijs op een manier die rustig leesbaar blijft voor een geïnteresseerde koper, garage of liefhebber.',
            'audience' => 'Geschikt om te delen met een '.$audienceLabel.'.',
            'future_transfer_note' => 'Bij verkoop kan deze historie straks worden overgedragen aan de volgende eigenaar. Die overdracht is nog niet actief.',
        ];
    }

    public function publicVerificationNote(Vehicle $vehicle): string
    {
        $metrics = $this->publicTimelineMetrics($vehicle);

        if ($metrics['public_attachment_count'] > 0) {
            return 'Onderhoud met datum, kilometerstand en zichtbare bijlagen maakt deze historie beter onderbouwd voor kopers, garages en liefhebbers.';
        }

        return 'Deze historie is gedeeld door de eigenaar. Niet elk bewijsstuk hoeft openbaar te zijn: aanvullende foto\'s, facturen of documenten kunnen privé in het account blijven.';
    }

    public function typeSpecificLandingUrl(Vehicle $vehicle): ?string
    {
        $vehicleType = $this->normalizedVehicleType($vehicle);

        return match ($vehicleType) {
            'motorcycle' => 'https://garagebook.nl/motor-onderhoud-app/',
            'car' => 'https://garagebook.nl/auto-onderhoud-app/',
            default => null,
        };
    }

    public function normalizedVehicleType(Vehicle $vehicle): ?string
    {
        $candidates = array_filter([
            $vehicle->getAttribute('vehicle_type'),
            $vehicle->getAttribute('type'),
            $vehicle->getAttribute('category'),
            $vehicle->getAttribute('categorie'),
        ], fn ($value) => is_string($value) && trim($value) !== '');

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeVehicleTypeValue($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    public function legacyVehicleSlug(Vehicle $vehicle): string
    {
        return Str::slug(trim($vehicle->nickname ?: $vehicle->brand.' '.$vehicle->model));
    }

    private function publicSlugBase(Vehicle $vehicle): string
    {
        $base = Str::slug(implode(' ', array_filter([
            $vehicle->year,
            $vehicle->brand,
            $vehicle->model,
            $this->variantForSlug($vehicle),
        ])));

        return $base !== '' ? $base : 'garage-vehicle';
    }

    private function publicTimelineMetrics(Vehicle $vehicle): array
    {
        $timelineItems = $this->publicTimelineItems($vehicle);
        $publicVehiclePhotoCount = count($this->publicVehiclePhotos($vehicle));
        $datedItems = collect($timelineItems)
            ->filter(fn (array $item) => filled($item['date_label']))
            ->values();

        $firstDateLabel = $datedItems->last()['date_label'] ?? null;
        $latestDateLabel = $datedItems->first()['date_label'] ?? null;
        $logs = $vehicle->relationLoaded('maintenanceLogs')
            ? $vehicle->maintenanceLogs
            : $vehicle->maintenanceLogs()->latest('maintenance_date')->latest('id')->get();
        $lastUpdatedAt = $logs->first()?->maintenance_date ?? $vehicle->updated_at;
        $publicAttachmentCount = collect($timelineItems)->sum(fn (array $item): int => count($item['public_attachments'] ?? []));
        $documentedKmCount = collect($timelineItems)->filter(fn (array $item): bool => (bool) ($item['has_km_reading'] ?? false))->count();
        $sharedCostCount = collect($timelineItems)->filter(fn (array $item): bool => filled($item['cost_label'] ?? null))->count();
        $hasPrivateEvidence = collect($timelineItems)->contains(fn (array $item): bool => (bool) ($item['has_private_attachments'] ?? false));

        $historyPeriodLabel = match (true) {
            $firstDateLabel && $latestDateLabel && $firstDateLabel !== $latestDateLabel => $firstDateLabel.' tot '.$latestDateLabel,
            $latestDateLabel !== null => 'Sinds '.$latestDateLabel,
            default => 'Nog in opbouw',
        };

        return [
            'timeline_items' => $timelineItems,
            'maintenance_count' => $logs->count(),
            'last_updated_label' => $lastUpdatedAt?->format('d-m-Y'),
            'history_period_label' => $historyPeriodLabel,
            'documented_km_count' => $documentedKmCount,
            'public_vehicle_photo_count' => $publicVehiclePhotoCount,
            'public_attachment_count' => $publicAttachmentCount,
            'visible_photo_count' => $publicVehiclePhotoCount + $publicAttachmentCount,
            'shared_cost_count' => $sharedCostCount,
            'has_private_evidence' => $hasPrivateEvidence,
        ];
    }

    private function normalizeVehicleTypeValue(string $value): ?string
    {
        $tokens = array_values(array_filter(explode('-', Str::slug($value))));

        if (array_intersect($tokens, ['motorcycle', 'motor', 'motorfiets', 'bike']) !== []) {
            return 'motorcycle';
        }

        if (array_intersect($tokens, ['car', 'auto']) !== []) {
            return 'car';
        }

        return null;
    }

    private function variantForDisplay(Vehicle $vehicle): ?string
    {
        $variant = trim((string) $vehicle->getAttribute('display_variant'));

        return $variant !== '' ? $variant : null;
    }

    private function variantForSlug(Vehicle $vehicle): ?string
    {
        $variant = $this->variantForDisplay($vehicle);

        if ($variant === null) {
            return null;
        }

        $modelSlug = Str::slug((string) $vehicle->model);
        $variantSlug = Str::slug($variant);

        if ($modelSlug !== '' && $variantSlug !== '' && str_contains($modelSlug, $variantSlug)) {
            return null;
        }

        return $variant;
    }

    private function slugExists(string $slug, ?int $ignoreVehicleId = null): bool
    {
        return Vehicle::query()
            ->when($ignoreVehicleId, fn ($query) => $query->whereKeyNot($ignoreVehicleId))
            ->where('public_slug', $slug)
            ->exists();
    }

    private function timelineConsistencyLabel(array $timelineItems): string
    {
        $dateSequence = collect($timelineItems)
            ->pluck('date_label')
            ->filter()
            ->map(function (string $value): ?string {
                $date = Carbon::createFromFormat('d-m-Y', $value);

                return $date?->toDateString();
            })
            ->filter()
            ->values();

        $datesAreOrdered = $dateSequence->all() === $dateSequence->sortDesc()->values()->all();
        $kmSignalsCount = collect($timelineItems)->filter(fn (array $item): bool => (bool) ($item['has_km_reading'] ?? false))->count();

        if ($datesAreOrdered && $kmSignalsCount >= 2) {
            return 'Datums en kilometerstanden vormen samen een logisch leesbare onderhoudslijn';
        }

        if ($datesAreOrdered) {
            return 'Onderhoudsmomenten staan in een consistente tijdvolgorde';
        }

        return 'De tijdlijn geeft onderhoudsmomenten en bewijs in één centraal overzicht weer';
    }

    private function publicAttachmentImage(string $path, Vehicle $vehicle): ?array
    {
        $path = ltrim($path, '/');

        if (! $this->publicStoragePathExists($path)) {
            return null;
        }

        $kind = MediaPath::isImage($path)
            ? 'image'
            : (MediaPath::isVideo($path)
                ? 'video'
                : (MediaPath::isPdf($path) ? 'pdf' : 'file'));

        $attachment = [
            'kind' => $kind,
            'label' => MediaPath::label($path),
            'alt' => 'Publieke bijlage bij onderhoud van '.$this->publicVehicleName($vehicle),
            'thumbnail_url' => null,
            'url' => asset('storage/'.$path),
        ];

        if ($kind === 'image') {
            $thumbnailPath = ImageThumbnail::path($path, 720) ?: $path;
            $attachment['alt'] = 'Publieke foto bij onderhoud van '.$this->publicVehicleName($vehicle);
            $attachment['thumbnail_url'] = asset('storage/'.ltrim($thumbnailPath, '/'));
        }

        return $attachment;
    }

    private function publicStoragePathExists(string $path): bool
    {
        return Storage::disk('public')->exists(ltrim($path, '/'));
    }
}

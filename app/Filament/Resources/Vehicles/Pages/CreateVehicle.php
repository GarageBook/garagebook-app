<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Services\DistanceUnitService;
use App\Support\Analytics;
use App\Support\AnalyticsEventTracker;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    protected static ?string $title = null;

    public function mount(): void
    {
        parent::mount();

        if ($this->isOutreachDemoUser()) {
            $this->queueOutreachDemoVehicleCreateBlockedEvent();
        }
    }

    public function create(bool $another = false): void
    {
        if ($this->isOutreachDemoUser()) {
            $this->queueOutreachDemoVehicleCreateBlockedEvent();

            return;
        }

        parent::create($another);
    }

    public function content(Schema $schema): Schema
    {
        if (! $this->isOutreachDemoUser()) {
            return parent::content($schema);
        }

        return $schema->components([
            View::make('filament.resources.vehicles.pages.outreach-demo-create-blocked')
                ->viewData([
                    'registerUrl' => $this->getOutreachDemoRegisterUrl(),
                    'backUrl' => VehicleResource::getUrl('index'),
                    'analyticsAttributes' => Analytics::clickTrackingAttributes(
                        'outreach_demo_register_cta_clicked',
                        $this->getOutreachDemoAnalyticsParams(),
                    ),
                ]),
        ]);
    }

    public function isOutreachDemoUser(): bool
    {
        return (bool) (auth()->user()?->is_outreach_demo ?? false);
    }

    /**
     * @return array<string, int|string>
     */
    public function getOutreachDemoAnalyticsParams(): array
    {
        return array_filter([
            'demo_user_id' => auth()->id(),
            'outreach_prospect_id' => $this->outreachProspectId(),
            'intended' => 'vehicle_create',
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function getOutreachDemoRegisterUrl(): string
    {
        return url('/register?'.http_build_query([
            'source' => 'outreach_demo',
            ...$this->getOutreachDemoAnalyticsParams(),
        ]));
    }

    public function getTitle(): string
    {
        return __('vehicles.create_title');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_if($this->isOutreachDemoUser(), 403);

        $data['user_id'] = auth()->id();
        $data['distance_unit'] = app(DistanceUnitService::class)->normalizeUnit($data['distance_unit'] ?? null);
        $data['current_km'] = (int) round(
            app(DistanceUnitService::class)->toKilometers($data['current_km'] ?? null, $data['distance_unit'], 0) ?? 0
        );

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return MaintenanceLogResource::getUrl('create', [
            'vehicle_id' => $this->record->id,
            'onboarding' => 1,
        ]);
    }

    protected function afterCreate(): void
    {
        app(AnalyticsEventTracker::class)->queueVehicleCreated($this->record);
    }

    private function queueOutreachDemoVehicleCreateBlockedEvent(): void
    {
        $userId = auth()->id();

        if (! is_int($userId)) {
            return;
        }

        app(AnalyticsEventTracker::class)->queueOutreachDemoVehicleCreateBlocked(
            $userId,
            $this->outreachProspectId(),
        );
    }

    private function outreachProspectId(): ?int
    {
        $prospectId = auth()->user()?->outreachProspect()->value('id');

        return is_numeric($prospectId) ? (int) $prospectId : null;
    }
}

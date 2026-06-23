<?php

namespace App\Filament\Resources\Concerns;

use App\Models\Vehicle;
use App\Services\PublicGarageService;
use App\Support\Analytics;
use Filament\Actions\Action;

trait HasPublicVehicleShareActions
{
    /**
     * @return array<int, Action>
     */
    protected function getPublicVehicleShareActions(Vehicle $vehicle, bool $includeCopyUrl = true): array
    {
        $pdfUrl = url('/maintenance/pdf?vehicle_id='.$vehicle->id);

        $actions = [];

        if ($vehicle->is_public) {
            $shareUrl = app(PublicGarageService::class)->publicUrl($vehicle);
            $actions[] = Action::make('openSharePage')
                ->label(__('maintenance.actions.open_external_page'))
                ->url($shareUrl)
                ->openUrlInNewTab()
                ->extraAttributes(Analytics::clickTrackingAttributes('public_vehicle_page_view_clicked', [
                    'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                    'location' => 'header_action',
                ]))
                ->color('warning')
                ->outlined();

            if ($includeCopyUrl) {
                $actions[] = Action::make('copyUrl')
                    ->label(__('maintenance.actions.copy_url'))
                    ->extraAttributes([
                        'onclick' => "navigator.clipboard.writeText('{$shareUrl}'); new FilamentNotification().title('Publieke link gekopieerd').success().send();",
                        ...Analytics::clickTrackingAttributes('public_vehicle_page_link_copied', [
                            'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                            'location' => 'header_action',
                        ]),
                    ])
                    ->color('warning')
                    ->outlined();
            }
        }

        $actions[] = Action::make('exportPdf')
            ->label(__('maintenance.actions.export_pdf'))
            ->url($pdfUrl)
            ->openUrlInNewTab()
            ->extraAttributes(Analytics::clickTrackingAttributes('public_share_created', [
                'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                'source' => 'export',
            ]))
            ->color('warning')
            ->outlined();

        return $actions;
    }
}

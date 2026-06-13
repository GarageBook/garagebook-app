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
        $shareUrl = app(PublicGarageService::class)->publicUrl($vehicle);
        $pdfUrl = url('/maintenance/pdf?vehicle_id=' . $vehicle->id);

        $actions = [
            Action::make('openSharePage')
                ->label(__('maintenance.actions.open_external_page'))
                ->url($shareUrl)
                ->openUrlInNewTab()
                ->extraAttributes(Analytics::clickTrackingAttributes('public_share_created', [
                    'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                    'source' => 'share',
                ]))
                ->color('warning')
                ->outlined(),
        ];

        if ($includeCopyUrl) {
            $actions[] = Action::make('copyUrl')
                ->label(__('maintenance.actions.copy_url'))
                ->extraAttributes([
                    'onclick' => "navigator.clipboard.writeText('{$shareUrl}')",
                    ...Analytics::clickTrackingAttributes('public_share_created', [
                        'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                        'source' => 'share',
                    ]),
                ])
                ->color('warning')
                ->outlined();
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

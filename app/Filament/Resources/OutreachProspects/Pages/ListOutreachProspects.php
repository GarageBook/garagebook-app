<?php

namespace App\Filament\Resources\OutreachProspects\Pages;

use App\Filament\Resources\OutreachProspects\OutreachProspectResource;
use App\Services\Outreach\OutreachProspectExportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOutreachProspects extends ListRecords
{
    protected static string $resource = OutreachProspectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (OutreachProspectExportService $exporter) {
                    $query = $this->getTableQueryForExport()->with('campaign');

                    return response()->streamDownload(function () use ($exporter, $query): void {
                        echo $exporter->toCsv($query);
                    }, 'outreach-prospects-' . now()->format('Y-m-d') . '.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),
            CreateAction::make(),
        ];
    }
}

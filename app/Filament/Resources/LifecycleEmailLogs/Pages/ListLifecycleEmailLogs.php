<?php

namespace App\Filament\Resources\LifecycleEmailLogs\Pages;

use App\Filament\Resources\LifecycleEmailLogs\LifecycleEmailLogResource;
use App\Services\Lifecycle\LifecycleEmailService;
use App\Services\LifecycleEmailLogExportService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ListLifecycleEmailLogs extends ListRecords
{
    protected static string $resource = LifecycleEmailLogResource::class;

    protected function getHeaderActions(): array
    {
        if (! LifecycleEmailLogResource::hasBackingTable()) {
            return [];
        }

        return [
            Action::make('queueNoVehicleCampaign')
                ->label('Queue no-vehicle campagne')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->requiresConfirmation()
                ->action(function (LifecycleEmailService $service): void {
                    abort_unless(auth()->user()?->isAdmin() ?? false, 403);

                    $result = $service->queueNoVehicleUsers();

                    Notification::make()
                        ->title('No-vehicle campagne queued')
                        ->body(sprintf(
                            'Gevonden: %d. Queued: %d. Overgeslagen: %d.',
                            $result['found'],
                            $result['queued'],
                            $result['skipped'],
                        ))
                        ->success()
                        ->send();
                }),
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (LifecycleEmailLogExportService $exporter) {
                    $query = $this->getTableQueryForExport()->with('user');

                    return response()->streamDownload(function () use ($exporter, $query): void {
                        echo $exporter->toCsv($query);
                    }, 'lifecycle-email-logs-'.now()->format('Y-m-d').'.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        if (! LifecycleEmailLogResource::hasBackingTable()) {
            return $schema->components([
                Section::make('Lifecycle e-maillogs zijn nog niet beschikbaar')
                    ->description('De tabel lifecycle_email_logs ontbreekt op deze omgeving nog of de migraties zijn nog niet volledig afgerond. Deze adminpagina blijft daarom bewust bereikbaar zonder server error.'),
            ]);
        }

        return parent::content($schema);
    }
}

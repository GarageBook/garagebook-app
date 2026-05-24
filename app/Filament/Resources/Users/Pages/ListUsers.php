<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Users\Widgets\UserActivationStats;
use App\Filament\Resources\Users\Widgets\UserGrowthChart;
use App\Filament\Resources\Users\Widgets\UserRetentionStats;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export data')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    return response()->streamDownload(function () {
                        echo "\xEF\xBB\xBF"; // UTF-8 BOM
                        $handle = fopen('php://output', 'w');

                        fputcsv($handle, [
                            'id',
                            'name',
                            'email',
                            'created_at',
                            'updated_at',
                            'is_admin',
                        ]);

                        User::query()->orderBy('id')->chunk(100, function ($users) use ($handle) {
                            foreach ($users as $user) {
                                fputcsv($handle, [
                                    $user->id,
                                    $user->name,
                                    $user->email,
                                    $user->created_at?->format('Y-m-d H:i:s'),
                                    $user->updated_at?->format('Y-m-d H:i:s'),
                                    $user->is_admin ? 'Yes' : 'No',
                                ]);
                            }
                        });

                        fclose($handle);
                    }, 'gebruikers-export-' . now()->format('Y-m-d') . '.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserActivationStats::class,
            UserRetentionStats::class,
            UserGrowthChart::class,
        ];
    }
}

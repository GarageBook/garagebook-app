<?php

namespace App\Filament\Resources\MaintenanceLogs\Schemas;

use App\Models\Vehicle;
use App\Support\MediaPath;
use Filament\Forms;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class MaintenanceLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Forms\Components\Select::make('vehicle_id')
                    ->label('Voertuig')
                    ->options(
                        Vehicle::where('user_id', auth()->id())
                            ->get()
                            ->mapWithKeys(function ($vehicle) {
                                return [
                                    $vehicle->id => $vehicle->nickname
                                        ?: $vehicle->brand . ' ' . $vehicle->model,
                                ];
                            })
                    )
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('description')
                    ->label('Omschrijving')
                    ->required(),

                Forms\Components\TextInput::make('km_reading')
                    ->label('Kilometerstand')
                    ->numeric()
                    ->suffix(' km')
                    ->required(),

                Forms\Components\DatePicker::make('maintenance_date')
                    ->label('Onderhoudsdatum')
                    ->required(),

                Forms\Components\TextInput::make('cost')
                    ->label('Kosten')
                    ->numeric()
                    ->prefix('€'),

                Forms\Components\TextInput::make('worked_hours')
                    ->label('Gewerkte uren')
                    ->numeric()
                    ->inputMode('decimal')
                    ->placeholder('bijv. 2.5')
                    ->suffix(' uur'),

                Forms\Components\FileUpload::make('media_attachments')
                    ->label('Foto\'s en video\'s')
                    ->disk('public')
                    ->directory('maintenance-attachments')
                    ->visibility('public')
                    ->acceptedFileTypes(['image/*', 'video/*'])
                    ->fetchFileInformation(false)
                    ->maxSize(102400)
                    ->multiple()
                    ->appendFiles()
                    ->reorderable()
                    ->downloadable()
                    ->openable()
                    ->panelLayout('grid')
                    ->imagePreviewHeight('160')
                    ->itemPanelAspectRatio('1:1')
                    ->previewable(true)
                    ->getUploadedFileUsing(static function (BaseFileUpload $component, string $file): ?array {
                        $url = $component->getVisibility() === 'private'
                            ? null
                            : $component->getDisk()->url($file);

                        return [
                            'name' => basename($file),
                            'size' => 1,
                            'type' => MediaPath::mimeType($file),
                            'url' => $url,
                        ];
                    })
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('file_attachments')
                    ->label('Bestanden')
                    ->disk('public')
                    ->directory('maintenance-attachments')
                    ->visibility('public')
                    ->fetchFileInformation(false)
                    ->maxSize(102400)
                    ->multiple()
                    ->reorderable()
                    ->downloadable()
                    ->openable()
                    ->previewable(false)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('notes')
                    ->label('Notities')
                    ->columnSpanFull(),

                // 🔥 REMINDERS (FIXED PROPERLY)
                Section::make('Herinnering')
                    ->description('Laat GarageBook je helpen herinneren aan toekomstig onderhoud')
                    ->schema([

                        Forms\Components\Toggle::make('reminder_enabled')
                            ->label('Herinnering inschakelen')
                            ->reactive(), // 👈 DIT IS DE FIX

                        Forms\Components\TextInput::make('interval_months')
                            ->label('Interval (maanden)')
                            ->numeric()
                            ->placeholder('bijv. 12')
                            ->visible(fn ($get) => $get('reminder_enabled')),

                        Forms\Components\TextInput::make('interval_km')
                            ->label('Interval (km)')
                            ->numeric()
                            ->placeholder('bijv. 6000')
                            ->visible(fn ($get) => $get('reminder_enabled')),

                    ])
                    ->collapsed(),
            ]);
    }
}

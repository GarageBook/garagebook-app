<?php

namespace App\Filament\Resources\MaintenanceLogs\Schemas;

use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Storage;

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

                Forms\Components\FileUpload::make('attachments')
                    ->label('Foto\'s, video\'s en bestanden')
                    ->disk('public')
                    ->directory('maintenance-attachments')
                    ->visibility('public')
                    ->fetchFileInformation(false)
                    ->maxSize(102400)
                    ->multiple()
                    ->appendFiles()
                    ->reorderable()
                    ->downloadable()
                    ->openable()
                    ->previewable(false)
                    ->extraAttributes([
                        'class' => 'gb-maintenance-attachments-upload',
                    ])
                    ->columnSpanFull(),

                ViewField::make('maintenance_media_gallery')
                    ->hiddenLabel()
                    ->dehydrated(false)
                    ->view('filament.forms.components.maintenance-media-gallery')
                    ->viewData(static fn (ViewField $component): array => [
                        'mediaStatePath' => (string) str($component->getStatePath())
                            ->replaceEnd('.maintenance_media_gallery', '.attachments'),
                        'attachments' => MaintenanceLog::normalizeAttachmentPaths($component->getRecord()?->attachments),
                        'storageBaseUrl' => rtrim(Storage::url(''), '/'),
                    ])
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

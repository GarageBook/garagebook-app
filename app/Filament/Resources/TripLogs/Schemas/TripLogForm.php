<?php

namespace App\Filament\Resources\TripLogs\Schemas;

use App\Models\TripLog;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TripLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('trips.form.section_title'))
                    ->schema([
                        Forms\Components\Select::make('vehicle_id')
                            ->label(__('trips.form.vehicle'))
                            ->options(
                                Vehicle::query()
                                    ->where('user_id', auth()->id())
                                    ->latest()
                                    ->get()
                                    ->mapWithKeys(fn (Vehicle $vehicle) => [
                                        $vehicle->id => $vehicle->nickname ?: ($vehicle->brand.' '.$vehicle->model),
                                    ])
                            )
                            ->searchable()
                            ->required()
                            ->default(fn () => request()->integer('vehicle_id') ?: null),

                        Forms\Components\DatePicker::make('ridden_at')
                            ->label(__('trips.form.ridden_at'))
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('title')
                            ->label(__('trips.form.title'))
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label(__('trips.form.description'))
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('source_file_path')
                            ->label(__('trips.form.source_file'))
                            ->disk('local')
                            ->directory(fn (callable $get) => 'trip-uploads/'.auth()->id().'/'.($get('vehicle_id') ?: 'draft'))
                            ->storeFileNamesIn('source_file_name')
                            ->acceptedFileTypes([
                                '.gpx',
                                'application/gpx+xml',
                                'application/xml',
                                'text/xml',
                                'text/plain',
                            ])
                            ->maxSize(25600)
                            ->helperText(__('trips.form.source_file_help'))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->downloadable()
                            ->openable()
                            ->visible(fn (string $operation): bool => $operation === 'create'),

                        Forms\Components\FileUpload::make('photos')
                            ->label(__('trips.form.photos'))
                            ->disk('local')
                            ->directory(fn (callable $get) => 'trip-photos/'.auth()->id().'/'.($get('vehicle_id') ?: 'draft'))
                            ->visibility('private')
                            ->image()
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'image/gif',
                                'image/bmp',
                            ])
                            ->maxSize(12288)
                            ->multiple()
                            ->appendFiles()
                            ->reorderable()
                            ->fetchFileInformation(false)
                            ->downloadable(false)
                            ->openable(false)
                            ->previewable(false)
                            ->helperText(__('trips.form.photos_help'))
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('photo_gallery')
                            ->label(__('trips.form.photo_gallery'))
                            ->content(fn (?TripLog $record) => new HtmlString(view('filament.resources.trip-logs.photo-gallery', ['record' => $record])->render()))
                            ->visible(fn (?TripLog $record): bool => $record !== null && count($record->photos ?? []) > 0)
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('source_format')
                            ->default('gpx'),

                        Forms\Components\Placeholder::make('status')
                            ->label(__('trips.form.status'))
                            ->content(fn (?TripLog $record): string => $record?->status ?? TripLog::STATUS_PENDING)
                            ->visible(fn (?TripLog $record): bool => $record !== null),

                        Forms\Components\Placeholder::make('processing_notice')
                            ->label(__('trips.form.processing_notice_label'))
                            ->content(__('trips.form.processing_notice'))
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('failure_reason')
                            ->label(__('trips.form.failure_reason'))
                            ->content(fn (?TripLog $record): string => $record?->failure_reason ?: '-')
                            ->visible(fn (?TripLog $record): bool => $record?->status === TripLog::STATUS_FAILED)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}

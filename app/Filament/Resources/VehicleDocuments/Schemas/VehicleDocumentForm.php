<?php

namespace App\Filament\Resources\VehicleDocuments\Schemas;

use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Privedocument')
                    ->description('Bestanden in deze documentkluis zijn prive. Ze worden nooit openbaar gedeeld en zijn alleen zichtbaar voor jou binnen je account.')
                    ->schema([
                        Forms\Components\Select::make('vehicle_id')
                            ->label('Voertuig')
                            ->options(
                                Vehicle::query()
                                    ->where('user_id', auth()->id())
                                    ->latest()
                                    ->get()
                                    ->mapWithKeys(fn (Vehicle $vehicle) => [
                                        $vehicle->id => $vehicle->nickname ?: ($vehicle->brand . ' ' . $vehicle->model),
                                    ])
                            )
                            ->searchable()
                            ->required()
                            ->default(fn () => request()->integer('vehicle_id') ?: null),

                        Forms\Components\TextInput::make('title')
                            ->label('Titel')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('document_type')
                            ->label('Documenttype')
                            ->options(VehicleDocument::TYPE_OPTIONS)
                            ->required()
                            ->default('other'),

                        Forms\Components\FileUpload::make('file_path')
                            ->label('Bestand')
                            ->disk('local')
                            ->directory(fn (Forms\Get $get) => 'vehicle-documents/' . ($get('vehicle_id') ?: 'draft'))
                            ->visibility('private')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'video/mp4',
                                'video/quicktime',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(102400)
                            ->required()
                            ->preserveFilenames(false)
                            ->storeFileNamesIn('original_filename')
                            ->downloadable(false)
                            ->openable(false),

                        Forms\Components\Hidden::make('original_filename'),
                        Forms\Components\Hidden::make('mime_type'),
                        Forms\Components\Hidden::make('file_size'),

                        Forms\Components\DatePicker::make('document_date')
                            ->label('Documentdatum')
                            ->native(false),

                        Forms\Components\DatePicker::make('expires_at')
                            ->label('Vervaldatum')
                            ->native(false),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notitie')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}

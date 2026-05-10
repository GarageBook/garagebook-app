<?php

namespace App\Filament\Resources\VehicleDocuments\Schemas;

use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('documents.form.section_title'))
                    ->description(__('documents.form.section_description'))
                    ->schema([
                        Forms\Components\Select::make('vehicle_id')
                            ->label(__('documents.form.vehicle'))
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
                            ->label(__('documents.form.title'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('document_type')
                            ->label(__('documents.form.document_type'))
                            ->options(VehicleDocument::TYPE_OPTIONS)
                            ->required()
                            ->default('other'),

                        Forms\Components\FileUpload::make('file_path')
                            ->label(__('documents.form.file'))
                            ->disk('local')
                            ->directory(fn (Get $get) => 'vehicle-documents/' . ($get('vehicle_id') ?: 'draft'))
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
                            ->openable(false)
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('original_filename'),
                        Forms\Components\Hidden::make('mime_type'),
                        Forms\Components\Hidden::make('file_size'),

                        Forms\Components\DatePicker::make('document_date')
                            ->label(__('documents.form.document_date'))
                            ->native(false),

                        Forms\Components\DatePicker::make('expires_at')
                            ->label(__('documents.form.expires_at'))
                            ->native(false),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('documents.form.notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}

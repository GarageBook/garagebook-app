<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Filament\Resources\TripLogs\TripLogResource;
use App\Models\TripLog;
use App\Services\Trips\TripLogProcessingService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class TripLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'tripLogs';

    protected static ?string $title = 'Recente trips';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('trips.form.section_title'))
                    ->schema([
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
                            ->directory(fn (RelationManager $livewire) => 'trip-uploads/'.auth()->id().'/'.$livewire->getOwnerRecord()->getKey())
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
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('photos')
                            ->label(__('trips.form.photos'))
                            ->disk('local')
                            ->directory(fn (RelationManager $livewire) => 'trip-photos/'.auth()->id().'/'.$livewire->getOwnerRecord()->getKey())
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

                        Forms\Components\Placeholder::make('processing_notice')
                            ->label(__('trips.form.processing_notice_label'))
                            ->content(__('trips.form.processing_notice'))
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('status')
                            ->label(__('trips.form.status'))
                            ->content(fn (?TripLog $record): string => $record?->status ?? TripLog::STATUS_PENDING)
                            ->visible(fn (?TripLog $record): bool => $record !== null),

                        Forms\Components\Placeholder::make('failure_reason')
                            ->label(__('trips.form.failure_reason'))
                            ->content(fn (?TripLog $record): string => $record?->failure_reason ?: '-')
                            ->visible(fn (?TripLog $record): bool => $record?->status === TripLog::STATUS_FAILED)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('user_id', auth()->id()))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('trips.table.title'))
                    ->formatStateUsing(fn (?string $state): string => $state ?: __('trips.table.no_title'))
                    ->url(fn (TripLog $record): string => TripLogResource::getUrl('view', ['record' => $record]))
                    ->searchable(),
                Tables\Columns\TextColumn::make('ridden_at')
                    ->label(__('trips.table.ridden_at'))
                    ->date('d-m-Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('distance_km')
                    ->label(__('trips.table.distance'))
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, ',', '.').' km' : __('trips.table.not_processed'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('photos_count')
                    ->label(__('trips.table.photos'))
                    ->state(fn (TripLog $record): int => count($record->photos ?? [])),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('trips.table.status'))
                    ->badge()
                    ->color(fn (string $state): string => TripLog::statusColor($state)),
            ])
            ->defaultSort('ridden_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['status'] = TripLog::STATUS_PENDING;
                        $data['source_format'] = $data['source_format'] ?? 'gpx';

                        return $data;
                    })
                    ->after(function (TripLog $record, TripLogProcessingService $processingService): void {
                        $processingService->reprocess($record);
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (TripLog $record): string => TripLogResource::getUrl('view', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
                Action::make('reprocess')
                    ->label(__('trips.actions.reprocess'))
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (TripLog $record): bool => $record->canBeReprocessed())
                    ->action(function (TripLog $record, TripLogProcessingService $processingService): void {
                        $processingService->reprocess($record);
                    }),
            ])
            ->paginated([5, 10, 25]);
    }
}

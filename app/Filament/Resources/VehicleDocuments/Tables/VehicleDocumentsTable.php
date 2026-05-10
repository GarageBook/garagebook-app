<?php

namespace App\Filament\Resources\VehicleDocuments\Tables;

use App\Models\VehicleDocument;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class VehicleDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('documents.table.title'))
                    ->searchable()
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('type_label')
                    ->label(__('documents.table.type'))
                    ->badge(),

                Tables\Columns\TextColumn::make('original_filename')
                    ->label(__('documents.table.file'))
                    ->wrap(),

                Tables\Columns\TextColumn::make('document_date')
                    ->label(__('documents.table.date'))
                    ->date('d-m-Y')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('documents.table.expires_at'))
                    ->date('d-m-Y')
                    ->badge()
                    ->color(fn (VehicleDocument $record) => $record->expires_at?->isPast() ? 'danger' : 'gray')
                    ->toggleable(),
            ])
            ->defaultSort('document_date', 'desc')
            ->emptyStateHeading(__('documents.table.empty_heading'))
            ->emptyStateDescription(__('documents.table.empty_description'))
            ->recordActions([
                Action::make('open')
                    ->label(__('documents.actions.open'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (VehicleDocument $record) => route('vehicle-documents.show', $record))
                    ->openUrlInNewTab(),
                Action::make('download')
                    ->label(__('documents.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (VehicleDocument $record) => route('vehicle-documents.download', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

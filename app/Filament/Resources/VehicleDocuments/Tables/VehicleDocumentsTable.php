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
                    ->label('Titel')
                    ->searchable()
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('type_label')
                    ->label('Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Bestand')
                    ->wrap(),

                Tables\Columns\TextColumn::make('document_date')
                    ->label('Datum')
                    ->date('d-m-Y')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Vervalt')
                    ->date('d-m-Y')
                    ->badge()
                    ->color(fn (VehicleDocument $record) => $record->expires_at?->isPast() ? 'danger' : 'gray')
                    ->toggleable(),
            ])
            ->defaultSort('document_date', 'desc')
            ->emptyStateHeading('Nog geen documenten toegevoegd')
            ->emptyStateDescription('Voeg hier bijvoorbeeld verzekeringsbewijzen, garantiebewijzen, aankoopbewijzen, handleidingen of keuringsrapporten toe. Alles blijft prive binnen jouw account.')
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-eye')
                    ->url(fn (VehicleDocument $record) => route('vehicle-documents.show', $record))
                    ->openUrlInNewTab(),
                Action::make('download')
                    ->label('Download')
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

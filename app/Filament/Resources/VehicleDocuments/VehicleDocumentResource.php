<?php

namespace App\Filament\Resources\VehicleDocuments;

use App\Filament\Resources\VehicleDocuments\Pages\CreateVehicleDocument;
use App\Filament\Resources\VehicleDocuments\Pages\EditVehicleDocument;
use App\Filament\Resources\VehicleDocuments\Pages\ListVehicleDocuments;
use App\Filament\Resources\VehicleDocuments\Schemas\VehicleDocumentForm;
use App\Filament\Resources\VehicleDocuments\Tables\VehicleDocumentsTable;
use App\Models\VehicleDocument;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleDocumentResource extends Resource
{
    protected static ?string $model = VehicleDocument::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationLabel = 'Documentkluis';

    protected static ?string $modelLabel = 'Voertuigdocument';

    protected static ?string $pluralModelLabel = 'Documentkluis';

    protected static ?string $slug = 'documentkluis';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        return 'new!';
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'info';
    }

    public static function form(Schema $schema): Schema
    {
        return VehicleDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleDocumentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('vehicle', function (Builder $query): void {
                $query->where('user_id', auth()->id());
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleDocuments::route('/'),
            'create' => CreateVehicleDocument::route('/create'),
            'edit' => EditVehicleDocument::route('/{record}/edit'),
        ];
    }
}

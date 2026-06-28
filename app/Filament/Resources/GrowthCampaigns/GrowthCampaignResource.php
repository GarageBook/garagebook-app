<?php

namespace App\Filament\Resources\GrowthCampaigns;

use App\Filament\Resources\GrowthCampaigns\Pages\CreateGrowthCampaign;
use App\Filament\Resources\GrowthCampaigns\Pages\EditGrowthCampaign;
use App\Filament\Resources\GrowthCampaigns\Pages\ListGrowthCampaigns;
use App\Models\GrowthCampaign;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class GrowthCampaignResource extends Resource
{
    protected static ?string $model = GrowthCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Growth campagnes';

    protected static ?string $modelLabel = 'Growth campagne';

    protected static ?string $pluralModelLabel = 'Growth campagnes';

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 213;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')
                ->label('Naam')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    GrowthCampaign::STATUS_DRAFT => 'Draft',
                    GrowthCampaign::STATUS_ACTIVE => 'Active',
                    GrowthCampaign::STATUS_PAUSED => 'Paused',
                    GrowthCampaign::STATUS_COMPLETED => 'Completed',
                    GrowthCampaign::STATUS_ARCHIVED => 'Archived',
                ])
                ->required()
                ->default(GrowthCampaign::STATUS_DRAFT),
            Forms\Components\DateTimePicker::make('starts_at')
                ->label('Startdatum'),
            Forms\Components\DateTimePicker::make('ends_at')
                ->label('Einddatum'),
            Forms\Components\Textarea::make('stop_criteria')
                ->label('Stopcriteria')
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('scale_criteria')
                ->label('Opschaalcriteria')
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('kpi_notes')
                ->label('KPI-notities')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Start')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Einde')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Bijgewerkt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrowthCampaigns::route('/'),
            'create' => CreateGrowthCampaign::route('/create'),
            'edit' => EditGrowthCampaign::route('/{record}/edit'),
        ];
    }
}

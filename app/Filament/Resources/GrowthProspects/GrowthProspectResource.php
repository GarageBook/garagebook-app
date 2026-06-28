<?php

namespace App\Filament\Resources\GrowthProspects;

use App\Filament\Resources\GrowthProspects\Pages\CreateGrowthProspect;
use App\Filament\Resources\GrowthProspects\Pages\EditGrowthProspect;
use App\Filament\Resources\GrowthProspects\Pages\ImportGrowthProspects;
use App\Filament\Resources\GrowthProspects\Pages\ListGrowthProspects;
use App\Models\GrowthProspect;
use App\Services\Growth\GrowthProspectTrackingUrlGenerator;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class GrowthProspectResource extends Resource
{
    protected static ?string $model = GrowthProspect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Growth prospects';

    protected static ?string $modelLabel = 'Growth prospect';

    protected static ?string $pluralModelLabel = 'Growth prospects';

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 214;

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
            Section::make('Identiteit')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Naam')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('website')
                        ->label('Website')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('category')
                        ->label('Categorie')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('subcategory')
                        ->label('Subcategorie')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('region')
                        ->label('Regio')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('estimated_reach')
                        ->label('Geschat bereik')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('partner_slug')
                        ->label('Partner slug')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                ])
                ->columns(2),
            Section::make('Contact')
                ->schema([
                    Forms\Components\TextInput::make('contact_name')
                        ->label('Contactpersoon')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('primary_contact_channel')
                        ->label('Primair contactkanaal')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('newsletter_status')
                        ->label('Newsletter status')
                        ->maxLength(255),
                    Forms\Components\DateTimePicker::make('last_contacted_at')
                        ->label('Laatst benaderd'),
                    Forms\Components\DateTimePicker::make('next_follow_up_at')
                        ->label('Volgende opvolging'),
                ])
                ->columns(2),
            Section::make('Pipeline')
                ->schema([
                    Forms\Components\TextInput::make('priority')
                        ->label('Prioriteit')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('warmth')
                        ->label('Warmte')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('score')
                        ->label('Score')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(255),
                    Forms\Components\TextInput::make('status')
                        ->label('Status')
                        ->maxLength(255),
                ])
                ->columns(2),
            Section::make('Campagne')
                ->schema([
                    Forms\Components\Select::make('campaign_id')
                        ->label('Growth campagne')
                        ->relationship('campaign', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('tracking_url')
                        ->label('Tracking URL')
                        ->readOnly()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn (Forms\Components\TextInput $component, ?GrowthProspect $record) => $component->state(
                            $record ? app(GrowthProspectTrackingUrlGenerator::class)->generate($record) : null
                        ))
                        ->copyable()
                        ->placeholder('Beschikbaar zodra partner slug en campagne zijn ingevuld')
                        ->columnSpanFull(),
                ]),
            Section::make('Notities')
                ->schema([
                    Forms\Components\Textarea::make('why_interesting')
                        ->label('Waarom interessant')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('approach_strategy')
                        ->label('Benaderstrategie')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notities')
                        ->rows(5)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('campaign'))
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('website')
                    ->label('Website')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categorie')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('campaign.name')
                    ->label('Campagne')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioriteit')
                    ->badge()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('warmth')
                    ->label('Warmte')
                    ->badge()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('next_follow_up_at')
                    ->label('Volgende opvolging')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('campaign_id')
                    ->label('Campagne')
                    ->relationship('campaign', 'name'),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(fn (): array => GrowthProspect::query()
                        ->whereNotNull('status')
                        ->distinct()
                        ->orderBy('status')
                        ->pluck('status', 'status')
                        ->all()),
                SelectFilter::make('priority')
                    ->label('Prioriteit')
                    ->options(fn (): array => GrowthProspect::query()
                        ->whereNotNull('priority')
                        ->distinct()
                        ->orderBy('priority')
                        ->pluck('priority', 'priority')
                        ->all()),
                SelectFilter::make('warmth')
                    ->label('Warmte')
                    ->options(fn (): array => GrowthProspect::query()
                        ->whereNotNull('warmth')
                        ->distinct()
                        ->orderBy('warmth')
                        ->pluck('warmth', 'warmth')
                        ->all()),
                SelectFilter::make('category')
                    ->label('Categorie')
                    ->options(fn (): array => GrowthProspect::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrowthProspects::route('/'),
            'create' => CreateGrowthProspect::route('/create'),
            'import' => ImportGrowthProspects::route('/import'),
            'edit' => EditGrowthProspect::route('/{record}/edit'),
        ];
    }
}

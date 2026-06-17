<?php

namespace App\Filament\Resources\OutreachProspects;

use App\Filament\Resources\OutreachProspects\Pages\CreateOutreachProspect;
use App\Filament\Resources\OutreachProspects\Pages\EditOutreachProspect;
use App\Filament\Resources\OutreachProspects\Pages\ListOutreachProspects;
use App\Models\OutreachProspect;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OutreachProspectResource extends Resource
{
    protected static ?string $model = OutreachProspect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Outreach prospects';

    protected static ?string $modelLabel = 'Outreach prospect';

    protected static ?string $pluralModelLabel = 'Outreach prospects';

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 215;

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
            Select::make('outreach_campaign_id')
                ->label('Campagne')
                ->relationship('campaign', 'name')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('company_name')
                ->label('Bedrijfsnaam')
                ->required()
                ->maxLength(255),
            TextInput::make('contact_name')
                ->label('Contactpersoon')
                ->maxLength(255),
            TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->maxLength(255),
            TextInput::make('website')
                ->label('Website')
                ->maxLength(255),
            TextInput::make('city')
                ->label('Plaats')
                ->maxLength(255),
            TextInput::make('token')
                ->label('Token')
                ->disabled()
                ->dehydrated(false)
                ->placeholder('Wordt automatisch gegenereerd'),
            Textarea::make('notes')
                ->label('Notities')
                ->rows(5),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['campaign', 'user'])->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Bedrijfsnaam')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Plaats')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('website')
                    ->label('Website')
                    ->url(fn (OutreachProspect $record) => filled($record->website) ? (str_starts_with($record->website, 'http') ? $record->website : 'https://' . $record->website) : null)
                    ->openUrlInNewTab()
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('campaign.name')
                    ->label('Campagne')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('clicked_at')
                    ->label('Geklikt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->placeholder('Nog niet'),
                Tables\Columns\TextColumn::make('first_login_at')
                    ->label('Eerste login')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->placeholder('Nog niet'),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Laatste login')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->placeholder('Nog niet'),
                Tables\Columns\TextColumn::make('login_count')
                    ->label('Logins')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('demo_url')
                    ->label('Demo-link')
                    ->state(fn (OutreachProspect $record) => $record->demoUrl())
                    ->copyable()
                    ->copyMessage('Demo-link gekopieerd')
                    ->limit(40),
            ])
            ->filters([
                SelectFilter::make('click_status')
                    ->label('Klikstatus')
                    ->options([
                        'clicked' => 'Heeft geklikt',
                        'not_clicked' => 'Nog niet geklikt',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'clicked' => $query->whereNotNull('clicked_at'),
                            'not_clicked' => $query->whereNull('clicked_at'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('login_status')
                    ->label('Loginstatus')
                    ->options([
                        'logged_in' => 'Heeft ingelogd',
                        'not_logged_in' => 'Nog niet ingelogd',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'logged_in' => $query->whereNotNull('first_login_at'),
                            'not_logged_in' => $query->whereNull('first_login_at'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('outreach_campaign_id')
                    ->label('Campagne')
                    ->relationship('campaign', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutreachProspects::route('/'),
            'create' => CreateOutreachProspect::route('/create'),
            'edit' => EditOutreachProspect::route('/{record}/edit'),
        ];
    }
}

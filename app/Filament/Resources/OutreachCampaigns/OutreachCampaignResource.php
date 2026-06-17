<?php

namespace App\Filament\Resources\OutreachCampaigns;

use App\Filament\Resources\OutreachCampaigns\Pages\CreateOutreachCampaign;
use App\Filament\Resources\OutreachCampaigns\Pages\EditOutreachCampaign;
use App\Filament\Resources\OutreachCampaigns\Pages\ListOutreachCampaigns;
use App\Models\OutreachCampaign;
use App\Services\Outreach\OutreachEmailService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OutreachCampaignResource extends Resource
{
    protected static ?string $model = OutreachCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Outreach campagnes';

    protected static ?string $modelLabel = 'Outreach campagne';

    protected static ?string $pluralModelLabel = 'Outreach campagnes';

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
            TextInput::make('name')
                ->label('Naam')
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Textarea::make('description')
                ->label('Beschrijving')
                ->rows(4),
            TextInput::make('email_subject')
                ->label('E-mail onderwerp')
                ->placeholder(app(OutreachEmailService::class)->defaultSubject()),
            Textarea::make('email_body')
                ->label('E-mail body')
                ->rows(16)
                ->placeholder(app(OutreachEmailService::class)->defaultBody())
                ->helperText('Beschikbare placeholders: {{company_name}}, {{contact_name}}, {{demo_url}}.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('prospects')->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('prospects_count')
                    ->label('Prospects')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Bijgewerkt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                Action::make('sendTestMail')
                    ->label('Verstuur testmail')
                    ->requiresConfirmation()
                    ->modalDescription('Testmail wordt verzonden naar: willemvanveelen@icloud.com')
                    ->action(function (OutreachCampaign $record, OutreachEmailService $service): void {
                        $prospect = $record->prospects()->orderBy('id')->first();

                        if (! $prospect) {
                            Notification::make()->title('Geen prospect beschikbaar voor testmail.')->danger()->send();

                            return;
                        }

                        $service->sendTestMail($record, $prospect);

                        Notification::make()->title('Testmail verstuurd.')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutreachCampaigns::route('/'),
            'create' => CreateOutreachCampaign::route('/create'),
            'edit' => EditOutreachCampaign::route('/{record}/edit'),
        ];
    }
}

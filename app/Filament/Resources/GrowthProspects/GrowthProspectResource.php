<?php

namespace App\Filament\Resources\GrowthProspects;

use App\Filament\Resources\GrowthProspects\Pages\CreateGrowthProspect;
use App\Filament\Resources\GrowthProspects\Pages\EditGrowthProspect;
use App\Filament\Resources\GrowthProspects\Pages\ImportGrowthProspects;
use App\Filament\Resources\GrowthProspects\Pages\ListGrowthProspects;
use App\Models\GrowthCampaign;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;
use App\Services\Growth\GrowthOutreachEventLogger;
use App\Services\Growth\GrowthProspectOutreachService;
use App\Services\Growth\GrowthProspectTrackingUrlGenerator;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
                    Forms\Components\TextInput::make('normalized_domain')
                        ->label('Genormaliseerd domein')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('organization_key')
                        ->label('Organisatie key')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('category')
                        ->label('Categorie')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('subcategory')
                        ->label('Subcategorie')
                        ->maxLength(255),
                    Forms\Components\Select::make('prospect_type')
                        ->label('Type')
                        ->options(array_combine(GrowthProspect::PROSPECT_TYPES, GrowthProspect::PROSPECT_TYPES)),
                    Forms\Components\Select::make('prospect_subtype')
                        ->label('Subtype')
                        ->options(array_combine(GrowthProspect::PROSPECT_SUBTYPES, GrowthProspect::PROSPECT_SUBTYPES))
                        ->searchable(),
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
                    Forms\Components\TextInput::make('normalized_email')
                        ->label('Genormaliseerd e-mail')
                        ->email()
                        ->maxLength(255),
                    Forms\Components\Select::make('email_status')
                        ->label('E-mailstatus')
                        ->options(array_combine(GrowthProspect::EMAIL_STATUSES, GrowthProspect::EMAIL_STATUSES))
                        ->default(GrowthProspect::EMAIL_STATUS_MISSING),
                    Forms\Components\Toggle::make('verification_required')
                        ->label('Verificatie nodig'),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefoon')
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
                    Forms\Components\Select::make('lifecycle_status')
                        ->label('Lifecycle')
                        ->options(array_combine(GrowthProspect::LIFECYCLE_STATUSES, GrowthProspect::LIFECYCLE_STATUSES))
                        ->default(GrowthProspect::LIFECYCLE_NEW),
                    Forms\Components\TextInput::make('skip_reason')
                        ->label('Skip reason')
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['campaign', 'lastCampaign']))
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('website')
                    ->label('Website')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('normalized_domain')
                    ->label('Domein')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('prospect_type')
                    ->label('Type')
                    ->badge()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('prospect_subtype')
                    ->label('Subtype')
                    ->badge()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('email_status')
                    ->label('E-mailstatus')
                    ->badge()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->label('Lifecycle')
                    ->badge()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('last_campaign_slug')
                    ->label('Laatste campagne')
                    ->searchable()
                    ->sortable()
                    ->placeholder(fn (GrowthProspect $record): string => $record->campaign?->slug ?: '-'),
                Tables\Columns\TextColumn::make('last_contacted_at')
                    ->label('Laatst benaderd')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('skip_reason')
                    ->label('Skip reason')
                    ->badge()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('verification_required')
                    ->label('Verificatie')
                    ->boolean(),
                Tables\Columns\TextColumn::make('campaign.name')
                    ->label('Campagne')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
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
                SelectFilter::make('email_presence')
                    ->label('E-mailadres')
                    ->options([
                        'has_email' => 'Heeft e-mailadres',
                        'missing_email' => 'Geen e-mailadres',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'has_email' => $query->whereNotNull('email')->where('email', '!=', ''),
                        'missing_email' => $query->where(fn (Builder $query): Builder => $query->whereNull('email')->orWhere('email', '')),
                        default => $query,
                    }),
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
                SelectFilter::make('email_status')
                    ->label('E-mailstatus')
                    ->options(array_combine(GrowthProspect::EMAIL_STATUSES, GrowthProspect::EMAIL_STATUSES)),
                SelectFilter::make('lifecycle_status')
                    ->label('Lifecycle')
                    ->options(array_combine(GrowthProspect::LIFECYCLE_STATUSES, GrowthProspect::LIFECYCLE_STATUSES)),
                SelectFilter::make('prospect_subtype')
                    ->label('Subtype')
                    ->options(array_combine(GrowthProspect::PROSPECT_SUBTYPES, GrowthProspect::PROSPECT_SUBTYPES)),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('bulkAssignCampaign')
                    ->label('Koppel aan campagne')
                    ->icon('heroicon-o-megaphone')
                    ->form([
                        Forms\Components\Select::make('campaign_id')
                            ->label('Campagne')
                            ->options(fn (): array => GrowthCampaign::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $campaign = GrowthCampaign::query()->findOrFail($data['campaign_id']);

                        $records->each(fn (GrowthProspect $record) => $record->update([
                            'campaign_id' => $campaign->id,
                        ]));

                        Notification::make()
                            ->title('Prospects gekoppeld aan campagne')
                            ->body($records->count().' prospects gekoppeld aan '.$campaign->name.'.')
                            ->success()
                            ->send();
                    }),
                BulkAction::make('sendClub2026Outreach')
                    ->label('Verstuur Club2026 outreach')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalHeading('Club2026 outreach versturen')
                    ->modalDescription('Controleer de preview hieronder. De verzending start pas na bevestiging.')
                    ->modalSubmitActionLabel('Versturen')
                    ->modalContent(function (Collection $records): View {
                        return view('filament.resources.growth-prospects.bulk-club2026-outreach-preview', self::club2026OutreachPreviewData($records));
                    })
                    ->action(function (Collection $records, GrowthProspectOutreachService $service): void {
                        $result = $service->sendClub2026Bulk($records);

                        Notification::make()
                            ->title('Club2026 outreach verwerkt')
                            ->body('Verzonden: '.$result['sent'].'. Overgeslagen: '.$result['skipped'].'.')
                            ->success()
                            ->send();
                    }),
                BulkAction::make('markReady')
                    ->label('Mark as ready')
                    ->icon('heroicon-o-check')
                    ->action(function (Collection $records): void {
                        $records->each(fn (GrowthProspect $record) => $record->update([
                            'status' => GrowthProspect::LIFECYCLE_READY,
                            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
                            'skip_reason' => null,
                            'verification_required' => false,
                        ]));

                        self::sendBulkUpdatedNotification($records->count(), 'Prospects ready gezet');
                    }),
                BulkAction::make('markManualReview')
                    ->label('Mark as manual review')
                    ->icon('heroicon-o-eye')
                    ->action(function (Collection $records): void {
                        $records->each(fn (GrowthProspect $record) => $record->update([
                            'verification_required' => true,
                            'skip_reason' => 'manual_review_required',
                        ]));

                        self::sendBulkUpdatedNotification($records->count(), 'Prospects voor handmatige controle gemarkeerd');
                    }),
                BulkAction::make('verifyEmail')
                    ->label('Verify email')
                    ->icon('heroicon-o-shield-check')
                    ->action(function (Collection $records): void {
                        $records->each(fn (GrowthProspect $record) => $record->update([
                            'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
                            'verification_required' => false,
                        ]));

                        self::sendBulkUpdatedNotification($records->count(), 'E-mails geverifieerd');
                    }),
                BulkAction::make('markEmailMissing')
                    ->label('Mark email missing')
                    ->icon('heroicon-o-envelope')
                    ->action(function (Collection $records): void {
                        $records->each(fn (GrowthProspect $record) => $record->update([
                            'email_status' => GrowthProspect::EMAIL_STATUS_MISSING,
                            'verification_required' => true,
                            'skip_reason' => 'missing_email',
                        ]));

                        self::sendBulkUpdatedNotification($records->count(), 'E-mail ontbreekt gemarkeerd');
                    }),
                BulkAction::make('markDuplicate')
                    ->label('Merge/mark duplicate')
                    ->icon('heroicon-o-link')
                    ->form([
                        Forms\Components\Select::make('duplicate_of_id')
                            ->label('Hoofdrecord')
                            ->options(fn (): array => GrowthProspect::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each(fn (GrowthProspect $record) => $record->update([
                            'duplicate_of_id' => $data['duplicate_of_id'],
                            'skip_reason' => 'duplicate',
                        ]));

                        self::sendBulkUpdatedNotification($records->count(), 'Duplicaten gemarkeerd');
                    }),
                BulkAction::make('addToCampaign')
                    ->label('Add to campaign')
                    ->icon('heroicon-o-megaphone')
                    ->form([
                        Forms\Components\Select::make('campaign_id')
                            ->label('Growth campagne')
                            ->options(fn (): array => GrowthCampaign::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $campaign = GrowthCampaign::query()->find($data['campaign_id']);
                        $records->each(fn (GrowthProspect $record) => $record->update([
                            'campaign_id' => $campaign?->id,
                            'last_campaign_id' => $campaign?->id,
                            'last_campaign_slug' => $campaign?->slug,
                        ]));

                        self::sendBulkUpdatedNotification($records->count(), 'Prospects aan campagne toegevoegd');
                    }),
                BulkAction::make('skipForCampaign')
                    ->label('Skip for campaign')
                    ->icon('heroicon-o-no-symbol')
                    ->form([
                        Forms\Components\Select::make('campaign_id')
                            ->label('Growth campagne')
                            ->options(fn (): array => GrowthCampaign::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('reason')
                            ->label('Reden')
                            ->options([
                                'already_contacted_recently' => 'already_contacted_recently',
                                'already_received_campaign' => 'already_received_campaign',
                                'missing_email' => 'missing_email',
                                'invalid_email' => 'invalid_email',
                                'duplicate' => 'duplicate',
                                'archived' => 'archived',
                                'no_website' => 'no_website',
                                'manual_review_required' => 'manual_review_required',
                            ])
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data, GrowthOutreachEventLogger $events): void {
                        $campaign = GrowthCampaign::query()->find($data['campaign_id']);
                        $records->each(function (GrowthProspect $record) use ($campaign, $data, $events): void {
                            $record->update(['skip_reason' => $data['reason']]);
                            $events->log($record, GrowthOutreachEvent::TYPE_SKIPPED, $campaign, $campaign?->slug, $data['reason']);
                        });

                        self::sendBulkUpdatedNotification($records->count(), 'Prospects overgeslagen');
                    }),
                BulkAction::make('archive')
                    ->label('Mark as archived')
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $records->each(fn (GrowthProspect $record) => $record->update([
                            'status' => 'archived',
                            'lifecycle_status' => GrowthProspect::LIFECYCLE_ARCHIVED,
                        ]));

                        self::sendBulkUpdatedNotification($records->count(), 'Prospects gearchiveerd');
                    }),

            ]);
    }

    public static function club2026OutreachPreviewData(iterable $records): array
    {
        $records = collect($records);

        $records->each(fn (GrowthProspect $record) => $record->loadMissing('campaign'));

        return [
            'count' => $records->count(),
            'sendableCount' => $records->filter(fn (GrowthProspect $record): bool => self::isClub2026OutreachSendable($record))->count(),
            'subject' => 'Gratis digitaal onderhoudsboekje voor jullie leden',
            'body' => self::club2026OutreachPreviewBody(),
            'trackingUrlNote' => 'Elke geselecteerde prospect krijgt een unieke tracking URL.',
            'warningWithoutEmail' => $records->filter(fn (GrowthProspect $record): bool => blank($record->email))->count(),
            'warningArchived' => $records->filter(fn (GrowthProspect $record): bool => $record->status === 'archived')->count(),
            'warningWithoutTrackingUrl' => $records->filter(fn (GrowthProspect $record): bool => ! blank($record->email) && $record->status !== 'archived' && app(GrowthProspectTrackingUrlGenerator::class)->generate($record) === null)->count(),
        ];
    }

    private static function club2026OutreachPreviewBody(): string
    {
        return implode(PHP_EOL, [
            'Hoi {{contact_name_or_name}},',
            '',
            'Ik ben Willem, maker van GarageBook: een gratis digitaal onderhoudsboekje voor motoren.',
            '',
            'Ik denk dat dit interessant kan zijn voor jullie leden: ze kunnen onderhoud, documenten, foto’s en historie van hun motor netjes bijhouden — handig bij onderhoud, verkoop, taxatie of gewoon om de geschiedenis compleet te houden.',
            '',
            'Ik heb een speciale link voor jullie klaargezet:',
            '{{tracking_url}}',
            '',
            'GarageBook is gratis te gebruiken voor één voertuig. Het zou mooi zijn als jullie dit eens willen bekijken en eventueel delen met leden, bijvoorbeeld in een nieuwsbrief of clubbericht.',
            '',
            'Geen commerciële verplichting of gedoe; vooral een handig hulpmiddel voor motorrijders die hun motor serieus nemen.',
            '',
            'Groet,',
            'Willem',
            'GarageBook',
            'https://garagebook.nl',
        ]);
    }

    private static function isClub2026OutreachSendable(GrowthProspect $record): bool
    {
        return ! blank($record->email)
            && $record->status !== 'archived'
            && app(GrowthProspectTrackingUrlGenerator::class)->generate($record) !== null;
    }

    private static function sendBulkUpdatedNotification(int $count, string $title): void
    {
        Notification::make()
            ->title($title)
            ->body($count.' prospects bijgewerkt.')
            ->success()
            ->send();
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

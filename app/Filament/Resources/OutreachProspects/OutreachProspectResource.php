<?php

namespace App\Filament\Resources\OutreachProspects;

use App\Filament\Resources\OutreachProspects\Pages\CreateOutreachProspect;
use App\Filament\Resources\OutreachProspects\Pages\EditOutreachProspect;
use App\Filament\Resources\OutreachProspects\Pages\ListOutreachProspects;
use App\Filament\Resources\OutreachProspects\Pages\ViewOutreachProspect;
use App\Models\OutreachEmailLog;
use App\Models\OutreachProspect;
use App\Services\Outreach\OutreachEmailService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
            TextInput::make('demo_url')
                ->label('Demo-link')
                ->readOnly()
                ->dehydrated(false)
                ->afterStateHydrated(fn (TextInput $component, ?OutreachProspect $record) => $component->state($record?->demoUrl()))
                ->copyable()
                ->hint('Volledige URL, selecteerbaar en kopieerbaar.'),
            Textarea::make('notes')
                ->label('Notities')
                ->rows(5),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('campaign.name')
                ->label('Campagne'),
            TextEntry::make('company_name')
                ->label('Bedrijfsnaam'),
            TextEntry::make('contact_name')
                ->label('Contactpersoon')
                ->placeholder('-'),
            TextEntry::make('email')
                ->label('E-mail')
                ->placeholder('-'),
            TextEntry::make('website')
                ->label('Website')
                ->placeholder('-'),
            TextEntry::make('city')
                ->label('Plaats')
                ->placeholder('-'),
            TextEntry::make('demo_url')
                ->label('Demo-link')
                ->state(fn (OutreachProspect $record) => $record->demoUrl())
                ->copyable()
                ->copyMessage('Demo-link gekopieerd'),
            TextEntry::make('clicked_at')
                ->label('Geklikt')
                ->dateTime('d-m-Y H:i')
                ->placeholder('Nog niet'),
            TextEntry::make('first_login_at')
                ->label('Eerste login')
                ->dateTime('d-m-Y H:i')
                ->placeholder('Nog niet'),
            TextEntry::make('last_login_at')
                ->label('Laatste login')
                ->dateTime('d-m-Y H:i')
                ->placeholder('Nog niet'),
            TextEntry::make('login_count')
                ->label('Logins'),
            TextEntry::make('notes')
                ->label('Notities')
                ->placeholder('-'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['campaign', 'user', 'latestEmailLog'])
                ->addSelect([
                    'latest_outreach_mail_status' => OutreachEmailLog::query()
                        ->selectRaw("CASE WHEN status <> ? AND queued_at IS NOT NULL THEN 'queued' ELSE status END", [OutreachEmailLog::STATUS_SENT])
                        ->whereColumn('outreach_email_logs.outreach_prospect_id', 'outreach_prospects.id')
                        ->whereColumn('outreach_email_logs.outreach_campaign_id', 'outreach_prospects.outreach_campaign_id')
                        ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [OutreachEmailLog::STATUS_SENT])
                        ->latest('id')
                        ->limit(1),
                    'latest_outreach_sent_at' => OutreachEmailLog::query()
                        ->select('sent_at')
                        ->whereColumn('outreach_email_logs.outreach_prospect_id', 'outreach_prospects.id')
                        ->whereColumn('outreach_email_logs.outreach_campaign_id', 'outreach_prospects.outreach_campaign_id')
                        ->where('status', OutreachEmailLog::STATUS_SENT)
                        ->latest('sent_at')
                        ->limit(1),
                ])
                ->latest('id'))
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
                    ->url(fn (OutreachProspect $record) => filled($record->website) ? (str_starts_with($record->website, 'http') ? $record->website : 'https://'.$record->website) : null)
                    ->openUrlInNewTab()
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->url(fn (OutreachProspect $record) => filled($record->email) ? 'mailto:'.$record->email : null)
                    ->openUrlInNewTab()
                    ->searchable()
                    ->sortable()
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
                Tables\Columns\TextColumn::make('latest_outreach_mail_status')
                    ->label('Mailstatus')
                    ->badge()
                    ->placeholder('niet verstuurd')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        OutreachEmailLog::STATUS_SENT => 'verstuurd',
                        OutreachEmailLog::STATUS_SKIPPED => 'overgeslagen',
                        OutreachEmailLog::STATUS_FAILED => 'mislukt',
                        'queued' => 'gequeued',
                        default => 'niet verstuurd',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        OutreachEmailLog::STATUS_SENT => 'success',
                        OutreachEmailLog::STATUS_SKIPPED => 'warning',
                        OutreachEmailLog::STATUS_FAILED => 'danger',
                        'queued' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('latest_outreach_sent_at')
                    ->label('Laatst verstuurd op')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('Nog niet'),
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
                Filter::make('not_emailed')
                    ->label('Nog niet gemaild')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('emailLogs', fn (Builder $logQuery) => $logQuery
                        ->whereColumn('outreach_email_logs.outreach_campaign_id', 'outreach_prospects.outreach_campaign_id')
                        ->where('status', OutreachEmailLog::STATUS_SENT))),
                Filter::make('emailed')
                    ->label('Gemaild')
                    ->query(fn (Builder $query): Builder => $query->whereHas('emailLogs', fn (Builder $logQuery) => $logQuery
                        ->whereColumn('outreach_email_logs.outreach_campaign_id', 'outreach_prospects.outreach_campaign_id')
                        ->where('status', OutreachEmailLog::STATUS_SENT))),
                Filter::make('failed_mail')
                    ->label('Mislukt')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDoesntHave('emailLogs', fn (Builder $logQuery) => $logQuery
                            ->whereColumn('outreach_email_logs.outreach_campaign_id', 'outreach_prospects.outreach_campaign_id')
                            ->where('status', OutreachEmailLog::STATUS_SENT))
                        ->whereHas('emailLogs', fn (Builder $logQuery) => $logQuery
                            ->whereColumn('outreach_email_logs.outreach_campaign_id', 'outreach_prospects.outreach_campaign_id')
                            ->where('status', OutreachEmailLog::STATUS_FAILED))),
                Filter::make('missing_email')
                    ->label('Geen e-mailadres')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->whereNull('email')->orWhere('email', ''))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('copyDemoLink')
                    ->label('Kopieer demo-link')
                    ->action(fn (OutreachProspect $record) => null)
                    ->extraAttributes(fn (OutreachProspect $record) => [
                        'onclick' => self::copyDemoLinkJs($record->demoUrl()),
                    ]),
                Action::make('openDemo')
                    ->label('Open demo')
                    ->url(fn (OutreachProspect $record) => $record->demoUrl())
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkAction::make('sendOutreachMail')
                    ->label('Verstuur outreach-mail')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalHeading('Verstuur outreach-mail')
                    ->modalSubmitActionLabel('Nu versturen')
                    ->modalContent(function (Collection $records, OutreachEmailService $service) {
                        /** @var ?OutreachProspect $firstProspect */
                        $firstProspect = $records->load('campaign')->first();

                        if (! $firstProspect || ! $firstProspect->campaign) {
                            return view('filament.resources.outreach-prospects.bulk-mail-preview', [
                                'count' => $records->count(),
                                'subject' => $service->defaultSubject(),
                                'body' => 'Geen campagne of prospect beschikbaar voor preview.',
                            ]);
                        }

                        $preview = $service->renderForProspect($firstProspect->campaign, $firstProspect);

                        return view('filament.resources.outreach-prospects.bulk-mail-preview', [
                            'count' => $records->count(),
                            'subject' => $preview['subject'],
                            'body' => $preview['body'],
                        ]);
                    })
                    ->action(function (Collection $records, OutreachEmailService $service): void {
                        $quota = app(OutreachQuota::class);

                        if ($quota->hasReachedLimit()) {
                            Notification::make()
                                ->title('Outreach-daglimiet bereikt')
                                ->body($quota->limitReachedMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $result = $service->queueBulkSend($records->load('campaign'));

                        Notification::make()
                            ->title('Outreach-mailverzending gestart')
                            ->body('Gequeued: '.$result['queued'].'. Overgeslagen: '.$result['skipped'].'.')
                            ->success()
                            ->send();
                    }),
                BulkAction::make('exportSelected')
                    ->label('Export geselecteerde CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Collection $records) {
                        return response()->streamDownload(function () use ($records): void {
                            echo "\xEF\xBB\xBF";
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, ['company_name', 'city', 'email', 'website', 'demo_url', 'clicked_at', 'first_login_at', 'last_login_at', 'login_count']);

                            foreach ($records as $record) {
                                /** @var OutreachProspect $record */
                                fputcsv($handle, [
                                    $record->company_name,
                                    $record->city,
                                    $record->email,
                                    $record->website,
                                    $record->demoUrl(),
                                    $record->clicked_at?->format('Y-m-d H:i:s'),
                                    $record->first_login_at?->format('Y-m-d H:i:s'),
                                    $record->last_login_at?->format('Y-m-d H:i:s'),
                                    $record->login_count,
                                ]);
                            }

                            fclose($handle);
                        }, 'outreach-prospects-selected-'.now()->format('Y-m-d').'.csv', [
                            'Content-Type' => 'text/csv; charset=UTF-8',
                        ]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutreachProspects::route('/'),
            'create' => CreateOutreachProspect::route('/create'),
            'view' => ViewOutreachProspect::route('/{record}'),
            'edit' => EditOutreachProspect::route('/{record}/edit'),
        ];
    }

    public static function copyDemoLinkJs(string $url): string
    {
        $url = addslashes($url);

        return "navigator.clipboard.writeText('{$url}'); new FilamentNotification().title('Demo-link gekopieerd').success().send();";
    }
}

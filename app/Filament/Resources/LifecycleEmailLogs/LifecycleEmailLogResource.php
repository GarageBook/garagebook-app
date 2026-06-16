<?php

namespace App\Filament\Resources\LifecycleEmailLogs;

use App\Filament\Resources\LifecycleEmailLogs\Pages\ListLifecycleEmailLogs;
use App\Filament\Resources\LifecycleEmailLogs\Pages\ViewLifecycleEmailLog;
use App\Models\LifecycleEmailLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use UnitEnum;

class LifecycleEmailLogResource extends Resource
{
    protected static ?string $model = LifecycleEmailLog::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Lifecycle e-maillogs';

    protected static ?string $modelLabel = 'Lifecycle e-maillog';

    protected static ?string $pluralModelLabel = 'Lifecycle e-maillogs';

    protected static string | UnitEnum | null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 211;

    public static function hasBackingTable(): bool
    {
        return SchemaFacade::hasTable('lifecycle_email_logs');
    }

    public static function hasUsersTable(): bool
    {
        return SchemaFacade::hasTable('users');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::hasBackingTable()) {
            return null;
        }

        return (string) static::getModel()::query()->count();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                if (static::hasUsersTable()) {
                    $query->with('user');
                }

                return $query->latest('created_at');
            })
            ->columns([
                Tables\Columns\TextColumn::make('user_display_name')
                    ->label('Gebruiker')
                    ->state(fn (LifecycleEmailLog $record): string => $record->userDisplayName()),
                Tables\Columns\TextColumn::make('user_display_email')
                    ->label('E-mailadres')
                    ->state(fn (LifecycleEmailLog $record): string => $record->userDisplayEmail()),
                Tables\Columns\TextColumn::make('email_key')
                    ->label('E-mail key')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Onderwerp')
                    ->limit(70)
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => LifecycleEmailLog::STATUS_QUEUED,
                        'warning' => LifecycleEmailLog::STATUS_SKIPPED,
                        'success' => LifecycleEmailLog::STATUS_SENT,
                        'danger' => LifecycleEmailLog::STATUS_FAILED,
                    ]),
                Tables\Columns\TextColumn::make('reason_skipped')
                    ->label('Waarom geskipt')
                    ->limit(60)
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vehicles_count')
                    ->label('Voertuigen')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('maintenance_logs_count')
                    ->label('Onderhoudslogs')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Documenten')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Laatste login')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('clicked_at')
                    ->label('Klik op CTA')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Verzonden op')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('failed_at')
                    ->label('Mislukt op')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Foutmelding')
                    ->limit(80)
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('email_key')
                    ->label('E-mail key')
                    ->options([
                        'no_vehicle_added' => 'no_vehicle_added',
                        'no_maintenance_log_day_3' => 'no_maintenance_log_day_3',
                        'no_maintenance_log_day_14' => 'no_maintenance_log_day_14',
                        'no_maintenance_log_day_30' => 'no_maintenance_log_day_30',
                        'after_first_maintenance_log' => 'after_first_maintenance_log',
                        'inactive_user_return' => 'inactive_user_return',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        LifecycleEmailLog::STATUS_QUEUED => LifecycleEmailLog::STATUS_QUEUED,
                        LifecycleEmailLog::STATUS_SENT => LifecycleEmailLog::STATUS_SENT,
                        LifecycleEmailLog::STATUS_FAILED => LifecycleEmailLog::STATUS_FAILED,
                        LifecycleEmailLog::STATUS_SKIPPED => LifecycleEmailLog::STATUS_SKIPPED,
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Nog geen lifecycle e-maillogs beschikbaar.')
            ->emptyStateDescription('Deze pagina blijft beschikbaar, ook als de logs-tabel nog leeg is of pas na een deploy gevuld wordt.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLifecycleEmailLogs::route('/'),
            'view' => ViewLifecycleEmailLog::route('/{record}'),
        ];
    }
}

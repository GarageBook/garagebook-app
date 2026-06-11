<?php

namespace App\Filament\Resources\LifecycleEmailLogs;

use App\Filament\Resources\LifecycleEmailLogs\Pages\ListLifecycleEmailLogs;
use App\Filament\Resources\LifecycleEmailLogs\Pages\ViewLifecycleEmailLog;
use App\Models\LifecycleEmailLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->latest('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Gebruiker')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('E-mailadres')
                    ->searchable(),
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
                        'success' => LifecycleEmailLog::STATUS_SENT,
                        'danger' => LifecycleEmailLog::STATUS_FAILED,
                    ]),
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
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Gebruiker')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('email_key')
                    ->label('E-mail key')
                    ->options([
                        'no_maintenance_log_day_3' => 'no_maintenance_log_day_3',
                        'no_maintenance_log_day_14' => 'no_maintenance_log_day_14',
                        'no_maintenance_log_day_30' => 'no_maintenance_log_day_30',
                        'after_first_maintenance_log' => 'after_first_maintenance_log',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        LifecycleEmailLog::STATUS_QUEUED => LifecycleEmailLog::STATUS_QUEUED,
                        LifecycleEmailLog::STATUS_SENT => LifecycleEmailLog::STATUS_SENT,
                        LifecycleEmailLog::STATUS_FAILED => LifecycleEmailLog::STATUS_FAILED,
                    ]),
            ])
            ->recordActions([
                Tables\Actions\ViewAction::make(),
            ])
            ->toolbarActions([]);
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

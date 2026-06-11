<?php

namespace App\Filament\Resources\LifecycleEmailTemplates;

use App\Filament\Resources\LifecycleEmailTemplates\Pages\EditLifecycleEmailTemplate;
use App\Filament\Resources\LifecycleEmailTemplates\Pages\ListLifecycleEmailTemplates;
use App\Models\LifecycleEmailTemplate;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LifecycleEmailTemplateResource extends Resource
{
    protected static ?string $model = LifecycleEmailTemplate::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Lifecycle e-mails';

    protected static ?string $modelLabel = 'Lifecycle e-mailtemplate';

    protected static ?string $pluralModelLabel = 'Lifecycle e-mailtemplates';

    protected static string | UnitEnum | null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 210;

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

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('email_key')
                ->label('E-mail key')
                ->disabled(),
            Forms\Components\TextInput::make('name')
                ->label('Naam')
                ->required(),
            Forms\Components\TextInput::make('subject')
                ->label('Onderwerp')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('body')
                ->label('Body')
                ->rows(16)
                ->required()
                ->helperText('Markdown wordt ondersteund in lifecycle-mails.'),
            Forms\Components\TextInput::make('cta_text')
                ->label('CTA-tekst')
                ->required()
                ->maxLength(255),
            Forms\Components\Toggle::make('is_active')
                ->label('Actief')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('id'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_key')
                    ->label('E-mail key')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Onderwerp')
                    ->limit(70)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Bijgewerkt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Actief'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLifecycleEmailTemplates::route('/'),
            'edit' => EditLifecycleEmailTemplate::route('/{record}/edit'),
        ];
    }
}

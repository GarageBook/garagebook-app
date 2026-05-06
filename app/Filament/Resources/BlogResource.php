<?php

namespace App\Filament\Resources;

use App\Models\Blog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class BlogResource extends Resource
{
    protected static ?string $model = Blog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Blogs';

    protected static ?string $pluralModelLabel = 'Blogs';

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
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

            Forms\Components\TextInput::make('title')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set) =>
                    $set('slug', \Str::slug($state))
                ),

            Forms\Components\TextInput::make('slug')
                ->required(),

            Forms\Components\Textarea::make('excerpt')
                ->rows(3),

            Forms\Components\FileUpload::make('hero_image')
                ->image()
                ->disk('public')
                ->directory('blog-images')
                ->visibility('public')
                ->moveFiles() // 🔥 DIT WAS JE HELE PROBLEEM
                ->preserveFilenames()
                ->imageEditor(),

            Forms\Components\RichEditor::make('content')
                ->required()
                ->columnSpanFull(),

            Forms\Components\DateTimePicker::make('published_at'),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable(),
            Tables\Columns\TextColumn::make('published_at')->date(),
        ]);
    }
}

<?php

namespace App\Filament\Resources\Blogs\Schemas;

use Filament\Forms;

class BlogForm
{
    public static function configure($schema)
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
                ->moveFiles()
                ->imageEditor(),

            Forms\Components\RichEditor::make('content')
                ->required()
                ->columnSpanFull(),

            Forms\Components\DateTimePicker::make('published_at'),

        ]);
    }
}
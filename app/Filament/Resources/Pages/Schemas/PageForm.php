<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Forms\Components\TextInput::make('title')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set) =>
                    $set('slug', \Str::slug($state))
                ),

            Forms\Components\TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true),

            Forms\Components\RichEditor::make('content')
                ->required()
                ->columnSpanFull(),

        ]);
    }
}
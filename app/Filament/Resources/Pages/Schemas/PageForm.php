<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pagina')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set) =>
                            $set('slug', \Str::slug($state))
                        ),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\FileUpload::make('hero_image')
                        ->image()
                        ->disk('public')
                        ->directory('page-images')
                        ->visibility('public')
                        ->moveFiles()
                        ->imageEditor(),

                    Forms\Components\RichEditor::make('content')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('SEO')
                ->description('Gebruik unieke, beschrijvende metadata. Schrijf voor klikgedrag en duidelijkheid, niet voor keyword stuffing.')
                ->schema([
                    Forms\Components\TextInput::make('meta_title')
                        ->helperText('Aanbevolen: een compacte, unieke titel die de pagina helder beschrijft.')
                        ->maxLength(70)
                        ->placeholder('Bijvoorbeeld: Over GarageBook | Onderhoud slim bijhouden'),

                    Forms\Components\Textarea::make('meta_description')
                        ->helperText('Schrijf een natuurlijke samenvatting die de inhoud en het voordeel van deze pagina uitlegt.')
                        ->rows(3)
                        ->maxLength(170)
                        ->placeholder('Vat de pagina samen in 1 à 2 korte zinnen die uitnodigen om door te klikken.'),

                    Forms\Components\TextInput::make('canonical_url')
                        ->url()
                        ->helperText('Laat leeg om automatisch de huidige page-URL als canonical te gebruiken.')
                        ->placeholder('https://app.garagebook.nl/over-ons'),

                    Forms\Components\Toggle::make('indexable')
                        ->default(true)
                        ->inline(false)
                        ->helperText('Zet uit voor pagina’s die niet in zoekmachines horen te verschijnen.'),
                ])
                ->columns(2),
        ]);
    }
}

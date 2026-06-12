<?php

namespace App\Filament\Resources\LifecycleEmailLogs\Pages;

use App\Filament\Resources\LifecycleEmailLogs\LifecycleEmailLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ListLifecycleEmailLogs extends ListRecords
{
    protected static string $resource = LifecycleEmailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        if (! LifecycleEmailLogResource::hasBackingTable()) {
            return $schema->components([
                Section::make('Lifecycle e-maillogs zijn nog niet beschikbaar')
                    ->description('De tabel lifecycle_email_logs ontbreekt op deze omgeving nog of de migraties zijn nog niet volledig afgerond. Deze adminpagina blijft daarom bewust bereikbaar zonder server error.'),
            ]);
        }

        return parent::content($schema);
    }
}

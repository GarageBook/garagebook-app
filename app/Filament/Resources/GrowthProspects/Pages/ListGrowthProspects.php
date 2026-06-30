<?php

namespace App\Filament\Resources\GrowthProspects\Pages;

use App\Filament\Resources\GrowthProspects\GrowthProspectResource;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListGrowthProspects extends ListRecords
{
    protected static string $resource = GrowthProspectResource::class;

    public function getTabs(): array
    {
        return [
            'ready_community2026' => Tab::make('Ready for Community2026')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('lifecycle_status', GrowthProspect::LIFECYCLE_READY)
                    ->where('prospect_type', 'community')
                    ->whereIn('email_status', [GrowthProspect::EMAIL_STATUS_FOUND, GrowthProspect::EMAIL_STATUS_VERIFIED])
                    ->where('verification_required', false)
                    ->whereNull('duplicate_of_id')),
            'needs_email' => Tab::make('Needs email')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(fn (Builder $query): Builder => $query->whereNull('email')->orWhere('email', ''))
                    ->orWhere('email_status', GrowthProspect::EMAIL_STATUS_MISSING)),
            'needs_website' => Tab::make('Needs website')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(fn (Builder $query): Builder => $query->whereNull('website')->orWhere('website', ''))
                    ->whereNull('normalized_domain')),
            'manual_review' => Tab::make('Manual review')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('verification_required', true)
                    ->orWhere('skip_reason', 'manual_review_required')),
            'recently_contacted' => Tab::make('Recently contacted')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('last_contacted_at', '>=', now()->subDays(90))),
            'already_contacted' => Tab::make('Already contacted')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereHas('outreachEvents', fn (Builder $query): Builder => $query->where('event_type', GrowthOutreachEvent::TYPE_SENT))),
            'duplicates' => Tab::make('Duplicates')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('duplicate_of_id')
                    ->orWhere('skip_reason', 'duplicate')),
            'archived' => Tab::make('Archived')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('lifecycle_status', GrowthProspect::LIFECYCLE_ARCHIVED)
                    ->orWhere('status', 'archived')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importProspects')
                ->label('Import prospects')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(static::getResource()::getUrl('import')),
            CreateAction::make()
                ->label('Create Prospect'),
        ];
    }
}

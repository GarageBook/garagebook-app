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
            'all' => Tab::make('Alle prospects')
                ->badge(fn (): int => $this->baseActiveProspectsQuery()->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->activeProspectsQuery($query)),
            'ready_community2026' => Tab::make('Ready for Community2026')
                ->badge(fn (): int => $this->readyForCampaignCount('community2026'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->readyForCampaignQuery($query, 'community2026')),
            'ready_club2026' => Tab::make('Ready for Club2026')
                ->badge(fn (): int => $this->readyForCampaignCount('club2026'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->readyForCampaignQuery($query, 'club2026')),
            'ready_classic2026' => Tab::make('Ready for Classic2026')
                ->badge(fn (): int => $this->readyForCampaignCount('classic2026'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->readyForCampaignQuery($query, 'classic2026')),
            'needs_email' => Tab::make('Needs email')
                ->badge(fn (): int => $this->needsEmailQuery(GrowthProspect::query())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->needsEmailQuery($query)),
            'needs_website' => Tab::make('Needs website')
                ->badge(fn (): int => $this->needsWebsiteQuery(GrowthProspect::query())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->needsWebsiteQuery($query)),
            'manual_review' => Tab::make('Manual review')
                ->badge(fn (): int => $this->manualReviewQuery(GrowthProspect::query())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->manualReviewQuery($query)),
            'recently_contacted' => Tab::make('Recently contacted')
                ->badge(fn (): int => $this->recentlyContactedQuery(GrowthProspect::query())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->recentlyContactedQuery($query)),
            'already_contacted' => Tab::make('Already contacted')
                ->badge(fn (): int => $this->alreadyContactedQuery(GrowthProspect::query())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->alreadyContactedQuery($query)),
            'duplicates' => Tab::make('Duplicates')
                ->badge(fn (): int => $this->duplicatesQuery(GrowthProspect::query())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->duplicatesQuery($query)),
            'archived' => Tab::make('Archived')
                ->badge(fn (): int => $this->archivedQuery(GrowthProspect::query())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->archivedQuery($query)),
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

    private function readyForCampaignQuery(Builder $query, string $campaignSlug): Builder
    {
        return $this->readyProspectsQuery($query)
            ->whereHas('campaign', fn (Builder $query): Builder => $query->where('slug', $campaignSlug));
    }

    private function readyForCampaignCount(string $campaignSlug): int
    {
        return $this->readyForCampaignQuery(GrowthProspect::query(), $campaignSlug)->count();
    }

    private function readyProspectsQuery(Builder $query): Builder
    {
        return $query
            ->where('lifecycle_status', GrowthProspect::LIFECYCLE_READY)
            ->whereIn('email_status', [GrowthProspect::EMAIL_STATUS_FOUND, GrowthProspect::EMAIL_STATUS_VERIFIED])
            ->where('verification_required', false)
            ->whereNull('duplicate_of_id');
    }

    private function baseActiveProspectsQuery(): Builder
    {
        return $this->activeProspectsQuery(GrowthProspect::query());
    }

    private function activeProspectsQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('lifecycle_status')
                ->orWhere('lifecycle_status', '!=', GrowthProspect::LIFECYCLE_ARCHIVED);
        })->where(function (Builder $query): void {
            $query->whereNull('status')
                ->orWhere('status', '!=', 'archived');
        });
    }

    private function needsEmailQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->whereNull('email')->orWhere('email', '');
            })->orWhere('email_status', GrowthProspect::EMAIL_STATUS_MISSING);
        });
    }

    private function needsWebsiteQuery(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->whereNull('website')->orWhere('website', '');
            })
            ->whereNull('normalized_domain');
    }

    private function manualReviewQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('lifecycle_status', GrowthProspect::LIFECYCLE_MANUAL_REVIEW)
                ->orWhere('verification_required', true)
                ->orWhere('skip_reason', 'manual_review_required');
        });
    }

    private function recentlyContactedQuery(Builder $query): Builder
    {
        return $query->where('last_contacted_at', '>=', now()->subDays(90));
    }

    private function alreadyContactedQuery(Builder $query): Builder
    {
        return $query->whereHas('outreachEvents', fn (Builder $query): Builder => $query->where('event_type', GrowthOutreachEvent::TYPE_SENT));
    }

    private function duplicatesQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNotNull('duplicate_of_id')
                ->orWhere('skip_reason', 'duplicate');
        });
    }

    private function archivedQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('lifecycle_status', GrowthProspect::LIFECYCLE_ARCHIVED)
                ->orWhere('status', 'archived');
        });
    }
}

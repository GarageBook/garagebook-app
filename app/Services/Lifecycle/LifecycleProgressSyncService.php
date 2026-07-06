<?php

namespace App\Services\Lifecycle;

use App\Enums\LifecycleMilestone;
use App\Models\LifecycleMilestoneEntry;
use App\Models\LifecycleStateEntry;
use App\Models\User;
use Illuminate\Support\Carbon;

class LifecycleProgressSyncService
{
    public function __construct(
        private readonly LifecycleStateService $stateService,
        private readonly LifecycleMilestoneService $milestoneService,
        private readonly EngagementScoreService $engagementScoreService,
    ) {}

    /**
     * @return array{state_created: bool, state_transitioned: bool, milestones_created: int}
     */
    public function syncUser(User $user, ?Carbon $syncedAt = null): array
    {
        $syncedAt ??= now();
        $state = $this->stateService->determine($user)->value;
        $currentEntry = LifecycleStateEntry::query()
            ->where('user_id', $user->id)
            ->whereNull('exited_at')
            ->latest('entered_at')
            ->latest('id')
            ->first();

        $stateCreated = false;
        $stateTransitioned = false;

        if (! $currentEntry instanceof LifecycleStateEntry) {
            LifecycleStateEntry::query()->create([
                'user_id' => $user->id,
                'state' => $state,
                'entered_at' => $syncedAt,
            ]);
            $stateCreated = true;
        } elseif ($currentEntry->state !== $state) {
            $currentEntry->forceFill([
                'exited_at' => $syncedAt,
            ])->save();

            LifecycleStateEntry::query()->create([
                'user_id' => $user->id,
                'state' => $state,
                'entered_at' => $syncedAt,
            ]);
            $stateCreated = true;
            $stateTransitioned = true;
        }

        $milestonesCreated = 0;

        foreach ($this->milestoneService->achieved($user) as $milestone) {
            if ($this->recordMilestone($user, $milestone, $syncedAt)) {
                $milestonesCreated++;
            }
        }

        return [
            'state_created' => $stateCreated,
            'state_transitioned' => $stateTransitioned,
            'milestones_created' => $milestonesCreated,
        ];
    }

    private function recordMilestone(User $user, LifecycleMilestone $milestone, Carbon $syncedAt): bool
    {
        $entry = LifecycleMilestoneEntry::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'milestone' => $milestone->value,
            ],
            [
                'achieved_at' => $syncedAt,
                'metadata' => $this->milestoneMetadata($user),
            ],
        );

        return $entry->wasRecentlyCreated;
    }

    /**
     * @return array{state: string, engagement_score: int, vehicles_count: int, maintenance_logs_count: int, documents_count: int, public_vehicles_count: int}
     */
    private function milestoneMetadata(User $user): array
    {
        $vehicles = $user->vehicles()->withCount(['maintenanceLogs', 'documents'])->get();

        return [
            'state' => $this->stateService->determine($user)->value,
            'engagement_score' => $this->engagementScoreService->score($user),
            'vehicles_count' => $vehicles->count(),
            'maintenance_logs_count' => $vehicles->sum('maintenance_logs_count'),
            'documents_count' => $vehicles->sum('documents_count'),
            'public_vehicles_count' => $vehicles
                ->filter(fn ($vehicle): bool => (bool) $vehicle->is_public && filled($vehicle->public_slug))
                ->count(),
        ];
    }
}

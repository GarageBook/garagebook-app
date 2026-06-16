<?php

namespace App\Http\Controllers\Lifecycle;

use App\Models\User;
use App\Services\LifecycleEmailService;
use App\Support\AnalyticsEventTracker;
use Illuminate\Http\RedirectResponse;

class LifecycleEmailClickController
{
    public function __invoke(User $user, string $emailKey, LifecycleEmailService $service): RedirectResponse
    {
        $service->markLifecycleEmailClicked($user, $emailKey);
        app(AnalyticsEventTracker::class)->queueLifecycleEmailClicked($emailKey);

        return redirect()->to($service->resolveCtaDestination($user, $emailKey));
    }
}

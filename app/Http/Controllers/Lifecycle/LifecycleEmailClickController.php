<?php

namespace App\Http\Controllers\Lifecycle;

use App\Models\User;
use App\Services\LifecycleEmailService;
use App\Support\AnalyticsEventTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LifecycleEmailClickController
{
    public function __invoke(Request $request, User $user, string $emailKey, LifecycleEmailService $service): RedirectResponse
    {
        $service->markLifecycleEmailClicked($user, $emailKey, $request->integer('log') ?: null);
        app(AnalyticsEventTracker::class)->queueLifecycleEmailClicked($emailKey);

        return redirect()->to($service->resolveCtaDestination($user, $emailKey));
    }
}

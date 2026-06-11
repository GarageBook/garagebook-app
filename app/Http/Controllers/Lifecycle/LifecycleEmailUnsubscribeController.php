<?php

namespace App\Http\Controllers\Lifecycle;

use App\Models\User;
use App\Services\LifecycleEmailService;
use Illuminate\Contracts\View\View;

class LifecycleEmailUnsubscribeController
{
    public function __invoke(User $user, LifecycleEmailService $service): View
    {
        $service->markLifecycleEmailsUnsubscribed($user);

        return view('lifecycle.unsubscribed');
    }
}

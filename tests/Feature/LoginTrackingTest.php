<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LoginTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_event_sets_first_and_last_login_timestamps(): void
    {
        $user = User::factory()->create([
            'first_login_at' => null,
            'last_login_at' => null,
        ]);

        Carbon::setTestNow('2026-04-22 12:00:00');
        Event::dispatch(new Login('web', $user, false));

        $user->refresh();

        $this->assertSame('2026-04-22 12:00:00', $user->first_login_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-22 12:00:00', $user->last_login_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-04-22 13:00:00');
        Event::dispatch(new Login('web', $user, false));

        $user->refresh();

        $this->assertSame('2026-04-22 12:00:00', $user->first_login_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-22 13:00:00', $user->last_login_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }
}

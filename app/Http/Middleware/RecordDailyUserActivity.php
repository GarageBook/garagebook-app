<?php

namespace App\Http\Middleware;

use App\Models\UserDailyActivity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordDailyUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if ($user !== null) {
            $seenAt = now();
            $activityDate = $seenAt->copy()->timezone('Europe/Amsterdam')->toDateString();

            try {
                UserDailyActivity::query()->upsert([[
                    'user_id' => $user->getKey(),
                    'activity_date' => $activityDate,
                    'first_seen_at' => $seenAt,
                    'last_seen_at' => $seenAt,
                    'created_at' => $seenAt,
                    'updated_at' => $seenAt,
                ]], ['user_id', 'activity_date'], ['last_seen_at', 'updated_at']);
            } catch (Throwable $exception) {
                Log::warning('Daily user activity could not be recorded.', [
                    'user_id' => $user->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        }

        return $response;
    }
}

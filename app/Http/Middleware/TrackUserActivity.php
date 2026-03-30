<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    private const SESSION_KEY = 'user-activity.last-tracked-at';

    private const THROTTLE_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if (! $user) {
            return $response;
        }

        try {
            $now = now();
            $lastTrackedAt = $request->session()->get(self::SESSION_KEY);

            if ($lastTrackedAt) {
                $lastTracked = Carbon::parse($lastTrackedAt);

                if ($lastTracked->diffInSeconds($now, true) < self::THROTTLE_SECONDS) {
                    return $response;
                }
            }

            DB::table('users')
                ->where('id', $user->getKey())
                ->update(['last_seen_at' => $now]);

            $user->forceFill(['last_seen_at' => $now]);
            $request->session()->put(self::SESSION_KEY, $now->toIso8601String());
        } catch (\Throwable) {
            // Never let presence tracking break the request cycle.
        }

        return $response;
    }
}

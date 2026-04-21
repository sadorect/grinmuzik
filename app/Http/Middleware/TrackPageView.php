<?php

namespace App\Http\Middleware;

use App\Jobs\ResolvePageViewLocation;
use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->isMethod('GET')
            && ! $request->is('musicdirector*')
            && ! $request->is('livewire*')
            && ! $request->expectsJson()
            && ! $request->header('X-Livewire')
            && ! $request->is('_ignition*')
        ) {
            try {
                $pageView = PageView::create([
                    'path' => '/' . ltrim($request->path(), '/'),
                    'session_id' => session()->getId(),
                    'user_id' => auth()->id(),
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr($request->userAgent() ?? '', 0, 500),
                ]);

                ResolvePageViewLocation::dispatchAfterResponse($pageView->id);
            } catch (\Throwable) {
                // Analytics should never break the user-facing request.
            }
        }

        return $response;
    }
}

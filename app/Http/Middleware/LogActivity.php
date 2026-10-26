<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                return $response;
            }

            // Skip failed requests and forms that came back with validation errors
            if ($response->getStatusCode() >= 400) {
                return $response;
            }
            if ($request->hasSession() && $request->session()->has('errors')) {
                return $response;
            }

            ActivityLog::record($request);
        } catch (\Throwable $e) {
            // logging must never break the system
        }

        return $response;
    }
}

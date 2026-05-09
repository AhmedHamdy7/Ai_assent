<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) env('DASHBOARD_SECRET', '');

        if ($expected === '') {
            return $next($request);
        }

        $provided = (string) ($request->query('key') ?? $request->cookie('dashboard_key') ?? '');

        if (!hash_equals($expected, $provided)) {
            abort(403, 'Forbidden — append ?key=YOUR_DASHBOARD_SECRET');
        }

        $response = $next($request);

        if ($request->query('key')) {
            $cookie = cookie('dashboard_key', $expected, 60 * 24 * 7);
            return method_exists($response, 'withCookie') ? $response->withCookie($cookie) : $response;
        }

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireConnectHubRole
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('printgate.role') === 'hub', 404);
        abort_unless(in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) || $request->isSecure(), 400, 'HTTPS is required.');

        return $next($request);
    }
}
